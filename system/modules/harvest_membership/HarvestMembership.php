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


class HarvestMembership extends Controller
{

    /**
     * Invoice Config
     * @var array
     */
    protected $arrConfig;


    public function __construct()
    {
        parent::__construct();

        global $objPage;
        $this->arrConfig = $this->Database->execute("SELECT * FROM tl_page WHERE id=".(int)$objPage->rootId)->fetchAssoc();
        $this->import('Database');

        // Make sure Harvest classes are available
        Harvest::getAPI();
    }

    /**
     * Create and send invoice to new users
     * @param   int
     * @param   array
     */
    public function invoiceNewUser($intId, $arrData)
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
        $intContact = $this->createContact($intClient, $arrMember);

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
     * Prevent duplicate member name in Harvest when submitting backend fields
     * @param   mixed
     * @param   DataContainer
     * @return  mixed
     */
    public function preventDuplicateMember($varValue, $dc)
    {
        $arrMember = array(
            'company'               => $this->Input->post('company'),
            'firstname'             => $this->Input->post('firstname'),
            'lastname'              => $this->Input->post('lastname'),
            'harvest_membership'    => $this->Input->post('harvest_membership'),
        );

        $strName = $this->generateClientName($arrMember, Harvest::getSubscription($arrMember));
        $intId = array_search($strName, $this->getClientLookupTable());

        if ($intId !== false && $intId != $dc->activeRecord->harvest_client_id) {
            throw new Exception('Mitglied bereits vorhanden.');
        }

        return $varValue;
    }

    /**
     * Create a member data backup in the session
     * @param   FrontendUser
     */
    public function backupInSession()
    {
        if (FE_USER_LOGGED_IN === true) {
            $_SESSION['OLD_MEMBER_DATA'] = FrontendUser::getInstance()->getData();
        }
    }

    /**
     * Update Harvest client and contact when saving a member
     * @param   DataContainer|FrontendUser
     */
    public function updateMember($dc)
    {
        $arrMember = (TL_MODE == 'BE' ? $dc->activeRecord->row() : $dc->getData());

        // Cannot update without Harvest link
        if ($arrMember['harvest_client_id'] < 1 || $arrMember['harvest_id'] < 1) {
            return;
        }

        // Prevent duplicate members in frontend, reset if necessary
        if (TL_MODE == 'FE' && is_array($_SESSION['OLD_MEMBER_DATA'])) {
            $strName = $this->generateClientName($arrMember, Harvest::getSubscription($arrMember));
            $intId = array_search($strName, $this->getClientLookupTable());

            if ($intId !== false && $intId != $arrMember['harvest_client_id']) {
                $_SESSION['PERSONALDATA_ERROR'] = $GLOBALS['TL_LANG']['ERR']['harvestDuplicate'];

                $this->Database->prepare("UPDATE tl_member SET firstname=?, lastname=?, company=? WHERE id=?")->executeUncached($_SESSION['OLD_MEMBER_DATA']['firstname'], $_SESSION['OLD_MEMBER_DATA']['lastname'], $_SESSION['OLD_MEMBER_DATA']['company'], $arrMember['id']);

                unset($_SESSION['OLD_MEMBER_DATA']);
                return;
            }
        }

        $objResult = Harvest::getClient($arrMember['harvest_client_id']);

        if ($objResult->isSuccess()) {
            $objClient = $this->prepareClient($arrMember, $objResult->data);
            Harvest::updateClient($objClient);
        }

        $objResult = Harvest::getContact($arrMember['harvest_id']);

        if ($objResult->isSuccess()) {
            $objContact = $this->prepareContact($arrMember, $objResult->data);
            Harvest::updateContact($objContact);
                }
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
        $objResult = Harvest::getClients();

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

            $objResult = Harvest::getClient($intId);

            if ($objResult->isSuccess()) {
                $objClient = $this->prepareClient($arrMember, $objResult->data);
                Harvest::updateClient($objClient);
            }

            return $intId;
        }

        $objResult = Harvest::createClient($this->prepareClient($arrMember));

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
    protected function createContact($intClient, $arrMember)
    {
        $objResult = Harvest::getClientContacts($intClient);

        if ($objResult->isSuccess()) {

            foreach ($objResult->data as $objContact) {
                if ($objContact->first_name == $arrMember['firstname'] && $objContact->last_name == $arrMember['lastname']) {

                    // Update existing data, it must be exactly the same as in Contao
                    Harvest::updateContact($this->prepareContact($arrMember, $objContact));

                    return $objContact->id;
                }
            }
        }

        $objResult = Harvest::createContact($this->prepareContact($arrMember));

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

        $arrSubscription = Harvest::getSubscription($arrMember);

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
}

