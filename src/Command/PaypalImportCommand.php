<?php

declare(strict_types=1);

namespace App\Command;

use App\PaypalHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:paypal:import', 'Import PayPal payments into CashCtrl.')]
class PaypalImportCommand extends Command
{
    public function __construct(private readonly PaypalHelper $paypalHelper)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('from', InputArgument::REQUIRED, 'Start date for PayPal transactions to import (Y-m-d).')
            ->addArgument('to', InputArgument::OPTIONAL, 'End date for PayPal transactions to import (Y-m-d).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $startDate = $this->getDate($input->getArgument('from'));
            $endDate = $this->getDate($input->getArgument('to') ?: $input->getArgument('from'));
            $startDate->setTime(0, 0, 0);
            $endDate->setTime(23, 59, 59);
        } catch (\RuntimeException $exception) {
            $io->error('Invalid date format');

            return Command::FAILURE;
        }

        $transactions = $this->paypalHelper->getTransactions($startDate, $endDate);

        if ([] === $transactions) {
            $io->success('No transactions for this date.');

            return Command::SUCCESS;
        }

        $io->progressStart(\count($transactions));

        foreach ($transactions as $transaction) {
            $name = ($transaction['payer_info']['payer_name']['alternate_full_name'] ?? '');

            switch (true) {
                case str_starts_with($transaction['cart_info']['item_details'][0]['item_name'] ?? '', 'Ko-fi'):
                    $message = 'Ko-fi '.$transaction['transaction_info']['invoice_id'].' von '.$name;
                    break;

                case $transaction['transaction_info']['transaction_note'] ?? null:
                    $message = 'Rechnung '.$transaction['transaction_info']['transaction_note'].' von '.$name;
                    break;

                default:
                    $message = 'PayPal-Zahlung '.$transaction['transaction_info']['transaction_id'].' von '.$name;
                    break;
            }

            try {
                if ($io->confirm($message)) {
                    $this->paypalHelper->bookTransaction($transaction);
                }
            } catch (\RuntimeException $exception) {
                $io->error('Error for transaction '.$transaction['transaction_info']['transaction_id'].' :'.$exception->getMessage());
                continue;
            } finally {
                $io->progressAdvance();
            }
        }

        $io->progressFinish();

        return Command::SUCCESS;
    }

    private function getDate(string $value): \DateTime
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \RuntimeException('Invalid date format');
        }

        $date = new \DateTime($value);

        if ($date->format('Y-m-d') !== $value) {
            throw new \RuntimeException('Invalid date format');
        }

        return $date;
    }
}
