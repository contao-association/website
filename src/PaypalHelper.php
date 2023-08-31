<?php

declare(strict_types=1);

namespace App;

use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalHttp\HttpRequest;
use Terminal42\CashctrlApi\Api\OrderBookentryEndpoint;
use Terminal42\CashctrlApi\Entity\Journal;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\Entity\OrderBookentry;
use Terminal42\ContaoBuildTools\ErrorHandlingTrait;

class PaypalHelper
{
    use ErrorHandlingTrait;

    private readonly array $teamMembers;

    public function __construct(
        private readonly PayPalHttpClient $client,
        private readonly CashctrlHelper $cashctrlHelper,
        private readonly OrderBookentryEndpoint $bookentry,
        string $teamMembers,
    ) {
        $this->teamMembers = explode(',', $teamMembers);
    }

    public function getTransactions(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $path = '/v1/reporting/transactions?'.http_build_query([
            'transaction_status' => 'S',
            'fields' => 'all',
            'start_date' => $startDate->format('c'),
            'end_date' => $endDate->format('c'),
        ]);

        $request = new HttpRequest($path, 'GET');
        $request->headers['Content-Type'] = 'application/json';

        $result = $this->client->execute($request);
        $data = json_decode(json_encode($result->result->transaction_details, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        $transactions = [];

        // Merge money conversion transactions
        foreach ($data as $transaction) {
            // Money conversion
            if ('T0200' === ($transaction['transaction_info']['transaction_event_code'] ?? null)) {
                continue;
            }

            if ('EUR' !== $transaction['transaction_info']['transaction_amount']['currency_code']) {
                foreach ($data as $v) {
                    if (
                        'EUR' === $v['transaction_info']['transaction_amount']['currency_code']
                        && (
                            $transaction['transaction_info']['transaction_id'] === ($v['transaction_info']['paypal_reference_id'] ?? null)
                            || ($transaction['transaction_info']['paypal_reference_id'] ?? '') === ($v['transaction_info']['paypal_reference_id'] ?? null)
                        )
                    ) {
                        $transaction['transaction_info']['transaction_amount']['currency_code'] = 'EUR';
                        $transaction['transaction_info']['transaction_amount']['value'] = $v['transaction_info']['transaction_amount']['value'];
                        break;
                    }
                }
            }

            $transactions[] = $transaction;
        }

        return $transactions;
    }

    public function bookTransaction(array $transaction): Journal
    {
        if ('EUR' !== $transaction['transaction_info']['transaction_amount']['currency_code']) {
            throw new \RuntimeException(sprintf('Currency "%s" is not supported', $transaction['transaction_info']['transaction_amount']['currency_code']));
        }

        $dateAdded = $this->getDateAdded($transaction);
        $amount = (float) $transaction['transaction_info']['transaction_amount']['value'];
        $paypalAccount = $this->cashctrlHelper->getAccountId(1040);

        $journal = new Journal(abs($amount), $paypalAccount, $paypalAccount, $dateAdded);
        $journal->setReference($transaction['transaction_info']['transaction_id']);
        $journal->setTitle($this->getTitle($transaction));

        switch (true) {
            case str_starts_with($transaction['cart_info']['item_details'][0]['item_name'] ?? '', 'Ko-fi'):
                $journal->setCreditId($this->cashctrlHelper->getAccountId(1090));
                $journal->setTitle('Ko-fi '.$transaction['transaction_info']['invoice_id'].' - '.$this->getName($transaction));
                break;

            case $amount < 0:
                $journal->setDebitId($this->getDebitAccount($transaction));
                break;

            case 'T1107' === ($transaction['transaction_info']['transaction_event_code'] ?? ''): // Refund
                $journal->setCreditId($this->getDebitAccount($transaction));
                break;

            case null !== ($order = $this->findOpenOrder($transaction)):
                $bookEntry = new OrderBookentry($paypalAccount, $order->getId());
                $bookEntry->setAmount(abs($amount));
                $bookEntry->setDate($dateAdded);
                $bookEntry->setReference($journal->getReference());
                $this->bookentry->create($bookEntry);
                $this->bookFee($transaction, $journal);

                return $journal;

            default:
                $journal->setCreditId($this->cashctrlHelper->getAccountId(1090));
                $this->sentryOrThrow(
                    'PayPal-Zahlung in CashCtrl prüfen: '.$journal->getReference().' von '.$this->getName($transaction),
                    null,
                    [
                        'transaction' => $transaction,
                    ],
                );
                break;
        }

        $this->cashctrlHelper->addJournalEntry($journal);

        $this->bookFee($transaction, $journal);

        return $journal;
    }

    private function bookFee(array $transaction, Journal $journal): void
    {
        $feeAmount = (float) ($transaction['transaction_info']['fee_amount']['value'] ?? 0);

        if ($feeAmount >= 0) {
            return;
        }

        $fee = new Journal(
            abs($feeAmount),
            $this->cashctrlHelper->getAccountId(1040),
            $this->cashctrlHelper->getAccountId(6841),
            $this->getDateAdded($transaction),
        );
        $fee->setReference($journal->getReference());
        $fee->setTitle('PayPal Gebühren für '.$journal->getTitle());

        $this->cashctrlHelper->addJournalEntry($fee);
    }

    private function getDateAdded(array $transaction): \DateTimeInterface
    {
        return new \DateTime($transaction['transaction_info']['transaction_initiation_date']);
    }

    private function getName(array $transaction): string
    {
        return $transaction['payer_info']['payer_name']['alternate_full_name'] ?? '';
    }

    private function getTitle(array $transaction): string
    {
        // Refund to our account
        if ('T1107' === ($transaction['transaction_info']['transaction_event_code'] ?? '')) {
            return 'Rückzahlung '.$transaction['transaction_info']['paypal_reference_id'].' - '.$this->getName($transaction);
        }

        if (isset($transaction['transaction_info']['invoice_id']) && !is_numeric($transaction['transaction_info']['invoice_id'])) {
            return $transaction['transaction_info']['invoice_id'].' - '.$this->getName($transaction);
        }

        if (isset($transaction['cart_info']['item_details'][0]['item_name'])) {
            return $transaction['cart_info']['item_details'][0]['item_name'].' - '.$this->getName($transaction);
        }

        if (isset($transaction['transaction_info']['transaction_subject'])) {
            return $transaction['transaction_info']['transaction_subject'].' - '.$this->getName($transaction);
        }

        if (isset($transaction['transaction_info']['transaction_note'])) {
            return $transaction['transaction_info']['transaction_note'].' - '.$this->getName($transaction);
        }

        return $this->getName($transaction);
    }

    private function getDebitAccount(array $transaction): int
    {
        $email = $transaction['payer_info']['email_address'] ?? '';

        // Software License
        if (\in_array($email, ['paypal@rapidmail.de', 'gold@ko-fi.com'], true)) {
            return $this->cashctrlHelper->getAccountId(6573);
        }

        // Accounting
        if ('support@ec3m.ch' === $email) {
            return $this->cashctrlHelper->getAccountId(6530);
        }

        // Contao team members often have expenses paid through PayPal
        if (\in_array($email, $this->teamMembers, true)) {
            return $this->cashctrlHelper->getAccountId(2120);
        }

        return $this->cashctrlHelper->getAccountId(2000);
    }

    private function findOpenOrder(array $transaction): Order|null
    {
        if (
            (float) $transaction['transaction_info']['transaction_amount']['value'] <= 0
            || (empty($transaction['transaction_info']['transaction_note']))
        ) {
            return null;
        }

        $note = $transaction['transaction_info']['transaction_note'];

        /** @var Order $order */
        foreach ($this->cashctrlHelper->order->list()->onlyOpen() as $order) {
            if (str_contains((string) $note, $order->getNr())) {
                return $order;
            }
        }

        return null;
    }
}
