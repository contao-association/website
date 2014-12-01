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

class AssociateGroups extends Controller {

	public function __construct() {
		parent::__construct();
		$this->import('Database');
	}

	/**
	 * Save member groups to the association table
	 *
	 * @param object $objDC
	 * @return mixed
	 * @link http://www.contao.org/callbacks.html onsubmit_callback
	 */
	public function submitGroups($objDC) {
		if($objDC instanceof FrontendUser) {
			$strType = 'member';
			$arrGroups = deserialize($objDC->groups, true);
		} elseif(!is_object($objDC) || !$objDC->table || !is_object($objDC->activeRecord)) {
			return;
		} else {
			$strType = substr($objDC->table, 3);
			$arrGroups = deserialize($objDC->activeRecord->groups, true);
		}

		$arrGroups = array_filter(array_unique(array_map('intval', $arrGroups)));

		if(!$arrGroups) {
			$strQuery = <<<EOT
DELETE
FROM	tl_{$strType}_to_group
WHERE	{$strType}_id = ?
EOT;
			$this->Database->prepare($strQuery)->execute($objDC->id);

		} else {
			$strQuery = <<<EOT
SELECT	group_id
FROM	tl_{$strType}_to_group
WHERE	{$strType}_id = ?
EOT;
			$arrAssociations = $this->Database->prepare($strQuery)->execute($objDC->id)->fetchEach('group_id');

			$arrDelete = array_diff($arrAssociations, $arrGroups);
			$arrInsert = array_diff($arrGroups, $arrAssociations);

			if($arrDelete) {
				$strWildcards = rtrim(str_repeat('?,', count($arrDelete)), ',');
				$strQuery = <<<EOT
DELETE
FROM	tl_{$strType}_to_group
WHERE	{$strType}_id = ?
AND		group_id IN ($strWildcards)
EOT;
				array_unshift($arrDelete, $objDC->id);
				$this->Database->prepare($strQuery)->execute($arrDelete);
			}

			if($arrInsert) {
				$strValues = sprintf('(%s,%s,?), ', time(), intval($objDC->id));
				$strValues = rtrim(str_repeat($strValues, count($arrInsert)), ', ');
				$strQuery = <<<EOT
INSERT
INTO	tl_{$strType}_to_group (tstamp, {$strType}_id, group_id)
VALUES	$strValues
EOT;
				$this->Database->prepare($strQuery)->execute($arrInsert);
			}
		}

		return $varValue;
	}

	/**
	 * Delete groups when member/user is deleted
	 *
	 * @param object $objDC DataContainer
	 * @return void
	 * @link http://www.contao.org/callbacks.html ondelete_callback
	 */
	public function deleteGroups($objDC) {
		$strType = substr($objDC->table, 3);
		$strQuery = <<<EOT
DELETE
FROM	tl_{$strType}_to_group
WHERE	{$strType}_id = ?
EOT;
		$this->Database->prepare($strQuery)->execute($objDC->activeRecord->id);
	}

	/**
	 * Add groups for a new member
	 *
	 * @param int $intId
	 * @param array $arrData
	 * @return void
	 * @link http://www.contao.org/hooks.html#createNewUser
	 */
	public function createNewUser($intId, $arrData) {
		$arrGroups = deserialize($arrData['groups']);

		if(!is_array($arrGroups) || !count($arrGroups)) {
			return;
		}

		$arrGroups = array_map('intval', $arrGroups);
		$strValues = sprintf('(%s,%s,?), ', time(), $intId);
		$strValues = rtrim(str_repeat($strValues, count($arrGroups)), ', ');
		$strQuery = <<<EOT
INSERT
INTO	tl_member_to_group (tstamp, member_id, group_id)
VALUES	$strValues
EOT;
		$this->Database->prepare($strQuery)->execute($arrGroups);
	}

	private function createTable($strType) {
		$strQuery = <<<EOT
CREATE TABLE `tl_{$strType}_to_group` (

  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `{$strType}_id` int(10) unsigned NOT NULL default '0',
  `group_id` int(10) unsigned NOT NULL default '0',

  PRIMARY KEY  (`id`)

) ENGINE=MyISAM DEFAULT CHARSET=utf8;
EOT;
		$this->Database->query($strQuery);
	}

	private function cleanTable($strType) {
		if($this->Database->tableExists('tl_' . $strType . '_to_group')) {
			$this->Database->execute('TRUNCATE tl_' . $strType . '_to_group');
		} else {
			$this->createTable($strType);
		}
	}

	public function syncAssociationTable($strType) {
		if(!$this->Database->fieldExists('groups', 'tl_' . $strType)) {
			throw new Exception('Can not handle ' . $strType . ' for group association sync');
		}
		$this->cleanTable($strType);

		$strQuery = <<<EOT
SELECT		id, groups
FROM		tl_{$strType}
WHERE		groups != ''
ORDER BY	id
EOT;
		$objGroups = $this->Database->execute($strQuery);

		$intTime = time();
		while($objGroups->next()) {
			$arrGroups = deserialize($objGroups->groups);
			if(!is_array($arrGroups) || !count($arrGroups)) {
				continue;
			}

			$strValues = sprintf('(%s,%s,?), ', $intTime, $objGroups->id);
			$strValues = rtrim(str_repeat($strValues, count($arrGroups)), ', ');
			$strQuery = <<<EOT
INSERT
INTO	tl_{$strType}_to_group (tstamp, {$strType}_id, group_id)
VALUES	$strValues
EOT;
			$this->Database->prepare($strQuery)->execute($arrGroups);
		}
	}

	/**
	 * Delete tl_member_to_group and create new
	 *
	 * @return void
	 */
	public function syncMemberToGroup() {
		$this->syncAssociationTable('member');
		$this->redirect($this->Environment->script . '?do=member');
	}

	/**
	 * Delete tl_user_to_group and create new
	 *
	 * @return void
	 */
	public function syncUserToGroup() {
		$this->syncAssociationTable('user');
		$this->redirect($this->Environment->script . '?do=user');
	}

}
