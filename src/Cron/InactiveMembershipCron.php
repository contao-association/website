<?php

declare(strict_types=1);

namespace App\Cron;

use App\ErrorHandlingTrait;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Versions;
use Doctrine\DBAL\Connection;

#[AsCronJob('daily')]
class InactiveMembershipCron
{
    use ErrorHandlingTrait;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        $ids = $this->connection->fetchFirstColumn("
            SELECT id
            FROM tl_member
            WHERE
                membership_stop != ''
                AND membership_stop < UNIX_TIMESTAMP()
                AND membership!='inactive'
        ");

        foreach ($ids as $id) {
            $this->connection->executeStatement("UPDATE tl_member SET membership='inactive', stop='', disable='' WHERE id=?", [$id]);

            $version = new Versions('tl_member', $id);
            $version->setEditUrl("contao/main.php?do=member&act=edit&id=$id");

            $version->create(true);
        }
    }
}
