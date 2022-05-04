<?php

declare(strict_types=1);

namespace App\Cron;

use App\ErrorHandlingTrait;
use App\PaypalHelper;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use App\CashctrlHelper;

/**
 * @CronJob("daily")
 */
class PaypalImportCron
{
    use ErrorHandlingTrait;

    private PaypalHelper $paypalHelper;
    private CashctrlHelper $cashctrlHelper;

    public function __construct(PaypalHelper $paypalHelper, CashctrlHelper $cashctrlHelper)
    {
        $this->paypalHelper = $paypalHelper;
        $this->cashctrlHelper = $cashctrlHelper;
    }

    public function __invoke(): void
    {
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
    }
}
