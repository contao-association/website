<?php

declare(strict_types=1);

namespace App\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

class HarvestMigration extends AbstractMigration
{
    private Connection $connection;

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

        return isset($columns['harvest_id']) && !isset($columns['harvest_contact_id']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement("
            ALTER TABLE tl_member 
            CHANGE COLUMN `harvest_id` `harvest_contact_id` int(10) unsigned NOT NULL default '0'
        ");

        return $this->createResult(true);
    }
}
