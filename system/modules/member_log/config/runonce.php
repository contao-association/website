<?php

/**
 * member_log extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2008-2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-member_log
 */

$objMembers = Database::getInstance()->execute("SELECT id, dateAdded FROM tl_member");

while ($objMembers->next()) {
    $arrSet = array
    (
        'pid' => $objMembers->id,
        'tstamp' => $objMembers->dateAdded,
        'dateAdded' => $objMembers->dateAdded,
        'type' => 'registration',
        'data' => $objMembers->dateAdded
    );

    Database::getInstance()->prepare("INSERT INTO tl_member_log %s")
                           ->set($arrSet)
                           ->execute();
}
