<?php

declare(strict_types=1);

namespace App\EventListener\Cashctrl;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\MemberModel;
use App\CashctrlApi;

class MemberSyncListener
{
    private CashctrlApi $api;

    public function __construct(CashctrlApi $api)
    {
        $this->api = $api;
    }

    /**
     * @Callback(table="tl_member", target="config.oncreate_version")
     * @Callback(table="tl_member", target="config.onrestore_version")
     *
     * @param int|string $memberId
     */
    public function updateClientAndContact(string $table, $memberId): void
    {
        if ('tl_member' !== $table) {
            throw new \InvalidArgumentException("Invalid call to sync table \"$table\" with Cashctrl.");
        }

        $member = MemberModel::findByPk($memberId);

        if (null === $member) {
            throw new \InvalidArgumentException("Member ID \"$memberId\" was not found.");
        }

        $this->api->syncMember($member);
    }
}
