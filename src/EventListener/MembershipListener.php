<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\DataContainer;
use Contao\Date;
use Contao\FrontendUser;
use Contao\ModuleRegistration;
use Contao\StringUtil;
use Contao\Template;
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
            if (!$dc instanceof DataContainer && ($config['invisible'] ?? false)) {
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
        $t = explode('::', $tag);

        if ('subscription' !== $t[0]) {
            return false;
        }

        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser || !isset($this->memberships[$user->membership])) {
            return '';
        }

        switch ($t[1] ?? null) {
            case 'renew':
                $date = \DateTime::createFromFormat('U', $user->membership_invoiced);
                $date->add(new \DateInterval('P1D'));

                return Date::parse('d. F Y', $date->format('U'));

            case 'payment':
                $config = $this->memberships[$user->membership];
                $transId = 'payment_yearly';
                $price = $config['price'];

                if ($config['custom'] ?? false) {
                    $price = $user->membership_amount;
                } elseif ('month' === $config['type']) {
                    if (($config['freeMember'] ?? false) && 'month' === $user->membership_interval) {
                        $transId = 'payment_monthly';
                    } else {
                        $price = 12 * $config['price'];
                    }
                }

                return $this->translator->trans($transId, ['{amount}' => number_format((float) $price, 2, '.', "'")]);

            case 'amount':
                return $this->formatAmount($user->membership, (string) $user->membership_amount);

            case 'title':
                return $this->translator->trans('membership.'.$user->membership);

            case 'label':
                return $this->getMembershipLabel($user->membership, (string) $user->membership_amount);
        }

        return '';
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
        $activeMember = $data instanceof DataContainer ? $data->activeRecord->membership_member : $data->membership_member;

        $groups = [];

        if ($this->memberships[$level]['group'] ?? null) {
            $groups[] = $this->memberships[$level]['group'];
        }

        if ($activeMember && 'active' !== $level && !($this->memberships[$level]['invisible'] ?? false)) {
            $groups[] = $this->memberships['active']['group'];
        }

        $this->connection->update(
            'tl_member',
            [
                'username' => $email,
                'groups' => serialize($groups)
            ],
            ['id' => $data->id]
        );
    }

    /**
     * @Hook("parseTemplate")
     */
    public function addToMemberTemplate(Template $template): void
    {
        if (0 !== strpos($template->getName(), 'member_') || !($user = $this->security->getUser()) instanceof FrontendUser) {
            return;
        }

        $template->membershipConfig = $this->memberships[$user->membership] ?? [];
    }

    private function getMembershipLabel(string $membership, string $amount = null): string
    {
        return $this->translator->trans('membership.'.$membership).' '.$this->formatAmount($membership, $amount);
    }

    private function formatAmount(string $membership, string $amount = null): string
    {
        $config = $this->memberships[$membership];

        if (null !== $amount && ($config['custom'] ?? false)) {
            $price = number_format((float) $amount, 2, '.', "'");

            return $this->translator->trans('membership_year', ['{price}' => $price]);
        }

        $price = number_format($config['price'], 2, '.', "'");

        return $this->translator->trans('membership_'.$config['type'], ['{price}' => $price]);
    }
}
