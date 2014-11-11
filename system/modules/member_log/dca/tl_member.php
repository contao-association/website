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
 * Load the tl_member_log language file
 */
$this->loadLanguageFile('tl_member_log');

/**
 * Add child table to tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['config']['ctable'][] = 'tl_member_log';

/**
 * Add the onsubmit_callback to tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['config']['onload_callback'][] = array('MemberLog', 'storeOldData');
$GLOBALS['TL_DCA']['tl_member']['config']['onsubmit_callback'][] = array('MemberLog', 'logDateAdded');
$GLOBALS['TL_DCA']['tl_member']['config']['onversion_callback'][] = array('MemberLog', 'logUpdatedData');

/**
 * Add operation to tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['list']['operations']['log'] = array
(
    'label'               => &$GLOBALS['TL_LANG']['tl_member']['log'],
    'href'                => 'table=tl_member_log',
    'icon'                => 'system/modules/member_log/assets/icon.png'
);

/**
 * Update the tl_member palettes
 */
$GLOBALS['TL_DCA']['tl_member']['palettes']['default'] .= ';{log_legend},member_log_note';

/**
 * Add fields to tl_member
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['member_log_note'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_member_log']['text'],
    'exclude'                 => true,
    'inputType'               => 'textarea',
    'eval'                    => array('doNotSaveEmpty'=>true),
    'save_callback'           => array
    (
        array('tl_member_member_log', 'getLogNote')
    ),
);

class tl_member_member_log extends Backend
{

    /**
     * Do not save the log note
     * @return null
     */
    public function getLogNote()
    {
        return null;
    }
}
