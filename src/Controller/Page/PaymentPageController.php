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

/**
 * @Page(path="{orderId}", requirements={"orderId"="\d+"}, contentComposition=false)
 */
class PaymentPageController
{
    private UriSigner $uriSigner;
    private CashctrlHelper $cashctrl;
    private StripeHelper $stripeHelper;
    private StripeClient $stripeClient;

    public function __construct(UriSigner $uriSigner, CashctrlHelper $cashctrl, StripeHelper $stripeHelper, StripeClient $stripeClient)
    {
        $this->uriSigner = $uriSigner;
        $this->cashctrl = $cashctrl;
        $this->stripeHelper = $stripeHelper;
        $this->stripeClient = $stripeClient;
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
            'locale' => strtolower($member->language),
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
