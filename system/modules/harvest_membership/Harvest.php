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
 * @copyright  Contao Association 2013
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @license    commercial
 */


class Harvest
{

    /**
     * Magically call HarvestAPI methods and return result
     */
    public static function __callStatic($name, $arguments)
    {
        $objAPI = static::getAPI();

        return call_user_func_array(array($objAPI, $name), $arguments);
    }

    /**
     * Retrieve cached instance of HarvestAPI
     * @return  object
     */
    public static function getAPI()
    {
        static $objAPI;

        if (null === $objAPI) {
            require_once TL_ROOT . '/plugins/HaPi/HarvestAPI.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Abstract.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Result.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Exception.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Client.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Contact.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Invoice.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/InvoiceItemCategory.php';
            require_once TL_ROOT . '/plugins/HaPi/Harvest/InvoiceMessage.php';

            $objAPI = new HarvestAPI();
            $objAPI->setUser($GLOBALS['TL_CONFIG']['harvest_user']);
            $objAPI->setPassword(Encryption::getInstance()->decrypt($GLOBALS['TL_CONFIG']['harvest_password']));
            $objAPI->setAccount($GLOBALS['TL_CONFIG']['harvest_account']);
        }

        return $objAPI;
    }

    /**
     * Decode all entities in the data
     * @param   array
     * @return  array
     */
    public static function prepareData($varData)
    {
        if (is_array($varData)) {
            foreach ($varData as $k => $v) {
                $varData[$k] = static::prepareData($v);
            }

            return $varData;
        }

        return String::getInstance()->decodeEntities($v);
    }

    /**
     * Compile subscription config from member data
     * @param   array
     * @return  array|false
     */
    public static function getSubscription(array $arrMember)
    {
        $arrSubscription = false;

        foreach (deserialize($GLOBALS['TL_CONFIG']['harvest_memberships'], true) as $i => $arrConfig)
        {
            if ($arrMember['harvest_membership']['membership'] == $arrConfig['group'])
            {
                if ($arrConfig['custom'] && $arrMember['harvest_membership']['custom_'.$i] > $arrConfig['price'])
                {
                    $arrConfig['price'] = $arrMember['harvest_membership']['custom_'.$i];
                }

                $arrConfig['price'] = number_format($arrConfig['price'], 2);

                $arrSubscription = $arrConfig;
                break;
            }
        }

        return $arrSubscription;
    }
}
