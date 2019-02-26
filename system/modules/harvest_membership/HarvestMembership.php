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

    public function __construct()
    {
        parent::__construct();

        $this->import('Database');

        // Make sure Harvest classes are available
        Harvest::getAPI();
    }

    /**
     * Create and send invoice to new users
     *
     * @param int   $intId
     * @param array $arrMemberData
     */
    public function createAndInvoiceNewClient($intId, &$arrMemberData)
    {
        $arrMember = $arrMemberData;

        if (is_array($arrMember['harvest_membership'])) {

            $arrMember['id'] = $intId;

            $arrMember = Harvest::prepareData($arrMember);
            $arrSubscription = Harvest::getSubscription($arrMember);

            if ($arrSubscription === false) {
                $GLOBALS['SENTRY_CLIENT']->getIdent(
                    $GLOBALS['SENTRY_CLIENT']->captureMessage('Unable to generate Harvest client for member ID '.$arrMember['id'].' (no membership found)')
                );
                $this->log(('Unable to generate Harvest client for member ID '.$arrMember['id'].' (no membership found)'), __METHOD__, TL_ERROR);
                return;
            }

            $arrMember['harvest_client_id'] = $this->createClient($arrMember, $arrSubscription);
            $arrMember['harvest_id'] = $this->createContact($arrMember['harvest_client_id'], $arrMember);

            if ($arrMember['harvest_client_id'] < 1 || $arrMember['harvest_id'] < 1) {
                $GLOBALS['SENTRY_CLIENT']->getIdent(
                    $GLOBALS['SENTRY_CLIENT']->captureMessage('Error creating client/contact in Harvest for member ID '.$arrMember['id'])
                );
                $this->log('Error creating client/contact in Harvest for member ID '.$arrMember['id'], __METHOD__, TL_ERROR);
                return;
            }

            // Create and send membership invoice
            $objInvoice = new HarvestInvoice();
            $intInvoice = $objInvoice->createMembershipInvoice($arrMember, $arrSubscription);

            if ($intInvoice > 0) {
                $objInvoice->sendNewInvoiceMail($intInvoice, $arrMember);
            }

            // Assign member to the designated groups
            $arrGroups = deserialize($arrMember['groups'], true);
            $arrGroups[] = $arrSubscription['group'];
            $this->Database->prepare("UPDATE tl_member SET harvest_client_id=?, harvest_id=?, harvest_invoice=?, groups=?, language=? WHERE id=?")
                           ->executeUncached($arrMember['harvest_client_id'], $arrMember['harvest_id'], $intInvoice, serialize($arrGroups), $GLOBALS['TL_LANGUAGE'], $arrMember['id']);

            // Update member groups so other modules like associategroups can perform actions
            $arrMemberData['groups'] = serialize($arrGroups);
        }
    }


    /**
     * Prevent duplicate member name in Harvest when submitting backend fields
     *
     * @param mixed  $varValue
     * @param object $dc
     *
     * @return mixed
     * @throws Exception
     */
    public function preventDuplicateMember($varValue, $dc)
    {
        $arrMember = array(
            'company'               => $this->Input->post('company'),
            'firstname'             => $this->Input->post('firstname'),
            'lastname'              => $this->Input->post('lastname'),
            'harvest_membership'    => $this->Input->post('harvest_membership'),
        );

        $strName = Harvest::generateClientName($arrMember, Harvest::getSubscription($arrMember));
        $intId = array_search($strName, Harvest::getClientLookupTable());

        if ($intId !== false && $intId != $dc->activeRecord->harvest_client_id) {
            throw new Exception('Mitglied bereits vorhanden.');
        }

        return $varValue;
    }

    /**
     * Create a member data backup in the session
     */
    public function backupInSession()
    {
        if (FE_USER_LOGGED_IN === true) {
            $_SESSION['OLD_MEMBER_DATA'] = FrontendUser::getInstance()->getData();
        }
    }

    /**
     * Update Harvest client and contact when saving a member
     *
     * @param DataContainer|FrontendUser $dc
     */
    public function updateMember($dc)
    {
        $arrMember = (TL_MODE == 'BE' ? $dc->activeRecord->row() : $dc->getData());
        $arrMember = Harvest::prepareData($arrMember);

        // Cannot update without Harvest link
        if ($arrMember['harvest_client_id'] < 1 || $arrMember['harvest_id'] < 1) {
            return;
        }

        // Prevent duplicate members in frontend, reset if necessary
        if (TL_MODE == 'FE' && is_array($_SESSION['OLD_MEMBER_DATA'])) {
            $strName = Harvest::generateClientName($arrMember, Harvest::getSubscription($arrMember));
            $intId = array_search($strName, Harvest::getClientLookupTable());

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
     * Search for client ID on Harvest, create client if none exists
     *
     * @param array $arrMember       tl_member data
     * @param array $arrSubscription subscription configuration
     *
     * @return int                   ID of the client record
     */
    protected function createClient($arrMember, $arrSubscription)
    {
        $arrClients = Harvest::getClientLookupTable();

        $strName = Harvest::generateClientName($arrMember, $arrSubscription);

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
            $GLOBALS['SENTRY_CLIENT']->getIdent(
                $GLOBALS['SENTRY_CLIENT']->captureMessage('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')')
            );
            $this->log(('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')'), __METHOD__, TL_ERROR);
            return 0;
        }

        $arrClients = Harvest::getClientLookupTable();

        if (($intId = array_search($strName, $arrClients)) !== false) {
            return $intId;
        }

        return (int) $objResult->data;
    }

    /**
     * Create client contact in Harvest if it does not yet exist
     *
     * @param int   $intClient Harvest client ID
     * @param array $arrMember tl_member record data
     *
     * @return int             ID of the contact record
     */
    protected function createContact($intClient, $arrMember)
    {
        if (!$intClient) {
            return 0;
        }

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
            $GLOBALS['SENTRY_CLIENT']->getIdent(
                $GLOBALS['SENTRY_CLIENT']->captureMessage('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')')
            );
            $this->log(('Unable to create Harvest client for member ID '.$arrMember['id'].' (Error '.$objResult->code.')'), __METHOD__, TL_ERROR);
            return 0;
        }

        return (int) $objResult->data;
    }

    /**
     * Create/update Harvest client object from member data
     *
     * @param array  $arrMember
     * @param object $objClient
     *
     * @return object
     */
    protected function prepareClient($arrMember, $objClient=null)
    {
        if (null === $objClient) {
            $objClient = new Harvest_Client();
        }

        $arrCountries = $this->getCountries();
        $arrSubscription = Harvest::getSubscription($arrMember);

        $objClient->name = ampersand(Harvest::generateClientName($arrMember, $arrSubscription));

        if ($arrSubscription['company']) {
            $objClient->details = ampersand(sprintf("%s%s\n%s %s%s",
                ($arrMember['company'] ? $arrMember['firstname'].' '.$arrMember['lastname']."\n" : ''),
                $arrMember['street'],
                $arrMember['postal'],
                $arrMember['city'],
                ($arrMember['country'] == 'ch' ? '' : "\n".$arrCountries[$arrMember['country']]))
            );
        } else {
            $objClient->details = ampersand(sprintf("%s%s\n%s %s%s",
                ($arrMember['company'] ? $arrMember['company']."\n" : ''),
                $arrMember['street'],
                $arrMember['postal'],
                $arrMember['city'],
                ($arrMember['country'] == 'ch' ? '' : "\n".$arrCountries[$arrMember['country']]))
            );
        }

        return $objClient;
    }

    /**
     * Update a Harvest contact from member data
     *
     * @param array  $arrMember
     * @param object $objContact
     *
     * @return object
     * @throws Exception
     */
    protected function prepareContact($arrMember, $objContact = null)
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

