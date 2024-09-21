<?php

declare(strict_types=1);

namespace App\EventListener;

use App\RapidmailHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\MemberModel;
use Rapidmail\ApiClient\Exception\ApiException;

#[AsCallback(table: 'tl_member', target: 'config.oncreate_version')]
#[AsCallback(table: 'tl_member', target: 'config.onrestore_version')]
class RapidmailSyncListener
{
    public function __construct(private readonly RapidmailHelper $rapidmail)
    {
    }

    /**
     * @param int|string $memberId
     */
    public function __invoke(string $table, $memberId): void
    {
        if ('tl_member' !== $table) {
            throw new \InvalidArgumentException(\sprintf('Invalid call to sync table "%s" with Rapidmail.', $table));
        }

        if (!$this->rapidmail->isConfigured()) {
            return;
        }

        $member = MemberModel::findById($memberId);

        if (null === $member) {
            throw new \InvalidArgumentException(\sprintf('Member ID "%s" was not found.', $memberId));
        }

        $queryParams = $this->rapidmail
            ->recipients()
            ->params()
            ->newQueryParam()
            ->setRecipientlistId($this->rapidmail->getRecipientlistId())
            ->setForeignId((string) $member->id)
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
