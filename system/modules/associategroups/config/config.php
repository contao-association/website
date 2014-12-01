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

$GLOBALS['TL_HOOKS']['createNewUser'][] = array('AssociateGroups', 'createNewUser');

$GLOBALS['BE_MOD']['accounts']['member']['sync_tl_member_to_group']
	= array('AssociateGroups', 'syncMemberToGroup');
$GLOBALS['BE_MOD']['accounts']['user']['sync_tl_user_to_group']
	= array('AssociateGroups', 'syncUserToGroup');

TL_MODE == 'BE' && $GLOBALS['TL_CSS'][] = 'system/modules/associategroups/html/backend.css';
