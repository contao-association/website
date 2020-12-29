<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\FrontendUser;
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
    public function getMembershipOptions(): array
    {
        $options = [];

        foreach ($this->memberships as $membership => $config) {
            $label = $this->translator->trans('membership.'.$membership).' ';
            $price = number_format($config['price'], 2, '.', "'");

            if ($config['custom']) {
                $label .= $this->translator->trans('membership_custom', ['{price}' => $price]);
            } else {
                $label .= $this->translator->trans('membership_fixed', ['{price}' => $price]);
            }

            $options[$membership] = $label;
        }

        return $options;
    }

    /**
     * @Callback(table="tl_member", target="config.onsubmit")
     *
     * @param FrontendUser|DataContainer
     */
    public function updateMember($data): void
    {
        $email = $data instanceof DataContainer ? $data->activeRecord->email : $data->email;
        $level = $data instanceof DataContainer ? $data->activeRecord->membership : $data->membership;

        $this->connection->update(
            'tl_member',
            [
                'username' => $email,
                'groups' => serialize([$this->memberships[$level]['group'] ?? 0])
            ],
            ['id' => $data->id]
        );
    }
}
