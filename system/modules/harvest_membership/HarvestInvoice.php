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


class HarvestInvoice extends Controller
{

    public function __construct()
    {
        parent::__construct();

        $this->import('Database');

        // Make sure Harvest classes are available
        Harvest::getAPI();
    }

    /**
     * Create new invoice
     * @param   array   tl_member data
     * @param   array   subscription data (see Harvest::getSubscription)
     * @return  int     ID of the new Harvest invoice
     */
    public function createMembershipInvoice($arrMember, $arrSubscription)
    {
        $arrConfig = $this->getRootPage($arrMember['language']);

        // Create invoice
        $objInvoice = new Harvest_Invoice();
        $objInvoice->issued_at = date('Y-m-d');
        $objInvoice->due_at = date('Y-m-d', strtotime('+'.$arrConfig['harvest_due'].' days'));
        $objInvoice->due_at_human_format = 'custom';
        $objInvoice->client_id = $arrMember['harvest_client_id'];
        $objInvoice->number = $arrMember['id'] . '/' . date('Y');
        $objInvoice->kind = 'free_form';
        $objInvoice->purchase_order = $arrMember['id'];
        $objInvoice->notes = $arrConfig['harvest_notes'];
        $objInvoice->csv_line_items =
'
kind,description,quantity,unit_price,amount,taxed,taxed2,project_id
'.$arrConfig['harvest_category'].',' . sprintf($arrConfig['harvest_format'], $arrSubscription['label']) . ',1.00,' . $arrSubscription['price'] . ',' . $arrSubscription['price'] . ',false,false,1
  ';

        $objResult = Harvest::createInvoice($objInvoice);

        if (!$objResult->isSuccess()) {
            $this->log('Unable to create Harvest invoice for member ID '.$arrMember['id'].' (Error '.$objResult->code.')', __METHOD__, TL_ERROR);
            return 0;
        }

        return $objResult->data;
    }

    /**
     * Send invoice to client
     * @param   int     Harvest invoice ID
     * @param   string  Email address of recipient
     * @param   bool    Send recurring message
     */
    public function sendInvoice($intId, $strRecipient, $blnRecurring=false)
    {
        $objMessage = new Harvest_InvoiceMessage();
        $objMessage->invoice_id = $intInvoice;
        $objMessage->attach_pdf = true;
        $objMessage->send_me_a_copy = true;
        $objMessage->include_pay_pal_link = true;
        $objMessage->recipients = $strRecipient;
        $objMessage->body = $arrConfig['harvest_message'];

        $objResult = Harvest::sendInvoiceMessage($intInvoice, $objMessage);

        if (!$objResult->isSuccess()) {
            $this->log('Unable to send Harvest invoice to '.$strRecipient.' (Error '.$objResult->code.')', __METHOD__, TL_ERROR);
            return false;
        }

        return true;
    }


    /**
     * Get invoice config from page settings
     * @param   string
     */
    protected function getRootPage($strLanguage)
    {
        global $objPage;

        $intPage = (is_object($objPage) ? (int) $objPage->rootId : 0);

        return $this->Database->prepare("SELECT * FROM tl_page WHERE id=? OR language=? OR fallback='1'")
                              ->limit(1)
                              ->execute($intPage, $strLanguage)
                              ->fetchAssoc();
    }
}