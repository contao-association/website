<?php

declare(strict_types=1);

namespace App\Command;

use App\StripeHelper;
use Stripe\Charge;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StripeSyncCommand extends Command
{
    protected static $defaultName = 'app:stripe:import';

    private StripeHelper $stripeHelper;

    public function __construct(StripeHelper $stripeHelper)
    {
        parent::__construct();

        $this->stripeHelper = $stripeHelper;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sync Stripe transactions with Cashctrl.')
            ->addArgument('from', InputArgument::REQUIRED, 'From-date for Stripe charges to import (Y-m-d).')
            ->addArgument('to', InputArgument::REQUIRED, 'To-date for Stripe charges to import (Y-m-d).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $from = $this->getDate($input->getArgument('from'));
            $to = $this->getDate($input->getArgument('to'));
        } catch (\RuntimeException $exception) {
            $io->error('Invalid date format');
            return 1;
        }

        /** @var Charge $charge */
        foreach ($io->progressIterate($this->stripeHelper->getCharges($from, $to)) as $charge) {
            $this->stripeHelper->importCharge($charge);
        }

        return 0;
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
