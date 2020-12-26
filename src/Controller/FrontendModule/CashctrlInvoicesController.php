<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Contao\ModuleModel;
use Contao\Template;
use App\CashctrlApi;
use Symfony\Component\Security\Core\Security;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\XmlHelper;
use Contao\Environment;
use Contao\Input;
use Contao\CoreBundle\Exception\ResponseException;
use Terminal42\CashctrlApi\ApiClient;

/**
 * @FrontendModule(category="user")
 */
class CashctrlInvoicesController extends AbstractFrontendModuleController
{
    private CashctrlApi $api;
    private Security $security;

    public function __construct(CashctrlApi $api, Security $security)
    {
        $this->api = $api;
        $this->security = $security;
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


        $orders = [];
        $dateFormat = isset($GLOBALS['objPage']) ? $GLOBALS['objPage']->dateFormat : $GLOBALS['TL_CONFIG']['dateFormat'];

        /** @var Order $order */
        foreach ($this->api->listInvoices($member) as $order) {
            if (!$order->isBook || $order->getAssociateId() !== (int) $member->cashctrl_id) {
                continue;
            }

            if ((int) Input::get('invoice') === $order->getId()) {
                throw new ResponseException(new Response(
                    $this->api->downloadInvoice($order, null, $member->language ?: 'de'),
                    200,
                    [
                        'Content-Type' => 'application/pdf'
                    ]
                ));
            }

            $due = ApiClient::parseDateTime($order->dateDue);

            $orders[] = [
                'nr' => $order->getNr(),
                'date' => $order->getDate()->format($dateFormat),
                'due' => $due->format($dateFormat),
                'closed' => $order->isClosed,
                'total' => number_format($order->total, 2, '.', "'"),
                'status' => XmlHelper::parseValues($order->statusName)[$GLOBALS['TL_LANGUAGE']],
                'href' => Environment::get('request').'?invoice='.$order->getId(),
            ];
        }

        $template->orders = $orders;

        return $template->getResponse();
    }
}
