<?php

declare(strict_types=1);

namespace App\Cron;

use App\CashctrlHelper;
use App\PaypalHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Terminal42\ContaoBuildTools\ErrorHandlingTrait;

#[AsCronJob('daily')]
class PaypalImportCron
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly PaypalHelper $paypalHelper,
        private readonly CashctrlHelper $cashctrlHelper,
    ) {
    }

    public function __invoke(): void
    {
        $this->sentryCheckIn();

        $startDate = new \DateTime('yesterday 00:00:00');
        $endDate = new \DateTime('yesterday 23:59:59');

        $transactions = $this->paypalHelper->getTransactions($startDate, $endDate);

        foreach ($transactions as $transaction) {
            try {
                $this->paypalHelper->bookTransaction($transaction);
            } catch (\RuntimeException $exception) {
                $this->sentryOrThrow('Error in PayPal cronjob', $exception);
                continue;
            }
        }

        $this->sentryCheckIn(true);
    }
}
