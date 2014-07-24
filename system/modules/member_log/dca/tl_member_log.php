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
            'headerFields'            => array('username', 'email', 'firstname', 'lastname'),
            'flag'                    => 8,
            'panelLayout'             => 'filter;search,limit',
            'child_record_callback'   => array('tl_member_log', 'generateLabel')
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'                => 'act=select',
                'class'               => 'header_edit_all',
                'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations' => array
        (
            'edit' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_member_log']['edit'],
                'href'                => 'act=edit',
                'icon'                => 'edit.gif',
                'button_callback'     => array('tl_member_log', 'editButton')
            ),
            'delete' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_member_log']['delete'],
                'href'                => 'act=delete',
                'icon'                => 'delete.gif',
                'attributes'          => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"'
            ),
            'show' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_member_log']['show'],
                'href'                => 'act=show',
                'icon'                => 'show.gif'
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
     * Store the note data
     * @param DataContainer
     */
    public function storeNoteData(DataContainer $dc)
    {
        if (!$dc->dateAdded) {
            $this->Database->prepare("UPDATE tl_member_log SET dateAdded=? WHERE id=?")
                           ->execute(time(), $dc->id);
        }

        $this->Database->prepare("UPDATE tl_member_log SET type='note' WHERE id=?")
                       ->execute($dc->id);
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
                $strText = sprintf($GLOBALS['TL_LANG']['tl_member_log']['label_note'], nl2br($arrRow['text']));
                break;

            case 'personal_data':
                $arrDifference = array();
                $arrData = deserialize($arrRow['data'], true);

                // Compute the difference
                foreach ($arrData as $field => $difference) {
                    if (!isset($GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'])) {
                        continue;
                    }

                    $arrDifference[] = $GLOBALS['TL_DCA']['tl_member']['fields'][$field]['label'][0] . ' [<em>"' . $difference['old'] . '"</em> -> <em>"' . $difference['new'] . '"</em>]';
                }

                $strText = sprintf($GLOBALS['TL_LANG']['tl_member_log']['label_personal_data'], '<br>' . implode('<br>', $arrDifference));
                break;

            case 'registration':
                $strText = sprintf($GLOBALS['TL_LANG']['tl_member_log']['label_registration'], $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $arrRow['data']));
                break;
        }

        return '<span style="padding-left:3px;color:#b3b3b3;">[' . $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $arrRow['dateAdded']) . ']</span> ' . $strText;
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
