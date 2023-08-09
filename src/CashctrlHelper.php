<?php

declare(strict_types=1);

namespace App;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\Date;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\Versions;
use Haste\Util\StringUtil;
use NotificationCenter\Model\Notification;
use Psr\Log\LoggerInterface;
use Stripe\BalanceTransaction;
use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\CashctrlApi\Api\AccountEndpoint;
use Terminal42\CashctrlApi\Api\Filter\ListFilter;
use Terminal42\CashctrlApi\Api\Filter\OrderListFilter;
use Terminal42\CashctrlApi\Api\FiscalperiodEndpoint;
use Terminal42\CashctrlApi\Api\JournalEndpoint;
use Terminal42\CashctrlApi\Api\OrderBookentryEndpoint;
use Terminal42\CashctrlApi\Api\OrderDocumentEndpoint;
use Terminal42\CashctrlApi\Api\OrderEndpoint;
use Terminal42\CashctrlApi\Api\PersonEndpoint;
use Terminal42\CashctrlApi\ApiClient;
use Terminal42\CashctrlApi\Entity\Journal;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\Entity\OrderBookentry;
use Terminal42\CashctrlApi\Entity\OrderItem;
use Terminal42\CashctrlApi\Entity\Person;
use Terminal42\CashctrlApi\Entity\PersonAddress;
use Terminal42\CashctrlApi\Entity\PersonContact;
use Terminal42\CashctrlApi\Exception\RuntimeException;
use Terminal42\CashctrlApi\Result;
use Terminal42\ContaoBuildTools\ErrorHandlingTrait;

class CashctrlHelper
{
    use ErrorHandlingTrait;

    final public const STATUS_OPEN = 16;
    final public const STATUS_PAID = 18;
    final public const STATUS_OVERDUE = 86;
    final public const STATUS_NOTIFIED = 87;

    private array $dateFormat = [];
    private array $accountIds = [];

    public function __construct(
        public PersonEndpoint $person,
        public OrderEndpoint $order,
        public OrderDocumentEndpoint $orderDocument,
        public FiscalperiodEndpoint $fiscalperiod,
        public JournalEndpoint $journal,
        public OrderBookentryEndpoint $orderBookentry,
        public AccountEndpoint $account,
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly StripeClient $stripeClient,
        private readonly TokenChecker $tokenChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger,
        private readonly array $memberships,
        private readonly string $projectDir,
        private readonly int $paymentNotificationId,
    ) {
    }

    public function syncMember(MemberModel $member): bool
    {
        $person = null;

        if ($member->cashctrl_id) {
            $person = $this->person->read((int) $member->cashctrl_id);
        }

        if (null === $person) {
            $person = new Person($member->company ?: null, $member->firstname, $member->lastname);
            $person->setSequenceNumberId(1000);
        }

        $this->updatePerson($person, $member);

        try {
            if (null !== $person->getId()) {
                $this->person->update($person);

                return true;
            }

            $result = $this->person->create($person);

            $member->cashctrl_id = $result->insertId();
            $member->save();
        } catch (RuntimeException $exception) {
            $this->sentryOrThrow("Error updating member ID $member->id in CashCtrl: ".$exception->getMessage(), null, ['person' => $person->toArray()]);

            return false;
        }

        return true;
    }

    public function createAndSendInvoice(MemberModel $member, int $notificationId, \DateTimeImmutable $invoiceDate): Order|null
    {
        $notification = Notification::findByPk($notificationId);

        if (null === $notification) {
            $this->sentryOrThrow('Notification ID "'.$notificationId.'" not found, cannot send invoices');

            return null;
        }

        $invoiceDate ??= new \DateTimeImmutable();
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $invoiceDate = $invoiceDate->setTime(0, 0);

        $invoice = $this->createMemberInvoice($member, $invoiceDate);

        $status = 'manual';
        if (!empty($member->stripe_payment_method)) {
            $status = $this->chargeWithStripe($invoice, $member) ? 'paid' : 'error';
        }

        // Archive invoice AFTER Stripe payment, so the PDF includes the payment information
        $pdf = $this->archiveInvoice($invoice, 'ch' === $member->country ? 1011 : 1013, $member->language ?: 'de');

        if (!$this->sendInvoiceNotification($notification, $invoice, $member, ['invoice_pdf' => $pdf, 'payment_status' => $status])) {
            $this->sentryOrThrow('Unable to send invoice email to '.$member->email);

            return $invoice;
        }

        $this->order->updateStatus($invoice->getId(), 'paid' === $status ? self::STATUS_NOTIFIED : self::STATUS_OPEN);

        return $invoice;
    }

    public function sendInvoiceNotification(Notification $notification, Order $invoice, MemberModel $member, array $tokens = []): bool
    {
        $invoiceDueDate = clone $invoice->getDate();
        $invoiceDueDate->add(new \DateInterval('P'.(int) $invoice->getDueDays().'D'));

        $tokens = array_merge($tokens, [
            'admin_email' => Config::get('adminEmail'),
            'membership_label' => $this->translator->trans('membership.'.$member->membership, [], null, $member->language ?: 'de'),
            'invoice_number' => $invoice->getNr(),
            'invoice_date' => $invoice->getDate()->format($this->getDateFormat($member)),
            'invoice_due_days' => $invoice->getDueDays(),
            'invoice_due_date' => $invoiceDueDate->format($this->getDateFormat($member)),
            'invoice_total' => number_format($invoice->total, 2, '.', "'"),
            'payment_first' => $invoice->getId() === $member->cashctrl_invoice,
        ]);

        if ($invoice->dateLastBooked) {
            $tokens['payment_date'] = ApiClient::parseDateTime($invoice->dateLastBooked)->format($this->getDateFormat($member));
            $tokens['payment_total'] = number_format($invoice->total - $invoice->open, 2, '.', "'");
        }

        if (!$invoice->isClosed) {
            $tokens['payment_link'] = $this->getPaymentLink($invoice, $member);
        }

        StringUtil::flatten($member->row(), 'member', $tokens);

        $result = $notification->send($tokens);

        if (\in_array(false, $result, true)) {
            return false;
        }

        return true;
    }

    public function downloadInvoice(Order $invoice, int|null $templateId = null, string|null $language = null): string
    {
        if (null !== $templateId) {
            $this->orderDocument->update($invoice->getId(), ['templateId' => $templateId]);
        }

        return $this->orderDocument->downloadPdf([$invoice->getId()], $language);
    }

    public function archiveInvoice(Order $invoice, int|null $templateId = null, string|null $language = null): string
    {
        $year = $invoice->getDate()->format('Y');
        $quarter = ceil($invoice->getDate()->format('n') / 3);

        $name = str_replace('/', '-', $invoice->getNr());
        $targetFile = 'var/invoices/'.$year.'/Q'.$quarter.'/'.$name.'.pdf';

        if ($this->filesystem->exists($targetFile)) {
            return $targetFile;
        }

        $this->filesystem->dumpFile(
            $this->projectDir.'/'.$targetFile,
            $this->downloadInvoice($invoice, $templateId, $language)
        );

        return $targetFile;
    }

    public function listInvoices(int $cahctrlId): array
    {
        $invoices = [];

        foreach ($this->fiscalperiod->list() as $period) {
            if ($period->getStart() < new \DateTime('2021-01-01')) {
                continue;
            }

            $orders = $this->order
                ->list()
                ->inFiscalPeriod($period->getId())
                ->filter('associateId', $cahctrlId, ListFilter::EQUALS)
                ->get()
            ;

            foreach ($orders as $order) {
                $invoices[$order->getId()] = $order;
            }
        }

        return $invoices;
    }

    /**
     * @return array<Order>
     */
    public function getOverdueInvoices(): array
    {
        return $this->order
            ->list()
            ->ofType(OrderListFilter::TYPE_SALES)
            ->onlyOverdue()
            ->sortBy('lastUpdated')
            ->get()
        ;
    }

    /**
     * @return array<Order>
     */
    public function getLastPaidInvoices(): array
    {
        return $this->order
            ->list()
            ->ofType(OrderListFilter::TYPE_SALES)
            ->withStatus(self::STATUS_PAID)
            ->sortBy('lastUpdated', 'DESC')
            ->get()
        ;
    }

    public function notifyInvoicePaid(Order $order, MemberModel $member, Notification $notification): void
    {
        try {
            if (!$this->sendInvoiceNotification($notification, $order, $member)) {
                $this->sentryOrThrow('Unable to send payment notification to '.$member->email);

                return;
            }
        } catch (\Exception $e) {
            $this->sentryOrThrow('Unable to send payment notification to '.$member->email, $e);

            return;
        }

        $this->logger->info('Sent payment notification for CashCtrl invoice '.$order->getNr().' to '.$member->email);

        if ($order->getId() === (int) $member->cashctrl_invoice) {
            $objVersions = new Versions('tl_member', $member->id);
            $objVersions->setUsername($member->username);
            $objVersions->setUserId(0);
            $objVersions->setEditUrl('contao/main.php?do=member&act=edit&id='.$member->id);
            $objVersions->initialize();

            $member->cashctrl_invoice = 0;
            $member->disable = '';
            $member->save();

            $objVersions->create(true);
        }

        $this->syncMember($member);

        try {
            $this->order->updateStatus($order->getId(), self::STATUS_NOTIFIED);
        } catch (RuntimeException $exception) {
            $this->sentryOrThrow(
                'Failed to update invoice status to "notified": '.$exception->getMessage(),
                $exception,
                [
                    'order' => $order->toArray(),
                    'member' => $member->row(),
                ]);
        }
    }

    public function addJournalEntry(Journal $journal): Result
    {
        $this->setFiscalPeriod($journal->getDateAdded());

        return $this->journal->create($journal);
    }

    public function addOrderBookentry(OrderBookentry $bookentry): Result
    {
        $this->setFiscalPeriod($bookentry->getDate());

        return $this->orderBookentry->create($bookentry);
    }

    public function getAccountId(int $accountNumber): int|null
    {
        if (isset($this->accountIds[$accountNumber])) {
            return $this->accountIds[$accountNumber];
        }

        $accounts = $this->account->list()->filter('number', (string) $accountNumber);

        foreach ($accounts as $account) {
            if ($account->getNumber() === (string) $accountNumber) {
                return $this->accountIds[$accountNumber] = $account->getId();
            }
        }

        return null;
    }

    public function bookToJournal(float $amount, \DateTimeInterface $created, int $account, string $reference, string $title, string|null $balanceTransaction): void
    {
        // Make sure timezone in bookkeeping is set to Switzerland
        $created = $created->setTimezone(new \DateTimeZone('Europe/Zurich'));

        $entry = new Journal(
            $amount,
            $this->getAccountId($account),
            $this->getAccountId(1106),
            $created
        );
        $entry->setReference($reference);
        $entry->setTitle($title);

        $this->addJournalEntry($entry);

        if ($balanceTransaction) {
            $this->bookBalanceTransaction($balanceTransaction, $created, $reference, 'Stripe Gebühren für '.$title);
        }
    }

    public function bookToOrder(Charge $charge, Order $order, bool $sendNotification = true): void
    {
        if (!$charge->balance_transaction) {
            $this->sentryOrThrow('Missing balance_transaction to book Stripe charge '.$charge->id, null, [
                'charge' => $charge->toArray(),
            ]);

            return;
        }

        $transaction = $this->stripeClient->balanceTransactions->retrieve($charge->balance_transaction);
        $created = \DateTime::createFromFormat('U', (string) $transaction->created);

        $entry = new OrderBookentry($this->getAccountId(1106), $order->getId());
        $entry->setDescription($this->getStripePaymentDescription($charge));
        $entry->setAmount((float) ($transaction->amount / 100));
        $entry->setReference($transaction->id);
        $entry->setDate($created);

        $this->addOrderBookentry($entry);

        $this->bookBalanceTransaction(
            $transaction,
            $created,
            $entry->getReference(),
            'Stripe Gebühren für '.$order->getNr()
        );

        // Re-fetch order with updated booking entry
        $order = $this->order->read($order->getId());

        if ($sendNotification && $order->open <= 0) {
            $this->framework->initialize();

            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());
            $notification = Notification::findByPk($this->paymentNotificationId);

            if (null === $member || null === $notification) {
                $this->order->updateStatus($order->getId(), self::STATUS_PAID);
            } else {
                $this->notifyInvoicePaid($order, $member, $notification);
            }
        }
    }

    private function createMemberInvoice(MemberModel $member, \DateTimeImmutable $invoiceDate): Order
    {
        $order = $this->prepareMemberInvoice($member, $invoiceDate);

        $insertId = $this->order->create($order)->insertId();

        return $this->order->read($insertId);
    }

    private function prepareMemberInvoice(MemberModel $member, \DateTimeImmutable $invoiceDate): Order
    {
        $this->setFiscalPeriod($invoiceDate);

        $membership = $this->memberships[$member->membership];
        $monthly = 'month' === $member->membership_interval && 'month' === $membership['type'] && ($membership['freeMember'] ?? false);

        $invoiceDescription = sprintf(
            '%s/%s - %s %s%s',
            $member->id,
            $invoiceDate->format($monthly ? 'm-Y' : 'Y'),
            $member->firstname,
            $member->lastname,
            $member->company ? ', '.$member->company : ''
        );

        $order = new Order((int) $member->cashctrl_id, 4);
        $order->setNr($member->id.'/'.$invoiceDate->format($monthly ? 'm-Y' : 'Y'));
        $order->setDate($invoiceDate);
        $order->setDueDays(30);
        $order->setDescription($invoiceDescription);

        $order->addItem($this->createInvoiceItem($member->membership, $member, $invoiceDate, null, $monthly));

        if ('active' !== $member->membership && !($membership['invisible'] ?? false) && $member->membership_member) {
            $order->addItem($this->createInvoiceItem(
                'active',
                $member,
                $invoiceDate,
                $membership['freeMember'] ?? false ? 0 : null,
                $monthly
            ));
        }

        $paidUntil = $invoiceDate->add(new \DateInterval($monthly ? 'P1M' : 'P1Y'))->sub(new \DateInterval('P1D'));
        if ($member->membership_invoiced < $paidUntil->getTimestamp()) {
            $member->membership_invoiced = $paidUntil->getTimestamp();
            $member->save();
        }

        return $order;
    }

    private function bookBalanceTransaction(BalanceTransaction|string $transaction, \DateTimeInterface $created, string $reference, string $title): void
    {
        try {
            if (!$transaction instanceof BalanceTransaction) {
                $transaction = $this->stripeClient->balanceTransactions->retrieve($transaction);
            }

            $fee = new Journal(
                (float) ($transaction->fee / 100),
                $this->getAccountId(1106),
                $this->getAccountId(6842),
                $created
            );
            $fee->setReference($reference);
            $fee->setTitle($title);

            $this->addJournalEntry($fee);
        } catch (ApiErrorException) {
            // Balance transaction not found
        }
    }

    private function updatePerson(Person $person, MemberModel $member): void
    {
        $person->setNr('M-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT));
        $person->setCategoryId($this->getCategoryId($member->membership));
        $person->setIsInactive(((bool) $member->disable) || 'inactive' === $member->membership);

        $person->setCompany($member->company);
        $person->setFirstName($member->firstname);
        $person->setLastName($member->lastname);
        $person->setTitleId($this->getTitleId($member->gender));
        $person->setLanguage($member->language);
        $person->setDateBirth($member->dateOfBirth ? date('Y-m-d', (int) $member->dateOfBirth) : '');
        $person->setVatUid($member->tax_id);

        $person->setCustomfield(2, date('Y-m-d', (int) $member->dateAdded));
        $person->setCustomfield(3, $member->stop ? date('Y-m-d', (int) $member->stop) : '');

        $invoiceAddress = $this->findAddress($person, PersonAddress::TYPE_MAIN);
        $invoiceAddress->setAddress($member->street);
        $invoiceAddress->setZip($member->postal);
        $invoiceAddress->setCity($member->city);
        $invoiceAddress->setCountry($member->country);

        $this->setContact($person, PersonContact::TYPE_EMAIL_WORK, (string) $member->email);
        $this->setContact($person, PersonContact::TYPE_PHONE_WORK, (string) $member->phone);
        $this->setContact($person, PersonContact::TYPE_MOBILE_WORK, (string) $member->mobile);
        $this->setContact($person, PersonContact::TYPE_FAX, (string) $member->fax);
        $this->setContact($person, PersonContact::TYPE_WEBSITE, (string) $member->website);
    }

    private function findAddress(Person $person, string $type): PersonAddress
    {
        if (null !== ($addresses = $person->getAddresses())) {
            foreach ($addresses as $address) {
                if ($type === $address->getType()) {
                    return $address;
                }
            }
        }

        $address = new PersonAddress($type);
        $person->addAddress($address);

        return $address;
    }

    private function setContact(Person $person, string $type, string $address): void
    {
        if (null !== ($contacts = $person->getContacts())) {
            foreach ($contacts as $contact) {
                if ($type === $contact->getType()) {
                    if (empty($address)) {
                        $person->removeContact($contact);
                    } else {
                        $contact->setAddress($address);
                    }

                    return;
                }
            }
        }

        if (!empty($address)) {
            $person->addContact(new PersonContact($address, $type));
        }
    }

    private function getCategoryId(string $membership): int|null
    {
        if (!isset($this->memberships[$membership]['categoryId'])) {
            return null;
        }

        return (int) $this->memberships[$membership]['categoryId'];
    }

    private function getTitleId(string $gender): int
    {
        return match ($gender) {
            'male' => 1,
            'female' => 2,
            'other' => 5,
            default => 0,
        };
    }

    private function getDateFormat(MemberModel $member): string
    {
        $locale = $member->language ?: 'de';

        if (isset($this->dateFormat[$locale])) {
            return $this->dateFormat[$locale];
        }

        $page = $this->getRootPageForLocale($locale);

        if (null !== $page && $page->dateFormat) {
            return $this->dateFormat[$locale] = $page->dateFormat;
        }

        return $this->dateFormat[$locale] = $GLOBALS['TL_CONFIG']['dateFormat'];
    }

    private function getRootPageForLocale(string $locale): PageModel|null
    {
        $t = PageModel::getTable();
        $columns = ["$t.language=? AND $t.type='root'"];

        if (!$this->tokenChecker->isPreviewMode()) {
            $time = Date::floorToMinute();
            $columns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        return PageModel::findOneBy($columns, [$locale]);
    }

    private function getPaymentLink(Order $order, MemberModel $member): string|null
    {
        $rootPage = $this->getRootPageForLocale($member->language ?: 'de');

        if (null === $rootPage) {
            return null;
        }

        $paymentPage = PageModel::findFirstPublishedByTypeAndPid('payment', $rootPage->id);

        if (null === $paymentPage) {
            return null;
        }

        $url = $this->urlGenerator->generate(RouteObjectInterface::OBJECT_BASED_ROUTE_NAME, [
            RouteObjectInterface::CONTENT_OBJECT => $paymentPage,
            'orderId' => $order->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url);
    }

    private function setFiscalPeriod(\DateTimeInterface|null $date = null): void
    {
        if (null === $date) {
            $date = new \DateTime();
        }

        foreach ($this->fiscalperiod->list() as $period) {
            if ($period->getStart() <= $date && $period->getEnd() >= $date) {
                $this->fiscalperiod->switch($period->getId());

                return;
            }
        }

        throw new \RuntimeException('No fiscal period for current date found');
    }

    private function createInvoiceItem(string $subscription, MemberModel $member, \DateTimeImmutable $invoiceDate, $price = null, bool $monthly = false): OrderItem
    {
        $itemName = $this->translator->trans(
            'invoice_name.'.$subscription,
            [],
            'messages',
            $member->language ?: 'de'
        );

        $itemDescription = $this->translator->trans(
            'invoice_description.'.$subscription,
            [
                '{from}' => $invoiceDate->format('d.m.Y'),
                '{to}' => $invoiceDate->add(new \DateInterval($monthly ? 'P1M' : 'P1Y'))->sub(new \DateInterval('P1D'))->format('d.m.Y'),
            ],
            'messages',
            $member->language ?: 'de'
        );

        $membership = $this->memberships[$subscription];

        if (null === $price) {
            $price = $membership['custom'] ? $member->membership_amount : $membership['price'];
        }

        $item = new OrderItem(
            $membership['accountId'],
            $itemName,
            (float) $price
        );
        $item->setQuantity('month' === $membership['type'] && !$monthly ? 12 : 1);
        $item->setDescription($itemDescription);

        return $item;
    }

    private function chargeWithStripe(Order $order, MemberModel $member): bool
    {
        if (empty($member->stripe_payment_method)) {
            return false;
        }

        try {
            $paymentIntent = $this->stripeClient->paymentIntents->create([
                'amount' => (int) round($order->total * 100),
                'currency' => strtolower($order->currencyCode ?: 'eur'),
                'confirm' => true,
                'customer' => $member->stripe_customer,
                'description' => $order->getDescription(),
                'metadata' => [
                    'cashctrl_order_id' => $order->getId(),
                    'auto_payment' => true,
                ],
                'payment_method_types' => ['card', 'sepa_debit', 'giropay'],
                'off_session' => true,
                'payment_method' => $member->stripe_payment_method,
            ]);

            if (!\in_array($paymentIntent->status, ['succeeded', 'processing'], true)) {
                $this->sentryOrThrow("Stripe PaymentIntent created with status \"{$paymentIntent->status}\"", null, [
                    'payment_intent' => $paymentIntent->toArray(),
                    'order' => $order->toArray(),
                    'member' => $member->row(),
                ]);
            }

            // Set order to "open" otherwise we can't add book entries
            $this->order->updateStatus($order->getId(), self::STATUS_OPEN);

            foreach ($paymentIntent->charges as $charge) {
                // Only book transaction if it was "immediate" (e.g. credit card) but not SEPA etc.
                if ($charge->balance_transaction) {
                    $this->bookToOrder($charge, $order, false);
                }
            }

            return true;
        } catch (ApiErrorException $exception) {
            $this->sentryOrThrow('Failed charging member invoice through Stripe.', $exception, [
                'order' => $order->toArray(),
                'member' => $member->row(),
            ]);

            return false;
        }
    }

    private function getStripePaymentDescription(Charge $charge): string
    {
        if ($charge->payment_method_details) {
            switch ($charge->payment_method_details['type'] ?? '') {
                case 'card':
                    $card = $charge->payment_method_details['card'] ?? [];

                    switch ($card['brand']) {
                        case 'mastercard':
                            return 'Zahlung (MasterCard '.($card['last4'] ?? '').')';

                        case 'visa':
                            return 'Zahlung (VISA '.($card['last4'] ?? '').')';
                    }
                    break;

                case 'sepa_debit':
                    return 'Zahlung (SEPA Lastschrift)';
            }
        }

        return 'Zahlung Stripe';
    }
}
