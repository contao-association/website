<?php

declare(strict_types=1);

namespace App\Command;

use App\CashctrlHelper;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\System;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Terminal42\CashctrlApi\Entity\Order;

#[AsCommand('app:invoices', '(Re-)send recurring invoices for a given date.')]
class RecurringInvoicesCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly CashctrlHelper $cashctrl,
        private readonly LoggerInterface $logger,
        private readonly array $memberships,
        private readonly int $invoiceNotificationId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('date', InputArgument::REQUIRED, 'Date to create the invoices for (Y-m-d).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->framework->initialize();
        System::loadLanguageFile('default');

        $io = new SymfonyStyle($input, $output);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input->getArgument('date') ?? '')) {
            $io->error('Invalid date format');

            return Command::FAILURE;
        }

        $date = new \DateTimeImmutable($input->getArgument('date'));

        if ($date->format('Y-m-d') !== $input->getArgument('date')) {
            $io->error('Invalid date format');

            return Command::FAILURE;
        }

        [$monthly, $yearly] = [
            array_keys(array_filter($this->memberships, static fn (array $m) => 'month' === ($m['type'] ?? null) && ($m['freeMember'] ?? false))),
            array_keys(array_filter($this->memberships, static fn (array $m) => 'month' !== ($m['type'] ?? null) || !($m['freeMember'] ?? false))),
        ];

        // Find members which have been added today some time ago
        // @see http://stackoverflow.com/a/2218577
        $ids = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT id
                FROM tl_member
                WHERE
                    DATE_FORMAT(FROM_UNIXTIME(membership_start),'%Y-%m-%d') != :today
                    AND (
                        (
                            membership IN (:yearly)
                            AND DATE_FORMAT(FROM_UNIXTIME(membership_start),'%m-%d') = :currentMonth
                        ) OR (
                            membership IN (:monthly) AND (
                                (
                                    membership_interval != 'month'
                                    AND DATE_FORMAT(FROM_UNIXTIME(membership_start),'%m-%d') = :currentMonth
                                ) OR (
                                    membership_interval = 'month'
                                    AND DATE_FORMAT(FROM_UNIXTIME(membership_start),'%d') = :currentDay
                                )
                            )
                        )
                    )
                    AND disable=''
                    AND (start='' OR start<=UNIX_TIMESTAMP())
                    AND (stop='' OR stop>UNIX_TIMESTAMP())
                    AND (membership_stop='' OR membership_stop>UNIX_TIMESTAMP())
            SQL,
            [
                'today' => $date->format('Y-m-d'),
                'currentMonth' => $date->format('m-d'),
                'currentDay' => $date->format('d'),
                'yearly' => $yearly,
                'monthly' => $monthly,
            ],
            [
                'yearly' => ArrayParameterType::STRING,
                'monthly' => ArrayParameterType::STRING,
            ],
        );

        if ([] === $ids || null === ($members = MemberModel::findMultipleByIds($ids))) {
            $io->warning('No members found that renew on '.$date->format('F jS, Y'));

            return Command::SUCCESS;
        }

        foreach ($members as $member) {
            $invoiced = (new \DateTime())->setTimestamp((int) $member->membership_invoiced);
            if (
                !$io->confirm(\sprintf(
                    'Invoicing %s %s (%s), membership invoiced until %s (%s). Continue?',
                    $member->firstname,
                    $member->lastname,
                    $member->email,
                    $invoiced->format('F jS, Y'),
                    $invoiced->format('Y-m-d'),
                ))
            ) {
                continue;
            }

            try {
                $this->cashctrl->syncMember($member);
                $invoice = $this->cashctrl->createAndSendInvoice($member, $this->invoiceNotificationId, $date);

                if ($invoice instanceof Order) {
                    $this->logger->info('Recurring membership invoice '.$invoice->getNr().' sent to '.$member->email);
                }
            } catch (\Exception) {
                $io->error('Unable to send recurring invoice to '.$member->email.' (member ID '.$member->id.')');
            }
        }

        return Command::SUCCESS;
    }
}
