<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\CashctrlHelper;
use App\StripeHelper;
use Contao\CoreBundle\ServiceAnnotation\Page;
use Contao\MemberModel;
use Contao\PageModel;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Page(path="{orderId}", requirements={"orderId"="\d+"}, contentComposition=false)
 */
class PaymentPageController
{
    public function __construct(
        private readonly UriSigner $uriSigner,
        private readonly CashctrlHelper $cashctrl,
        private readonly StripeHelper $stripeHelper,
        private readonly StripeClient $stripeClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(int $orderId, PageModel $pageModel, Request $request)
    {
        if (!$this->uriSigner->checkRequest($request)) {
            throw new BadRequestException();
        }

        $order = $this->cashctrl->order->read($orderId);

        if (null === $order) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        if ($order->isClosed) {
            return new Response('Invoice already paid', Response::HTTP_GONE);
        }

        $member = MemberModel::findOneBy('cashctrl_id', $order->getAssociateId());

        if (null === $member) {
            throw new BadRequestException();
        }

        $lineItems = [];
        foreach ($order->getItems() as $orderItem) {
            // Stripe does not support line items with negative amounts (e.g. a discount)
            // so we have to create one line item only with the invoice total
            if ($orderItem->getUnitPrice() < 0) {
                $lineItems = [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($order->currencyCode ?: 'eur'),
                        'product_data' => ['name' => $this->translator->trans('invoice_nr').' '.$order->getNr()],
                        'unit_amount' => (int) round($order->total * 100),
                    ],
                ]];
                break;
            }

            $lineItems[] = [
                'quantity' => $orderItem->getQuantity(),
                'price_data' => [
                    'currency' => strtolower($order->currencyCode ?: 'eur'),
                    'product_data' => ['name' => $orderItem->getName()],
                    'unit_amount' => (int) round($orderItem->getUnitPrice() * 100),
                ],
            ];
        }

        $customer = $this->stripeHelper->createOrUpdateCustomer($member);
        $session = $this->stripeClient->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $customer->id,
            'locale' => strtolower($member->language ?: $GLOBALS['TL_LANGUAGE'] ?? 'de'),
            'payment_intent_data' => [
                'description' => $order->getDescription(),
                'metadata' => [
                    'cashctrl_order_id' => $order->getId(),
                    'contao_member_id' => $member->id,
                ]
            ],
            'metadata' => [
                'cashctrl_order_id' => $order->getId(),
                'contao_member_id' => $member->id,
            ],
            'line_items' => $lineItems,
            'success_url' => $this->getTargetPage($pageModel)->getAbsoluteUrl(),
            'cancel_url' => $request->query->get('cancel_url') ?: PageModel::findFirstPublishedRegularByPid($pageModel->rootId)->getAbsoluteUrl(),
        ]);

        return new RedirectResponse($session->url);
    }

    private function getTargetPage(PageModel $pageModel): PageModel
    {
        $targetPage = null;

        if ($pageModel->jumpTo > 0) {
            $targetPage = PageModel::findByPk($pageModel->jumpTo);
        }

        if (null === $targetPage) {
            $targetPage = PageModel::findFirstPublishedRegularByPid($pageModel->id);
        }

        if (null === $targetPage) {
            throw new \RuntimeException('Confirmation page for payment page ID '.$pageModel->id.' not found');
        }

        return $targetPage;
    }
}
