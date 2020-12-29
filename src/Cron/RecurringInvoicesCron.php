<?php

declare(strict_types=1);

namespace App\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use App\CashctrlHelper;

/**
 * @CronJob("daily")
 */
class RecurringInvoicesCron
{
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
        $this->framework = $framework;
        $this->connection = $connection;
        $this->cashctrl = $cashctrl;
        $this->logger = $logger;
        $this->notificationId = $notificationId;
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        // Find members which have been added today some time ago
        // @see http://stackoverflow.com/a/2218577
        $ids = $this->connection->fetchFirstColumn("
            SELECT id 
            FROM tl_member 
            WHERE (
                DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')
                OR (
                    (
                        DATE_FORMAT(NOW(),'%Y') % 4 <> 0
                        OR (
                            DATE_FORMAT(NOW(),'%Y') % 100 = 0
                            AND DATE_FORMAT(NOW(),'%Y') % 400 <> 0
                        )
                    )
                    AND DATE_FORMAT(NOW(),'%m-%d') = '03-01'
                    AND DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%m-%d') = '02-29'
                )
            )
            AND DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%Y-%m-%d') != DATE_FORMAT(NOW(),'%Y-%m-%d')
            AND disable=''
            AND (start='' OR start<=UNIX_TIMESTAMP())
            AND (stop='' OR stop>UNIX_TIMESTAMP())
        ");

        if (false === $ids || null === ($members = MemberModel::findMultipleByIds($ids))) {
            return;
        }

        foreach ($members as $member) {
            $this->cashctrl->syncMember($member);
            $invoice = $this->cashctrl->createAndSendInvoice($member, $this->notificationId);

            if (null !== $invoice) {
                $this->logger->info('Recurring membership invoice '.$invoice->getNr().' sent to '.$member->email);
            }
        }
    }
}
