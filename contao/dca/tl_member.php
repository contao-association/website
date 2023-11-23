<?php

use App\EventListener\MemberLogoListener;
use Contao\CoreBundle\EventListener\Widget\HttpUrlListener;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_member']['config']['closed'] = true;
$GLOBALS['TL_DCA']['tl_member']['config']['notCopyable'] = true;
$GLOBALS['TL_DCA']['tl_member']['config']['ctable'][] = 'tl_member_log';

$GLOBALS['TL_DCA']['tl_member']['list']['label']['fields'] = ['icon', 'company', 'firstname', 'lastname', 'membership'];
$GLOBALS['TL_DCA']['tl_member']['list']['sorting']['fields'] = ['membership_start'];

unset(
    $GLOBALS['TL_DCA']['tl_member']['list']['global_operations'],
    $GLOBALS['TL_DCA']['tl_member']['list']['operations']['copy']
);

$GLOBALS['TL_DCA']['tl_member']['list']['operations']['log'] = [
    'href' => 'table=tl_member_log',
    'icon' => 'cssimport.svg',
];

$GLOBALS['TL_DCA']['tl_member']['palettes'] = [
    '__selector__' => ['membership', 'listing'],
    'default' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,tax_id,street,postal,city,state,country;{contact_legend:hide},phone,mobile,fax,website,language;{login_legend:hide},email,password;{subscription_legend},membership,membership_member,membership_start,membership_stop,membership_invoiced,membership_interval;{listing_legend:hide},listing;{account_legend:hide},disable,start,stop;{log_legend},member_log_note',
    'support' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,tax_id,street,postal,city,state,country;{contact_legend:hide},phone,mobile,fax,website,language;{login_legend:hide},email,password;{subscription_legend},membership,membership_amount,membership_member,membership_start,membership_stop,membership_invoiced,membership_interval;{listing_legend:hide},listing;{account_legend:hide},disable,start,stop;{log_legend},member_log_note',
];

$GLOBALS['TL_DCA']['tl_member']['subpalettes']['listing'] = 'listing_name,listing_link,listing_logo';

unset(
    $GLOBALS['TL_DCA']['tl_member']['fields']['assignDir'],
    $GLOBALS['TL_DCA']['tl_member']['fields']['homeDir'],
    $GLOBALS['TL_DCA']['tl_member']['fields']['language']['options_callback']
);

$GLOBALS['TL_DCA']['tl_member']['fields']['language']['options'] = ['de' => 'Deutsch', 'en' => 'English'];
$GLOBALS['TL_DCA']['tl_member']['fields']['tstamp']['eval']['doNotLog'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['password']['eval']['doNotLog'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['trustedTokenVersion']['eval']['doNotLog'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['backupCodes']['eval']['doNotLog'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['secret']['eval']['doNotLog'] = true;

$GLOBALS['TL_DCA']['tl_member']['fields']['tax_id'] = [
    'inputType' => 'text',
    'search' => true,
    'eval' => ['maxlength' => 32, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'address', 'tl_class' => 'w50'],
    'sql' => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership'] = [
    'inputType' => 'radio',
    'filter' => true,
    'eval' => ['mandatory' => true, 'submitOnChange' => (TL_MODE === 'BE'), 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'membership', 'tl_class' => 'w50'],
    'sql' => "varchar(16) NOT NULL default 'active'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_member'] = [
    'inputType' => 'checkbox',
    'eval' => ['feEditable' => true, 'feViewable' => true, 'feGroup' => 'membership', 'tl_class' => 'w50'],
    'sql' => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_interval'] = [
    'inputType' => TL_MODE === 'BE' ? 'select' : 'radio',
    'default' => 'year',
    'options' => ['month', 'year'],
    'reference' => &$GLOBALS['TL_LANG']['tl_member']['membership_interval'],
    'eval' => ['mandatory' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'membership', 'tl_class' => 'w50'],
    'sql' => "varchar(8) NOT NULL default 'year'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_amount'] = [
    'default' => '200',
    'sorting' => true,
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'digit', 'minval' => 200, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'membership', 'tl_class' => 'w50'],
    'sql' => "int(10) NOT NULL default '200'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_start'] = [
    'exclude' => true,
    'sorting' => true,
    'flag' => DataContainer::SORT_DAY_DESC,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'clr w50 wizard'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_stop'] = [
    'exclude' => true,
    'sorting' => true,
    'flag' => DataContainer::SORT_DAY_DESC,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_invoiced'] = [
    'exclude' => true,
    'sorting' => true,
    'flag' => DataContainer::SORT_DAY_DESC,
    'inputType' => 'text',
    'eval' => ['rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
    'sql' => "varchar(10) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['member_log_note'] = [
    'inputType' => 'textarea',
    'eval' => ['doNotSaveEmpty' => true],
    'save_callback' => [static function ($value, DataContainer $dc) {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $dc->createNewVersion = $dc->createNewVersion || !empty($value);

        return null;
    }],
];

$GLOBALS['TL_DCA']['tl_member']['fields']['listing'] = [
    'label' => (TL_MODE === 'FE' ? ['', &$GLOBALS['TL_LANG']['tl_member']['listing'][1]] : [&$GLOBALS['TL_LANG']['tl_member']['listing'][0], &$GLOBALS['TL_LANG']['tl_member']['listing'][1]]),
    'inputType' => 'checkbox',
    'eval' => ['submitOnChange' => true, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'listing', 'tl_class' => 'clr'],
    'sql' => "char(1) NOT NULL default '1'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['listing_name'] = [
    'inputType' => 'text',
    'eval' => ['maxlength' => 64, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'listing', 'tl_class' => 'clr w50'],
    'sql' => "varchar(64) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['listing_link'] = [
    'inputType' => 'text',
    'eval' => ['maxlength' => 255, 'rgxp' => HttpUrlListener::RGXP_NAME, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'listing', 'tl_class' => 'w50'],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['listing_logo'] = [
    'inputType' => 'fineUploader',
    'eval' => [
        'multiple' => false,
        'storeFile' => true,
        'uploadFolder' => MemberLogoListener::UPLOAD_DIR,
        'uploaderLimit' => 1,
        'addToDbafs' => false,
        'doNotOverwrite' => true,
        'extensions' => 'jpg,jpeg,png,svg',
        'feEditable' => true,
        'feViewable' => true,
        'feGroup' => 'listing',
        'tl_class' => 'clr',
    ],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cashctrl_id'] = [
    'eval' => ['doNotShow' => true, 'doNotLog' => true],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cashctrl_associates'] = [
    'eval' => ['doNotShow' => true, 'doNotLog' => true],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cashctrl_invoice'] = [
    'eval' => ['doNotShow' => true, 'doNotLog' => true],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['harvest_client_id'] = [
    'eval' => ['doNotShow' => true, 'doNotLog' => true],
    'sql' => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['stripe_customer'] = [
    'eval' => ['doNotShow' => true, 'doNotLog' => true],
    'sql' => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['stripe_payment_method'] = [
    'eval' => ['doNotShow' => true, 'doNotLog' => true],
    'sql' => "varchar(32) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['street']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['postal']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['city']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['country']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['country']['default'] = 'ch';

$GLOBALS['TL_DCA']['tl_member']['fields']['postal']['eval']['maxlength'] = 10;

$GLOBALS['TL_DCA']['tl_member']['fields']['city']['filter'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['login']['filter'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['groups']['filter'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['email']['search'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['search'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['sorting'] = false;

$GLOBALS['TL_DCA']['tl_member']['fields']['login']['eval']['doNotShow'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['groups']['eval']['doNotShow'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['groups']['eval']['feEditable'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['doNotShow'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['feEditable'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['feViewable'] = false;

$GLOBALS['TL_DCA']['tl_member']['fields']['email']['eval']['unique'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['email']['eval']['maxlength'] = 64;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['sql'] = 'varchar(64) COLLATE utf8mb4_unicode_ci NULL';
