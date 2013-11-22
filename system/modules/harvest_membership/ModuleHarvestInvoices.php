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
 * @copyright  terminal42 gmbh 2013
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class ModuleHarvestInvoices extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_harvest_invoices';


    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### HARVEST INVOICES ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = $this->Environment->script.'?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        if (FE_USER_LOGGED_IN !== true) {
            return '';
        }

        $this->import('FrontendUser', 'User');

        return parent::generate();
    }


    protected function compile()
    {
        if (!$this->User->harvest_client_id) {
            return $this->message('Ihr Konto wurde im Rechnungssystem nicht gefunden.');
        }

        Harvest::getAPI();
        $objFilter = new Harvest_Invoice_Filter();
        $objFilter->client = $this->User->harvest_client_id;
        $objResult = Harvest::getInvoices($objFilter);

        if (!$objResult->isSuccess()) {
            return $this->message('Bei der Kommunikation mit dem Rechnungssystem ist ein Fehler aufgetreten.');
        }

        $arrInvoices = array();

        foreach ($objResult->data as $objInvoice) {

            switch ($objInvoice->state) {
                case 'draft':
                case 'closed':
                    continue;

                case 'open':
                case 'partial':
                    $objInvoice->state_label = $GLOBALS['TL_LANG']['HAPI'][$objInvoice->state];

                    if (strtotime($objInvoice->due_at) < mktime(0,0,0)) {
                        $objInvoice->state = 'late';
                        $objInvoice->state_label = $GLOBALS['TL_LANG']['HAPI']['late'];
                    }
                    break;

                case 'paid':
                    $objInvoice->state_label = $GLOBALS['TL_LANG']['HAPI'][$objInvoice->state];
                    break;
            }

            $objInvoice->url = 'https://' . $GLOBALS['TL_CONFIG']['harvest_account'] . '.harvestapp.com/client/invoices/' . $objInvoice->client_key;
            $objInvoice->issued_at = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], strtotime($objInvoice->issued_at));
            $objInvoice->due_at = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], strtotime($objInvoice->due_at));

            $arrInvoices[] = $objInvoice;
        }

        if (empty($arrInvoices)) {
            return $this->message('Es wurden keine Rechnungen gefunden.', 'empty');
        }

        $this->Template->invoices = $objResult->data;
    }


    protected function message($strMessage, $strType='error')
    {
        $this->Template = new FrontendTemplate('mod_message');

        $this->Template->type = $strType;
        $this->Template->message = $strMessage;
    }
}

