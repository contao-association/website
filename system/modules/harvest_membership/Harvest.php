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


/**
 * Class Harvest
 *
 * @method static Harvest_Result getClients
 * @method static Harvest_Result getClient
 * @method static Harvest_Result createClient
 * @method static Harvest_Result updateClient
 * @method static Harvest_Result getClientContacts
 * @method static Harvest_Result getContact
 * @method static Harvest_Result createContact
 * @method static Harvest_Result updateContact
 * @method static Harvest_Result getInvoices
 * @method static Harvest_Result getInvoice
 * @method static Harvest_Result createInvoice
 * @method static Harvest_Result getInvoicePayments($invoice_id)
 * @method static Harvest_Result getInvoicePayment
 * @method static Harvest_Result createInvoicePayment
 * @method static Harvest_Result deleteInvoicePayment
 * @method static Harvest_Result sendInvoiceMessage
 */
class Harvest
{

    /**
     * API result caching
     * @var array
     */
    private static $arrCache = array();

    /**
     * Magically call HarvestAPI methods and return result
     * Caches single result lookups for better performance
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        switch ($name)
        {
            case 'getInvoice':
            case 'getClient':
            case 'getContact':
                if (isset(static::$arrCache[$name][$arguments[0]])) {
                    return static::$arrCache[$name][$arguments[0]];
                }
                $blnCache = true;
                break;

            default:
                $blnCache = false;
                break;
        }

        $varResult = call_user_func_array(array(static::getAPI(), $name), $arguments);

        if (true === $blnCache) {
            static::$arrCache[$name][$arguments[0]] = $varResult;
        }

        return $varResult;
    }

    /**
     * Retrieve cached instance of HarvestAPI
     * @return  object
     */
    public static function getAPI()
    {
        static $objAPI;

        if (null === $objAPI) {
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/HarvestAPI.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Abstract.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Result.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Exception.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Client.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Contact.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Invoice.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/InvoiceItemCategory.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/InvoiceMessage.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Invoice/Filter.php';
            /** @noinspection PhpIncludeInspection */
            require_once TL_ROOT . '/plugins/HaPi/Harvest/Payment.php';

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

        return String::getInstance()->decodeEntities($varData);
    }

    /**
     * Compile subscription config from member data
     * @param   array
     * @return  array|false
     */
    public static function getSubscription(array $arrMember)
    {
        if (!isset(static::$arrCache['getSubscription'][$arrMember['id']])) {

            static::$arrCache['getSubscription'][$arrMember['id']] = false;

            $arrMember['harvest_membership'] = deserialize($arrMember['harvest_membership'], true);

            foreach (deserialize($GLOBALS['TL_CONFIG']['harvest_memberships'], true) as $i => $arrConfig)
            {
                if ($arrMember['harvest_membership']['membership'] == $arrConfig['group'])
                {
                    if ($arrConfig['custom'] && $arrMember['harvest_membership']['custom_'.$i] > $arrConfig['price'])
                    {
                        $arrConfig['price'] = $arrMember['harvest_membership']['custom_'.$i];
                    }

                    $arrConfig['price'] = number_format($arrConfig['price'], 2);

                    static::$arrCache['getSubscription'][$arrMember['id']] = $arrConfig;
                    break;
                }
            }
        }

        return static::$arrCache['getSubscription'][$arrMember['id']];
    }

    /**
     * Generate client name from member and subscription data
     * @param   array
     * @param   array
     * @return  string
     */
    public static function generateClientName($arrMember, $arrSubscription)
    {
        if ($arrSubscription['company']) {
            return htmlspecialchars(($arrMember['company'] ? $arrMember['company'] : ($arrMember['firstname'].' '.$arrMember['lastname'])));
        } else {
            return htmlspecialchars($arrMember['firstname'].' '.$arrMember['lastname']);
        }
    }

    /**
     * Get list of Harvest client names, ID is array key
     * @return  array
     */
    public static function getClientLookupTable()
    {
        $arrResult = array();
        $objResult = Harvest::getClients();

        if (!$objResult->isSuccess()) {
            return array();
        }

        foreach ($objResult->data as $objClient) {
            $arrResult[(int) $objClient->id] = (string) $objClient->name;
        }

        return $arrResult;
    }
}
