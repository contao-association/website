<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\ModuleRegistration;
use Contao\PageModel;
use Contao\Versions;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule("registration", category="user")
 */
class RegistrationController extends ModuleRegistration
{
    private array $memberships;

    private ?Request $request;

    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(array $memberships)
    {
        // do not call parent constructor

        $this->memberships = $memberships;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section): Response
    {
        parent::__construct($model, $section);

        $this->request = $request;

        $buffer = $this->generate();

        $this->request = null;

        return new Response($buffer);
    }

    protected function createNewUser($arrData): void
    {
        $member = $this->createMember($arrData);
        $this->sendInvoice($member);

        $jumpTo = $this->objModel->getRelated('jumpTo');
        if ($jumpTo instanceof PageModel) {
            throw new RedirectResponseException($jumpTo->getAbsoluteUrl());
        }

        throw new RedirectResponseException($this->request->getUri());
    }

    private function createMember(array $data): MemberModel
    {
        $data['tstamp'] = time();
        $data['dateAdded'] = $data['tstamp'];
        $data['disable'] = '1';
        $data['login'] = '1';
        $data['username'] = $data['email'];

        if (isset($this->memberships[$data['membership']])) {
            $membership = $this->memberships[$data['membership']];
            $data['groups'] = [$membership['group']];
        }

        $newMember = new MemberModel();
        $newMember->setRow($data);
        $newMember->save();

        // Create the initial version, this will synced with Cashctrl
        $objVersions = new Versions('tl_member', $newMember->id);
        $objVersions->setUsername($data['username']);
        $objVersions->setUserId(0);
        $objVersions->setEditUrl('contao/main.php?do=member&act=edit&id=%s&rt=1');
        $objVersions->initialize();

        return $newMember;
    }

    private function sendInvoice(MemberModel $member): void
    {
        // TODO send invoice
    }
}
