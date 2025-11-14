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

#[AsCommand('app:cashctrl:sync', 'Updates all member data in Cashctrl.')]
class CashctrlSyncCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CashctrlHelper $cashctrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('member_ids', InputArgument::OPTIONAL, 'A comma separated list of member IDs if not all should be synced.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();
        System::loadLanguageFile('default');

        if ($ids = $input->getArgument('member_ids')) {
            $members = MemberModel::findBy(['tl_member.id IN ('.implode(',', array_map(intval(...), explode(',', (string) $ids))).')'], []);
        } else {
            $members = MemberModel::findAll();
        }

        $io = new SymfonyStyle($input, $output);

        if (null === $members) {
            $io->error('No members found');

            return Command::FAILURE;
        }

        $io->progressStart(is_countable($members) ? \count($members) : 0);

        foreach ($members as $member) {
            $this->cashctrl->syncMember($member);
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('Synchronization complete.');

        return Command::SUCCESS;
    }
}
