<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Contao\CoreBundle\Framework\ContaoFramework;
use App\CashctrlHelper;
use Contao\MemberModel;

class CashctrlSyncCommand extends Command
{
    protected static $defaultName = 'app:cashctrl:sync';

    private ContaoFramework $framework;
    private CashctrlHelper $cashctrl;

    public function __construct(ContaoFramework $framework, CashctrlHelper $cashctrl)
    {
        parent::__construct();
        $this->framework = $framework;
        $this->cashctrl = $cashctrl;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Updates all member data in Cashctrl.')
            ->addArgument('member_ids', InputArgument::OPTIONAL, 'A comma separated list of member IDs if not all should be synced.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();

        if ($ids = $input->getArgument('member_ids')) {
            $members = MemberModel::findBy(['tl_member.id IN ('.implode(',', array_map('intval', explode(',', $ids))).')'], []);
        } else {
            $members = MemberModel::findAll();
        }

        $io = new SymfonyStyle($input, $output);

        if (null === $members) {
            $io->error('No members found');
            return 1;
        }

        $io->progressStart(count($members));

        foreach ($members as $member) {
            $this->cashctrl->syncMember($member);
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('Synchronization complete.');

        return 0;
    }
}
