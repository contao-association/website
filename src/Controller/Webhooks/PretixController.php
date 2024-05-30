<?php

declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\PretixHelper;
use Oneup\ContaoSentryBundle\ErrorHandlingTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/_webhooks/pretix', methods: ['POST'])]
class PretixController
{
    use ErrorHandlingTrait;

    public function __construct(private readonly PretixHelper $pretixHelper)
    {
    }

    public function __invoke(Request $request): Response
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        switch ($data['action']) {
            case 'pretix.event.order.placed':
            case 'pretix.event.order.approved':
                $invoices = $this->pretixHelper->getInvoices($data['organizer'], $data['event'], $data['code']);

                if (1 === \count($invoices)) {
                    try {
                        $this->pretixHelper->bookOrder($data['event'], $invoices[0]);
                    } catch (\Exception $exception) {
                        $this->sentryOrThrow(
                            $exception->getMessage(),
                            $exception,
                            [
                                'data' => $data,
                                'invoice' => $invoices[0],
                            ],
                        );
                    }
                }
                break;

            default:
                throw new BadRequestHttpException(sprintf('Unsupported Pretix action "%s"', $data['action']));
        }

        return new Response();
    }
}
