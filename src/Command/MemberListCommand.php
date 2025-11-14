<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:member-list', 'Generate a list of active Association members')]
class MemberListCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly array $memberships,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('columns', mode: InputOption::VALUE_REQUIRED)
            ->addOption('format', mode: InputOption::VALUE_REQUIRED, default: 'txt')
            ->addOption('public', description: 'Show public members only (visible on contao.org)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $columns = $input->getOption('columns') ? explode(',', (string) $input->getOption('columns')) : ['id', 'firstname', 'lastname', 'company', 'email', 'membership', 'language'];
        $data = [];

        foreach ($this->getActiveMembers() as $member) {
            $listing = $this->memberships[$member['membership']]['listing'] ?? [];

            if ($input->getOption('public') && (!$member['listing'] || empty($listing['name']))) {
                continue;
            }

            $data[] = array_intersect_key($member, array_flip($columns));
        }

        switch ($input->getOption('format')) {
            case 'json':
                $output->writeln(json_encode($data));
                break;

            case 'csv':
                if (!$output instanceof StreamOutput) {
                    throw new \RuntimeException('CSV Format wird nur bei Stream-Output unterstützt.');
                }

                $stream = $output->getStream();
                fputcsv($stream, array_keys($data[0]));
                array_walk($data, static fn ($row) => fputcsv($stream, $row));
                break;

            case 'txt':
            default:
                $io = new SymfonyStyle($input, $output);
                $io->success(\count($data).' Mitglieder gefunden.');
                $io->table($columns, $data);
                break;
        }

        return Command::SUCCESS;
    }

    private function getActiveMembers(): \Traversable
    {
        return $this->connection->executeQuery("
            SELECT *
            FROM tl_member
            WHERE disable=0
              AND membership!='inactive'
              AND (start='' OR start<UNIX_TIMESTAMP())
              AND (stop='' OR stop>UNIX_TIMESTAMP())
              AND (membership_start='' OR membership_start<UNIX_TIMESTAMP())
              AND (membership_stop='' OR membership_stop>UNIX_TIMESTAMP())
        ")->iterateAssociative();
    }
}
