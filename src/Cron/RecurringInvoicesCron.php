<?php

declare(strict_types=1);

namespace App\Cron;

use App\ErrorHandlingTrait;
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
    use ErrorHandlingTrait;

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
        int $invoiceNotificationId
    ) {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->cashctrl = $cashctrl;
        $this->logger = $logger;
        $this->notificationId = $invoiceNotificationId;
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $ids = $this->connection->fetchFirstColumn("
            SELECT id
            FROM tl_member
            WHERE membership_invoiced < UNIX_TIMESTAMP()
                AND DATE_FORMAT(FROM_UNIXTIME(membership_start),'%Y-%m-%d') != DATE_FORMAT(NOW(),'%Y-%m-%d')
                AND disable=''
                AND (start='' OR start<=UNIX_TIMESTAMP())
                AND (stop='' OR stop>UNIX_TIMESTAMP())
                AND (membership_stop='' OR membership_stop>UNIX_TIMESTAMP())
        ");

        if (false === $ids || null === ($members = MemberModel::findMultipleByIds($ids))) {
            return;
        }

        foreach ($members as $member) {
            try {
                $this->cashctrl->syncMember($member);
                $invoiceDate = (new \DateTimeImmutable())->setTimestamp((int) $member->membership_invoiced)->add(new \DateInterval('P1D'));
                $invoice = $this->cashctrl->createAndSendInvoice($member, $this->notificationId, $invoiceDate);

                if (null !== $invoice) {
                    $this->logger->info('Recurring membership invoice '.$invoice->getNr().' sent to '.$member->email);
                }
            } catch (\Exception $e) {
                $this->sentryOrThrow('Unable to send recurring invoice to '.$member->email.' (member ID '.$member->id.')', $e);
            }
        }
    }
}
