<?php

declare(strict_types=1);

namespace App;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Terminal42\CashctrlApi\Api\TaxEndpoint;
use Terminal42\CashctrlApi\Entity\Journal;
use Terminal42\CashctrlApi\Entity\JournalItem;

class PretixHelper
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CashctrlHelper $cashctrlHelper,
        private readonly TaxEndpoint $taxEndpoint,
        private readonly string $pretixToken,
    ) {
    }

    public function getEvents(string $organizer): \Generator
    {
        $response = $this->httpClient->request(
            'GET',
            "https://pretix.eu/api/v1/organizers/$organizer/events/?ordering=date_from",
            [
                'headers' => [
                    'Authorization' => 'Token '.$this->pretixToken,
                    'Accept' => 'application/json',
                ],
            ],
        );

        $data = $response->toArray();

        foreach ($data['results'] as $event) {
            yield $event;
        }
    }

    public function getInvoices(string $organizer, string $event, string|null $code = null): \Generator
    {
        $url = "https://pretix.eu/api/v1/organizers/$organizer/events/$event/invoices?ordering=date";

        if (null !== $code) {
            $url .= "&order=$code";
        }

        while ($url) {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Token '.$this->pretixToken,
                        'Accept' => 'application/json',
                    ],
                ],
            );

            $data = $response->toArray();
            $url = $data['next'];

            foreach ($data['results'] as $invoice) {
                yield $invoice;
            }
        }
    }

    public function bookOrder(string $event, array $invoice): void
    {
        // Invoices from Pretix shop in TEST mode
        if (str_contains('TEST', (string) $invoice['number'])) {
            return;
        }

        $creditAccount = $this->getAccountForInvoice($invoice);
        $debitAccount = $this->cashctrlHelper->getAccountId(1105);
        $dateAdded = new \DateTime($invoice['date']);
        $amount = $this->getInvoiceTotal($invoice);

        if ($amount < 0) {
            $oldDebitAccount = $debitAccount;
            $debitAccount = $creditAccount;
            $creditAccount = $oldDebitAccount;
            $amount = abs($amount);
        }

        $journal = new Journal($amount, $creditAccount, $debitAccount, $dateAdded);
        $journal->setReference($invoice['number']);
        $journal->setTitle(strtoupper($event).'-'.$invoice['order'].' - '.$this->getInvoiceName($invoice));

        if ($taxId = $this->getTaxId($invoice)) {
            $journal->setTaxId($taxId);
        }

        if (!$taxId && ($taxAmount = $this->getTaxTotal($invoice)) !== 0) {
            if ($taxAmount < 0) {
                $taxAmount = abs($taxAmount);
                $journal
                    ->addItem((new JournalItem($creditAccount))->setCredit($amount))
                    ->addItem((new JournalItem($debitAccount))->setDebit(number_format($amount - $taxAmount, 2, '.', '')))
                    ->addItem((new JournalItem($this->cashctrlHelper->getAccountId(2201)))->setDebit(number_format($taxAmount, 2, '.', '')))
                ;
            } else {
                $journal
                    ->addItem((new JournalItem($debitAccount))->setDebit($amount))
                    ->addItem((new JournalItem($creditAccount))->setCredit(number_format($amount - $taxAmount, 2, '.', '')))
                    ->addItem((new JournalItem($this->cashctrlHelper->getAccountId(2201)))->setCredit(number_format($taxAmount, 2, '.', '')))
                ;
            }
        }

        $this->cashctrlHelper->addJournalEntry($journal);
    }

    public function getInvoiceTotal(array $invoice): float
    {
        $total = 0;

        foreach ($invoice['lines'] as $line) {
            $total += $line['gross_value'];
        }

        return $total;
    }

    public function getInvoiceName(array $invoice): string
    {
        if (!empty($invoice['invoice_to_name'])) {
            return $invoice['invoice_to_name'];
        }

        if (!empty($invoice['invoice_to_company'])) {
            return $invoice['invoice_to_company'];
        }

        return $invoice['invoice_to'];
    }

    private function getAccountForInvoice(array $invoice): int
    {
        if (str_starts_with((string) $invoice['number'], 'CAMP')) {
            return $this->cashctrlHelper->getAccountId(3421);
        }

        if (str_starts_with((string) $invoice['number'], 'CK20')) {
            return $this->cashctrlHelper->getAccountId(3420);
        }

        throw new \RuntimeException(\sprintf('Cannot determine account for Pretix invoice "%s"', $invoice['number']));
    }

    private function getTaxId(array $invoice): int|null
    {
        $rate = null;

        foreach ($invoice['lines'] as $line) {
            if (null !== $rate && $rate !== $line['tax_rate']) {
                return null;
            }

            $rate = $line['tax_rate'];
        }

        if (null === $rate) {
            return null;
        }

        foreach ($this->taxEndpoint->list() as $tax) {
            if ((float) $rate === (float) $tax->getPercentage()) {
                return $tax->getId();
            }
        }

        return null;
    }

    private function getTaxTotal(array $invoice): float
    {
        $tax = 0;

        foreach ($invoice['lines'] as $line) {
            $tax += $line['tax_value'];
        }

        return (float) $tax;
    }
}
