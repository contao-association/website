<?php

declare(strict_types=1);

namespace App\Command;

use App\CashctrlHelper;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use NotificationCenter\Model\Notification;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InvoicesReminderCommand extends Command
{
    protected static $defaultName = 'app:invoices:remind';
    protected static $defaultDescription = 'Send reminders for overdue invoices.';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CashctrlHelper $cashctrl,
        private readonly int $overdueNotificationId,
    ) {
        parent::__construct();
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->framework->initialize();

        $io = new SymfonyStyle($input, $output);

        $notification = Notification::findByPk($this->overdueNotificationId);

        if (null === $notification) {
            $io->error('Notification ID "'.$this->overdueNotificationId.'" not found, cannot send invoice reminders');

            return -1;
        }

        foreach ($this->cashctrl->getOverdueInvoices() as $order) {
            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

            if (null === $member) {
                $io->warning('Invoice '.$order->getNr().' does not belong to a member ('.$order->getAssociateId().'/'.$order->associateName.')');
                continue;
            }

            if ($input->getArgument('member') && (int) $input->getArgument('member') !== (int) $member->id) {
                continue;
            }

            $invoiceDueDate = clone $order->getDate();
            $invoiceDueDate->add(new \DateInterval('P'.(int) $order->getDueDays().'D'));

            if (
                !$io->confirm(sprintf(
                    'Sending reminder for %s %s (%s), invoices %s was due on %s. Continue?',
                    $member->firstname,
                    $member->lastname,
                    $member->email,
                    $order->getNr(),
                    $invoiceDueDate->format('Y-m-d')
                ))
            ) {
                continue;
            }

            try {
                $pdf = $this->cashctrl->archiveInvoice($order);

                if (!$this->cashctrl->sendInvoiceNotification($notification, $order, $member, ['invoice_pdf' => $pdf])) {
                    $io->error('Unable to send invoice reminder to '.$member->email);
                    continue;
                }
            } catch (\Throwable $e) {
                $io->error('Unable to send invoice reminder to '.$member->email.': '.$e->getMessage());
                continue;
            }

            if (CashctrlHelper::STATUS_OVERDUE !== $order->getStatusId()) {
                $this->cashctrl->order->updateStatus($order->getId(), CashctrlHelper::STATUS_OVERDUE);
            }
        }

        return 0;
    }

    protected function configure(): void
    {
        $this->addArgument('member', InputArgument::OPTIONAL, 'Send invoice reminders for a specific member ID');
    }
}
