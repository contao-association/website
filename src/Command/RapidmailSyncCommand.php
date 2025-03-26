<?php

declare(strict_types=1);

namespace App\Command;

use App\RapidmailHelper;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\System;
use Rapidmail\ApiClient\Exception\ApiException;
use Rapidmail\ApiClient\Service\Response\HalResponse;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:rapidmail:sync', 'Updates recipient list in Rapidmail.')]
class RapidmailSyncCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RapidmailHelper $rapidmail,
    ) {
        parent::__construct();
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return $this->rapidmail->isConfigured();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $members = $this->getMembers();
        $recipients = $this->getRecipients();
        $recipientForeignIds = array_column($recipients, 'foreign_id', 'id');

        $io = new SymfonyStyle($input, $output);

        $recipientsWithoutModel = array_diff(
            $recipientForeignIds,
            array_keys($members),
        );

        if ([] !== $recipientsWithoutModel) {
            $io->writeln('Deleting recipients without matching member');
            $io->progressStart(\count($recipientsWithoutModel));

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

                $io->progressAdvance();
                ++$throttle;
            }

            $io->progressFinish();
        }

        if ([] !== $members) {
            $io->writeln('Updating members');
            $io->progressStart(\count($members));

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
                        $exception->getMessage(),
                    ]);
                }

                $io->progressAdvance();
                ++$throttle;
            }

            $io->progressFinish();
        }

        $io->success('Synchronization complete.');

        return Command::SUCCESS;
    }

    /**
     * @return array<MemberModel>
     */
    private function getMembers(): array
    {
        $this->framework->initialize();
        System::loadLanguageFile('default');

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
