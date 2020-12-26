<?php

declare(strict_types=1);

namespace App;

use Terminal42\CashctrlApi\Api\PersonEndpoint;
use Contao\MemberModel;
use Terminal42\CashctrlApi\Entity\Person;
use Terminal42\CashctrlApi\Entity\PersonAddress;
use Terminal42\CashctrlApi\Entity\PersonContact;
use Terminal42\CashctrlApi\Entity\Order;
use Terminal42\CashctrlApi\Entity\OrderItem;
use Terminal42\CashctrlApi\Api\OrderEndpoint;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\CashctrlApi\Api\OrderDocumentEndpoint;
use Symfony\Component\Filesystem\Filesystem;
use Terminal42\CashctrlApi\Api\Filter\ListFilter;

class CashctrlApi
{
    public PersonEndpoint $person;
    public OrderEndpoint $order;
    public OrderDocumentEndpoint $orderDocument;
    private TranslatorInterface $translator;
    private Filesystem $filesystem;
    private array $memberships;
    private string $projectDir;

    public function __construct(PersonEndpoint $person, OrderEndpoint $order, OrderDocumentEndpoint $orderDocument, TranslatorInterface $translator, Filesystem $filesystem, array $memberships, string $projectDir)
    {
        $this->person = $person;
        $this->order = $order;
        $this->orderDocument = $orderDocument;
        $this->translator = $translator;
        $this->filesystem = $filesystem;
        $this->memberships = $memberships;
        $this->projectDir = $projectDir;
    }

    public function syncMember(MemberModel $member): void
    {
        if ($member->cashctrl_id) {
            $person = $this->person->read((int) $member->cashctrl_id);
        }

        if (null === $person) {
            $person = new Person($member->company ?: null, $member->firstname, $member->lastname);
            $person->setSequenceNumberId(1000);
        }

        $this->updatePerson($person, $member);

        if (null !== $person->getId()) {
            $this->person->update($person);
            return;
        }

        $result = $this->person->create($person);

        $member->cashctrl_id = $result->insertId();
        $member->save();
    }

    public function createMemberInvoice(MemberModel $member): Order
    {
        $membership = $this->memberships[$member->membership];

        $order = new Order((int) $member->cashctrl_id, 4);
        $order->setNr($member->id.'/'.date('Y'));
        $order->setDueDays(30);
        $order->addItem(new OrderItem(
            $membership['accountId'],
            $this->translator->trans('invoice_description', ['{membership}' => $this->translator->trans('membership.'.$member->membership)]),
            (float) ($membership['custom'] ? $member->membership_amount : $membership['price'])
        ));

        $insertId = $this->order->create($order)->insertId();

        return $this->order->read($insertId);
    }

    public function downloadInvoice(Order $invoice, int $templateId = null, string $language = null): string
    {
        if (null === $language && isset($GLOBALS['TL_LANGUAGE'])) {
            $language = (string) $GLOBALS['TL_LANGUAGE'];
        }

        if (null !== $templateId) {
            $this->orderDocument->update($invoice->getId(), ['templateId' => $templateId]);
        }

        return $this->orderDocument->downloadPdf([$invoice->getId()], $language);
    }

    public function archiveInvoice(Order $invoice, int $templateId = null, string $language = null): string
    {
        $year = $invoice->getDate()->format('Y');
        $quarter = ceil($invoice->getDate()->format('n') / 4);

        $name = str_replace('/', '-', $invoice->getNr());
        $targetFile = 'var/invoices/'.$year.'/Q'.$quarter.'/'.$name.'.pdf';
        $this->filesystem->dumpFile(
            $this->projectDir.'/'.$targetFile,
            $this->downloadInvoice($invoice, $templateId, $language)
        );

        return $targetFile;
    }

    public function markInvoiceSent(int $invoiceId): void
    {
        $this->order->updateStatus($invoiceId, 16);
    }

    public function listInvoices(MemberModel $member): array
    {
        if (!$member->cashctrl_id) {
            return [];
        }

        return $this->order->list()->filter('associateId', $member->cashctrl_id, ListFilter::EQUALS)->get();
    }

    private function updatePerson(Person $person, MemberModel $member): void
    {
        $person->setNr('M-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT));
        $person->setCategoryId($this->getCategoryId($member->membership));
        $person->setIsInactive((bool) $member->disable);

        $person->setCompany($member->company);
        $person->setFirstName($member->firstname);
        $person->setLastName($member->lastname);
        $person->setTitleId($this->getTitleId($member->gender));
        $person->setLanguage($member->language);
        $person->setDateBirth($member->dateOfBirth ? date('Y-m-d', (int) $member->dateOfBirth) : '');

        $person->setCustomfield(2, date('Y-m-d', (int) $member->dateAdded));
        $person->setCustomfield(3, $member->stop ? date('Y-m-d', (int) $member->stop) : '');
        $person->setCustomfield(4, $member->tax_id);

        $invoiceAddress = $this->findAddress($person, PersonAddress::TYPE_MAIN);
        $invoiceAddress->address = $member->street;
        $invoiceAddress->zip = $member->postal;
        $invoiceAddress->city = $member->city;
        $invoiceAddress->country = $member->country;

        $this->setContact($person, PersonContact::TYPE_EMAIL, PersonContact::PURPOSE_INVOICE, (string) $member->email);
        $this->setContact($person, PersonContact::TYPE_PHONE, PersonContact::PURPOSE_INVOICE, (string) $member->phone);
        $this->setContact($person, PersonContact::TYPE_MOBILE, PersonContact::PURPOSE_INVOICE, (string) $member->mobile);
        $this->setContact($person, PersonContact::TYPE_FAX, PersonContact::PURPOSE_INVOICE, (string) $member->fax);
        $this->setContact($person, PersonContact::TYPE_WEBSITE, PersonContact::PURPOSE_INVOICE, (string) $member->website);
    }

    private function findAddress(Person $person, string $type): PersonAddress
    {
        if (null !== ($addresses = $person->getAddresses())) {
            foreach ($addresses as $address) {
                if ($type === $address->type) {
                    return $address;
                }
            }
        }

        $address = new PersonAddress($type);
        $person->addAddress($address);

        return $address;
    }

    private function setContact(Person $person, string $type, string $purpose, string $address): void
    {
        if (null !== ($contacts = $person->getContacts())) {
            foreach ($contacts as $contact) {
                if ($type === $contact->type && $purpose === $contact->purpose) {
                    if (empty($address)) {
                        $person->removeContact($contact);
                    } else {
                        $contact->address = $address;
                    }
                    return;
                }
            }
        }

        if (!empty($address)) {
            $person->addContact(new PersonContact($address, $purpose, $type));
        }
    }

    private function getCategoryId(string $membership): int
    {
        if (!isset($this->memberships[$membership])) {
            return 1;
        }

        return (int) $this->memberships[$membership]['categoryId'];
    }

    private function getTitleId(string $gender): int
    {
        switch ($gender) {
            case 'male':
                return 1;

            case 'female':
                return 2;

            case 'other':
                return 5;
        }

        return 0;
    }
}
