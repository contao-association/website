<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use App\StripeHelper;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Date;
use Contao\PageModel;
use Stripe\StripeClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Contao\ModuleModel;
use Contao\Template;
use App\CashctrlHelper;
use Symfony\Component\Security\Core\Security;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\XmlHelper;
use Contao\Environment;
use Contao\Input;
use Contao\CoreBundle\Exception\ResponseException;
use Terminal42\CashctrlApi\ApiClient;

/**
 * @FrontendModule(category="user")
 */
class InvoicesController extends AbstractFrontendModuleController
{
    private Security $security;
    private CashctrlHelper $cashctrl;
    private StripeHelper $stripeHelper;
    private StripeClient $stripeClient;
    private HttpClientInterface $httpClient;
    private string $harvestId;
    private string $harvestToken;

    public function __construct(Security $security, CashctrlHelper $cashctrl, StripeHelper $stripeHelper, StripeClient $stripeClient, HttpClientInterface $httpClient, string $harvestId, string $harvestToken)
    {
        $this->security = $security;
        $this->cashctrl = $cashctrl;
        $this->stripeHelper = $stripeHelper;
        $this->stripeClient = $stripeClient;
        $this->httpClient = $httpClient;
        $this->harvestId = $harvestId;
        $this->harvestToken = $harvestToken;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return new Response();
        }

        $member = MemberModel::findByPk($user->id);

        if (null === $member) {
            return new Response();
        }

        $invoices = [];
        $dateFormat = isset($GLOBALS['objPage']) ? $GLOBALS['objPage']->dateFormat : $GLOBALS['TL_CONFIG']['dateFormat'];

        $this->addCashctrlInvoices($member, $invoices, $dateFormat, $request, $model);
        $this->addHarvestInvoices($member, $invoices, $dateFormat);

        $template->invoices = $invoices;
        $template->paymentError = (bool) $request->query->get('paymentError');

        return $template->getResponse();
    }

    private function addCashctrlInvoices(MemberModel $member, array &$invoices, string $dateFormat, Request $request, ModuleModel $model): void
    {
        /** @var Order $order */
        foreach ($this->cashctrl->listInvoices($member) as $order) {
            if (!$order->isBook || $order->getAssociateId() !== (int) $member->cashctrl_id) {
                continue;
            }

            if (
                $request->isMethod('POST')
                && 'pay_invoice' === $request->request->get('FORM_SUBMIT')
                && (string) $order->getId() === $request->request->get('invoice')
            ) {
                $this->initiatePayment($order, $member, $model);
            }

            if ((int) Input::get('invoice') === $order->getId()) {
                throw new ResponseException(new Response(
                    $this->cashctrl->downloadInvoice($order, null, $member->language ?: 'de'),
                    200,
                    [
                        'Content-Type' => 'application/pdf'
                    ]
                ));
            }

            $due = ApiClient::parseDateTime($order->dateDue);

            $invoices[] = [
                'id' => $order->getId(),
                'nr' => $order->getNr(),
                'date' => $order->getDate()->format($dateFormat),
                'due' => $due->format($dateFormat),
                'closed' => $order->isClosed,
                'total' => number_format($order->total, 2, '.', "'"),
                'href' => $this->getPageModel()->getFrontendUrl().'?invoice='.$order->getId(),
                'isPdf' => true,
                'enablePayment' => !empty($model->jumpTo) && !$order->isClosed,
            ];
        }
    }

    private function addHarvestInvoices(MemberModel $member, array &$invoices, string $dateFormat): void
    {
        // New members don't have invoices in Harvest
        if (!$member->harvest_client_id) {
            return;
        }

        $response = $this->httpClient->request('GET', 'https://api.harvestapp.com/v2/invoices?client_id='.$member->harvest_client_id, [
            'headers' => [
                'Harvest-Account-Id' => $this->harvestId,
            ],
            'auth_bearer' => $this->harvestToken
        ]);

        if (200 !== $response->getStatusCode()) {
            return;
        }

        foreach ($response->toArray()['invoices'] as $invoice) {
            if (!\in_array($invoice['state'], ['open', 'partial', 'paid'], true)) {
                continue;
            }

            $invoices[] = [
                'id' => $invoice['number'],
                'nr' => $invoice['number'],
                'date' => Date::parse($dateFormat, strtotime($invoice['issue_date'])),
                'due' => Date::parse($dateFormat, strtotime($invoice['due_date'])),
                'closed' => 'paid' === $invoice['state'],
                'total' => number_format($invoice['amount'], 2, '.', "'"),
                'href' => 'https://contaoassociation.harvestapp.com/client/invoices/' . $invoice['client_key'],
                'isPdf' => false,
                'enablePayment' => false,
            ];
        }
    }

    private function initiatePayment(Order $order, MemberModel $member, ModuleModel $moduleModel): void
    {
        // need to re-fetch to get the line items
        $order = $this->cashctrl->order->read($order->getId());

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
            'customer' => $customer->id,
            'metadata' => ['cashctrl_order_id' => $order->getId()],
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => PageModel::findByPk($moduleModel->jumpTo)->getAbsoluteUrl(),
            'cancel_url' => $this->getPageModel()->getAbsoluteUrl().'?paymentError=true',
        ]);

        throw new RedirectResponseException($session->url);
    }
}
