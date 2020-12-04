<?php

declare(strict_types=1);

namespace App;

use Terminal42\CashctrlApi\Api\PersonEndpoint;
use Contao\MemberModel;
use Terminal42\CashctrlApi\Entity\Person;
use Terminal42\CashctrlApi\Entity\PersonAddress;
use Terminal42\CashctrlApi\Entity\PersonContact;

class CashctrlApi
{
    public PersonEndpoint $person;
    private array $memberships;

    public function __construct(PersonEndpoint $person, array $memberships)
    {
        $this->person = $person;
        $this->memberships = $memberships;
    }

    public function syncMember(MemberModel $member)
    {
        if ($member->cashctrl_id) {
            $person = $this->person->read((int) $member->cashctrl_id);
            $this->updatePerson($person, $member);
            $this->person->update($person);
        } else {
            $person = new Person($member->company ?: null, $member->firstname, $member->lastname);
            $person->setCategoryId(1);
            $person->setSequenceNumberId(1000);
            $person->setNr('M-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT));
            $this->updatePerson($person, $member);
            $result = $this->person->create($person);

            $member->cashctrl_id = $result->insertId();
            $member->save();
        }
    }

    private function updatePerson(Person $person, MemberModel $member): void
    {
        $person->setIsInactive((bool) $member->disable);
        $person->setCompany($member->company);
        $person->setFirstName($member->firstname);
        $person->setLastName($member->lastname);
        $person->setTitleId($this->getTitleId($member->gender));
        $person->setLanguage($member->language);
        $person->setDateBirth($member->dateOfBirth ? date('Y-m-d', (int) $member->dateOfBirth) : '');

        $person->setCustomfield(1, $this->getMembership($member->membership));
        $person->setCustomfield(2, date('Y-m-d', (int) $member->dateAdded));
        $person->setCustomfield(3, $member->stop ? date('Y-m-d', (int) $member->stop) : '');

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

    private function getMembership(string $membership): ?string
    {
        if (!isset($this->memberships[$membership])) {
            return null;
        }

        return $this->memberships[$membership]['type'];
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
