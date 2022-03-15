<?php

declare(strict_types=1);

namespace App\Command;

use App\RapidmailHelper;
use Rapidmail\ApiClient\Exception\ApiException;
use Rapidmail\ApiClient\Service\Response\HalResponse;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;

class RapidmailSyncCommand extends Command
{
    protected static $defaultName = 'app:rapidmail:sync';

    private ContaoFramework $framework;
    private RapidmailHelper $rapidmail;

    public function __construct(ContaoFramework $framework, RapidmailHelper $rapidmail)
    {
        parent::__construct();

        $this->framework = $framework;
        $this->rapidmail = $rapidmail;
    }

    public function isEnabled(): bool
    {
        return $this->rapidmail->isConfigured();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Updates recipient list in Rapidmail.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $members = $this->getMembers();
        $recipients = $this->getRecipients();
        $recipientForeignIds = array_column($recipients, 'foreign_id', 'id');

        $io = new SymfonyStyle($input, $output);

        $recipientsWithoutModel = array_diff(
            $recipientForeignIds,
            array_keys($members)
        );

        if (!empty($recipientsWithoutModel)) {
            $io->writeln('Deleting recipients without matching member');
            $io->progressStart(count($recipientsWithoutModel));

            // Prevent API rate limit
            sleep(1);

            $throttle = 0;
            foreach ($recipientsWithoutModel as $recipientId => $foreignId) {
                if ($throttle > 8) {
                    // Prevent API rate limit
                    sleep(1);
                    $throttle = 0;
                }

                $this->rapidmail->recipients()->delete($recipientId);

                $io->progressAdvance();
                ++$throttle;
            }

            $io->progressFinish();
        }

        if (!empty($members)) {
            $io->writeln('Updating members');
            $io->progressStart(count($members));

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
                    $io->error([
                        'Error synchronizing member ID '.$member->id,
                        $exception->getMessage()
                    ]);
                }

                $io->progressAdvance();
                ++$throttle;
            }

            $io->progressFinish();
        }

        $io->success('Synchronization complete.');

        return 0;
    }

    /**
     * @return MemberModel[]
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
