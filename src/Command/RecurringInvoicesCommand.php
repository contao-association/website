<?php

declare(strict_types=1);

namespace App\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use App\CashctrlHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RecurringInvoicesCommand extends Command
{
    protected static $defaultName = 'app:invoices';

    private ContaoFramework $framework;
    private Connection $connection;
    private CashctrlHelper $cashctrl;
    private LoggerInterface $logger;
    private int $notificationId;

    public function __construct(
        ContaoFramework $framework,
        Connection $connection,
        CashctrlHelper $cashctrl,
        LoggerInterface $logger,
        int $notificationId
    ) {
        parent::__construct();

        $this->framework = $framework;
        $this->connection = $connection;
        $this->cashctrl = $cashctrl;
        $this->logger = $logger;
        $this->notificationId = $notificationId;
    }

    protected function configure()
    {
        $this
            ->setDescription('(Re-)send recurring invoices for a given date.')
            ->addArgument('date', InputArgument::REQUIRED, 'Date to create the invoices for (Y-m-d).')
        ;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->framework->initialize();

        $io = new SymfonyStyle($input, $output);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input->getArgument('date') ?? '')) {
            $io->error('Invalid date format');
            return 1;
        }

        $date = new \DateTimeImmutable($input->getArgument('date'));

        if ($date->format('Y-m-d') !== $input->getArgument('date')) {
            $io->error('Invalid date format');
            return 1;
        }

        // Find members which have been added today some time ago
        // @see http://stackoverflow.com/a/2218577
        $ids = $this->connection->fetchFirstColumn("
            SELECT id
            FROM tl_member
            WHERE
                DATE_FORMAT(FROM_UNIXTIME(membership_start),'%Y-%m-%d') != ?
                AND (
                    (
                        membership_interval != 'month'
                        AND DATE_FORMAT(FROM_UNIXTIME(membership_start),'%m-%d') = ?
                    ) OR (
                        membership_interval = 'month'
                        AND DATE_FORMAT(FROM_UNIXTIME(membership_start),'%d') = ?
                    )
                )
                AND disable=''
                AND (start='' OR start<=UNIX_TIMESTAMP())
                AND (stop='' OR stop>UNIX_TIMESTAMP())
                AND (membership_stop='' OR membership_stop>UNIX_TIMESTAMP())
        ", [$date->format('Y-m-d'), $date->format('m-d'), $date->format('d')]);

        if (false === $ids || null === ($members = MemberModel::findMultipleByIds($ids))) {
            $io->warning('No members found that renew on '.$date->format('F jS, Y'));
            return 0;
        }

        foreach ($members as $member) {
            $invoiced = (new \DateTime())->setTimestamp((int) $member->membership_invoiced);
            if (!$io->confirm(sprintf(
                'Invoicing %s %s (%s), membership invoiced until %s (%s). Continue?',
                $member->firstname,
                $member->lastname,
                $member->email,
                $invoiced->format('F jS, Y'),
                $invoiced->format('Y-m-d')
            ))) {
                continue;
            }

            try {
                $this->cashctrl->syncMember($member);
                $invoice = $this->cashctrl->createAndSendInvoice($member, $this->notificationId, $date);

                if (null !== $invoice) {
                    $this->logger->info('Recurring membership invoice '.$invoice->getNr().' sent to '.$member->email);
                }
            } catch (\Exception $e) {
                $io->error('Unable to send recurring invoice to '.$member->email.' (member ID '.$member->id.')');
            }
        }

        return 0;
    }
}
