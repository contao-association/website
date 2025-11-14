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
use Terminal42\CashctrlApi\Entity\Order;

#[AsCommand('app:invoices:registration', 'Recover registration invoices for a member.')]
class RegistrationRecoveryCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CashctrlHelper $cashctrl,
        private readonly int $registrationNotificationId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('member_id', InputArgument::REQUIRED, 'ID of the member to recover.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();
        System::loadLanguageFile('default');

        $io = new SymfonyStyle($input, $output);
        $memberId = $input->getArgument('member_id');
        $member = MemberModel::findById($memberId);

        if (!$member) {
            $io->error('Member ID '.$memberId.' was not found.');

            return Command::FAILURE;
        }

        if ($member->cashctrl_invoice > 0) {
            $io->error('Member ID '.$memberId.' already has a registration email! (ID '.$member->cashctrl_invoice.')');

            return Command::FAILURE;
        }

        if (
            !$io->confirm(\sprintf(
                'Invoicing %s %s (%s). Continue?',
                $member->firstname,
                $member->lastname,
                $member->email,
            ))
        ) {
            return Command::SUCCESS;
        }

        try {
            $this->cashctrl->syncMember($member);
            $invoice = $this->cashctrl->createAndSendInvoice($member, $this->registrationNotificationId, new \DateTimeImmutable());

            if ($invoice instanceof Order) {
                $member->cashctrl_invoice = $invoice->getId();
                $member->save();
            } else {
                $io->error('Unable to create invoice in CashCtrl');

                return Command::FAILURE;
            }
        } catch (\Exception) {
            $io->error('Unable to send invoice to '.$member->email);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
