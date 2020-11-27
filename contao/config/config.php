<?php

/**
 * Disable maintenance and settings
 */
unset(
    $GLOBALS['BE_MOD']['design']['tpl_editor'],
    $GLOBALS['BE_MOD']['system']['settings']
);
$GLOBALS['TL_MAINTENANCE'] = ['Contao\Crawl'];


/**
 * Backend modules
 */
$GLOBALS['BE_MOD']['accounts']['member']['tables'][] = 'tl_member_log';


/**
 * Notificiation Tokens
 */
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_subject'][] = 'invoice_number';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_text'][] = 'membership_label';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_html'][] = 'membership_label';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_text'][] = 'invoice_number';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_text'][] = 'invoice_issue_date';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_text'][] = 'invoice_due_date';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_text'][] = 'invoice_amount';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_text'][] = 'invoice_url';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_html'][] = 'invoice_number';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_html'][] = 'invoice_issue_date';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_html'][] = 'invoice_due_date';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_html'][] = 'invoice_amount';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['email_html'][] = 'invoice_url';
$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration']['attachment_tokens'][] = 'invoice_pdf';
