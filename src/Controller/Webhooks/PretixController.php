<?php

declare(strict_types=1);

namespace App\Controller\Webhooks;

use App\ErrorHandlingTrait;
use App\PretixHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/_webhooks/pretix", methods={"POST"})
 */
class PretixController
{
    use ErrorHandlingTrait;

    private PretixHelper $pretixHelper;

    public function __construct(PretixHelper $pretixHelper)
    {
        $this->pretixHelper = $pretixHelper;
    }

    public function __invoke(Request $request)
    {
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        switch ($data['action']) {
            case 'pretix.event.order.placed':
            case 'pretix.event.order.approved':
                $invoices = $this->pretixHelper->getInvoices($data['organizer'], $data['event'], $data['code']);

                if (1 === count($invoices)) {
                    try {
                        $this->pretixHelper->bookOrder($data['event'], $invoices[0]);
                    } catch (\Exception $exception) {
                        $this->sentryOrThrow($exception->getMessage(), $exception, [
                            'data' => $data,
                            'invoice' => $invoices[0],
                        ]);
                    }
                }
                break;

            default:
                throw new BadRequestHttpException("Unsupported Pretix action \"{$data['action']}\"");
        }

        return new Response();
    }
}
