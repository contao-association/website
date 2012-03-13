<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *
 * PHP version 5
 * @copyright  Contao Verein Schweiz 2011
 * @author     Andreas Schempp <andreas.schempp@iserv.ch>
 * @license    commercial
 * @version    $Id: $
 */


/**
 * Listing
 */
array_insert($GLOBALS['TL_DCA']['tl_member']['list']['label']['fields'], 2, array('company'));
$GLOBALS['TL_DCA']['tl_member']['list']['label']['format'] = '%s %s (%s) <span style="color:#b3b3b3; padding-left:3px;">[%s]</span>';

/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['street']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['postal']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['city']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['phone']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['country']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['country']['default'] = 'ch';

foreach( array('company', 'firstname', 'lastname', 'street', 'postal', 'city', 'country') as $field )
{
	$GLOBALS['TL_DCA']['tl_member']['fields'][$field]['eval']['feGroup'] = 'left_column';
}

foreach( array('phone', 'fax', 'mobile', 'email', 'website', 'password') as $field )
{
	$GLOBALS['TL_DCA']['tl_member']['fields'][$field]['eval']['feGroup'] = 'right_column';
}

$GLOBALS['TL_DCA']['tl_member']['fields']['harvest_membership']['eval']['feGroup'] = 'membership';

