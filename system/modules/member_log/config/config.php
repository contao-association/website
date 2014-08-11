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
 * Add the tl_member_log table to members module
 */
$GLOBALS['BE_MOD']['accounts']['member']['tables'][] = 'tl_member_log';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['createNewUser'][] = array('MemberLog', 'logNewUser');
$GLOBALS['TL_HOOKS']['updatePersonalData'][] = array('MemberLog', 'logPersonalData');
