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
use App\CashctrlApi;
use NotificationCenter\Model\Notification;
use function Sentry\captureMessage;
use Contao\Config;
use Haste\Util\StringUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @FrontendModule("registration", category="user")
 */
class RegistrationController extends ModuleRegistration
{
    private CashctrlApi $api;
    private TranslatorInterface $translator;
    private array $memberships;

    private ?Request $request;

    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(CashctrlApi $api, TranslatorInterface $translator, array $memberships)
    {
        // do not call parent constructor

        $this->api = $api;
        $this->translator = $translator;
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

    private function sendInvoice(MemberModel $member)
    {
        $notification = Notification::findByPk($this->objModel->nc_notification);

        if (null === $notification) {
            $this->sentryOrThrow('No notification is configured to send invoices (Registration module ID '.$this->objModel->id.')');
            return;
        }

        $invoice = $this->api->createMemberInvoice($member);
        $pdf = $this->api->archiveInvoice($invoice, 'ch' === $member->country ? 11 : 13);

        $dateFormat = isset($GLOBALS['objPage']) ? $GLOBALS['objPage']->dateFormat : $GLOBALS['TL_CONFIG']['dateFormat'];

        $tokens = [
            'admin_email' => Config::get('adminEmail'),
            'membership_label' => $this->translator->trans('membership.'.$member->membership),
            'invoice_number' => $invoice->getNr(),
            'invoice_date' => $invoice->getDate()->format($dateFormat),
            'invoice_due_days' => $invoice->getDueDays(),
            'invoice_total' => number_format($invoice->total, 2, '.', "'"),
            'invoice_pdf' => $pdf,
        ];

        StringUtil::flatten($member->row(), 'member', $tokens);

        $result = $notification->send($tokens);

        if (\in_array(false, $result, true)) {
            $this->sentryOrThrow('Unable to send invoice email to '.$member->email);
            return;
        }

        $this->api->markInvoiceSent($invoice->getId());
    }

    private function sentryOrThrow(string $message): void
    {
        if (null === captureMessage($message)) {
            throw new \RuntimeException($message);
        }
    }
}
