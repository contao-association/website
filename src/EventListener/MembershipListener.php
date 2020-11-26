<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Input;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

class MembershipListener
{
    private Connection $connection;
    private TranslatorInterface $translator;
    private array $memberships;

    public function __construct(Connection $connection, TranslatorInterface $translator, array $memberships)
    {
        $this->connection = $connection;
        $this->translator = $translator;
        $this->memberships = $memberships;
    }

    /**
     * @Callback(table="tl_member", target="fields.membership.options")
     */
    public function getMembershipOptions()
    {
        $options = [];

        foreach ($this->memberships as $membership => $config) {
            $options[$membership] = $this->translator->trans('membership.'.$membership);
        }

        return $options;
    }

    /**
     * @Callback(table="tl_member", target="config.onsubmit")
     */
    public function updateGroup(DataContainer $dc): void
    {
        if ('edit' !== Input::get('act')) {
            return;
        }

        if (!isset($this->memberships[$dc->activeRecord->membership])) {
            return;
        }

        $membership = $this->memberships[$dc->activeRecord->membership];

        $this->connection->update(
            'tl_member',
            ['groups' => serialize([$membership['group']])],
            ['id' => $dc->id]
        );
    }
}
