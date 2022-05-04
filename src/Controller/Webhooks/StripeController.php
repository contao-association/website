<?php

declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\ErrorHandlingTrait;
use App\StripeHelper;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/_webhooks/stripe", methods={"POST"})
 */
class StripeController
{
    use ErrorHandlingTrait;

    private StripeHelper $stripeHelper;
    private string $stripeSecret;

    public function __construct(StripeHelper $stripeHelper, string $stripeSecret)
    {
        $this->stripeHelper = $stripeHelper;
        $this->stripeSecret = $stripeSecret;
    }

    public function __invoke(Request $request)
    {
        $event = Webhook::constructEvent($request->getContent(), $request->headers->get('Stripe-Signature', ''), $this->stripeSecret);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                /**
                 * @var PaymentIntent $paymentIntent
                 * @noinspection PhpPossiblePolymorphicInvocationInspection
                 */
                $paymentIntent = $event->data->object;
                foreach ($paymentIntent->charges as $charge) {
                    $this->stripeHelper->importCharge($charge);
                }
                break;

            case 'payment_intent.payment_failed':
                /**
                 * @var PaymentIntent $paymentIntent
                 * @noinspection PhpPossiblePolymorphicInvocationInspection
                 */
                $paymentIntent = $event->data->object;
                $this->sentryOrThrow('Please handle payment_intent.payment_failed', null, [
                    'event' => $event->toArray(),
                ]);
                // TODO handle failed payments
                break;

            case 'charge.succeeded':
                /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                $this->stripeHelper->importCharge($event->data->object);
                break;

            case 'checkout.session.completed':
                /**
                 * @var Session
                 * @noinspection PhpPossiblePolymorphicInvocationInspection
                 */
                $session = $event->data->object;
                $this->stripeHelper->importOrderPayment($session);
                $this->stripeHelper->storePaymentMethod($session);
                break;

            default:
                $this->sentryOrThrow('Unsupported Stripe event: '.$event->type, null, [
                    'event' => $event->toArray(),
                ]);
        }

        return new Response();
    }
}
