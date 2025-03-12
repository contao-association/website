<?php

declare(strict_types=1);

namespace App\Command;

use App\CashctrlHelper;
use App\StripeHelper;
use Stripe\Refund;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:stripe:import', 'Import Stripe transactions into CashCtrl.')]
class StripeImportCommand extends Command
{
    public function __construct(
        private readonly StripeHelper $stripeHelper,
        private readonly CashctrlHelper $cashctrlHelper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('from', InputArgument::REQUIRED, 'From-date for Stripe charges to import (Y-m-d).')
            ->addArgument('to', InputArgument::OPTIONAL, 'To-date for Stripe charges to import (Y-m-d).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $from = $this->getDate($input->getArgument('from'));
            $to = $this->getDate($input->getArgument('to') ?: $input->getArgument('from'));
        } catch (\RuntimeException) {
            $io->error('Invalid date format');

            return Command::FAILURE;
        }

        $io->title('Importing Stripe charges from '.$from->format('d.m.Y').' to '.$to->format('d.m.Y'));

        foreach ($this->stripeHelper->getCharges($from, $to) as $charge) {
            $refund = null;

            if ($charge instanceof Refund) {
                $refund = $charge;
                $charge = $this->stripeHelper->client->charges->retrieve($refund->charge);
            }

            if (!$charge->paid) {
                continue;
            }

            switch ($charge->application) {
                case 'ca_BNCWzVqBWfaL53LdFYzpoumNOsvo2936': // Ko-fi
                    $message = \sprintf(
                        'Ko-fi donation from %s (%s %s on %s)',
                        $charge->billing_details['name'],
                        strtoupper((string) $charge->currency),
                        number_format($charge->amount / 100, 2, '.', "'"),
                        \DateTime::createFromFormat('U', (string) $charge->created)->format('d.m.Y'),
                    );
                    break;

                case 'ca_9uvq9hdD9LslRRCLivQ5cDhHsmFLX023': // Pretix
                    $message = \sprintf(
                        'Pretix order %s (%s %s on %s)',
                        $charge->description,
                        strtoupper((string) $charge->currency),
                        number_format($charge->amount / 100, 2, '.', "'"),
                        \DateTime::createFromFormat('U', (string) $charge->created)->format('d.m.Y'),
                    );
                    break;

                case null: // Payments from Stripe API
                    if (empty($charge->metadata->cashctrl_order_id)) {
                        continue 2;
                    }

                    $order = $this->cashctrlHelper->order->read((int) $charge->metadata->cashctrl_order_id);

                    if (null === $order) {
                        $io->error('CashCtrl order ID "'.$charge->metadata->cashctrl_order_id.'" not found');
                        continue 2;
                    }

                    $message = \sprintf(
                        'CashCtrl order %s from %s (%s %s on %s)',
                        $order->getNr(),
                        $order->associateName,
                        strtoupper((string) $charge->currency),
                        $charge->amount / 100,
                        \DateTime::createFromFormat('U', (string) $charge->created)->format('d.m.Y'),
                    );
                    break;

                default:
                    $io->error("Unknown Stripe application \"$charge->application\"");
                    continue 2;
            }

            if ($refund) {
                $message = \sprintf(
                    'STORNO: %s %s on %s for %s',
                    strtoupper((string) $refund->currency),
                    number_format($refund->amount / 100, 2, '.', "'"),
                    \DateTime::createFromFormat('U', (string) $refund->created)->format('d.m.Y'),
                    $message,
                );
            }

            if ($io->confirm($message)) {
                try {
                    if ($refund) {
                        $this->stripeHelper->importRefund($refund, $charge);
                    } else {
                        $this->stripeHelper->importCharge($charge);
                    }
                } catch (\Exception $exception) {
                    $io->error($exception->getMessage());
                }
            }
        }

        return Command::SUCCESS;
    }

    private function getDate(string $value): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \RuntimeException('Invalid date format');
        }

        $date = new \DateTimeImmutable($value);

        if ($date->format('Y-m-d') !== $value) {
            throw new \RuntimeException('Invalid date format');
        }

        return $date;
    }
}
