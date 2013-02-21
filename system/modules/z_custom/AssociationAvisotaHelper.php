<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2013 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *
 * PHP version 5
 * @copyright  Contao Association 2013
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 * @license    commercial
 */


class AssociationAvisotaHelper extends System
{
    public function addToList($intMemberID, $arrMemberData)
    {
        // currently we only have one list (ID 1)
        $arrLists = array(1);

        $objPrepared = Database::getInstance()->prepare('
			SELECT	r.confirmed, r.id AS rid
			FROM	tl_avisota_recipient_list AS l
			LEFT JOIN (
				SELECT	r1.confirmed, r1.id, r1.pid
				FROM	tl_avisota_recipient AS r1
				WHERE	r1.email = ?
			) AS r ON l.id = r.pid
			WHERE l.id = ?
		');
        $intTime = time();

        foreach($arrLists as $intListID) {
            $objAlreadySubscribed = $objPrepared->execute($arrMemberData['email'], $intListID);

            if(!$objAlreadySubscribed->numRows) // list doesn't exist
                continue;

            $arrData = array(
                'email' => $arrMemberData['email'],
                'confirmed' => 1,
                'tstamp' => $intTime
            );

            if(!$objAlreadySubscribed->rid) { // no existing subscription
                $arrData['pid'] = $intListID;
                $arrData['addedOn'] = $intTime;
                Database::getInstance()->prepare(
                    'INSERT INTO tl_avisota_recipient %s'
                )->set($arrData)->execute();

            } elseif(!$objAlreadySubscribed->confirmed) { // unconfirmed subscription found
                $arrData['token'] = '';
                Database::getInstance()->prepare(
                    'UPDATE tl_avisota_recipient %s WHERE id = ?'
                )->set($arrData)->execute($objAlreadySubscribed->rid);
            }
        }
    }
}