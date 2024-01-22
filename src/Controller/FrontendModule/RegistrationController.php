<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use App\CashctrlHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\ModuleRegistration;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Terminal42\CashctrlApi\Entity\Order;

#[AsFrontendModule('registration', category: 'user')]
class RegistrationController extends ModuleRegistration
{
    private Request|null $request = null;

    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        private readonly CashctrlHelper $cashctrl,
        private readonly array $memberships,
        private readonly int $registrationNotificationId,
    ) {
        // do not call parent constructor
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

        if (!$this->cashctrl->syncMember($member)) {
            throw new \RuntimeException('Unable to create new member ID '.$member->id.' in CashCtrl');
        }

        $invoice = $this->cashctrl->createAndSendInvoice($member, $this->registrationNotificationId, new \DateTimeImmutable());

        if ($invoice instanceof Order) {
            $member->cashctrl_invoice = $invoice->getId();
            $member->save();
        }

        $this->sendAdminNotification($member->id, $arrData);

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
        $data['membership_start'] = mktime(0, 0, 0);
        $data['disable'] = '1';
        $data['login'] = '1';
        $data['username'] = $data['email'];
        $data['language'] = $GLOBALS['TL_LANGUAGE'];

        $level = $data['membership'];
        $groups = [];

        if ($this->memberships[$level]['group'] ?? null) {
            $groups[] = $this->memberships[$level]['group'];
        }

        if ($data['membership_member'] && 'active' !== $level && !($this->memberships[$level]['invisible'] ?? false)) {
            $groups[] = $this->memberships['active']['group'];
        }

        $data['groups'] = $groups;

        $newMember = new MemberModel();
        $newMember->setRow($data);
        $newMember->save();

        return $newMember;
    }
}
