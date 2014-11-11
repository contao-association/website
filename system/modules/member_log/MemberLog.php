<?php

/**
 * member_log extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-member_log
 */

class MemberLog extends Controller
{

    /**
     * Log the new user info
     * @param integer
     * @param array
     */
    public function logNewUser($intId, $arrData)
    {
        $arrSet = array
        (
            'pid' => $intId,
            'tstamp' => $arrData['dateAdded'],
            'dateAdded' => $arrData['dateAdded'],
            'type' => 'registration',
            'data' => $arrData['dateAdded']
        );

        Database::getInstance()->prepare("INSERT INTO tl_member_log %s")
                               ->set($arrSet)
                               ->execute();
    }

    /**
     * Store the old data in the session
     */
    public function storeOldData()
    {
        if (TL_MODE != 'FE') {
            return;
        }

        $_SESSION['MEMBER_LOG_DATA'] = FrontendUser::getInstance()->getData();
    }

    /**
     * Log the personal data
     * @param object
     * @param array
     */
    public function logPersonalData($objUser, $arrData)
    {
        $arrDifference = array();

        // Compute the difference
        foreach ($arrData as $k => $v) {
            if ($_SESSION['MEMBER_LOG_DATA'][$k] != $v && $k != 'password') {
                $arrDifference[$k] = array
                (
                    'old' => $_SESSION['MEMBER_LOG_DATA'][$k],
                    'new' => $v
                );
            }
        }

        if (empty($arrDifference)) {
            return;
        }

        $time = time();

        $arrSet = array
        (
            'pid' => $objUser->id,
            'tstamp' => $time,
            'dateAdded' => $time,
            'type' => 'personal_data',
            'data' => serialize($arrDifference)
        );

        Database::getInstance()->prepare("INSERT INTO tl_member_log %s")
                               ->set($arrSet)
                               ->execute();

        // Free the session
        unset($_SESSION['MEMBER_LOG_DATA']);
    }

    /**
     * Log the date added
     * @param DataContainer
     */
    public function logDateAdded($dc)
    {
        if (!$dc instanceof DataContainer) {
            return;
        }

        $objLog = Database::getInstance()->prepare("SELECT id FROM tl_member_log WHERE type='registration' AND pid=?")
                                         ->limit(1)
                                         ->execute($dc->id);

        if ($objLog->numRows) {
            return;
        }

        $objMember = Database::getInstance()->prepare("SELECT dateAdded FROM tl_member WHERE id=?")
                                            ->limit(1)
                                            ->execute($dc->id);

        $arrSet = array
        (
            'pid' => $dc->id,
            'tstamp' => $objMember->dateAdded,
            'dateAdded' => $objMember->dateAdded,
            'user' => BackendUser::getInstance()->id,
            'type' => 'registration',
            'data' => $objMember->dateAdded
        );

        Database::getInstance()->prepare("INSERT INTO tl_member_log %s")
                               ->set($arrSet)
                               ->execute();
    }

    /**
     * Log the updated data
     * @param string
     * @param integer
     */
    public function logUpdatedData($strTable, $intId)
    {
        $objVersions = Database::getInstance()->prepare("SELECT * FROM tl_version WHERE pid=? AND fromTable=? ORDER BY version DESC")
                                              ->limit(1, 1)
                                              ->execute($intId, $strTable);

        if (!$objVersions->numRows) {
            return;
        }

        $objMember = Database::getInstance()->prepare("SELECT * FROM tl_member WHERE id=?")
                                            ->limit(1)
                                            ->execute($intId);

        if ($objMember === null) {
            return;
        }

        $arrData = deserialize($objVersions->data, true);
        $arrDifference = array();

        // Compute the difference
        foreach ($objMember->row() as $k => $v) {
            if ($arrData[$k] != $v) {
                $arrDifference[$k] = array
                (
                    'old' => $arrData[$k],
                    'new' => $v
                );
            }
        }

        // No need to store the tstamp
        unset($arrDifference['tstamp']);

        // Do not store password for security reasons
        unset($arrDifference['password']);

        if (empty($arrDifference)) {
            return;
        }

        $time = time();

        $arrSet = array
        (
            'pid' => $intId,
            'tstamp' => $time,
            'dateAdded' => $time,
            'user' => BackendUser::getInstance()->id,
            'type' => 'personal_data',
            'data' => serialize($arrDifference),
            'text' => $this->Input->post('member_log_note'),
        );

        Database::getInstance()->prepare("INSERT INTO tl_member_log %s")
                               ->set($arrSet)
                               ->execute();
    }
}
