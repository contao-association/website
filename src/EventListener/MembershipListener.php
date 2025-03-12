<?php

declare(strict_types=1);

namespace App\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use Contao\Date;
use Contao\FrontendUser;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class MembershipListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly array $memberships,
    ) {
    }

    /**
     * @param DataContainer|null $dc
     */
    #[AsCallback(table: 'tl_member', target: 'fields.membership.options')]
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

    #[AsHook('replaceInsertTags')]
    public function replaceInsertTags(string $tag): string|false
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
                return $this->getRenewDate($user);

            case 'payment':
                return $this->getPayment($user);

            case 'amount':
                return $this->formatAmount($user->membership);

            case 'title':
                return $this->translator->trans('membership.'.$user->membership);

            case 'label':
                return $this->getMembershipLabel($user->membership, $user);

            case 'member':
                $isActive = 'active' === $user->membership || (!($this->memberships[$user->membership]['invisible'] ?? false) && $user->membership_member);

                return $isActive ? 'Aktiviere die nachfolgende Checkbox, wenn du weiterhin Aktivmitglied bleiben möchtest.' : '';

            case '':
                if ('inactive' === $user->membership || (!empty($user->membership_stop) && $user->membership_stop <= time())) {
                    return 'Du hast kein aktives Abonnement der Contao Association.';
                }

                $title = $this->translator->trans('membership.'.$user->membership);
                $payment = $this->getPayment($user);
                $date = $this->getRenewDate($user);

                return "Du unterstützt Contao als <strong>$title</strong> mit <strong>$payment</strong>. Vielen Dank dafür! ❤️<br>"
                    .(empty($user->membership_stop) ? "Die nächste Rechnung erhältst du am $date." : "Dein Abonnement endet am $date, du erhältst keine weitere Rechnung von uns.");
        }

        return false;
    }

    #[AsCallback(table: 'tl_member', target: 'config.onload')]
    public function adjustDca(): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return;
        }

        $id = 'donation.year';
        $config = $this->memberships[$user->membership];

        if ('month' === $config['type'] && ($config['freeMember'] ?? false) && 'month' === $user->membership_interval) {
            $id = 'donation.month';
        }

        $GLOBALS['TL_DCA']['tl_member']['fields']['membership_amount']['label'] = [$this->translator->trans($id)];
    }

    #[AsCallback(table: 'tl_member', target: 'config.onsubmit')]
    public function updateMember(DataContainer|FrontendUser $data): void
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
                'groups' => serialize($groups),
            ],
            ['id' => $data->id],
        );
    }

    #[AsHook('parseTemplate')]
    public function addToMemberTemplate(Template $template): void
    {
        if (!str_starts_with($template->getName(), 'member_') || !($user = $this->security->getUser()) instanceof FrontendUser) {
            return;
        }

        $template->membershipConfig = $this->memberships[$user->membership] ?? [];
    }

    private function getMembershipLabel(string $membership, FrontendUser|null $user = null): string
    {
        return $this->translator->trans('membership.'.$membership).' '.$this->formatAmount($membership, $user);
    }

    private function formatAmount(string $membership, FrontendUser|null $user = null): string
    {
        $config = $this->memberships[$membership];

        if (!isset($config['price'])) {
            return '';
        }

        if ($user) {
            return $this->getPayment($user, 'membership');
        }

        $price = number_format($config['price'], 2, '.', "'");

        return $this->translator->trans('membership_'.$config['type'], ['{price}' => $price]);
    }

    private function getPayment(FrontendUser $user, string $label = 'payment'): string
    {
        $config = $this->memberships[$user->membership];
        $transId = $label.'_year';
        $price = $config['price'];

        if ('month' === $config['type']) {
            if (($config['freeMember'] ?? false) && 'month' === $user->membership_interval) {
                $transId = $label.'_month';
            } else {
                $price = 12 * $config['price'];
            }
        }

        if ($user->membership_amount > 0) {
            $price += $user->membership_amount;
        }

        return $this->translator->trans($transId, ['{price}' => number_format((float) $price, 2, '.', "'")]);
    }

    private function getRenewDate(FrontendUser $user): string
    {
        $date = \DateTime::createFromFormat('U', $user->membership_invoiced);
        $date->add(new \DateInterval('P1D'));

        return Date::parse('d. F Y', (int) $date->format('U'));
    }
}
