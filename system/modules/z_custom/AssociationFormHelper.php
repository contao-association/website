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


class AssociationFormHelper extends System
{
    public function processFormData($arrData, $arrForm, $arrFiles, $arrLabels)
    {
        // Only handle calendar form
        if ($arrForm['id'] != 2) {
            return;
        }

        $objStartTime = new Date($arrData['startDate'], $GLOBALS['TL_CONFIG']['datimFormat']);
        $objEndTime = new Date($arrData['endDate'], $GLOBALS['TL_CONFIG']['datimFormat']);

        $arrSet = array
        (
            'pid'       => 1,
            'title'     => $arrData['title'],
            'addTime'   => 1,
            'startDate' => $objStartTime->tstamp,
            'startTime' => $objStartTime->tstamp,
            'endDate'   => $objEndTime->tstamp,
            'endTime'   => $objEndTime->tstamp,
            'published' => ''
        );

        if ($arrData['url']) {
            $url = $arrData['url'];

            if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
                $url = 'http://' . $url;
            }

            $arrSet['source']   = 'external';
            $arrSet['url']      = $url;
            $arrSet['target']   = 1;
        }

        Database::getInstance()->prepare('INSERT INTO tl_calendar_events %s')->set($arrSet)->execute();
    }
}
