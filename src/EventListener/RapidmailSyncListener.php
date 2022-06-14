<?php

declare(strict_types=1);

namespace App\EventListener;

use App\RapidmailHelper;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\MemberModel;
use Rapidmail\ApiClient\Exception\ApiException;

/**
 * @Callback(table="tl_member", target="config.oncreate_version")
 * @Callback(table="tl_member", target="config.onrestore_version")
 */
class RapidmailSyncListener
{
    private RapidmailHelper $rapidmail;

    public function __construct(RapidmailHelper $rapidmail)
    {
        $this->rapidmail = $rapidmail;
    }

    /**
     * @param int|string $memberId
     */
    public function __invoke(string $table, $memberId)
    {
        if ('tl_member' !== $table) {
            throw new \InvalidArgumentException("Invalid call to sync table \"$table\" with Rapidmail.");
        }

        if (!$this->rapidmail->isConfigured()) {
            return;
        }

        $member = MemberModel::findByPk($memberId);

        if (null === $member) {
            throw new \InvalidArgumentException("Member ID \"$memberId\" was not found.");
        }

        $queryParams = $this->rapidmail
            ->recipients()
            ->params()
            ->newQueryParam()
            ->setRecipientlistId($this->rapidmail->getRecipientlistId())
            ->setForeignId((int) $member->id)
        ;

        try {
            $recipients = $this->rapidmail->recipients()->query($queryParams);
        } catch (ApiException $e) {
            if (!str_contains($e->getMessage(), 'API error 404')) {
                throw $e;
            }

            $recipients = false;
        }

        if (false === $recipients || 0 === $recipients->count()) {
            $this->rapidmail->createRecipient($member);
        } elseif (1 === $recipients->count()) {
            $recipients->rewind();
            $this->rapidmail->updateRecipient($recipients->current()->toArray(), $member);
        } else {
            throw new \RuntimeException('Cannot handle more than one recipient for a Contao member.');
        }
    }
}
