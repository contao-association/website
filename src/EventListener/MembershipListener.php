<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Contao\FrontendUser;
use Contao\ModuleRegistration;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class MembershipListener
{
    private Connection $connection;
    private Security $security;
    private TranslatorInterface $translator;
    private array $memberships;

    public function __construct(Connection $connection, Security $security, TranslatorInterface $translator, array $memberships)
    {
        $this->connection = $connection;
        $this->security = $security;
        $this->translator = $translator;
        $this->memberships = $memberships;
    }

    /**
     * @Callback(table="tl_member", target="fields.membership.options")
     */
    public function getMembershipOptions($dc = null): array
    {
        $options = [];

        foreach ($this->memberships as $membership => $config) {
            if (!$dc instanceof DataContainer && ($config['legacy'] ?? false)) {
                continue;
            }

            $options[$membership] = $this->getMembershipLabel($membership);
        }

        return $options;
    }

    /**
     * @Hook("replaceInsertTags")
     *
     * @return false|string
     */
    public function replaceInsertTags(string $tag)
    {
        if ('subscription::label' !== $tag) {
            return false;
        }

        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser || !isset($this->memberships[$user->membership])) {
            return '';
        }

        return $this->getMembershipLabel($user->membership, $user->membership_amount);
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

    private function getMembershipLabel(string $membership, string $amount = null): string
    {
        $config = $this->memberships[$membership];

        $label = $this->translator->trans('membership.'.$membership).' ';

        if (null !== $amount && ($config['custom'] ?? false)) {
            $price = number_format((float) $amount, 2, '.', "'");
            $label .= $this->translator->trans('membership_year', ['{price}' => $price]);
        } else {
            $price = number_format($config['price'], 2, '.', "'");
            $label .= $this->translator->trans('membership_'.$config['type'], ['{price}' => $price]);
        }

        return $label;
    }
}
