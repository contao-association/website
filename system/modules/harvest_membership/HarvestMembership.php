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
 * @copyright  Contao Association 2011-2013
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @license    commercial
 */


class HarvestMembership extends Frontend
{

    /**
     * Harvest API object
     * @var object
     */
    protected $HaPi;

    /**
     * Invoice Config
     * @var array
     */
    protected $arrConfig;


    public function __construct()
    {
        parent::__construct();

        $this->import('Encryption');
        $this->import('String');

        require_once TL_ROOT . '/plugins/HaPi/HarvestAPI.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/Abstract.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/Result.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/Exception.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/Client.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/Contact.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/Invoice.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/InvoiceItemCategory.php';
        require_once TL_ROOT . '/plugins/HaPi/Harvest/InvoiceMessage.php';

        $this->HaPi = new HarvestAPI();
        $this->HaPi->setUser($GLOBALS['TL_CONFIG']['harvest_user']);
        $this->HaPi->setPassword($this->Encryption->decrypt($GLOBALS['TL_CONFIG']['harvest_password']));
        $this->HaPi->setAccount($GLOBALS['TL_CONFIG']['harvest_account']);

        global $objPage;
        $this->arrConfig = $this->Database->execute("SELECT * FROM tl_page WHERE id=".(int)$objPage->rootId)->fetchAssoc();
    }


    public function replaceTag($strTag)
    {
        $arrTag = trimsplit('::', $strTag);

        if ($arrTag[0] == 'harvest')
        {
            switch( $arrTag[1] )
            {
                case 'payments_received':
                    $strCacheFile = TL_ROOT . '/system/tmp/' . md5('harvest-payments_received-'.mktime(date('H'),0,0));

                    if (is_file($strCacheFile))
                    {
                        $intTotal = file_get_contents($strCacheFile);
                    }
                    else
                    {
                        // Start with CHF converted to EUR
                        $intTotal = 3560;

                        $objInvoices = $this->HaPi->getInvoices();

                        if ($objInvoices->isSuccess())
                        {
                            foreach( $objInvoices->data as $objInvoice )
                            {
                                // Only count Euro
                                if (strpos($objInvoice->currency, 'EUR') === false)
                                {
                                    continue;
                                }

                                $intTotal += (int)$objInvoice->amount;
                            }

                            file_put_contents($strCacheFile, $intTotal);
                        }
                    }

                    return number_format($intTotal, 0, '.', "'") . '.–';
                    break;

                case 'members':
                    $objMembers = $this->Database->execute("SELECT COUNT(*) AS total FROM tl_member");
                    return (int) $objMembers->total;
                    break;
            }
        }

        return false;
    }


    public function createNewUser($intId, $arrData)
    {
        if (is_array($arrData['harvest_membership']))
        {
            $arrData['id'] = $intId;

            $this->generateInvoice($arrData);
        }
    }


    public function generateInvoice($arrMember)
    {
        $arrSubscription = false;

        foreach( deserialize($GLOBALS['TL_CONFIG']['harvest_memberships'], true) as $i => $arrConfig )
        {
            if ($arrMember['harvest_membership']['membership'] == $arrConfig['group'])
            {
                $arrConfig['price'];

                if ($arrConfig['custom'] && $arrMember['harvest_membership']['custom_'.$i] > $arrConfig['price'])
                {
                    $arrConfig['price'] = $arrMember['harvest_membership']['custom_'.$i];
                }

                $arrConfig['price'] = number_format($arrConfig['price'], 2);

                $arrSubscription = $arrConfig;
                break;
            }
        }

        if ($arrSubscription == false)
        {
            $this->log(('Unable to generate Harvest membership for member ID '.$arrMember['id'].' (no membership found)'), __METHOD__, TL_ERROR);
            return;
        }

        $arrMember = $this->prepareData($arrMember);
        $arrCountries = $this->getCountries();

        $strAddress = sprintf("%s%s\n%s %s%s",
                            ($arrMember['company'] ? $arrMember['firstname'].' '.$arrMember['lastname']."\n" : ''),
                            $arrMember['street'],
                            $arrMember['postal'],
                            $arrMember['city'],
                            ($arrMember['country'] == 'ch' ? '' : "\n".$arrCountries[$arrMember['country']]));

        // Create client
        $objClient = new Harvest_Client();
        $objClient->name = htmlspecialchars(($arrMember['company'] ? $arrMember['company'] : ($arrMember['firstname'].' '.$arrMember['lastname'])));
        $objClient->details = htmlspecialchars($strAddress);

        $objResult = $this->HaPi->createClient($objClient);

        if (!$objResult->isSuccess())
        {
            $this->log(('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')'), __METHOD__, TL_ERROR);
            return;
        }

        $intClient = $objResult->data;

        // Create contact for this client
        $objContact = new Harvest_Contact();
        $objContact->first_name = $arrMember['firstname'];
        $objContact->last_name = $arrMember['lastname'];
        $objContact->email = $arrMember['email'];
        $objContact->phone_office = $arrMember['phone'];
        $objContact->phone_mobile = $arrMember['mobile'];
        $objContact->fax = $arrMember['fax'];
        $objContact->client_id = $intClient;

        $objResult = $this->HaPi->createContact($objContact);

        if (!$objResult->isSuccess())
        {
            $this->log(('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')'), __METHOD__, TL_ERROR);
            return;
        }

        // Create invoice
        $objInvoice = new Harvest_Invoice();
        $objInvoice->issued_at = date('Y-m-d');
        $objInvoice->due_at = date('Y-m-d', strtotime('+'.$this->arrConfig['harvest_due'].' days'));
        $objInvoice->due_at_human_format = 'custom'; //'NET '.$this->arrConfig['harvest_due']; //sprintf('%s Tage', $this->arrConfig['harvest_due']);
        $objInvoice->client_id = $intClient;
        $objInvoice->number = $arrMember['id'] . '/' . date('Y');
        $objInvoice->kind = 'free_form';
        $objInvoice->purchase_order = $arrMember['id'];
        $objInvoice->notes = $this->arrConfig['harvest_notes'];
        $objInvoice->csv_line_items =
'
kind,description,quantity,unit_price,amount,taxed,taxed2,project_id
'.$this->arrConfig['harvest_category'].',' . sprintf($this->arrConfig['harvest_format'], $arrSubscription['label']) . ',1.00,' . $arrSubscription['price'] . ',' . $arrSubscription['price'] . ',false,false,1
  ';

        $objResult = $this->HaPi->createInvoice($objInvoice);

        if (!$objResult->isSuccess())
        {
            $this->log('Unable to create Harvest invoice for member ID '.$arrMember['id'].' (Error '.$objResult->code.')', __METHOD__, TL_ERROR);
            return;
        }

        $intInvoice = $objResult->data;

        $objMessage = new Harvest_InvoiceMessage();
        $objMessage->invoice_id = $intInvoice;
        $objMessage->attach_pdf = true;
        $objMessage->send_me_a_copy = true;
        $objMessage->include_pay_pal_link = true;
        $objMessage->recipients = $arrMember['email'];
        $objMessage->body = $this->arrConfig['harvest_message'];

        $objResult = $this->HaPi->sendInvoiceMessage($intInvoice, $objMessage);

        if (!$objResult->isSuccess())
        {
            $this->log('Unable to send Harvest invoice to member ID '.$arrMember['id'].' (Error '.$objResult->code.')', __METHOD__, TL_ERROR);
            return;
        }

        // Mitglied der entsprechenden Gruppe zuweisen
        $arrGroups = deserialize($arrMember['groups'], true);
        $arrGroups[] = $arrSubscription['group'];
        $this->Database->prepare("UPDATE tl_member SET groups=? WHERE id=?")->execute(serialize($arrGroups), $arrMember['id']);

        $this->killCache();
    }


    /**
     * Decode all entities in the data
     * @param array
     * @return array
     */
    public function prepareData(array $arrData)
    {
        foreach( $arrData as $k => $v )
        {
            if (is_string($v))
            {
                $arrData[$k] = $this->String->decodeEntities($v);
            }
            elseif (is_array($v))
            {
                $arrData[$k] = $this->prepareData($v);
            }
        }

        return $arrData;
    }


    public function killCache($varValue, $dc)
    {
        $strCacheFile = 'system/tmp/' . md5('harvest-payments_received-'.mktime(0,0,0));

        if (is_file(TL_ROOT . '/' . $strCacheFile))
        {
            $this->import('Files');
            $this->Files->delete($strCacheFile);
        }

        return $varValue;
    }
}

