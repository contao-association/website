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
 * @version    $Id: config.php 124 2010-07-08 17:05:11Z aschempp $
 */


/**
 * Frontend modules
 */
$GLOBALS['FE_MOD']['user']['avatar']		= 'ModuleFancyAvatar';


/**
 * Backend widget
 */
$GLOBALS['BE_FFL']['avatar']				= 'FancyAvatar';


/**
 * Form field
 */
$GLOBALS['TL_FFL']['avatar']				= 'FormFancyAvatar';


/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['replaceInsertTags'][]	= array('FancyAvatar', 'replaceTags');


/**
 * Default config
 */
$GLOBALS['TL_CONFIG']['avatar_dir']		= $GLOBALS['TL_CONFIG']['uploadPath'] . '/avatars';
$GLOBALS['TL_CONFIG']['avatar_default'] = $GLOBALS['TL_CONFIG']['uploadPath'] . '/avatars/default128.png';
$GLOBALS['TL_CONFIG']['avatar_maxsize'] = '500000';
$GLOBALS['TL_CONFIG']['avatar_maxdims'] = array(128, 128);
$GLOBALS['TL_CONFIG']['avatar_preview'] = '300';

