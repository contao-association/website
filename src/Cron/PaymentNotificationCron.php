<?php

declare(strict_types=1);

namespace App\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\ApiClientInterface;
use Terminal42\CashctrlApi\ApiClient;
use App\MembershipHelper;
use NotificationCenter\Model\Notification;
use function Sentry\captureMessage;
use Psr\Log\LoggerInterface;

/**
 * @CronJob("hourly")
 */
class PaymentNotificationCron
{
    private ContaoFramework $framework;
    private MembershipHelper $helper;
    private LoggerInterface $logger;
    private int $notificationId;

    public function __construct(ContaoFramework $framework, MembershipHelper $helper, LoggerInterface $logger, int $notificationId)
    {
        $this->framework = $framework;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->notificationId = $notificationId;
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $notification = Notification::findByPk($this->notificationId);

        if (null === $notification) {
            $this->helper->sentryOrThrow('Notification ID "'.$this->notificationId.'" not found, cannot send payment notification');
            return;
        }

        foreach ($this->helper->getLastUpdatedInvoices() as $order) {
            if (!$this->sendNotification($order)) {
                // Orders are sorted by lastUpdated. We assume this means all orders
                // after the first not in booking range are out of range too.
                return;
            }

            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

            // Do not send payment confirmation for first invoice (account activation)
            if (null === $member || $member->cashctrl_invoice === $order->getId()) {
                continue;
            }

            $this->logger->info('Sent payment notification for CashCtrl invoice '.$order->getNr().' to '.$member->email);

            if (!$this->helper->sendInvoiceNotification($notification, $order, $member)) {
                captureMessage('Unable to send payment notification to '.$member->email);
            }
        }
    }

    private function sendNotification(Order $order): bool
    {
        if (!$order->isClosed) {
            return false;
        }

        $bookDate = ApiClient::parseDateTime($order->dateLastBooked);
        $now = new \DateTime('-1 hour', new \DateTimeZone(ApiClientInterface::DATE_TIMEZONE));

        return $now->format('YmdH') === $bookDate->format('YmdH');
    }
}
