<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Terminal42\CashctrlApi\Api\AccountEndpoint;
use Terminal42\CashctrlApi\Api\CurrencyEndpoint;
use Terminal42\CashctrlApi\Api\FiscalperiodEndpoint;
use Terminal42\CashctrlApi\Api\JournalEndpoint;
use Terminal42\CashctrlApi\Api\TaxEndpoint;
use Terminal42\CashctrlApi\Entity\Journal;

#[AsCommand('app:cashctrl:import')]
class CashctrlImportCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly FiscalperiodEndpoint $fiscalperiod,
        private readonly AccountEndpoint $account,
        private readonly JournalEndpoint $journal,
        private readonly CurrencyEndpoint $currency,
        private readonly TaxEndpoint $tax,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('fibu3-file', InputArgument::REQUIRED)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $fp = fopen($input->getArgument('fibu3-file'), 'r');

        $header = fgetcsv($fp);

        if (false === $header) {
            throw new \RuntimeException('Ungültige Import-Datei.');
        }

        $accounts = $this->getAccounts();

        $data = [];

        while ($line = fgetcsv($fp)) {
            [, , , $soll, $haben, $mwstCode, , $betrag, $betragEUR] = $this->mapTypes($line);

            if (!\array_key_exists($soll, $accounts)) {
                throw new \RuntimeException('Kontonummer '.$soll.' in CashCtrl nicht vorhanden.');
            }

            if (!\array_key_exists($haben, $accounts)) {
                throw new \RuntimeException('Kontonummer '.$haben.' in CashCtrl nicht vorhanden.');
            }

            if ($betragEUR !== $betrag && null === $this->getCurrencyId($soll, $haben)) {
                throw new \RuntimeException('Währung für Konto '.$soll.' oder '.$haben.' fehlt.');
            }

            if ($mwstCode) {
                $this->getTaxId($mwstCode);
            }

            $data[] = $line;
        }

        // Start on January 1st
        $data = array_reverse($data);
        $skipped = [];
        $errors = [];

        if (!$this->io->confirm(\count($data).' Buchungssätze gefunden. Import starten?')) {
            return Command::FAILURE;
        }

        $this->selectFiscalPeriod();

        $this->io->progressStart(\count($data));

        foreach ($data as $line) {
            [, $beleg, $datum, $soll, $haben, $mwstCode, , $betrag, $betragEUR, $text] = $this->mapTypes($line);

            if (0 === $betrag && 0 === $betragEUR) {
                $skipped[] = $line;
                $this->io->progressAdvance();
                continue;
            }

            $dateAdded = \DateTime::createFromFormat('d.m.Y', $datum);

            if ($betrag >= 0 || (0 === $betrag && $betragEUR > 0)) {
                $debitId = $accounts[$soll];
                $creditId = $accounts[$haben];
            } else {
                $debitId = $accounts[$haben];
                $creditId = $accounts[$soll];
            }

            $entry = new Journal(abs((float) $betrag), $creditId, $debitId, $dateAdded);
            $entry->setTitle($text);
            $entry->setReference($beleg);

            if (0 === $betrag && $betragEUR > 0) {
                // Währungskorrektur in EUR verbuchen, Kontowährung ignorieren
                $entry->setAmount(abs((float) $betragEUR));
            } elseif (null !== ($currencyId = $this->getCurrencyId($soll, $haben))) {
                $entry->setCurrencyId($currencyId);
                $entry->setCurrencyRate(1 / $betrag * $betragEUR);
            }

            if ($mwstCode) {
                $entry->setTaxId($this->getTaxId($mwstCode));
            }

            try {
                $this->journal->create($entry);
            } catch (\Exception $e) {
                $errors[] = array_merge($line, [$e->getMessage()]);
            }

            $this->io->progressAdvance();
        }

        $this->io->progressFinish();
        $this->io->success((\count($data) - \count($skipped)).' Buchungssätze importiert, '.\count($skipped).' übersprungen!');

        if ([] !== $skipped) {
            $this->io->writeln('Ignorierte Buchungssätze:');
            $this->io->table($header, $skipped);
        }

        if ([] !== $errors) {
            $this->io->error('Fehlerhafte Buchungssätze:');
            $this->io->table(array_merge($header, ['Message']), $errors);
        }

        return Command::SUCCESS;
    }

    private function selectFiscalPeriod(): void
    {
        $periods = [];

        foreach ($this->fiscalperiod->list() as $period) {
            $periods[$period->getId()] = $period->getName();
        }

        $period = $this->io->choice('Rechnungsperiod wählen', $periods);
        $this->fiscalperiod->switch(array_search($period, $periods, true));
    }

    private function getAccounts(): array
    {
        $accounts = [];

        foreach ($this->account->list() as $account) {
            $accounts[$account->getNumber()] = $account->getId();
        }

        // Add manual account mapping
        $accounts[1175] = $accounts[1171];
        $accounts[2010] = $accounts[2001];
        $accounts[2140] = $accounts[2120];
        $accounts[2141] = $accounts[2121];
        $accounts[2205] = $accounts[2201];
        $accounts[2206] = $accounts[2211];
        $accounts[4430] = $accounts[4422];
        $accounts[6575] = $accounts[6573];
        $accounts[6892] = $accounts[6960];
        $accounts[9000] = $accounts[9200];

        // Wird nur von 2013 bis 2016 benötigt!
        // $accounts[3410] = $accounts[3420]; // Konferenz
        // $accounts[3420] = $accounts[3421]; // Camp
        // $accounts[4610] = $accounts[4620]; // Konferenz
        // $accounts[4620] = $accounts[4621]; // Camp

        return $accounts;
    }

    private function getCurrencyId($soll, $haben): int|null
    {
        $map = [
            1001 => 'CHF',
            1020 => 'CHF',
            1041 => 'CHF',
            1170 => 'CHF',
            2001 => 'CHF',
            2002 => 'USD',
            2010 => 'CHF',
            2141 => 'CHF',
            2200 => 'CHF',
            2210 => 'CHF',
        ];

        if (!isset($map[$soll]) && !isset($map[$haben])) {
            return null;
        }

        $code = $map[$soll] ?? $map[$haben];

        static $currencies = null;
        if (null === $currencies) {
            $currencies = [];

            foreach ($this->currency->list() as $currency) {
                $currencies[$currency->getCode()] = $currency->getId();
            }
        }

        if (!isset($currencies[$code])) {
            throw new \RuntimeException(sprintf('Währung %s fehlt in CashCtrl.', $code));
        }

        return $currencies[$code];
    }

    private function getTaxId(string $mwstCode): int
    {
        static $map = [
            'U-DE-190' => 7,
            'U190' => 7,
            'U-AT-200' => 10,
            'VM-DE-70' => 9,
            'VM70' => 9,
            'VM-DE-190' => 8,
            'VM190' => 8,
            'VM-AT-100' => 11,
            'VM-AT-200' => 12,
        ];

        if (!isset($map[$mwstCode])) {
            $choices = [];

            foreach ($this->tax->list() as $tax) {
                $choices[$tax->getId()] = $tax->getName();
            }

            $choice = $this->io->choice('Zuordnung für MwSt-Code "'.$mwstCode.'"', $choices);
            $map[$mwstCode] = array_search($choice, $choices, true);
        }

        return $map[$mwstCode];
    }

    /**
     * Decode numbers in CSV to int or float values.
     *
     * @see https://stackoverflow.com/questions/65255283/keep-integer-and-float-types-when-converting-csv-data-to-php-arrays-with-str-get
     *
     * @return array<string|int>
     */
    private function mapTypes(array $line): array
    {
        return array_map(static fn ($v) => is_numeric($v) ? $v + 0 : $v, $line);
    }
}
