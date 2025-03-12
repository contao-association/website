<?php

declare(strict_types=1);

namespace App\Command;

use App\CashctrlHelper;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\System;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:invoices:remind', 'Send reminders for overdue invoices.')]
class InvoicesReminderCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CashctrlHelper $cashctrl,
        private readonly int $overdueNotificationId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('member', InputArgument::OPTIONAL, 'Send invoice reminders for a specific member ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();
        System::loadLanguageFile('default');

        $io = new SymfonyStyle($input, $output);

        foreach ($this->cashctrl->getOverdueInvoices() as $order) {
            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

            if (null === $member) {
                $io->warning('Invoice '.$order->getNr().' does not belong to a member ('.$order->getAssociateId().'/'.$order->associateName.')');
                continue;
            }

            if ($input->getArgument('member') && (int) $input->getArgument('member') !== (int) $member->id) {
                continue;
            }

            $invoiceDueDate = \DateTime::createFromInterface($order->getDate());
            $invoiceDueDate->add(new \DateInterval('P'.(int) $order->getDueDays().'D'));

            if (
                !$io->confirm(\sprintf(
                    'Sending reminder for %s %s (%s), invoices %s was due on %s. Continue?',
                    $member->firstname,
                    $member->lastname,
                    $member->email,
                    $order->getNr(),
                    $invoiceDueDate->format('Y-m-d'),
                ))
            ) {
                continue;
            }

            try {
                $pdf = $this->cashctrl->archiveInvoice($order);

                if (!$this->cashctrl->sendInvoiceNotification($this->overdueNotificationId, $order, $member, ['invoice_pdf' => $pdf])) {
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

        return Command::SUCCESS;
    }
}
