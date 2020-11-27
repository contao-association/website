<?php

declare(strict_types=1);

namespace App\EventListener\Harvest;

use App\Harvest\Harvest;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\MemberModel;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

class MemberSyncListener
{
    private Harvest $harvest;
    private Connection $connection;
    private TranslatorInterface $translator;

    public function __construct(Harvest $harvest, Connection $connection, TranslatorInterface $translator)
    {
        $this->harvest = $harvest;
        $this->connection = $connection;
        $this->translator = $translator;
    }

    /**
     * @Callback(table="tl_member", target="config.oncreate_version")
     * @Callback(table="tl_member", target="config.onrestore_version")
     */
    public function updateClientAndContact(string $table, $memberId): void
    {
        if ('tl_member' !== $table) {
            throw new \InvalidArgumentException("Invalid call to sync table \"$table\" with Harvest.");
        }

        $member = MemberModel::findByPk($memberId);

        if (null === $member) {
            throw new \InvalidArgumentException("Member ID \"$memberId\" was not found.");
        }

        $clientId = $this->findAndReuseClientId($member);
        $member->harvest_client_id = $this->harvest->createOrUpdateClient($member, $clientId);
        $member->harvest_contact_id = $this->harvest->createOrUpdateContact($member, (int) $member->harvest_contact_id);
        $member->save();
    }

    /**
     * @Callback(table="tl_member", target="fields.firstname.save")
     * @Callback(table="tl_member", target="fields.lastname.save")
     * @Callback(table="tl_member", target="fields.company.save")
     */
    public function preventDuplicateName($value, DataContainer $dc)
    {
        $model = new MemberModel();
        $model->setRow($dc->activeRecord->row());
        $model->preventSaving(false);

        try {
            $this->findAndReuseClientId($model);
        } catch (\OverflowException $e) {
            throw new \RuntimeException($this->translator->trans('harvest_client_exists'));
        }

        return $value;
    }

    private function findAndReuseClientId(MemberModel $member): int
    {
        $clientId = (int) $member->harvest_client_id;
        $existingId = $this->harvest->findClientId($member);

        if (null === $existingId || $existingId === $clientId) {
            return $clientId;
        }

        // Re-use an existing client ID if it does not already belong to a different member
        $duplicate = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_member WHERE harvest_client_id=? AND id!=?',
            [$existingId, $member->id]
        );

        if ($duplicate > 0) {
            throw new \OverflowException('A Harvest client with name "'.$this->harvest->generateClientName($member).'" already exists.');
        }

        return $existingId;
    }
}
