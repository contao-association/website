<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\MemberModel;
use App\CashctrlHelper;

/**
 * @Callback(table="tl_member", target="config.oncreate_version")
 * @Callback(table="tl_member", target="config.onrestore_version")
 */
class CashctrlSyncListener
{
    private CashctrlHelper $cashctrl;

    public function __construct(CashctrlHelper $cashctrl)
    {
        $this->cashctrl = $cashctrl;
    }

    /**
     * @param int|string $memberId
     */
    public function __invoke(string $table, $memberId): void
    {
        if ('tl_member' !== $table) {
            throw new \InvalidArgumentException("Invalid call to sync table \"$table\" with Cashctrl.");
        }

        $member = MemberModel::findByPk($memberId);

        if (null === $member) {
            throw new \InvalidArgumentException("Member ID \"$memberId\" was not found.");
        }

        $this->cashctrl->syncMember($member);
    }
}
