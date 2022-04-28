<?php

declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\StripeHelper;
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
            case 'charge.succeeded':
                /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                $this->stripeHelper->importCharge($event->data->object);
                break;

            case 'checkout.session.completed':
                /** @noinspection PhpPossiblePolymorphicInvocationInspection */
                $this->stripeHelper->importCheckoutSession($event->data->object);
                break;

            default:
                throw new BadRequestHttpException('Unsupported Stripe event: '.$event->type);
        }

        return new Response();
    }
}
