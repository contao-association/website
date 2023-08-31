<?php

declare(strict_types=1);

namespace App;

use Contao\MemberModel;
use Rapidmail\ApiClient\Client;
use Rapidmail\ApiClient\Exception\ApiException;
use Rapidmail\ApiClient\Service\V1\Api\Recipients\Recipient\Parameter\RecipientCreateParam;
use Rapidmail\ApiClient\Service\V1\Api\Recipients\Recipient\RecipientService;

class RapidmailHelper
{
    private readonly Client $client;

    private readonly int $recipientlistId;

    private RecipientService|null $recipients = null;

    public function __construct(
        string $username,
        string $password,
        string $recipientlistId,
        private readonly array $memberships,
    ) {
        $this->client = new Client($username, $password);
        $this->recipientlistId = (int) $recipientlistId;
    }

    public function isConfigured(): bool
    {
        return !empty($this->recipientlistId);
    }

    public function recipients(): RecipientService
    {
        if (null === $this->recipients) {
            $this->recipients = $this->client->recipients();
        }

        return $this->recipients;
    }

    public function getRecipientlistId(): int
    {
        return $this->recipientlistId;
    }

    /**
     * @throws ApiException
     */
    public function createRecipient(MemberModel $member): void
    {
        // Do not create recipient for disabled members
        if (!$this->hasSubscription($member)) {
            return;
        }

        $data = $this->compileRecipientData($member)
            ->setRecipientlistId($this->recipientlistId)
            ->setForeignId((string) $member->id)
            ->setActivated(new \DateTime())
            ->setAttribute('status', 'active')
        ;

        $this->recipients()->create($data);
    }

    /**
     * @throws ApiException
     */
    public function updateRecipient(array $recipient, MemberModel $member): void
    {
        if (!$this->hasSubscription($member)) {
            $this->recipients()->delete($recipient['id']);

            return;
        }

        $this->recipients()->update(
            $recipient['id'],
            $this->compileRecipientData($member),
        );
    }

    private function compileRecipientData(MemberModel $member): RecipientCreateParam
    {
        $data = $this->recipients()->params()->newCreateParam();

        $data
            ->setFirstname($member->firstname)
            ->setLastname($member->lastname)
            ->setEmail($member->email)
            ->setAttribute('extra1', $member->membership)
            ->setAttribute('extra2', $this->isActiveMember($member) ? '1' : '0')
        ;

        return $data;
    }

    private function hasSubscription(MemberModel $member): bool
    {
        return !$member->disable
            && 'inactive' !== $member->membership
            && (!$member->start || $member->start <= time())
            && (!$member->stop || $member->stop > time())
            && (!$member->membership_start || $member->membership_start <= time())
            && (!$member->membership_stop || $member->membership_stop > time());
    }

    private function isActiveMember(MemberModel $member): bool
    {
        if ('active' === $member->membership) {
            return true;
        }

        if ($this->memberships[$member->membership]['invisible'] ?? false) {
            return false;
        }

        return (bool) $member->membership_member;
    }
}
