<?php if(!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * associategroups
 * for Contao CMS
 * 
 * @copyright	backboneIT | Oliver Hoff 2013
 * @copyright	Andreas Schempp 2010-2013
 * @author		Oliver Hoff <oliver@hofff.com>
 * @author		Andreas Schempp <andreas@schempp.ch>
 * @license		http://opensource.org/licenses/lgpl-3.0.html
 */

$GLOBALS['TL_DCA']['tl_member']['config']['ondelete_callback'][] = array('AssociateGroups', 'deleteGroups');
$GLOBALS['TL_DCA']['tl_member']['config']['onsubmit_callback'][] = array('AssociateGroups', 'submitGroups');

$GLOBALS['TL_DCA']['tl_member']['list']['global_operations']['sync_associate_groups'] = array(
	'label'				=> &$GLOBALS['TL_LANG']['tl_member']['sync_associate_groups'],
	'href'				=> 'key=sync_tl_member_to_group',
	'class'				=> 'sync_associate_groups header_sync_all',
	'attributes'		=> 'onclick="Backend.getScrollOffset();"',
);
