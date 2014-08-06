<?php

/**
 * member_log extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-member_log
 */

/**
 * Table tl_member_log
 */
$GLOBALS['TL_DCA']['tl_member_log'] = array
(

    // Config
    'config' => array
    (
        'dataContainer'               => 'Table',
        'ptable'                      => 'tl_member',
        'enableVersioning'            => true,
        'onload_callback' => array
        (
            array('tl_member_log', 'checkPermission')
        ),
        'onsubmit_callback' => array
        (
            array('tl_member_log', 'storeNoteData')
        ),
    ),

    // List
    'list' => array
    (
        'sorting' => array
        (
            'mode'                    => 4,
            'fields'                  => array('dateAdded DESC'),
            'headerFields'            => array('company', 'firstname', 'lastname', 'email'),
            'flag'                    => 8,
            'panelLayout'             => 'filter,search,limit',
            'child_record_callback'   => array('tl_member_log', 'generateLabel')
        ),
        'operations' => array
        (
            'edit' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_member_log']['edit'],
                'href'                => 'act=edit',
                'icon'                => 'edit.gif',
                'button_callback'     => array('tl_member_log', 'editButton')
            )
        )
    ),

    // Palettes
    'palettes' => array
    (
        'default'                     => '{text_legend},text'
    ),

    // Fields
    'fields' => array
    (
        'dateAdded' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_member_log']['dateAdded'],
            'flag'                    => 8,
        ),
        'user' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_member_log']['user'],
        ),
        'data' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_member_log']['data'],
        ),
        'type' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_member_log']['type'],
            'exclude'                 => true,
            'filter'                  => true,
            'options'                 => array('note', 'personal_data', 'registration'),
            'reference'               => &$GLOBALS['TL_LANG']['tl_member_log']['type'],
        ),
        'text' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_member_log']['text'],
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'textarea',
            'eval'                    => array('mandatory'=>true),
        )
    )
);


/**
 * Class tl_member_log
 *
 * Provide miscellaneous methods that are used by the data configuration array.
 */
class tl_member_log extends Backend
{

    /**
     * Check permissinos to edit table tl_member_log
     */
    public function checkPermission()
    {
        if ($this->Input->get('act') == '') {
            return;
        }

        // Allow to create
        if ($this->Input->get('act') == 'create') {
            return;
        }

        // Allow to edit but only notes
        if ($this->Input->get('act') == 'edit') {
            $objLog = $this->Database->prepare("SELECT id FROM tl_member_log WHERE id=? AND (type='' OR type='note')")
                                     ->limit(1)
                                     ->execute($this->Input->get('id'));

            if ($objLog->numRows) {
                return;
            }
        }

        $this->redirect('contao/main.php?act=error');
    }

    /**
     * Store the note data
     * @param DataContainer
     */
    public function storeNoteData(DataContainer $dc)
    {
        if (!$dc->dateAdded) {
            $this->Database->prepare("UPDATE tl_member_log SET dateAdded=? WHERE id=?")
                           ->execute(time(), $dc->id);
        }

        $this->Database->prepare("UPDATE tl_member_log SET type='note', user=? WHERE id=?")
                       ->execute(BackendUser::getInstance()->id, $dc->id);
    }

    /**
     * Generate the label and return it as HTML string
     * @param array
     * @return string
     */
    public function generateLabel($arrRow)
    {
        $strText = '';

        switch ($arrRow['type']) {
            case 'note':
                $strText = nl2br($arrRow['text']);
                break;

            case 'personal_data':
                $arrData = deserialize($arrRow['data'], true);
                $strText = '<table class="tl_listing">
<thead>
    <tr>
        <th class="tl_folder_tlist">' . $GLOBALS['TL_LANG']['tl_member_log']['label_personal_data_field'] . '</th>
        <th class="tl_folder_tlist">' . $GLOBALS['TL_LANG']['tl_member_log']['label_personal_data_old'] . '</th>
        <th class="tl_folder_tlist">' . $GLOBALS['TL_LANG']['tl_member_log']['label_personal_data_new'] . '</th>
    </tr>
</thead>
<tbody>';

                // Compute the difference
                foreach ($arrData as $field => $difference) {
                    if (!isset($GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'])) {
                        continue;
                    }

                    $strText .= '<tr>
    <td class="tl_file_list">' . $GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'][0] . '</td>
    <td class="tl_file_list">' . (($difference['old'] === '') ? '-' : $difference['old']) . '</td>
    <td class="tl_file_list">' . (($difference['new'] === '') ? '-' : $difference['new']) . '</td>
</tr>';
                }

                $strText .= '</tbody></table>';
                break;

            case 'registration':
                $strText = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $arrRow['data']);
                break;
        }

        $strUser = '';

        // Get the user info
        if ($arrRow['user']) {
            $objUser = $this->Database->prepare("SELECT * FROM tl_user WHERE id=?")
                                      ->limit(1)
                                      ->execute($arrRow['user']);

            if ($objUser->numRows) {
                $strUser = sprintf($GLOBALS['TL_LANG']['tl_member_log']['label_user'], $objUser->name, $objUser->id);
            } else {
                $strUser = sprintf($GLOBALS['TL_LANG']['tl_member_log']['label_user_deleted'], $arrRow['user']);
            }
        }

        return '
<div class="cte_type"><span class="tl_green"><strong>' . $GLOBALS['TL_DCA']['tl_member_log']['fields']['type']['reference'][$arrRow['type']] . ' - ' . $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $arrRow['dateAdded']) . '</strong>' . ($strUser ? (' - ' . $strUser) : '') . '</span></div>
<div class="limit_height' . (!$GLOBALS['TL_CONFIG']['doNotCollapse'] ? ' h64' : '') . '">
' . $strText . '
</div>';
    }

    /**
     * Return the edit button
     * @param array
     * @param string
     * @param string
     * @param string
     * @param string
     * @param string
     * @return string
     */
    public function editButton($row, $href, $label, $title, $icon, $attributes)
    {
        return ($row['type'] == 'note') ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.specialchars($title).'"'.$attributes.'>'.$this->generateImage($icon, $label).'</a> ' : $this->generateImage(preg_replace('/\.gif$/i', '_.gif', $icon)).' ';
    }
}
