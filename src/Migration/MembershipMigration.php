<?php

declare(strict_types=1);

namespace App\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

class MembershipMigration extends AbstractMigration
{
    private Connection $connection;

    private static $membershipMap = [
        1 => 'active',
        2 => 'passive',
        3 => 'support',
    ];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->getSchemaManager();

        if (!$schemaManager || !$schemaManager->tablesExist('tl_member')) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_member');

        return isset($columns['harvest_membership'])
            && !isset($columns['membership'])
            && !isset($columns['membership_amount']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement("
            ALTER TABLE tl_member 
                ADD COLUMN `membership` varchar(16) NOT NULL default '', 
                ADD COLUMN `membership_amount` int(10) NOT NULL default '200'
        ");

        $members = $this->connection->fetchAllAssociative('SELECT id, harvest_membership FROM tl_member');

        foreach ($members as $row) {
            $data = StringUtil::deserialize($row['harvest_membership']);

            if (empty($data) || !\is_array($data) || !isset(self::$membershipMap[$data['membership']])) {
                continue;
            }

            $this->connection->update(
                'tl_member',
                [
                    'membership' => self::$membershipMap[$data['membership']],
                    'membership_amount' => $data['custom_2'],
                ],
                ['id' => $row['id']]
            );
        }

        return $this->createResult(true);
    }
}
