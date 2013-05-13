<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2012 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *
 * PHP version 5
 * @copyright  Contao Association 2011-2013
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @license    commercial
 */


/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['system']['harvest_membership'] = array
(
    'tables'            => array('tl_harvest_settings'),
    'icon'                => 'system/modules/harvest_membership/html/icon.gif',
);


/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['createNewUser'][] = array('HarvestMembership', 'createNewUser');
$GLOBALS['TL_HOOKS']['replaceInsertTags'][] = array('HarvestMembership', 'replaceTag');


/**
 * Form fields
 */
$GLOBALS['BE_FFL']['membership'] = 'FormMembership';
$GLOBALS['TL_FFL']['membership'] = 'FormMembership';

