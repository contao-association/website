<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2009
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 * @version    $Id: tl_settings.php 124 2010-07-08 17:05:11Z aschempp $
 */


/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{avatar_legend},avatar_dir,avatar_default,avatar_maxsize,avatar_maxdims,avatar_preview';


/**
 * Fields
 */
$GLOBALS['TL_DCA']['tl_settings']['fields']['avatar_dir'] = array
(
	'label'			=>	&$GLOBALS['TL_LANG']['tl_settings']['avatar_dir'],
	'inputType'		=> 'fileTree',
	'eval'			=> array('fieldType'=>'radio', 'mandatory'=>true),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['avatar_default'] = array
(
	'label'			=>	&$GLOBALS['TL_LANG']['tl_settings']['avatar_default'],
	'inputType'		=> 'fileTree',
	'eval'			=> array('fieldType'=>'radio', 'files'=>true, 'filesOnly'=>true, 'extensions'=>'jpg,jpeg,png,gif'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['avatar_maxsize'] = array
(
	'label'			=>	&$GLOBALS['TL_LANG']['tl_settings']['avatar_maxsize'],
	'inputType'		=>	'text',
	'eval'			=>	array('rgxp'=>'digit', 'maxlength'=>10, 'mandatory'=>true),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['avatar_maxdims'] = array
(
	'label'			=>	&$GLOBALS['TL_LANG']['tl_settings']['avatar_maxdims'],
	'inputType'		=> 'text',
	'eval'			=> array('mandatory'=>true, 'multiple'=>true, 'size'=>2),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['avatar_preview'] = array
(
	'label'			=>	&$GLOBALS['TL_LANG']['tl_settings']['avatar_preview'],
	'default'		=> '400',
	'inputType'		=> 'text',
	'eval'			=> array('mandatory'=>true, 'rgxp'=>'digit'),
);

