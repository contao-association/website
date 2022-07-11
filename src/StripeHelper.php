<?php

declare(strict_types=1);

namespace App;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Stripe\Charge;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\StripeClient;

class StripeHelper
{
    use ErrorHandlingTrait;

    public function __construct(private readonly ContaoFramework $framework, private readonly StripeClient $client, private readonly CashctrlHelper $cashctrlHelper)
    {
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
                $this->cashctrlHelper->bookToJournal(
                    (float) ($charge->amount / 100),
                    \DateTime::createFromFormat('U', (string) $charge->created),
                    1090,
                    $charge->description ?? $charge->payment_intent,
                    "Ko-fi {$charge->metadata['Ko-fi Transaction Id']} - {$charge->billing_details['name']}",
                    $charge->balance_transaction
                );
                break;

            case 'ca_9uvq9hdD9LslRRCLivQ5cDhHsmFLX023': // Pretix
                $this->cashctrlHelper->bookToJournal(
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

                // Ignore payment if it was charged automatically and order is already marked as paid
                if ($order->open <= 0 && ($charge->metadata->auto_payment ?? false)) {
                    return;
                }

                $this->cashctrlHelper->bookToOrder($charge, $order);
                break;

            default:
                $this->sentryOrThrow("Unknown Stripe application \"$charge->application\"", null, [
                    'charge' => $charge->toArray(),
                ]);
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
