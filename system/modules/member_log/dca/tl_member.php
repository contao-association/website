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
    'icon'                => 'log.gif'
);
