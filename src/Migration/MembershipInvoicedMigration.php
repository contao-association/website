<?php

declare(strict_types=1);

namespace App\Migration;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Doctrine\DBAL\Connection;

class MembershipInvoicedMigration extends AbstractMigration
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

        return isset($columns['membership_start'], $columns['membership_stop']) && !isset($columns['membership_invoiced']);
    }

    public function run(): MigrationResult
    {
        $this->connection->executeStatement("ALTER TABLE tl_member ADD COLUMN `membership_invoiced` varchar(10) NOT NULL default ''");

        $members = $this->connection->executeQuery("SELECT id, membership_start, membership_stop FROM tl_member WHERE membership_start!=''");

        foreach ($members->iterateAssociative() as $member) {
            $invoiced = null;

            if ($member['membership_stop']) {
                $invoiced = (new \DateTime())->setTimestamp((int) $member['membership_stop']);
            }

            if (!$invoiced) {
                $invoiced = (new \DateTime())->setTimestamp((int) $member['membership_start'])->setTime(0, 0);

                $now = time();
                while ($now > $invoiced->getTimestamp()) {
                    $invoiced->add(new \DateInterval('P1Y'));
                }

                $invoiced->sub(new \DateInterval('P1D'));
            }

            $this->connection->update(
                'tl_member',
                ['membership_invoiced' => $invoiced->getTimestamp()],
                ['id' => $member['id']]
            );
        }

        return $this->createResult(true);
    }
}
