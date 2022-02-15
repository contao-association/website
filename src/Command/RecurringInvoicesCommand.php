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

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input->getArgument('date'))) {
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
                DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%m-%d') = ?
                AND DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%Y-%m-%d') != ?
                AND disable=''
                AND (start='' OR start<=UNIX_TIMESTAMP())
                AND (stop='' OR stop>UNIX_TIMESTAMP())
        ", [$date->format('m-d'), $date->format('Y-m-d')]);

        if (false === $ids || null === ($members = MemberModel::findMultipleByIds($ids))) {
            $io->warning('No members found that renew on '.$date->format('l, d F Y'));
            return 0;
        }

        foreach ($members as $member) {
            if (!$io->confirm(sprintf('Create and send invoice for %s %s (%s)?', $member->firstname, $member->lastname, $member->email))) {
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
