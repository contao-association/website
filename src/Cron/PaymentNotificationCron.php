<?php

declare(strict_types=1);

namespace App\Cron;

use App\CashctrlHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\System;
use Oneup\ContaoSentryBundle\ErrorHandlingTrait;

#[AsCronJob('hourly')]
class PaymentNotificationCron
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CashctrlHelper $cashctrl,
        private readonly int $paymentNotificationId,
    ) {
    }

    public function __invoke(): void
    {
        $this->sentryCheckIn();

        $this->framework->initialize();
        System::loadLanguageFile('default');

        foreach ($this->cashctrl->getLastPaidInvoices() as $order) {
            if (!$order->isClosed) {
                continue;
            }

            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

            if (null === $member) {
                continue;
            }

            $this->cashctrl->notifyInvoicePaid($order, $member, $this->paymentNotificationId);
        }

        $this->sentryCheckIn(true);
    }
}
