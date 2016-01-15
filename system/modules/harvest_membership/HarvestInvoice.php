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
    private $db;
    private $fibu3;

    public function __construct()
    {
        parent::__construct();

        $period = deserialize($GLOBALS['TL_CONFIG']['fibu3_period']);

        $this->db = Database::getInstance();
        $this->fibu3 = new FIBU3(
            $GLOBALS['TL_CONFIG']['fibu3_apikey'],
            DateTime::createFromFormat($GLOBALS['TL_CONFIG']['dateFormat'], $period[0]),
            DateTime::createFromFormat($GLOBALS['TL_CONFIG']['dateFormat'], $period[1])
        );

        // Make sure Harvest classes are available
        Harvest::getAPI();
    }

    /**
     * Create new invoice
     *
     * @param array $arrMember       tl_member data
     * @param array $arrSubscription subscription data (see Harvest::getSubscription)
     *
     * @return int                   ID of the new Harvest invoice
     */
    public function createMembershipInvoice($arrMember, $arrSubscription)
    {
        $arrConfig = $this->getRootPage($arrMember['language']);

        $invoiceId   = $arrMember['id'] . '/' . date('Y');
        $invoiceDate = new DateTime();

        // Create invoice
        $objInvoice = new Harvest_Invoice();
        $objInvoice->issued_at = $invoiceDate->format('Y-m-d');
        $objInvoice->due_at = date('Y-m-d', strtotime('+'.$arrConfig['harvest_due'].' days'));
        $objInvoice->due_at_human_format = 'custom';
        $objInvoice->client_id = $arrMember['harvest_client_id'];
        $objInvoice->number = $invoiceId;
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
            $GLOBALS['SENTRY_CLIENT']->getIdent(
                $GLOBALS['SENTRY_CLIENT']->captureMessage('Unable to create Harvest invoice for member ID '.$arrMember['id'].' (Error '.$objResult->code.')')
            );
            $this->log('Unable to create Harvest invoice for member ID '.$arrMember['id'].' (Error '.$objResult->code.')', __METHOD__, TL_ERROR);
            return 0;
        }

        try {
            $this->fibu3->book(
                sprintf('%s - %s', $invoiceId, Harvest::generateClientName($arrMember, $arrSubscription)),
                $invoiceDate,
                $arrSubscription['price'],
                '1100',
                $arrSubscription['account']
            );
        } catch (\Exception $e) {
            $GLOBALS['SENTRY_CLIENT']->getIdent($GLOBALS['SENTRY_CLIENT']->captureException($e));
            $this->log($e->getMessage(), __METHOD__, TL_ERROR);
        }

        return $objResult->data;
    }

    /**
     * Send email template for new invoices
     *
     * @param int   $intInvoice Harvest invoice ID
     * @param array $arrMember  tl_member data
     *
     * @return bool
     */
    public function sendNewInvoiceMail($intInvoice, $arrMember)
    {
        return $this->sendInvoiceMail($intInvoice, $arrMember, 'harvest_mail_new');
    }

    /**
     * Send email template for recurring invoices
     *
     * @param int   $intInvoice Harvest invoice ID
     * @param array $arrMember  tl_member data
     *
     * @return bool
     */
    public function sendRecurringInvoiceMail($intInvoice, $arrMember)
    {
        return $this->sendInvoiceMail($intInvoice, $arrMember, 'harvest_mail_recurring');
    }

    /**
     * Send recurring invoices
     */
    public function sendRecurringInvoices()
    {
        $time = time();

        // Find members which have been added today some time ago
        // @see http://stackoverflow.com/a/2218577
        $objMembers = $this->db->query("
            SELECT *
            FROM tl_member
            WHERE (
                DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')
                OR (
                    (
                        DATE_FORMAT(NOW(),'%Y') % 4 <> 0
                        OR (
                            DATE_FORMAT(NOW(),'%Y') % 100 = 0
                            AND DATE_FORMAT(NOW(),'%Y') % 400 <> 0
                        )
                    )
                    AND DATE_FORMAT(NOW(),'%m-%d') = '03-01'
                    AND DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%m-%d') = '02-29'
                )
            )
            AND DATE_FORMAT(FROM_UNIXTIME(dateAdded),'%Y-%m-%d') != DATE_FORMAT(NOW(),'%Y-%m-%d')
            AND disable=''
            AND (start='' OR start<$time)
            AND (stop='' OR stop>$time)
        ");

        while ($objMembers->next()) {
            $arrMember = $objMembers->row();
            $intInvoice = $this->createMembershipInvoice($arrMember, Harvest::getSubscription($arrMember));
            $this->sendRecurringInvoiceMail($intInvoice, $arrMember);
        }
    }

    /**
     * Automatically enable members if registration invoice has been paid
     */
    public function activateMembers()
    {
        // Only retrieve 10 members to prevent performance issues with the API
        $objMembers = $this->db->execute("SELECT * FROM tl_member WHERE disable='1' AND harvest_invoice>0 LIMIT 10");

        while ($objMembers->next()) {
            $objResult = Harvest::getInvoice($objMembers->harvest_invoice);

            if ($objResult->isSuccess() && $objResult->data->state == 'paid') {
                $this->db->prepare("UPDATE tl_member SET disable='', harvest_invoice=0 WHERE id=?")->executeUncached($objMembers->id);

                $objInvoice = $objResult->data;
                $arrRoot = $this->getRootPage($objMembers->language);

                try {
                    $objEmail = new EmailTemplate($arrRoot['harvest_mail_activated'], $arrRoot['language']);
                    $objEmail->send($objMembers->email, $this->getInvoiceTokens($objMembers->row(), $objInvoice));
                } catch (Exception $e) {
                    $GLOBALS['SENTRY_CLIENT']->getIdent($GLOBALS['SENTRY_CLIENT']->captureException($e));
                    $this->log($e->getMessage(), __METHOD__, TL_ERROR);
                }
            }
        }
    }

    /**
     * Send email confirmation for invoice payments
     */
    public function notifyPayments()
    {
        $lastRun = (int) $this->db->prepare(
            "SELECT tstamp FROM tl_lock WHERE name=?"
        )->limit(1)->executeUncached('harvest_payments')->tstamp;

        // Don't start job from 1970…
        if (0 === $lastRun) {
            return;
        }

        $lastRun = DateTime::createFromFormat('YmdH', $lastRun, new DateTimeZone('UTC'));
        $stop = clone $lastRun;
        $stop = $stop->add(new DateInterval('PT1H'));

        if ($stop->getTimestamp() > DateTime::createFromFormat('U', time(), new DateTimeZone('UTC'))->getTimestamp()) {
            return;
        }

        Harvest::getAPI();
        $objFilter                = new Harvest_Invoice_Filter();
        $objFilter->status        = Harvest_Invoice_Filter::PAID;
        $objFilter->updated_since = $lastRun->format('Y-m-d H:i');
        $objResult                = Harvest::getInvoices($objFilter);

        if ($objResult->isSuccess()) {

            /** @var Harvest_Invoice $objInvoice */
            foreach ($objResult->data as $objInvoice) {

                $objMember = $this->db->prepare(
                    "SELECT * FROM tl_member WHERE harvest_client_id=?"
                )->execute($objInvoice->client_id);

                // Do not send payment confirmation for first invoice (account activation)
                if (!$objMember->numRows || $objInvoice->id == $objMember->harvest_invoice) {
                    continue;
                }

                $objPayments = Harvest::getInvoicePayments($objInvoice->id);

                if (!$objPayments->isSuccess()) {
                    continue;
                }

                foreach ($objPayments->data as $objPayment) {
                    $created = new DateTime($objPayment->created_at, new DateTimeZone('UTC'));

                    if ($created->getTimestamp() >= $lastRun->getTimestamp() && $created->getTimestamp() < $stop->getTimestamp()) {
                        $arrRoot = $this->getRootPage($objMember->language);

                        try {
                            $objEmail = new EmailTemplate($arrRoot['harvest_mail_paid'], $arrRoot['language']);
                            $objEmail->send($objMember->email, $this->getInvoiceTokens($objMember->row(), $objInvoice, $objPayment));
                        } catch (Exception $e) {
                            $GLOBALS['SENTRY_CLIENT']->getIdent($GLOBALS['SENTRY_CLIENT']->captureException($e));
                            $this->log('Unable to send payment mail: ' . $e->getMessage(), __METHOD__, TL_ERROR);
                        }

                        break;
                    }
                }
            }

            $this->db->prepare(
                "UPDATE tl_lock SET tstamp=? WHERE name=?"
            )->execute($stop->format('YmdH'), 'harvest_payments');
        }
    }

    /**
     * Get invoice config from page settings
     *
     * @param string $strLanguage
     *
     * @return array
     */
    protected function getRootPage($strLanguage)
    {
        global $objPage;

        $intPage = (is_object($objPage) ? (int) $objPage->rootId : 0);

        return $this->db->prepare("SELECT * FROM tl_page WHERE type='root' AND (id=? OR language=? OR fallback='1')")
                              ->limit(1)
                              ->execute($intPage, $strLanguage)
                              ->fetchAssoc();
    }

    /**
     * Generate the list of simple tokens for email templates
     *
     * @param array  $arrMember
     * @param object $objInvoice
     * @param object $objPayment
     *
     * @return array
     */
    protected function getInvoiceTokens($arrMember, $objInvoice, $objPayment = null)
    {
        $arrConfig = $this->getRootPage($arrMember['language']);
        $strDateFormat = $arrConfig['dateFormat'] ?: $GLOBALS['TL_CONFIG']['dateFormat'];

        $arrTokens = $arrMember;
        $arrTokens['invoice_amount'] = $this->getFormattedNumber($objInvoice->amount);
        $arrTokens['invoice_number'] = $objInvoice->number;
        $arrTokens['invoice_issued_at'] = $this->parseDate($strDateFormat, strtotime($objInvoice->issued_at));
        $arrTokens['invoice_due_at'] = $this->parseDate($strDateFormat, strtotime($objInvoice->due_at));
        $arrTokens['invoice_url'] = 'https://' . $GLOBALS['TL_CONFIG']['harvest_account'] . '.harvestapp.com/client/invoices/' . $objInvoice->client_key;

        if (null !== $objPayment) {
            $arrTokens['payment_amount'] = $this->getFormattedNumber($objPayment->amount);
            $arrTokens['payment_created_at'] = $this->parseDate($strDateFormat, strtotime($objPayment->created_at));
            $arrTokens['payment_paid_at'] = $this->parseDate($strDateFormat, strtotime($objPayment->paid_at));
            $arrTokens['payment_recorded_by'] = $objPayment->recorded_by;
            $arrTokens['payment_paypal_id'] = $objPayment->pay_pal_transaction_id;
        }

        // Enrich member data / email tokens
        $arrSubscription = Harvest::getSubscription($arrMember);
        $arrTokens['subscription_label'] = $arrSubscription['label'];
        $arrTokens['subscription_price'] = $arrSubscription['price'];

        return $arrTokens;
    }

    /**
     * Send invoice mail to client
     *
     * @param int    $intInvoice     Harvest invoice ID
     * @param array  $arrMember      tl_member data
     * @param string $strTemplateKey Name of template ID key in tl_page
     *
     * @return bool
     */
    protected function sendInvoiceMail($intInvoice, $arrMember, $strTemplateKey)
    {
        $objResult = Harvest::getInvoice($intInvoice);

        if ($objResult->isSuccess()) {
            $objInvoice = $objResult->data;
            $arrRoot = $this->getRootPage($arrMember['language']);

            try {
                $objEmail = new EmailTemplate($arrRoot[$strTemplateKey], $arrRoot['language']);
                $objEmail->send($arrMember['email'], $this->getInvoiceTokens($arrMember, $objInvoice));

                $objMessage = new Harvest_InvoiceMessage();
                $objMessage->invoice_id = $objInvoice->id;
                $objMessage->attach_pdf = true;
                $objMessage->recipients = $GLOBALS['TL_CONFIG']['adminEmail'];
                $objMessage->body = 'Rechnung #' . $objInvoice->number;

                Harvest::sendInvoiceMessage($objInvoice->id, $objMessage);

                return true;
            } catch (Exception $e) {
                $GLOBALS['SENTRY_CLIENT']->getIdent($GLOBALS['SENTRY_CLIENT']->captureException($e));
            }
        }

        $GLOBALS['SENTRY_CLIENT']->getIdent(
            $GLOBALS['SENTRY_CLIENT']->captureMessage('Unable to send invoice email to "' . $arrMember['email'] . '" (Invoice ID ' . $intInvoice . ')')
        );
        $this->log('Unable to send invoice email to "' . $arrMember['email'] . '" (Invoice ID ' . $intInvoice . ')', __METHOD__, TL_ERROR);

        return false;
    }
}
