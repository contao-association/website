<?php

declare(strict_types=1);

namespace App\Controller\FrontendModule;

use App\Harvest\Harvest;
use Contao\Config;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\ModuleRegistration;
use Contao\PageModel;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Haste\Util\Format;
use Haste\Util\StringUtil;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use function Sentry\captureMessage;

/**
 * @FrontendModule("registration", category="user")
 */
class HarvestRegistrationController extends ModuleRegistration
{
    private Harvest $harvest;
    private Connection $connection;
    private TranslatorInterface $translator;
    private array $memberships;

    private ?Request $request;

    /**
     * @noinspection MagicMethodsValidityInspection
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(Harvest $harvest, Connection $connection, TranslatorInterface $translator, array $memberships)
    {
        // do not call parent constructor
        $this->harvest = $harvest;
        $this->connection = $connection;
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
        try {
            $member = $this->createMember($arrData);
        } catch (\OverflowException $e) {
            $this->Template->hasError = true;
            $this->Template->error = $this->translator->trans('harvest_client_exists');

            return;
        }

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

        if ($this->clientExists($newMember)) {
            throw new \OverflowException('A Harvest client with name "'.$this->harvest->generateClientName($newMember).'" already exists.');
        }

        $newMember->save();

        // Create the initial version, this will sync with Harvest
        $objVersions = new Versions('tl_member', $newMember->id);
        $objVersions->setUsername($data['username']);
        $objVersions->setUserId(0);
        $objVersions->setEditUrl('contao/main.php?do=member&act=edit&id=%s&rt=1');
        $objVersions->initialize();

        return $newMember;
    }

    private function clientExists(MemberModel $member): bool
    {
        $existingId = $this->harvest->findClientId($member);

        if (null === $existingId) {
            return false;
        }

        $duplicate = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_member WHERE harvest_client_id=?',
            [$existingId]
        );

        return $duplicate > 0;
    }

    private function sendInvoice(MemberModel $member): void
    {
        $invoice = $this->harvest->invoice->createMembershipInvoice($member);
        $pdf = $this->harvest->invoice->downloadPdf($invoice['id']);
        $notification = Notification::findByPk($this->objModel->nc_notification);

        if (null === $notification) {
            $this->sentryOrThrow('No notification is configured to send Harvest invoices (Registration module ID '.$this->objModel->id.')');
            return;
        }

        $tokens = [
            'admin_email' => Config::get('adminEmail'),
            'membership_label' => $this->translator->trans('membership.'.$member->membership),
            'invoice_number' => $invoice['number'],
            'invoice_issue_date' => Format::date(strtotime($invoice['issue_date'])),
            'invoice_due_date' => Format::date(strtotime($invoice['due_date'])),
            'invoice_amount' => number_format($invoice['amount'], 2, '.', "'"),
            'invoice_url' => $this->harvest->invoice->getClientUrl($invoice['client_key']),
            'invoice_pdf' => $pdf,
        ];

        StringUtil::flatten($member->row(), 'member', $tokens);

        $result = $notification->send($tokens);

        if (\in_array(false, $result, true)) {
            $this->sentryOrThrow('Unable to send Harvest invoice email to '.$member->email);
            return;
        }

        $this->harvest->api->invoices()->send($invoice['id']);
    }

    private function sentryOrThrow(string $message): void
    {
        if (null === captureMessage($message)) {
            throw new \RuntimeException($message);
        }
    }
}
