<?php

declare(strict_types=1);

namespace App;

use Contao\MemberModel;
use Contao\StringUtil;
use Rapidmail\ApiClient\Client;
use Rapidmail\ApiClient\Exception\ApiException;
use Rapidmail\ApiClient\Service\V1\Api\Recipients\Recipient\Parameter\RecipientCreateParam;
use Rapidmail\ApiClient\Service\V1\Api\Recipients\Recipient\RecipientService;

class RapidmailHelper
{
    private Client $client;
    private int $recipientlistId;

    private ?RecipientService $recipients = null;

    public function __construct(string $username, string $password, string $recipientlistId)
    {
        $this->client = new Client($username, $password);
        $this->recipientlistId = (int) $recipientlistId;
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
        if ($this->isMemberDisabled($member)) {
            return;
        }

        $data = $this->compileRecipientData($member)
            ->setRecipientlistId($this->recipientlistId)
            ->setForeignId($member->id)
            ->setActivated(new \DateTime())
            ->setAttribute('status', 'active')
        ;

        $this->recipients()->create($data);
    }

    /**
     * @throws ApiException
     */
    public function updateRecipient(array $recipient, MemberModel $member)
    {
        if ($this->isMemberDisabled($member)) {
            $this->recipients()->delete($recipient['id']);
            return;
        }

        $this->recipients()->update(
            $recipient['id'],
            $this->compileRecipientData($member)
        );
    }

    private function compileRecipientData(MemberModel $member): RecipientCreateParam
    {
        $data = $this->recipients()->params()->newCreateParam();

        $data
            ->setFirstname($member->firstname)
            ->setLastname($member->lastname)
            ->setEmail($member->email)
            ->setAttribute('extra1', (string) StringUtil::deserialize($member->groups, true)[0])
        ;

        return $data;
    }

    private function isMemberDisabled(MemberModel $member): bool
    {
        return $member->disable
            || ($member->start && $member->start > time())
            || ($member->stop && $member->stop <= time());
    }
}
