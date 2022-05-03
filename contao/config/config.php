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
unset(
    $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_registration'],
    $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['contao']['member_activation'],
);

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE']['association']['cashctrl'] = [
    'recipients'           => ['member_email', 'admin_email'],
    'email_subject'        => ['invoice_number', 'member_*', 'admin_email'],
    'email_text'           => ['membership_label', 'invoice_number', 'invoice_date', 'invoice_due_days', 'invoice_total', 'payment_first', 'payment_interval', 'payment_link', 'payment_date', 'payment_total', 'member_*', 'admin_email'],
    'email_html'           => ['membership_label', 'invoice_number', 'invoice_date', 'invoice_due_days', 'invoice_total', 'payment_first', 'payment_interval', 'payment_link', 'payment_date', 'payment_total', 'member_*', 'admin_email'],
    'email_sender_name'    => ['admin_email', 'member_*'],
    'email_sender_address' => ['admin_email', 'member_*'],
    'email_recipient_cc'   => ['admin_email', 'member_*'],
    'email_recipient_bcc'  => ['admin_email', 'member_*'],
    'email_replyTo'        => ['admin_email', 'member_*'],
    'attachment_tokens'    => ['invoice_pdf'],
];
