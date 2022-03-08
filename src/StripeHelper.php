<?php

declare(strict_types=1);

namespace App;

use Stripe\Charge;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Terminal42\CashctrlApi\Entity\Journal;

class StripeHelper
{
    private CashctrlHelper $cashctrlHelper;
    private StripeClient $client;

    public function __construct(CashctrlHelper $cashctrlHelper, string $stripeKey)
    {
        $this->cashctrlHelper = $cashctrlHelper;
        $this->client = new StripeClient(['api_key' => $stripeKey]);
    }

    /**
     * Retrieve Stripe charges for given day.
     *
     * @return \Generator|Charge[]
     */
    public function getCharges(\DateTimeInterface $from, \DateTimeInterface $to): \Generator
    {
        $charges = $this->client->charges->all([
            'created' => [
                'gte' => strtotime('today 00:00:00', $from->getTimestamp()),
                'lte' => strtotime('today 23:59:59', $to->getTimestamp()),
            ],
        ]);

        return $charges->autoPagingIterator();
    }

    public function importCharge(Charge $charge): void
    {
        switch ($charge->application) {
            case 'ca_BNCWzVqBWfaL53LdFYzpoumNOsvo2936': // Ko-fi
                $this->addChargeToJournal(
                    $charge,
                    1090,
                    $charge->description ?? $charge->payment_intent,
                    "Ko-fi {$charge->metadata['Ko-fi Transaction Id']} - {$charge->billing_details['name']}"
                );
                break;

            case 'ca_9uvq9hdD9LslRRCLivQ5cDhHsmFLX023': // Pretix
                $this->addChargeToJournal(
                    $charge,
                    1105,
                    $charge->description,
                    'Pretix Bestellung '.($charge->metadata['order'] ?? '')
                );
                break;

            default:
                throw new \RuntimeException("Unknown Stripe application \"$charge->application\"");
        }
    }

    private function addChargeToJournal(Charge $charge, int $account, string $reference, string $title): void
    {
        if ('eur' !== $charge->currency) {
            throw new \RuntimeException("Stripe currency \"$charge->currency\" is not supported.");
        }

        $stripeAccount = $this->cashctrlHelper->getAccountId(1106);
        $now = \DateTime::createFromFormat('U', (string) $charge->created);

        $entry = new Journal((float) ($charge->amount / 100), $this->cashctrlHelper->getAccountId($account), $stripeAccount, $now);
        $entry->setReference($reference);
        $entry->setTitle($title);

        $this->cashctrlHelper->addJournalEntry($entry);

        try {
            $transaction = $this->client->balanceTransactions->retrieve($charge->balance_transaction);

            $fee = new Journal((float) ($transaction->fee / 100), $stripeAccount, $this->cashctrlHelper->getAccountId(6842), $now);
            $fee->setReference($reference);
            $fee->setTitle('Stripe Gebühren für '.$title);

            $this->cashctrlHelper->addJournalEntry($fee);
        } catch (ApiErrorException $exception) {
            // Balance transaction not found
        }
    }
}
