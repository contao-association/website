<?php

declare(strict_types=1);

namespace App;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Terminal42\CashctrlApi\Api\TaxEndpoint;
use Terminal42\CashctrlApi\Entity\Journal;

class PretixHelper
{
    private HttpClientInterface $httpClient;
    private CashctrlHelper $cashctrlHelper;
    private TaxEndpoint $taxEndpoint;
    private string $pretixToken;

    public function __construct(HttpClientInterface $httpClient, CashctrlHelper $cashctrlHelper, TaxEndpoint $taxEndpoint, string $pretixToken)
    {
        $this->httpClient = $httpClient;
        $this->cashctrlHelper = $cashctrlHelper;
        $this->taxEndpoint = $taxEndpoint;
        $this->pretixToken = $pretixToken;
    }

    public function getOrder(string $organizer, string $event, string $code): array
    {
        $response = $this->httpClient->request(
            'GET',
            "https://pretix.eu/api/v1/organizers/$organizer/events/$event/orders/$code",
            [
                'headers' => [
                    'Authorization' => 'Token '.$this->pretixToken,
                    'Accept' => 'application/json'
                ]
            ]
        );

        return $response->toArray();
    }

    public function getInvoices(string $organizer, string $event, string $code): array
    {
        $response = $this->httpClient->request(
            'GET',
            "https://pretix.eu/api/v1/organizers/$organizer/events/$event/invoices?order=$code",
            [
                'headers' => [
                    'Authorization' => 'Token '.$this->pretixToken,
                    'Accept' => 'application/json'
                ]
            ]
        );

        return $response->toArray()['results'];
    }

    public function bookOrder(string $event, array $invoice): void
    {
        $creditAccount = $this->getAccountForInvoice($invoice);
        $debitAccount = $this->cashctrlHelper->getAccountId(1105);
        $dateAdded = new \DateTime($invoice['date']);

        $journal = new Journal($this->getTotal($invoice), $creditAccount, $debitAccount, $dateAdded);
        $journal->setReference(strtoupper($event).'-'.$invoice['order']);
        $journal->setTitle("{$invoice['number']} - ".$this->getName($invoice));

        if ($taxId = $this->getTaxId($invoice)) {
            $journal->setTaxId($taxId);
        }

        $this->cashctrlHelper->addJournalEntry($journal);

        if (!$taxId && ($taxAmount = $this->getTaxTotal($invoice)) > 0) {
            $taxJournal = new Journal($taxAmount, $this->cashctrlHelper->getAccountId(2201), $creditAccount, $dateAdded);
            $taxJournal->setReference($journal->getReference());
            $taxJournal->setTitle($journal->getTitle());
            $this->cashctrlHelper->addJournalEntry($taxJournal);
        }
    }

    private function getAccountForInvoice(array $invoice): int
    {
        if (0 === strpos($invoice['number'], 'CAMP')) {
            return $this->cashctrlHelper->getAccountId(3421);
        }

        throw new \RuntimeException("Cannot determine account for Pretix invoice \"{$invoice['number']}\"");
    }

    private function getTotal(array $invoice): float
    {
        $total = 0;

        foreach ($invoice['lines'] as $line) {
            $total += $line['gross_value'];
        }

        return $total;
    }

    private function getTaxId(array $invoice): ?int
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

    private function getName(array $invoice): string
    {
        if (!empty($invoice['invoice_to_name'])) {
            return $invoice['invoice_to_name'];
        }

        if (!empty($invoice['invoice_to_company'])) {
            return $invoice['invoice_to_company'];
        }

        return $invoice['invoice_to'];
    }
}
