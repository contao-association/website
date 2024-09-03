<?php

declare(strict_types=1);

namespace App\Cron;

use App\CashctrlHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Oneup\ContaoSentryBundle\ErrorHandlingTrait;

#[AsCronJob('0 9 1-7,15-21 * *')] // run job two weeks per month, PHP code will make sure it only sends on mondays
class InvoicesReminderCron
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CashctrlHelper $cashctrl,
        private readonly int $overdueNotificationId,
    ) {
    }

    public function __invoke(): void
    {
        $this->sentryCheckIn();

        // only send reminders on mondays
        if (1 !== (int) date('w')) {
            $this->sentryCheckIn(true);

            return;
        }

        $this->framework->initialize();

        $minDueDate = new \DateTimeImmutable('-2 weeks');

        foreach ($this->cashctrl->getOverdueInvoices() as $order) {
            // Skip invoices that are set to "Gemahnt 1" or "Gemahnt 2"
            if (\in_array($order->getStatusId(), [40, 43], true)) {
                continue;
            }

            $dueDate = \DateTime::createFromInterface($order->getDate());
            $dueDate->add(new \DateInterval('P'.(int) $order->getDueDays().'D'));

            if ($dueDate > $minDueDate) {
                // only send reminder if the invoice is at least two weeks overdue
                continue;
            }

            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

            if (null === $member) {
                $this->sentryOrThrow('Cannot send reminder for invoice '.$order->getNr().', it does not belong to a member ('.$order->getAssociateId().'/'.$order->associateName.')');
                continue;
            }

            try {
                $pdf = $this->cashctrl->archiveInvoice($order);

                if (!$this->cashctrl->sendInvoiceNotification($this->overdueNotificationId, $order, $member, ['invoice_pdf' => $pdf])) {
                    $this->sentryOrThrow('Unable to send invoice reminder to '.$member->email);
                    continue;
                }
            } catch (\Throwable $e) {
                $this->sentryOrThrow('Unable to send invoice reminder to '.$member->email, $e);
                continue;
            }

            if (CashctrlHelper::STATUS_OVERDUE !== $order->getStatusId()) {
                $this->cashctrl->order->updateStatus($order->getId(), CashctrlHelper::STATUS_OVERDUE);
            }
        }

        $this->sentryCheckIn(true);
    }
}
