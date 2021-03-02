<?php

declare(strict_types=1);

namespace App\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use App\CashctrlHelper;
use Contao\MemberModel;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use NotificationCenter\Model\Notification;
use function Sentry\captureMessage;
use Contao\Versions;
use Psr\Log\LoggerInterface;

/**
 * @CronJob("hourly")
 */
class ActivateMemberCron
{
    private ContaoFramework $framework;
    private CashctrlHelper $cashctrl;
    private LoggerInterface $logger;
    private int $notificationId;

    public function __construct(
        ContaoFramework $framework,
        CashctrlHelper $cashctrl,
        LoggerInterface $logger,
        int $notificationId
    ) {
        $this->framework = $framework;
        $this->cashctrl = $cashctrl;
        $this->logger = $logger;
        $this->notificationId = $notificationId;
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $notification = Notification::findByPk($this->notificationId);

        if (null === $notification) {
            $this->cashctrl->sentryOrThrow('Notification ID "'.$this->notificationId.'" not found, cannot send account activation notification');
            return;
        }

        $members = MemberModel::findBy(['cashctrl_invoice > 0'], []);

        if (null === $members) {
            return;
        }

        foreach ($members as $member) {
            $order = $this->cashctrl->order->read((int) $member->cashctrl_invoice);

            if (!$order->isClosed) {
                continue;
            }

            $objVersions = new Versions('tl_member', $member->id);
            $objVersions->setUsername($member->username);
            $objVersions->setUserId(0);
            $objVersions->setEditUrl('contao/main.php?do=member&act=edit&id=%s&rt=1');
            $objVersions->initialize();

            $member->cashctrl_invoice = 0;
            $member->disable = '';
            $member->save();

            $objVersions->create(true);

            $this->logger->info('Sent activation notification for CashCtrl invoice '.$order->getNr().' to '.$member->email);

            if (!$this->cashctrl->sendInvoiceNotification($notification, $order, $member)) {
                captureMessage('Unable to send account activation notification to '.$member->email);
                continue;
            }

            $order->setCustomfield(5, 'true');
            $this->cashctrl->order->update($order);
        }
    }
}
