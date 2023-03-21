<?php

declare(strict_types=1);

namespace App\Cron;

use App\CashctrlHelper;
use App\ErrorHandlingTrait;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use NotificationCenter\Model\Notification;

#[AsCronJob('0 9 * * 1')] // run every monday at 9am
class InvoicesReminderCron
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CashctrlHelper $cashctrl,
        private readonly int $overdueNotificationId
    ) {}

    public function __invoke(): void
    {
        $this->framework->initialize();

        $notification = Notification::findByPk($this->overdueNotificationId);

        if (null === $notification) {
            $this->sentryOrThrow('Notification ID "'.$this->overdueNotificationId.'" not found, cannot send invoice reminders');
            return;
        }

        foreach ($this->cashctrl->getOverdueInvoices() as $order) {
            // Skip invoices that are set to "Gemahnt 1" or "Gemahnt 2"
            if (\in_array($order->getStatusId(), [40, 43], true)) {
                continue;
            }

            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

            if (null === $member) {
                $this->sentryOrThrow('Invoice '.$order->getNr().' does not belong to a member ('.$order->getAssociateId().'/'.$order->associateName.')');
                continue;
            }

            try {
                $pdf = $this->cashctrl->archiveInvoice($order);

                if (!$this->cashctrl->sendInvoiceNotification($notification, $order, $member, ['invoice_pdf' => $pdf])) {
                    $this->sentryOrThrow('Unable to send invoice reminder to '.$member->email);
                    continue;
                }
            } catch (\Throwable $e) {
                $this->sentryOrThrow('Unable to send invoice reminder to '.$member->email, $e);
                continue;
            }

            if ($order->getStatusId() !== CashctrlHelper::STATUS_OVERDUE) {
                $this->cashctrl->order->updateStatus($order->getId(), CashctrlHelper::STATUS_OVERDUE);
            }
        }
    }
}
