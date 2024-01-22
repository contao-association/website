<?php

declare(strict_types=1);

namespace App\Cron;

use App\CashctrlHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\ContaoBuildTools\ErrorHandlingTrait;

#[AsCronJob('daily')]
class RecurringInvoicesCron
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly CashctrlHelper $cashctrl,
        private readonly LoggerInterface $logger,
        private readonly int $invoiceNotificationId,
    ) {
    }

    public function __invoke(): void
    {
        $this->sentryCheckIn();

        $this->framework->initialize();

        $ids = $this->connection->fetchFirstColumn("
            SELECT id
            FROM tl_member
            WHERE membership_invoiced != ''
                AND DATE_FORMAT(FROM_UNIXTIME(membership_invoiced),'%Y%m%d') < DATE_FORMAT(NOW(),'%Y%m%d')
                AND disable=''
                AND membership!='inactive'
                AND (start='' OR start<=UNIX_TIMESTAMP())
                AND (stop='' OR stop>UNIX_TIMESTAMP())
                AND (membership_stop='' OR membership_stop>UNIX_TIMESTAMP())
        ");

        if ([] === $ids || null === ($members = MemberModel::findMultipleByIds($ids))) {
            $this->sentryCheckIn(true);

            return;
        }

        foreach ($members as $member) {
            try {
                $this->cashctrl->syncMember($member);
                $invoiceDate = (new \DateTimeImmutable())->setTimestamp((int) $member->membership_invoiced)->add(new \DateInterval('P1D'));
                $invoice = $this->cashctrl->createAndSendInvoice($member, $this->invoiceNotificationId, $invoiceDate);

                if ($invoice instanceof Order) {
                    $this->logger->info('Recurring membership invoice '.$invoice->getNr().' sent to '.$member->email);
                }
            } catch (\Exception $e) {
                $this->sentryOrThrow('Unable to send recurring invoice to '.$member->email.' (member ID '.$member->id.')', $e);
            }
        }

        $this->sentryCheckIn(true);
    }
}
