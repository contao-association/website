<?php

declare(strict_types=1);

namespace App\EventListener;

use App\CashctrlHelper;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\MemberModel;

#[AsCallback(table: 'tl_member', target: 'config.oncreate_version')]
#[AsCallback(table: 'tl_member', target: 'config.onrestore_version')]
class CashctrlSyncListener
{
    public function __construct(private readonly CashctrlHelper $cashctrl)
    {
    }

    /**
     * @param int|string $memberId
     */
    public function __invoke(string $table, $memberId): void
    {
        if ('tl_member' !== $table) {
            throw new \InvalidArgumentException(sprintf('Invalid call to sync table "%s" with Cashctrl.', $table));
        }

        $member = MemberModel::findById($memberId);

        if (null === $member) {
            throw new \InvalidArgumentException(sprintf('Member ID "%s" was not found.', $memberId));
        }

        $this->cashctrl->syncMember($member);
    }
}
