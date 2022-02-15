<?php

declare(strict_types=1);

namespace App\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

class MembershipDateMigration extends AbstractMigration
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager || !$schemaManager->tablesExist('tl_member')) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_member');

        return isset($columns['dateadded'], $columns['stop']) && !isset($columns['membership_start']) && !isset($columns['membership_stop']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement("
            ALTER TABLE tl_member
                ADD COLUMN `membership_start` varchar(10) NOT NULL default '',
                ADD COLUMN `membership_stop` varchar(10) NOT NULL default ''");

        $this->connection->executeStatement("UPDATE tl_member SET membership_start=dateAdded, membership_stop=stop");

        return $this->createResult(true);
    }
}
