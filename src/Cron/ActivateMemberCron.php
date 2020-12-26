<?php

declare(strict_types=1);

namespace App\Cron;

use Contao\CoreBundle\Framework\ContaoFramework;
use App\CashctrlApi;
use Contao\MemberModel;
use App\MembershipHelper;
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
    private CashctrlApi $api;
    private MembershipHelper $helper;
    private LoggerInterface $logger;
    private int $notificationId;

    public function __construct(
        ContaoFramework $framework,
        CashctrlApi $api,
        MembershipHelper $helper,
        LoggerInterface $logger,
        int $notificationId
    ) {
        $this->framework = $framework;
        $this->api = $api;
        $this->helper = $helper;
        $this->logger = $logger;
        $this->notificationId = $notificationId;
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $notification = Notification::findByPk($this->notificationId);

        if (null === $notification) {
            $this->helper->sentryOrThrow('Notification ID "'.$this->notificationId.'" not found, cannot send account activation notification');
            return;
        }

        $members = MemberModel::findBy(['cashctrl_invoice > 0'], []);

        if (null === $members) {
            return;
        }

        foreach ($members as $member) {
            $order = $this->api->order->read((int) $members->cashctrl_invoice);

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

            if (!$this->helper->sendInvoiceNotification($notification, $order, $member)) {
                captureMessage('Unable to send account activation notification to '.$member->email);
            }
        }
    }
}
