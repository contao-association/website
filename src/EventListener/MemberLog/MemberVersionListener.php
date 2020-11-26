<?php

declare(strict_types=1);

namespace App\EventListener\MemberLog;

use Contao\BackendUser;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Input;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Security;

class MemberVersionListener
{
    private Connection $connection;
    private Security $security;

    public function __construct(Connection $connection, Security $security)
    {
        $this->connection = $connection;
        $this->security = $security;
    }

    /**
     * @Callback(table="tl_member", target="config.oncreate_version")
     */
    public function onCreate(string $table, $memberId, $versionNumber, array $newData): void
    {
        $memberId = (int) $memberId;
        $versionNumber = (int) $versionNumber;

        if ('tl_member' !== $table) {
            return;
        }

        if (1 === (int) $versionNumber) {
            $this->handleRegistration($memberId, $newData);
            return;
        }

        $this->handleVersion(
            $this->connection->fetchOne(
                "SELECT data FROM tl_version WHERE fromTable='tl_member' AND pid=? AND version<? ORDER BY version DESC LIMIT 1",
                [$memberId, $versionNumber]
            ),
            $memberId,
            $newData
        );
    }

    /**
     * @Callback(table="tl_member", target="config.onrestore_version")
     */
    public function onRestore(string $table, $memberId, $versionNumber, array $newData): void
    {
        $memberId = (int) $memberId;
        $versionNumber = (int) $versionNumber;

        if ('tl_member' !== $table) {
            return;
        }

        $this->handleVersion(
            $this->connection->fetchOne(
                "SELECT data FROM tl_version WHERE fromTable='tl_member' AND pid=? AND version!=? ORDER BY version DESC LIMIT 1",
                [$memberId, $versionNumber]
            ),
            $memberId,
            $newData
        );
    }

    private function handleRegistration(int $memberId, array $data): void
    {
        $registrationCount = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM tl_member_log WHERE pid=? AND type='registration'",
            [$memberId]
        );

        if ($registrationCount > 0) {
            return;
        }

        $this->connection->insert(
            'tl_member_log',
            [
                'pid' => $memberId,
                'tstamp' => $data['dateAdded'],
                'dateAdded' => $data['dateAdded'],
                'type' => 'registration',
                'data' => $data['dateAdded']
            ]
        );
    }

    private function handleVersion($versionData, int $memberId, array $newData): void
    {
        if (false === $versionData) {
            return;
        }

        $oldData = StringUtil::deserialize($versionData);

        if (!\is_array($oldData)) {
            return;
        }

        $this->storeDiff($oldData, $newData, $memberId);
    }

    private function storeDiff(array $oldData, array $newData, int $memberId)
    {
        $diff = [];

        foreach ($oldData as $k => $v) {
            if ('tstamp' === $k || 'password' === $k) {
                continue;
            }

            if ($newData[$k] !== $v) {
                $diff[$k] = [
                    'new' => $newData[$k],
                    'old' => $v
                ];
            }
        }

        $text = Input::post('member_log_note');

        if (!empty($diff)) {
            $type = 'personal_data';
            $data = serialize($diff);
        } elseif (!empty($text)) {
            $type = 'note';
            $data = null;
        } else {
            return;
        }

        $user = $this->security->getUser();
        $userId = $user instanceof BackendUser ? $user->id : 0;

        $arrSet = [
            'pid' => $memberId,
            'tstamp' => time(),
            'dateAdded' => time(),
            'user' => $userId,
            'type' => $type,
            'data' => $data,
            'text' => $text,
        ];

        $this->connection->insert('tl_member_log', $arrSet);
    }
}
