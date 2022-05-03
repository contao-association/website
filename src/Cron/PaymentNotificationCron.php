<?php

declare(strict_types=1);

namespace App\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use NotificationCenter\Model\Notification;
use Psr\Log\LoggerInterface;
use App\CashctrlHelper;

/**
 * @CronJob("hourly")
 */
class PaymentNotificationCron
{
    private ContaoFramework $framework;
    private CashctrlHelper $cashctrl;
    private int $notificationId;

    public function __construct(ContaoFramework $framework, CashctrlHelper $cashctrl, int $paymentNotificationId)
    {
        $this->framework = $framework;
        $this->cashctrl = $cashctrl;
        $this->notificationId = $paymentNotificationId;
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $notification = Notification::findByPk($this->notificationId);

        if (null === $notification) {
            $this->cashctrl->sentryOrThrow('Notification ID "'.$this->notificationId.'" not found, cannot send payment notification');
            return;
        }

        foreach ($this->cashctrl->getLastPaidInvoices() as $order) {
            if (!$order->isClosed) {
                continue;
            }

            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

            if (null === $member) {
                continue;
            }

            $this->cashctrl->notifyInvoicePaid($order, $member, $notification);
        }
    }
}
