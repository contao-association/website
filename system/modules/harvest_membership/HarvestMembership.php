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
        $arrSubscription = $this->getSubscription($arrMember);

        if ($arrSubscription == false)
        {
            $this->log(('Unable to generate Harvest membership for member ID '.$arrMember['id'].' (no membership found)'), __METHOD__, TL_ERROR);
            return;
        }

        $arrMember = $this->prepareData($arrMember);
        $intClient = $this->createClient($arrMember, $arrSubscription);
        $intContact = $this->createClientContact($intClient, $arrMember);

        if ($intClient < 1 || $intContact < 1) {
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
        $this->Database->prepare("UPDATE tl_member SET harvest_client_id=?, harvest_id=?, groups=? WHERE id=?")->execute($intClient, $intContact, serialize($arrGroups), $arrMember['id']);
    }

    /**
     * Generate subscription configuration
     * @param   array
     * @return  array|false
     */
    public function getSubscription($arrMember)
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

    /**
     * Generate client name from member and subscription data
     * @param   array
     * @param   array
     * @return  string
     */
    public function generateClientName($arrMember, $arrSubscription)
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
    public function getClientLookupTable()
    {
        $arrResult = array();
        $objResult = $this->HaPi->getClients();

        if (!$objResult->isSuccess()) {
            $this->log('Unable to retrieve clients from Harvest (Error '.$objResult->code.')', __METHOD__, TL_ERROR);
            return array();
        }

        foreach ($objResult->data as $objClient) {
            $arrResult[(int) $objClient->id] = (string) $objClient->name;
        }

        return $arrResult;
    }

    /**
     * Search for client ID on Harvest, create client if none exists
     * @param   array   tl_member data
     * @param   array   subscription configuration
     * @return  int     ID of the client record
     */
    protected function createClient($arrMember, $arrSubscription)
    {
        $arrCountries = $this->getCountries();
        $arrClients = $this->getClientLookupTable();

        $strName = $this->generateClientName($arrMember, $arrSubscription);

        if (($intId = array_search($strName, $arrClients)) !== false) {

            $objResult = $this->HaPi->getClient($intId);

            if ($objResult->isSuccess()) {
                $objClient = $this->prepareClient($arrMember, $objResult->data);
                $this->HaPi->updateClient($objClient);
            }

            return $intId;
        }

        $objResult = $this->HaPi->createClient($this->prepareClient($arrMember));

        if (!$objResult->isSuccess())
        {
            $this->log(('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')'), __METHOD__, TL_ERROR);
            return 0;
        }

        return (int) $objResult->data;
    }

    /**
     * Create client contact in Harvest if it does not yet exist
     * @param   int     Harvest client ID
     * @param   array   tl_member record data
     * @return  int     ID of the contact record
     */
    protected function createClientContact($intClient, $arrMember)
    {
        $objResult = $this->HaPi->getClientContacts($intClient);

        if ($objResult->isSuccess()) {

            foreach ($objResult->data as $objContact) {
                if ($objContact->first_name == $arrMember['firstname'] && $objContact->last_name == $arrMember['lastname']) {

                    // Update existing data, it must be exactly the same as in Contao
                    $this->HaPi->updateContact($this->prepareContact($arrMember, $objContact));

                    return $objContact->id;
                }
            }
        }

        $objResult = $this->HaPi->createContact($this->prepareContact($arrMember));

        if (!$objResult->isSuccess())
        {
            $this->log(('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')'), __METHOD__, TL_ERROR);
            return 0;
        }

        return (int) $objResult->data;
    }

    /**
     * Create/update Harvest client object from member data
     * @param   array
     * @param   object
     * @return  object
     */
    protected function prepareClient($arrMember, $objClient=null)
    {
        if (null === $objClient) {
            $objClient = new Harvest_Client();
        }

        $arrSubscription = $this->getSubscription($arrMember);

        $objClient->name = $this->generateClientName($arrMember, $arrSubscription);

        if ($arrSubscription['company']) {
            $objClient->details = sprintf("%s%s\n%s %s%s",
                ($arrMember['company'] ? $arrMember['firstname'].' '.$arrMember['lastname']."\n" : ''),
                $arrMember['street'],
                $arrMember['postal'],
                $arrMember['city'],
                ($arrMember['country'] == 'ch' ? '' : "\n".$arrCountries[$arrMember['country']])
            );

        } else {
            $objClient->details = sprintf("%s%s\n%s %s%s",
                ($arrMember['company'] ? $arrMember['company']."\n" : ''),
                $arrMember['street'],
                $arrMember['postal'],
                $arrMember['city'],
                ($arrMember['country'] == 'ch' ? '' : "\n".$arrCountries[$arrMember['country']])
            );
        }

        return $objClient;
    }

    /**
     * Update a Harvest contact from member data
     * @param   array
     * @param   object
     * @return  object
     */
    protected function prepareContact($arrMember, $objContact=null)
    {
        if (null === $objContact) {
            if ($arrMember['harvest_client_id'] < 1) {
                throw new Exception('Member must have a Harvest client ID to generate Harvest contact.');
            }

            $objContact = new Harvest_Contact();
            $objContact->client_id = $arrMember['harvest_client_id'];
        }

        $objContact->first_name = $arrMember['firstname'];
        $objContact->last_name = $arrMember['lastname'];
        $objContact->email = $arrMember['email'];
        $objContact->phone_office = $arrMember['phone'];
        $objContact->phone_mobile = $arrMember['mobile'];
        $objContact->fax = $arrMember['fax'];

        return $objContact;
    }

    /**
     * Decode all entities in the data
     * @param array
     * @return array
     */
    protected function prepareData(array $arrData)
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
}

