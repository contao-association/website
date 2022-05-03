<?php

declare(strict_types=1);

namespace App;

use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\Date;
use Contao\Versions;
use Psr\Log\LoggerInterface;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Terminal42\CashctrlApi\Api\AccountEndpoint;
use Terminal42\CashctrlApi\Api\FiscalperiodEndpoint;
use Terminal42\CashctrlApi\Api\JournalEndpoint;
use Terminal42\CashctrlApi\Api\OrderBookentryEndpoint;
use Terminal42\CashctrlApi\Api\PersonEndpoint;
use Contao\MemberModel;
use Terminal42\CashctrlApi\Entity\Journal;
use Terminal42\CashctrlApi\Entity\OrderBookentry;
use Terminal42\CashctrlApi\Entity\Person;
use Terminal42\CashctrlApi\Entity\PersonAddress;
use Terminal42\CashctrlApi\Entity\PersonContact;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\Entity\OrderItem;
use Terminal42\CashctrlApi\Api\OrderEndpoint;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\CashctrlApi\Api\OrderDocumentEndpoint;
use Symfony\Component\Filesystem\Filesystem;
use Terminal42\CashctrlApi\Api\Filter\ListFilter;
use Terminal42\CashctrlApi\Api\Filter\OrderListFilter;
use NotificationCenter\Model\Notification;
use Contao\Config;
use Terminal42\CashctrlApi\ApiClient;
use Haste\Util\StringUtil;
use Contao\PageModel;
use Terminal42\CashctrlApi\Exception\RuntimeException;
use Terminal42\CashctrlApi\Result;
use function Sentry\captureEvent;

class CashctrlHelper
{
    public PersonEndpoint $person;
    public OrderEndpoint $order;
    public OrderDocumentEndpoint $orderDocument;
    public FiscalperiodEndpoint $fiscalperiod;
    public JournalEndpoint $journal;
    public OrderBookentryEndpoint $orderBookentry;
    public AccountEndpoint $account;
    private TranslatorInterface $translator;
    private TokenChecker $tokenChecker;
    private UrlGeneratorInterface $urlGenerator;
    private UriSigner $uriSigner;
    private Filesystem $filesystem;
    private LoggerInterface $logger;
    private array $memberships;
    private string $projectDir;

    private array $dateFormat = [];
    private array $accountIds = [];

    public function __construct(
        PersonEndpoint $person,
        OrderEndpoint $order,
        OrderDocumentEndpoint $orderDocument,
        FiscalperiodEndpoint $fiscalperiod,
        JournalEndpoint $journal,
        OrderBookentryEndpoint $orderBookentry,
        AccountEndpoint $account,
        TranslatorInterface $translator,
        TokenChecker $tokenChecker,
        UrlGeneratorInterface $urlGenerator,
        UriSigner $uriSigner,
        Filesystem $filesystem,
        LoggerInterface $logger,
        array $memberships,
        string $projectDir
    ) {
        $this->person = $person;
        $this->order = $order;
        $this->orderDocument = $orderDocument;
        $this->fiscalperiod = $fiscalperiod;
        $this->journal = $journal;
        $this->orderBookentry = $orderBookentry;
        $this->account = $account;
        $this->translator = $translator;
        $this->tokenChecker = $tokenChecker;
        $this->urlGenerator = $urlGenerator;
        $this->uriSigner = $uriSigner;
        $this->filesystem = $filesystem;
        $this->logger = $logger;
        $this->memberships = $memberships;
        $this->projectDir = $projectDir;
    }

    public function syncMember(MemberModel $member): void
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
                return;
            }

            $result = $this->person->create($person);

            $member->cashctrl_id = $result->insertId();
            $member->save();
        } catch (RuntimeException $exception) {
            $this->sentryOrThrow("Error updating member ID {$member->id} in CashCtrl: ".$exception->getMessage(), $exception, ['person' => $person->toArray()]);
        }
    }

    public function createAndSendInvoice(MemberModel $member, int $notificationId, \DateTimeImmutable $invoiceDate = null): ?Order
    {
        $notification = Notification::findByPk($notificationId);

        if (null === $notification) {
            $this->sentryOrThrow('Notification ID "'.$notificationId.'" not found, cannot send invoices');
            return null;
        }

        $invoiceDate = $invoiceDate ?? new \DateTimeImmutable();
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $invoiceDate = $invoiceDate->setTime(0, 0);

        $invoice = $this->createMemberInvoice($member, $invoiceDate);
        $pdf = $this->archiveInvoice($invoice, 'ch' === $member->country ? 1011 : 1013, $member->language ?: 'de');

        if (!$this->sendInvoiceNotification($notification, $invoice, $member, ['invoice_pdf' => $pdf])) {
            $this->sentryOrThrow('Unable to send invoice email to '.$member->email);
            return $invoice;
        }

        $this->order->updateStatus($invoice->getId(), 16);

        return $invoice;
    }

    public function sendInvoiceNotification(Notification $notification, Order $invoice, MemberModel $member, array $tokens = []): bool
    {
        $tokens = array_merge($tokens, [
            'admin_email' => Config::get('adminEmail'),
            'membership_label' => $this->translator->trans('membership.'.$member->membership, [], null, $member->language ?: 'de'),
            'invoice_number' => $invoice->getNr(),
            'invoice_date' => $invoice->getDate()->format($this->getDateFormat($member)),
            'invoice_due_days' => $invoice->getDueDays(),
            'invoice_total' => number_format($invoice->total, 2, '.', "'"),
            'payment_first' => $invoice->getId() === $member->cashctrl_invoice,
        ]);

        if ($invoice->isClosed) {
            $tokens['payment_date'] = ApiClient::parseDateTime($invoice->dateLastBooked)->format($this->getDateFormat($member));
            $tokens['payment_total'] = number_format($invoice->total - $invoice->open, 2, '.', "'");
        } else {
            $tokens['payment_link'] = $this->getPaymentLink($invoice, $member);
        }

        StringUtil::flatten($member->row(), 'member', $tokens);

        $result = $notification->send($tokens);

        if (\in_array(false, $result, true)) {
            return false;
        }

        return true;
    }

    public function createMemberInvoice(MemberModel $member, \DateTimeImmutable $invoiceDate): Order
    {
        $order = $this->prepareMemberInvoice($member, $invoiceDate);

        $insertId = $this->order->create($order)->insertId();

        return $this->order->read($insertId);
    }

    public function prepareMemberInvoice(MemberModel $member, \DateTimeImmutable $invoiceDate): Order
    {
        $this->setFiscalPeriod($invoiceDate);

        $membership = $this->memberships[$member->membership];
        $monthly = 'month' === $member->membership_interval && 'month' === $membership['type'] && ($membership['freeMember'] ?? false);

        $invoiceDescription = sprintf(
            '%s/%s - %s %s%s',
            $member->id,
            $invoiceDate->format('Y'),
            $member->firstname,
            $member->lastname,
            ($member->company ? ', '.$member->company : '')
        );

        $order = new Order((int) $member->cashctrl_id, 4);
        $order->setNr($member->id.'/'.$invoiceDate->format($monthly ? 'm-Y' : 'Y'));
        $order->setDate($invoiceDate);
        $order->setDueDays(30);
        $order->setDescription($invoiceDescription);

        $order->addItem($this->createInvoiceItem($member->membership, $member, $invoiceDate, null, $monthly));

        if ('active' !== $member->membership && !($membership['legacy'] ?? false) && $member->membership_member) {
            $order->addItem($this->createInvoiceItem(
                'active',
                $member,
                $invoiceDate,
                $membership['freeMember'] ? 0 : null,
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

    public function downloadInvoice(Order $invoice, int $templateId = null, string $language): string
    {
        if (null !== $templateId) {
            $this->orderDocument->update($invoice->getId(), ['templateId' => $templateId]);
        }

        return $this->orderDocument->downloadPdf([$invoice->getId()], $language);
    }

    public function archiveInvoice(Order $invoice, int $templateId = null, string $language): string
    {
        $year = $invoice->getDate()->format('Y');
        $quarter = ceil($invoice->getDate()->format('n') / 3);

        $name = str_replace('/', '-', $invoice->getNr());
        $targetFile = 'var/invoices/'.$year.'/Q'.$quarter.'/'.$name.'.pdf';
        $this->filesystem->dumpFile(
            $this->projectDir.'/'.$targetFile,
            $this->downloadInvoice($invoice, $templateId, $language)
        );

        return $targetFile;
    }

    public function listInvoices(MemberModel $member): array
    {
        if (!$member->cashctrl_id) {
            return [];
        }

        $invoices = [];
        foreach ($this->fiscalperiod->list() as $period) {
            if ($period->getStart() < new \DateTime('2021-01-01')) {
                continue;
            }

            $invoices[] = $this->order
                ->list()
                ->inFiscalPeriod($period->getId())
                ->filter('associateId', $member->cashctrl_id, ListFilter::EQUALS)
                ->get();
        }

        return array_merge(...$invoices);
    }

    /**
     * @return Order[]
     */
    public function getLastPaidInvoices(): array
    {
        return $this->order
            ->list()
            ->ofType(OrderListFilter::TYPE_SALES)
            ->withStatus(18)
            ->sortBy('lastUpdated', 'DESC')
            ->get();
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
            $objVersions->setEditUrl('contao/main.php?do=member&act=edit&id=%s&rt=1');
            $objVersions->initialize();

            $member->cashctrl_invoice = 0;
            $member->disable = '';
            $member->save();

            $objVersions->create(true);
        }

        $this->syncMember($member);

        $this->order->updateStatus($order->getId(), 87);
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

    public function getAccountId(int $accountNumber): ?int
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

    public function sentryOrThrow(string $message, \Exception $exception = null, array $contexts = []): void
    {
        $event = Event::createEvent();
        $event->setMessage($message);

        foreach ($contexts as $name => $data) {
            $event->setContext($name, $data);
        }

        if (null === captureEvent($event, EventHint::fromArray(['exception' => $exception]))) {
            throw new \RuntimeException($message, 0, $exception);
        }
    }

    private function updatePerson(Person $person, MemberModel $member): void
    {
        $person->setNr('M-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT));
        $person->setCategoryId($this->getCategoryId($member->membership));
        $person->setIsInactive((bool) $member->disable);

        $person->setCompany($member->company);
        $person->setFirstName($member->firstname);
        $person->setLastName($member->lastname);
        $person->setTitleId($this->getTitleId($member->gender));
        $person->setLanguage($member->language);
        $person->setDateBirth($member->dateOfBirth ? date('Y-m-d', (int) $member->dateOfBirth) : '');

        $person->setCustomfield(2, date('Y-m-d', (int) $member->dateAdded));
        $person->setCustomfield(3, $member->stop ? date('Y-m-d', (int) $member->stop) : '');
        $person->setCustomfield(4, $member->tax_id);

        $invoiceAddress = $this->findAddress($person, PersonAddress::TYPE_MAIN);
        $invoiceAddress->setAddress($member->street);
        $invoiceAddress->setZip($member->postal);
        $invoiceAddress->setCity($member->city);
        $invoiceAddress->setCountry($member->country);

        $this->setContact($person, PersonContact::TYPE_EMAIL, PersonContact::PURPOSE_INVOICE, (string) $member->email);
        $this->setContact($person, PersonContact::TYPE_PHONE, PersonContact::PURPOSE_INVOICE, (string) $member->phone);
        $this->setContact($person, PersonContact::TYPE_MOBILE, PersonContact::PURPOSE_INVOICE, (string) $member->mobile);
        $this->setContact($person, PersonContact::TYPE_FAX, PersonContact::PURPOSE_INVOICE, (string) $member->fax);
        $this->setContact($person, PersonContact::TYPE_WEBSITE, PersonContact::PURPOSE_INVOICE, (string) $member->website);
    }

    private function findAddress(Person $person, string $type): PersonAddress
    {
        if (null !== ($addresses = $person->getAddresses())) {
            foreach ($addresses as $address) {
                if ($type === $address->type) {
                    return $address;
                }
            }
        }

        $address = new PersonAddress($type);
        $person->addAddress($address);

        return $address;
    }

    private function setContact(Person $person, string $type, string $purpose, string $address): void
    {
        if (null !== ($contacts = $person->getContacts())) {
            foreach ($contacts as $contact) {
                if ($type === $contact->type && $purpose === $contact->purpose) {
                    if (empty($address)) {
                        $person->removeContact($contact);
                    } else {
                        $contact->address = $address;
                    }
                    return;
                }
            }
        }

        if (!empty($address)) {
            $person->addContact(new PersonContact($address, $purpose, $type));
        }
    }

    private function getCategoryId(string $membership): int
    {
        if (!isset($this->memberships[$membership])) {
            return 1;
        }

        return (int) $this->memberships[$membership]['categoryId'];
    }

    private function getTitleId(string $gender): int
    {
        switch ($gender) {
            case 'male':
                return 1;

            case 'female':
                return 2;

            case 'other':
                return 5;
        }

        return 0;
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

    private function getRootPageForLocale(string $locale): ?PageModel
    {
        $t = PageModel::getTable();
        $columns = ["$t.language=? AND $t.type='root'"];

        if (!$this->tokenChecker->isPreviewMode())
        {
            $time = Date::floorToMinute();
            $columns[] = "$t.published='1' AND ($t.start='' OR $t.start<='$time') AND ($t.stop='' OR $t.stop>'$time')";
        }

        return PageModel::findOneBy($columns, [$locale]);
    }

    private function getPaymentLink(Order $order, MemberModel $member): ?string
    {
        $rootPage = $this->getRootPageForLocale($member->language ?: 'de');

        if (null === $rootPage) {
            return null;
        }

        $paymentPage = PageModel::findFirstPublishedByTypeAndPid('payment', $rootPage->id);

        if (null === $paymentPage) {
            return null;
        }

        $url = $this->urlGenerator->generate(RouteObjectInterface::OBJECT_BASED_ROUTE_NAME, [RouteObjectInterface::CONTENT_OBJECT => $paymentPage, 'orderId' => $order->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->uriSigner->sign($url);
    }

    private function setFiscalPeriod(\DateTimeInterface $date = null): void
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
        $item->setQuantity(('month' === $membership['type'] && !$monthly) ? 12 : 1);
        $item->setDescription($itemDescription);

        return $item;
    }
}
