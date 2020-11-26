<?php

declare(strict_types=1);

namespace App\EventListener\MemberLog;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Haste\Util\Format;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Callback(table="tl_member_log", target="list.sorting.child_record")
 */
class MemberLogListingListener
{
    private Connection $connection;
    private TranslatorInterface $translator;

    public function __construct(Connection $connection, TranslatorInterface $translator)
    {
        $this->connection = $connection;
        $this->translator = $translator;
    }

    public function __invoke(array $row)
    {
        if ('note' === $row['type']) {
            return $this->generateLine(nl2br($row['text']), $row);
        }

        if ('registration' === $row['type']) {
            return $this->generateLine(Format::datim($row['data']), $row);
        }

        if ('personal_data' === $row['type']) {
            $data = StringUtil::deserialize($row['data'], true);
            $text = '';

            if ($row['text']) {
                $text .= '<p>'.nl2br($row['text']).'</p>';
            }

            $text .= '<table class="tl_listing">
<thead>
    <tr>
        <th class="tl_folder_tlist">'.$this->translator->trans('tl_member_log.label_personal_data_field', [], 'contao_tl_member_log').'</th>
        <th class="tl_folder_tlist">'.$this->translator->trans('tl_member_log.label_personal_data_old', [], 'contao_tl_member_log').'</th>
        <th class="tl_folder_tlist">'.$this->translator->trans('tl_member_log.label_personal_data_new', [], 'contao_tl_member_log').'</th>
    </tr>
</thead>
<tbody>';

            foreach ($data as $field => $difference) {
                if (!isset($GLOBALS['TL_DCA']['tl_member']['fields'][$field])) {
                    continue;
                }

                $text .= '<tr>
    <td class="tl_file_list">'.Format::dcaLabel('tl_member', $field).'</td>
    <td class="tl_file_list">'.Format::dcaValue('tl_member', $field, $difference['old']).'</td>
    <td class="tl_file_list">'.Format::dcaValue('tl_member', $field, $difference['new']).'</td>
</tr>';
            }

            $text .= '</tbody></table>';

            return $this->generateLine($text, $row);
        }

        throw new \RuntimeException(sprintf('Unknown log type "%s"', $row['type']));
    }

    private function generateLine(string $text, array $row): string
    {
        $type = $GLOBALS['TL_DCA']['tl_member_log']['fields']['type']['reference'][$row['type']];
        $dateAdded = Format::datim($row['dateAdded']);
        $user = '';

        if ($row['user']) {
            $userData = $this->connection->fetchAssociative('SELECT * FROM tl_user WHERE id=?', [$row['user']]);

            if (false === $userData) {
                $user = $this->translator->trans('tl_member_log.label_user_deleted', [$row['user']], 'contao_tl_member_log');
            } else {
                $user = $this->translator->trans('tl_member_log.label_user', [$userData['name'], $userData['id']], 'contao_tl_member_log');
            }
        }

        return '
<div class="cte_type"><span class="tl_green"><strong class="tl_green">'.$type.' - '.$dateAdded.'</strong>'.($user ? (' - '.$user) : '').'</span></div>
'.$text;
    }
}
