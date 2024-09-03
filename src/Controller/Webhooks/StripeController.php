<?php

declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\StripeHelper;
use Oneup\ContaoSentryBundle\ErrorHandlingTrait;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/_webhooks/stripe', methods: ['POST'])]
class StripeController
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly StripeHelper $stripeHelper,
        private readonly string $stripeSecret,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $event = Webhook::constructEvent($request->getContent(), $request->headers->get('Stripe-Signature', ''), $this->stripeSecret);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                /**
                 * @var PaymentIntent $paymentIntent
                 *
                 * @noinspection PhpPossiblePolymorphicInvocationInspection
                 */
                $paymentIntent = $event->data->object;

                if ($charge = $paymentIntent->latest_charge) {
                    $this->stripeHelper->importCharge($charge);
                }
                break;

            case 'payment_intent.payment_failed':
                /**
                 * @var PaymentIntent $paymentIntent
                 *
                 * @noinspection PhpPossiblePolymorphicInvocationInspection
                 */
                $paymentIntent = $event->data->object;

                if (StripeHelper::APP_PRETIX === $paymentIntent->application) {
                    break;
                }

                $this->sentryOrThrow(
                    'Please handle payment_intent.payment_failed',
                    null,
                    [
                        'event' => $event->toArray(),
                    ],
                );
                // TODO handle failed payments
                break;

            default:
                $this->sentryOrThrow(
                    'Unsupported Stripe event: '.$event->type,
                    null,
                    [
                        'event' => $event->toArray(),
                    ],
                );
        }

        return new Response();
    }
}
