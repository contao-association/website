<?php

declare(strict_types=1);

namespace App\Harvest;

use Contao\MemberModel;
use Contao\System;
use Required\Harvest\Client;

class Harvest
{
    private Client $api;
    private array $memberships;

    private ?array $clientLookupTable = null;

    public function __construct(Client $api, array $memberships)
    {
        $this->api = $api;
        $this->memberships = $memberships;
    }

    public function findClientId(MemberModel $member): ?int
    {
        $map = $this->getClientLookupTable();
        $name = $this->generateClientName($member);

        return array_search($name, $map, true) ?: null;
    }

    public function createOrUpdateClient(MemberModel $member, int $clientId): int
    {
        $data = $this->generateClientData($member);

        if ($clientId) {
            $this->api->clients()->update($clientId, $data);
        } else {
            $response = $this->api->clients()->create($data);
            $clientId = $response['id'];
        }

        return $clientId;
    }

    public function createOrUpdateContact(MemberModel $member, int $contactId): int
    {
        $data = $this->generateContactData($member);

        if ($contactId) {
            $this->api->contacts()->update($contactId, $data);
        } else {
            $response = $this->api->contacts()->create($data);
            $contactId = $response['id'];
        }

        return $contactId;
    }

    public function generateClientName(MemberModel $member, string $membership = null): string
    {
        $membership = $membership ?: $member->membership;

        if (!isset($this->memberships[$membership])) {
            throw new \RuntimeException("Membership \"$membership\" not found");
        }

        if ($this->memberships[$membership]['company'] && $member->company) {
            return htmlspecialchars($member->company);
        }

        return htmlspecialchars($member->firstname.' '.$member->lastname);
    }

    private function getClientLookupTable(): array
    {
        if (null === $this->clientLookupTable) {
            $this->clientLookupTable = [];
            $clients = $this->api->clients()->all();

            foreach ($clients as $client) {
                $this->clientLookupTable[(int) $client['id']] = (string) $client['name'];
            }
        }

        return $this->clientLookupTable;
    }

    private function generateClientData(MemberModel $member): array
    {
        $countryNames = System::getCountries();
        $membership = $this->memberships[$member->membership]['company'];

        $data = [
            'name' => ampersand($this->generateClientName($member)),
            'address' => sprintf(
                "%s\n%s %s%s",
                $member->street,
                $member->postal,
                $member->city,
                'ch' === $member->country ? '' : ("\n".$countryNames[$member->country])
            ),
            'currency' => 'EUR'
        ];

        if ($member->company) {
            if ($membership['company']) {
                $data['address'] = $member->firstname.' '.$member->lastname."\n".$data['address'];
            } else {
                $data['address'] = $member->company."\n".$data['address'];
            }
        }

        return $data;
    }

    private function generateContactData(MemberModel $member): array
    {
        return [
            'client_id' => $member->harvest_client_id,
            'first_name' => $member->firstname,
            'last_name' => $member->lastname,
            'email' => $member->email,
            'phone_office' => $member->phone,
            'phone_mobile' => $member->mobile,
            'fax' => $member->fax,
        ];
    }
}
