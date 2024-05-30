<?php

declare(strict_types=1);

namespace App\Cron;

use App\RapidmailHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Oneup\ContaoSentryBundle\ErrorHandlingTrait;
use Rapidmail\ApiClient\Exception\ApiException;
use Rapidmail\ApiClient\Service\Response\HalResponse;

#[AsCronJob('weekly')]
class RapidmailSyncCron
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RapidmailHelper $rapidmail,
    ) {
    }

    public function __invoke(): void
    {
        $this->sentryCheckIn();

        $members = $this->getMembers();
        $recipients = $this->getRecipients();
        $recipientForeignIds = array_column($recipients, 'foreign_id', 'id');

        $recipientsWithoutModel = array_diff(
            $recipientForeignIds,
            array_keys($members),
        );

        if ([] !== $recipientsWithoutModel) {
            // Prevent API rate limit
            sleep(1);

            $throttle = 0;

            foreach (array_keys($recipientsWithoutModel) as $recipientId) {
                if ($throttle > 8) {
                    // Prevent API rate limit
                    sleep(1);
                    $throttle = 0;
                }

                $this->rapidmail->recipients()->delete($recipientId);

                ++$throttle;
            }
        }

        if ([] !== $members) {
            // Prevent API rate limit
            sleep(1);

            $throttle = 0;

            foreach ($members as $member) {
                if ($throttle > 8) {
                    // Prevent API rate limit
                    sleep(1);
                    $throttle = 0;
                }

                $recipientId = array_search($member->id, $recipientForeignIds, false);

                try {
                    if (false === $recipientId) {
                        $this->rapidmail->createRecipient($member);
                    } else {
                        $this->rapidmail->updateRecipient($recipients[$recipientId], $member);
                    }
                } catch (ApiException $exception) {
                    $this->sentryOrThrow('RapidMail error synchronizing member ID '.$member->id, $exception);
                }

                ++$throttle;
            }
        }

        $this->sentryCheckIn(true);
    }

    /**
     * @return array<MemberModel>
     */
    private function getMembers(): array
    {
        $this->framework->initialize();

        $collection = MemberModel::findAll();

        if (null === $collection) {
            return [];
        }

        $result = [];

        foreach ($collection as $model) {
            $result[$model->id] = $model;
        }

        return $result;
    }

    private function getRecipients(): array
    {
        $result = [];

        $queryParams = $this->rapidmail
            ->recipients()
            ->params()
            ->newQueryParam()
            ->setRecipientlistId($this->rapidmail->getRecipientlistId())
        ;

        foreach ($this->rapidmail->recipients()->query($queryParams) as $item) {
            if ($item instanceof HalResponse) {
                $result[$item['id']] = $item->toArray();
            }
        }

        return $result;
    }
}
