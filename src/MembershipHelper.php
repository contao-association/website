<?php

declare(strict_types=1);

namespace App;

use Contao\MemberModel;
use NotificationCenter\Model\Notification;
use Contao\Config;
use Symfony\Contracts\Translation\TranslatorInterface;
use Haste\Util\StringUtil;
use function Sentry\captureMessage;
use Terminal42\CashctrlApi\Entity\Order;
use Contao\PageModel;
use Terminal42\CashctrlApi\ApiClient;
use Terminal42\CashctrlApi\Api\Filter\OrderListFilter;

class MembershipHelper
{
    private CashctrlApi $api;
    private TranslatorInterface $translator;

    private array $dateFormat = [];

    public function __construct(CashctrlApi $api, TranslatorInterface $translator)
    {
        $this->api = $api;
        $this->translator = $translator;
    }

    /**
     * @return Order[]
     */
    public function getLastUpdatedInvoices(): array
    {
        return $this->api->order
            ->list()
            ->ofType(OrderListFilter::TYPE_SALES)
            ->withStatus(18)
            ->sortBy('lastUpdated', 'DESC')
            ->get()
        ;
    }

    public function createAndSendInvoice(MemberModel $member, int $notificationId): ?Order
    {
        $notification = Notification::findByPk($notificationId);

        if (null === $notification) {
            $this->sentryOrThrow('Notification ID "'.$notificationId.'" not found, cannot send invoices');
            return null;
        }

        $invoice = $this->api->createMemberInvoice($member);
        $pdf = $this->api->archiveInvoice($invoice, 'ch' === $member->country ? 11 : 13, $member->language ?: 'de');

        if (!$this->sendInvoiceNotification($notification, $invoice, $member, ['invoice_pdf' => $pdf])) {
            $this->sentryOrThrow('Unable to send invoice email to '.$member->email);
            return $invoice;
        }

        $this->api->markInvoiceSent($invoice->getId());

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
        ]);

        if ($invoice->isClosed) {
            $tokens['payment_date'] = ApiClient::parseDateTime($invoice->dateLastBooked)->format($this->getDateFormat($member));
            $tokens['payment_total'] = number_format($invoice->total - $invoice->open, 2, '.', "'");
        }

        StringUtil::flatten($member->row(), 'member', $tokens);

        $result = $notification->send($tokens);

        if (\in_array(false, $result, true)) {
            return false;
        }

        return true;
    }

    public function getDateFormat(MemberModel $member): string
    {
        $locale = $member->language ?: 'de';

        if (isset($this->dateFormat[$locale])) {
            return $this->dateFormat[$locale];
        }

        $page = PageModel::findOneBy(['language=?', "type='root'"], [$locale]);

        if (null !== $page && $page->dateFormat) {
            return $this->dateFormat[$locale] = $page->dateFormat;
        }

        return $this->dateFormat[$locale] = $GLOBALS['TL_CONFIG']['dateFormat'];
    }

    public function sentryOrThrow(string $message): void
    {
        if (null === captureMessage($message)) {
            throw new \RuntimeException($message);
        }
    }
}
