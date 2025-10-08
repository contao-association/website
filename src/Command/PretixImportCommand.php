<?php

declare(strict_types=1);

namespace App\Command;

use App\PretixHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:pretix:import', 'Import Pretix transactions into CashCtrl.')]
class PretixImportCommand extends Command
{
    public function __construct(
        private readonly PretixHelper $pretixHelper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('order', InputArgument::OPTIONAL, 'Order number to filter invoices for.')
            ->addOption('organizer', null, InputOption::VALUE_REQUIRED, 'The slug field of the organizer to fetch.', 'contao')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Import all invoices without individual confirmation.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $events = [];
        $organizer = $input->getOption('organizer');
        $all = (bool) $input->getOption('all');

        foreach ($this->pretixHelper->getEvents($organizer) as $event) {
            $events[$event['slug']] = reset($event['name']);
        }

        $slug = $io->choice('Select the Pretix event to import', $events);
        $io->title('Importing Pretix invoices from '.$events[$slug]);

        foreach ($this->pretixHelper->getInvoices($organizer, $slug, $input->getArgument('order')) as $invoice) {
            $total = $this->pretixHelper->getInvoiceTotal($invoice);

            $message = \sprintf(
                'Invoice %s from %s (%s %s on %s)',
                $invoice['number'],
                $this->pretixHelper->getInvoiceName($invoice),
                'EUR',
                number_format($total, 2, '.', "'"),
                (new \DateTime($invoice['date']))->format('d.m.Y'),
            );

            if ($total < 0) {
                $message = 'STORNO: '.$message;
            }

            if ($all) {
                $io->text('- '.$message);
            } elseif (!$io->confirm($message)) {
                continue;
            }

            try {
                $this->pretixHelper->bookOrder($slug, $invoice);
            } catch (\Exception $exception) {
                $io->error($exception->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
