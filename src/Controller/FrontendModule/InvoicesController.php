<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\Date;
use Contao\PageModel;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Contao\ModuleModel;
use Contao\Template;
use App\CashctrlHelper;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Terminal42\CashctrlApi\Entity\Order;
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
    private HttpClientInterface $httpClient;
    private UrlGeneratorInterface $urlGenerator;
    private UriSigner $uriSigner;
    private string $harvestId;
    private string $harvestToken;

    public function __construct(Security $security, CashctrlHelper $cashctrl, HttpClientInterface $httpClient, UrlGeneratorInterface $urlGenerator, UriSigner $uriSigner, string $harvestId, string $harvestToken)
    {
        $this->security = $security;
        $this->cashctrl = $cashctrl;
        $this->httpClient = $httpClient;
        $this->urlGenerator = $urlGenerator;
        $this->uriSigner = $uriSigner;
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

        $this->addCashctrlInvoices($member, $invoices, $dateFormat);
        $this->addHarvestInvoices($member, $invoices, $dateFormat);

        usort($invoices, static fn ($a, $b) => $b['tstamp'] <=> $a['tstamp']);

        $template->invoices = $invoices;
        $template->paymentError = (bool) $request->query->get('paymentError');

        return $template->getResponse();
    }

    private function addCashctrlInvoices(MemberModel $member, array &$invoices, string $dateFormat): void
    {
        // Find the payment page in the current root
        $paymentPage = PageModel::findFirstPublishedByTypeAndPid('payment', $this->getPageModel()->rootId);

        /** @var Order $order */
        foreach ($this->cashctrl->listInvoices($member) as $order) {
            if (!$order->isBook || $order->getAssociateId() !== (int) $member->cashctrl_id) {
                continue;
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
            $paymentHref = '';

            if ($paymentPage instanceof PageModel && !$order->isClosed) {
                $paymentHref = $this->urlGenerator->generate(RouteObjectInterface::OBJECT_BASED_ROUTE_NAME, array(RouteObjectInterface::CONTENT_OBJECT => $paymentPage, 'orderId' => $order->getId(), 'cancel_url' => $this->getPageModel()->getAbsoluteUrl()), UrlGeneratorInterface::ABSOLUTE_URL);
                $paymentHref = $this->uriSigner->sign($paymentHref);
            }

            $invoices[] = [
                'id' => $order->getId(),
                'nr' => $order->getNr(),
                'tstamp' => $order->getDate()->format('U'),
                'date' => $order->getDate()->format($dateFormat),
                'due' => $due->format($dateFormat),
                'closed' => $order->isClosed,
                'total' => number_format($order->total, 2, '.', "'"),
                'href' => $this->getPageModel()->getFrontendUrl().'?invoice='.$order->getId(),
                'isPdf' => true,
                'paymentHref' => $paymentHref,
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
                'tstamp' => strtotime($invoice['issue_date']),
                'date' => Date::parse($dateFormat, strtotime($invoice['issue_date'])),
                'due' => Date::parse($dateFormat, strtotime($invoice['due_date'])),
                'closed' => 'paid' === $invoice['state'],
                'total' => number_format($invoice['amount'], 2, '.', "'"),
                'href' => 'https://contaoassociation.harvestapp.com/client/invoices/' . $invoice['client_key'],
                'isPdf' => false,
            ];
        }
    }
}
