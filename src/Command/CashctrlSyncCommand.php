<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Contao\CoreBundle\Framework\ContaoFramework;
use App\CashctrlApi;
use Contao\MemberModel;

class CashctrlSyncCommand extends Command
{
    protected static $defaultName = 'app:cashctrl:sync';

    private ContaoFramework $framework;
    private CashctrlApi $api;

    public function __construct(ContaoFramework $framework, CashctrlApi $api)
    {
        parent::__construct();
        $this->framework = $framework;
        $this->api = $api;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Updates all member data in Cashctrl.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();
        $members = MemberModel::findAll();

        $io = new SymfonyStyle($input, $output);

        if (null === $members) {
            $io->error('No members found');
            return 1;
        }

        $io->progressStart(count($members));

        foreach ($members as $member) {
            $this->api->syncMember($member);
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success('All migrations completed.');

        return 0;
    }
}
