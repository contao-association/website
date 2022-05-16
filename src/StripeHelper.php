<?php

declare(strict_types=1);

namespace App;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use NotificationCenter\Model\Notification;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\StripeClient;
use Terminal42\CashctrlApi\Entity\Journal;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\Entity\OrderBookentry;

class StripeHelper
{
    use ErrorHandlingTrait;

    private ContaoFramework $framework;
    private CashctrlHelper $cashctrlHelper;
    private StripeClient $client;
    private int $notificationId;

    public function __construct(ContaoFramework $framework, StripeClient $client, CashctrlHelper $cashctrlHelper, int $paymentNotificationId)
    {
        $this->framework = $framework;
        $this->cashctrlHelper = $cashctrlHelper;
        $this->client = $client;
        $this->notificationId = $paymentNotificationId;
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
        if (!$charge->paid) {
            return;
        }

        if ('eur' !== $charge->currency) {
            throw new \RuntimeException("Stripe currency \"$charge->currency\" is not supported.");
        }

        switch ($charge->application) {
            case 'ca_BNCWzVqBWfaL53LdFYzpoumNOsvo2936': // Ko-fi
                $this->bookToJournal(
                    (float) ($charge->amount / 100),
                    \DateTime::createFromFormat('U', (string) $charge->created),
                    1090,
                    $charge->description ?? $charge->payment_intent,
                    "Ko-fi {$charge->metadata['Ko-fi Transaction Id']} - {$charge->billing_details['name']}",
                    $charge->balance_transaction
                );
                break;

            case 'ca_9uvq9hdD9LslRRCLivQ5cDhHsmFLX023': // Pretix
                $this->bookToJournal(
                    (float) ($charge->amount / 100),
                    \DateTime::createFromFormat('U', (string) $charge->created),
                    1105,
                    $charge->description,
                    'Pretix Bestellung '.($charge->metadata['order'] ?? ''),
                    $charge->balance_transaction
                );
                break;

            case null: // Payments from Stripe API
                if (empty($charge->metadata->cashctrl_order_id)) {
                    return;
                }

                $order = $this->cashctrlHelper->order->read((int) $charge->metadata->cashctrl_order_id);

                if (null === $order) {
                    $this->sentryOrThrow('Order ID "'.$charge->metadata->cashctrl_order_id.'" for Stripe charge "'.$charge->id.'" not found', null, [
                        'charge' => $charge->toArray(),
                    ]);
                    return;
                }

                $this->bookToOrder(
                    $order,
                    (float) ($charge->amount / 100),
                    \DateTime::createFromFormat('U', (string) $charge->created),
                    $charge->description ?? $charge->payment_intent,
                    $charge->balance_transaction,
                    (bool) ($charge->metadata->disable_notification ?? false)
                );
                break;

            default:
                $this->sentryOrThrow("Unknown Stripe application \"$charge->application\"", null, [
                    'charge' => $charge->toArray(),
                ]);
        }
    }

    public function importOrderPayment(Session $session): void
    {
        if (null === $session->payment_intent || empty($session->metadata->cashctrl_order_id)) {
            // Ignore Stripe sessions that are not for an invoice
            return;
        }

        $order = $this->cashctrlHelper->order->read((int) $session->metadata->cashctrl_order_id);

        if (null === $order) {
            $this->sentryOrThrow('Order ID "'.$session->metadata->cashctrl_order_id.'" for Stripe checkout session "'.$session->id.'" not found');
            return;
        }

        $paymentIntent = $session->payment_intent instanceof PaymentIntent ? $session->payment_intent : $this->client->paymentIntents->retrieve($session->payment_intent);

        /** @var Charge $charge */
        foreach ($paymentIntent->charges as $charge) {
            $this->bookToOrder(
                $order,
                (float) ($charge->amount / 100),
                \DateTime::createFromFormat('U', (string) $charge->created),
                $charge->description ?? $charge->payment_intent,
                $charge->balance_transaction,
                (bool) ($paymentIntent->metadata->disable_notification ?? false)
            );
        }
    }

    public function storePaymentMethod(Session $session): void
    {
        if (null === $session->setup_intent || empty($session->metadata->contao_member_id)) {
            // Ignore Stripe sessions without setup intent
            return;
        }

        $this->framework->initialize();

        $member = MemberModel::findByPk((int) $session->metadata->contao_member_id);

        if (null === $member) {
            $this->sentryOrThrow('Member ID "'.$session->metadata->contao_member_id.'" for Stripe checkout session "'.$session->id.'" not found');
            return;
        }

        $setupIntent = $session->setup_intent instanceof SetupIntent ? $session->setup_intent : $this->client->setupIntents->retrieve($session->setup_intent);

        if (null === $setupIntent->payment_method) {
            $this->sentryOrThrow('Stripe checkout session "'.$session->id.'" has no payment method');
            return;
        }

        $member->stripe_payment_method = $setupIntent->payment_method instanceof PaymentMethod ? $setupIntent->payment_method->id : $setupIntent->payment_method;
        $member->save();
    }

    private function bookToJournal(float $amount, \DateTimeInterface $created, int $account, string $reference, string $title, ?string $balanceTransaction): void
    {
        $entry = new Journal(
            $amount,
            $this->cashctrlHelper->getAccountId($account),
            $this->cashctrlHelper->getAccountId(1106),
            $created
        );
        $entry->setReference($reference);
        $entry->setTitle($title);

        $this->cashctrlHelper->addJournalEntry($entry);

        if ($balanceTransaction) {
            $this->bookBalanceTransaction($balanceTransaction, $created, $reference, 'Stripe Gebühren für '.$title);
        }
    }

    private function bookToOrder(Order $order, float $amount, \DateTimeInterface $created, string $reference, ?string $balanceTransaction, bool $disableNotification = false): void
    {
        $entry = new OrderBookentry($this->cashctrlHelper->getAccountId(1106), $order->getId());
        $entry->setDescription('Zahlung Stripe');
        $entry->setAmount($amount);
        $entry->setReference($reference);
        $entry->setDate($created);

        $this->cashctrlHelper->addOrderBookentry($entry);

        if ($balanceTransaction) {
            $this->bookBalanceTransaction(
                $balanceTransaction,
                $created,
                $entry->getReference(),
                'Stripe Gebühren für '.$order->getNr()
            );
        }

        // Re-fetch order with updated booking entry
        $order = $this->cashctrlHelper->order->read($order->getId());

        if ($order->open <= 0) {
            if ($disableNotification) {
                $this->cashctrlHelper->order->updateStatus($order->getId(), CashctrlHelper::STATUS_NOTIFIED);
                return;
            }

            $this->framework->initialize();

            $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());
            $notification = Notification::findByPk($this->notificationId);

            if (null === $member || null === $notification) {
                $this->cashctrlHelper->order->updateStatus($order->getId(), CashctrlHelper::STATUS_PAID);
            } else {
                $this->cashctrlHelper->notifyInvoicePaid($order, $member, $notification);
            }
        }
    }

    private function bookBalanceTransaction(string $id, \DateTimeInterface $created, string $reference, string $title): void
    {
        try {
            $transaction = $this->client->balanceTransactions->retrieve($id);

            $fee = new Journal(
                (float) ($transaction->fee / 100),
                $this->cashctrlHelper->getAccountId(1106),
                $this->cashctrlHelper->getAccountId(6842),
                $created
            );
            $fee->setReference($reference);
            $fee->setTitle($title);

            $this->cashctrlHelper->addJournalEntry($fee);
        } catch (ApiErrorException $exception) {
            // Balance transaction not found
        }
    }

    /**
     * Creates or updates a stripe customer from member data.
     * Returns the Stripe customer ID.
     */
    public function createOrUpdateCustomer(MemberModel $member): Customer
    {
        $data = [
            'name' => $member->company ?: sprintf('%s %s', $member->firstname, $member->lastname),
            'email' => $member->email,
            'address' => [
                'city' => $member->city,
                'country' => $member->country,
                'line1' => $member->company ? sprintf('%s %s', $member->firstname, $member->lastname) : $member->street,
                'line2' => $member->company ? $member->street : '',
                'postal_code' => $member->postal,
            ],
        ];

        if (empty($member->stripe_customer)) {
            $customer = $this->client->customers->create($data);

            $member->stripe_customer = $customer->id;
            $member->save();

            return $customer;
        }

        return $this->client->customers->update($member->stripe_customer, $data);
    }
}
