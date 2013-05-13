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
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_member']['subpalettes']['login'] .= ',harvest_membership';


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_member']['fields']['harvest_membership'] = array
(
    'label'                => &$GLOBALS['TL_LANG']['tl_member']['harvest_membership'],
    'inputType'            => 'membership',
    'eval'                => array('mandatory'=>true, 'feEditable'=>true, 'feGroup'=>'login', 'tl_class'=>'clr'),
);

$GLOBALS['TL_DCA']['tl_member']['fields']['disable']['save_callback'][] = array('HarvestMembership', 'killCache');

