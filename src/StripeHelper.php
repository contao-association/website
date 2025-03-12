<?php

declare(strict_types=1);

namespace App;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\System;
use Oneup\ContaoSentryBundle\ErrorHandlingTrait;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\SetupIntent;
use Stripe\StripeClient;
use Symfony\Component\Lock\Exception\ExceptionInterface;
use Symfony\Component\Lock\LockFactory;
use Terminal42\CashctrlApi\Entity\Order;

class StripeHelper
{
    use ErrorHandlingTrait;

    final public const APP_KOFI = 'ca_BNCWzVqBWfaL53LdFYzpoumNOsvo2936';

    final public const APP_PRETIX = 'ca_9uvq9hdD9LslRRCLivQ5cDhHsmFLX023';

    public function __construct(
        private readonly ContaoFramework $framework,
        public readonly StripeClient $client,
        private readonly CashctrlHelper $cashctrlHelper,
        private readonly LockFactory $lockFactory,
    ) {
    }

    /**
     * Retrieve Stripe charges for given day.
     *
     * @return \Generator<Charge|Refund>
     */
    public function getCharges(\DateTimeInterface $from, \DateTimeInterface $to): \Generator
    {
        $charges = $this->client->charges->all([
            'created' => [
                'gte' => strtotime('today 00:00:00', $from->getTimestamp()),
                'lte' => strtotime('today 23:59:59', $to->getTimestamp()),
            ],
        ]);

        yield from $charges->autoPagingIterator();

        $refunds = $this->client->refunds->all([
            'created' => [
                'gte' => strtotime('today 00:00:00', $from->getTimestamp()),
                'lte' => strtotime('today 23:59:59', $to->getTimestamp()),
            ],
        ]);

        yield from $refunds->autoPagingIterator();
    }

    /**
     * @return array<Charge>
     */
    public function findPaymentForMember(MemberModel $member, Order $order): array
    {
        if (!$member->stripe_customer || $order->isClosed) {
            return [];
        }

        $result = $this->client->charges->search([
            'query' => 'customer:"'.$member->stripe_customer.'" AND metadata["cashctrl_order_id"]:"'.$order->getId().'"',
        ]);

        return $result->data;
    }

    public function importCharge(Charge|string $charge): void
    {
        if (\is_string($charge)) {
            $charge = $this->client->charges->retrieve($charge);
        }

        if (!$charge->paid) {
            return;
        }

        if ('eur' !== $charge->currency) {
            throw new \RuntimeException(\sprintf('Stripe currency "%s" is not supported.', $charge->currency));
        }

        switch ($charge->application) {
            case self::APP_KOFI:
                $this->cashctrlHelper->bookToJournal(
                    $charge->amount,
                    \DateTime::createFromFormat('U', (string) $charge->created),
                    1090,
                    $charge->description ?? $charge->payment_intent,
                    "Ko-fi {$charge->metadata['Ko-fi Transaction Id']} - {$charge->billing_details['name']}",
                    $charge->balance_transaction,
                );
                break;

            case self::APP_PRETIX:
                $this->cashctrlHelper->bookToJournal(
                    $charge->amount,
                    \DateTime::createFromFormat('U', (string) $charge->created),
                    1105,
                    $charge->metadata['order'] ?? $charge->description,
                    $charge->description.' - Zahlung (Stripe)',
                    $charge->balance_transaction,
                );
                break;

            case null: // Payments from Stripe API
                try {
                    $lock = $this->lockFactory->createLock('cashctrl_order_'.$charge->metadata->cashctrl_order_id);
                    $lock->acquire(true);
                } catch (ExceptionInterface $exception) {
                    $this->sentryOrThrow(
                        'Failed acquiring lock for Stripe webhook.',
                        $exception,
                        ['charge' => $charge->toArray()],
                    );

                    return;
                }

                try {
                    $order = $this->cashctrlHelper->order->read((int) $charge->metadata->cashctrl_order_id);

                    if (null === $order) {
                        $this->sentryOrThrow(
                            'Order ID "'.$charge->metadata->cashctrl_order_id.'" for Stripe charge "'.$charge->id.'" not found',
                            null,
                            ['charge' => $charge->toArray()],
                        );

                        return;
                    }

                    // Ignore payment if it was charged automatically and order is already marked as paid
                    if ($order->open <= 0 && ($charge->metadata->auto_payment ?? false)) {
                        return;
                    }

                    if ($charge->balance_transaction) {
                        $this->cashctrlHelper->bookToOrder($charge, $order, CashctrlHelper::STATUS_NOTIFIED !== $order->getStatusId());
                    }
                } finally {
                    $lock->release();
                }
                break;

            default:
                $this->sentryOrThrow(
                    "Unknown Stripe application \"$charge->application\" for charge",
                    null,
                    ['charge' => $charge->toArray()],
                );
        }
    }

    public function importRefund(Refund $refund, Charge $charge): void
    {
        if ('eur' !== $refund->currency) {
            throw new \RuntimeException(\sprintf('Stripe currency "%s" is not supported.', $refund->currency));
        }

        if (self::APP_PRETIX !== $charge->application) {
            $this->sentryOrThrow(
                "Unknown Stripe application \"$charge->application\" for refund",
                null,
                ['refund' => $refund->toArray(), 'charge' => $charge->toArray()],
            );
            return;
        }

        $this->cashctrlHelper->bookToJournal(
            $refund->amount * -1,
            \DateTime::createFromFormat('U', (string) $refund->created),
            1105,
            $charge->metadata['order'] ?? $charge->description,
            $charge->description.' - Rückerstattung (Stripe)',
            $refund->balance_transaction,
        );
    }

    public function storePaymentMethod(Session $session): void
    {
        if (null === $session->setup_intent || empty($session->metadata->contao_member_id)) {
            // Ignore Stripe sessions without setup intent
            return;
        }

        $this->framework->initialize();
        System::loadLanguageFile('default');

        $member = MemberModel::findById((int) $session->metadata->contao_member_id);

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

    /**
     * Creates or updates a stripe customer from member data.
     * Returns the Stripe customer ID.
     */
    public function createOrUpdateCustomer(MemberModel $member): Customer
    {
        $data = [
            'name' => $member->company ?: \sprintf('%s %s', $member->firstname, $member->lastname),
            'email' => $member->email,
            'address' => [
                'city' => $member->city,
                'country' => $member->country,
                'line1' => $member->company ? \sprintf('%s %s', $member->firstname, $member->lastname) : $member->street,
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
