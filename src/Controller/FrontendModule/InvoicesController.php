<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use App\CashctrlHelper;
use App\StripeHelper;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\CashctrlApi\ApiClient;
use Terminal42\CashctrlApi\Entity\Order;

#[AsFrontendModule(category: 'user')]
class InvoicesController extends AbstractFrontendModuleController
{
    public function __construct(
        private readonly Security $security,
        private readonly CashctrlHelper $cashctrl,
        private readonly StripeHelper $stripeHelper,
        private readonly HttpClientInterface $httpClient,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
        private readonly string $harvestId,
        private readonly string $harvestToken,
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return new Response();
        }

        $member = MemberModel::findById($user->id);

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
        $cashctrlIds = array_filter(array_merge([$member->cashctrl_id], explode(',', (string) $member->cashctrl_associates)));

        foreach ($cashctrlIds as $cashctrlId) {
            /** @var Order $order */
            foreach ($this->cashctrl->listInvoices((int) $cashctrlId) as $order) {
                if (!$order->isBook || $order->getAssociateId() !== (int) $cashctrlId) {
                    continue;
                }

                if ((int) Input::get('invoice') === $order->getId()) {
                    throw new ResponseException(new Response($this->cashctrl->downloadInvoice($order, null, $member->language ?: 'de'), 200, ['Content-Type' => 'application/pdf']));
                }

                $due = ApiClient::parseDateTime($order->dateDue);
                $paymentHref = '';
                $isClosed = $order->isClosed;
                $status = $this->translator->trans($isClosed ? 'invoice_paid' : 'invoice_unpaid');

                if (!$isClosed) {
                    $charges = $this->stripeHelper->findPaymentForMember($member, $order);

                    foreach ($charges as $charge) {
                        if ('pending' === $charge->status) {
                            $isClosed = true;
                            $status = $this->translator->trans('sepa_debit' === $charge->payment_method_details['type'] ? 'invoice_pending_sepa' : 'invoice_pending');
                            break;
                        }
                    }
                }

                if (!$isClosed && $paymentPage instanceof PageModel) {
                    $paymentHref = $this->urlGenerator->generate(
                        RouteObjectInterface::OBJECT_BASED_ROUTE_NAME,
                        [
                            RouteObjectInterface::CONTENT_OBJECT => $paymentPage,
                            'orderId' => $order->getId(),
                            'cancel_url' => $this->getPageModel()->getAbsoluteUrl(),
                        ],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    );
                    $paymentHref = $this->uriSigner->sign($paymentHref);
                }

                $invoices[] = [
                    'id' => $order->getId(),
                    'nr' => $order->getNr(),
                    'tstamp' => $order->getDate()->format('U'),
                    'date' => $order->getDate()->format($dateFormat),
                    'due' => $due->format($dateFormat),
                    'closed' => $isClosed,
                    'status' => $status,
                    'total' => number_format($order->total, 2, '.', "'"),
                    'href' => $this->getPageModel()->getFrontendUrl().'?invoice='.$order->getId(),
                    'isPdf' => true,
                    'paymentHref' => $paymentHref,
                ];
            }
        }
    }

    private function addHarvestInvoices(MemberModel $member, array &$invoices, string $dateFormat): void
    {
        // New members don't have invoices in Harvest
        if (empty($member->harvest_client_id)) {
            return;
        }

        foreach (explode(',', (string) $member->harvest_client_id) as $clientId) {
            $this->addHarvestInvoicesForId((int) $clientId, $invoices, $dateFormat);
        }
    }

    private function addHarvestInvoicesForId(int $clientId, array &$invoices, string $dateFormat): void
    {
        $response = $this->httpClient->request(
            'GET',
            'https://api.harvestapp.com/v2/invoices?client_id='.$clientId,
            [
                'headers' => [
                    'Harvest-Account-Id' => $this->harvestId,
                ],
                'auth_bearer' => $this->harvestToken,
            ],
        );

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
                'tstamp' => strtotime((string) $invoice['issue_date']),
                'date' => Date::parse($dateFormat, strtotime((string) $invoice['issue_date'])),
                'due' => Date::parse($dateFormat, strtotime((string) $invoice['due_date'])),
                'closed' => 'paid' === $invoice['state'],
                'status' => $this->translator->trans('paid' === $invoice['state'] ? 'invoice_paid' : 'invoice_unpaid'),
                'total' => number_format($invoice['amount'], 2, '.', "'"),
                'href' => 'https://contaoassociation.harvestapp.com/client/invoices/'.$invoice['client_key'],
                'isPdf' => false,
            ];
        }
    }
}
