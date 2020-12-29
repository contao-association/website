<?php

$GLOBALS['TL_DCA']['tl_member']['config']['closed'] = true;
$GLOBALS['TL_DCA']['tl_member']['config']['notCopyable'] = true;
$GLOBALS['TL_DCA']['tl_member']['config']['ctable'][] = 'tl_member_log';

$GLOBALS['TL_DCA']['tl_member']['list']['label']['fields'] = array('icon', 'company', 'firstname', 'lastname');

unset(
    $GLOBALS['TL_DCA']['tl_member']['list']['global_operations'],
    $GLOBALS['TL_DCA']['tl_member']['list']['operations']['copy']
);

$GLOBALS['TL_DCA']['tl_member']['list']['operations']['log'] = [
    'href' => 'table=tl_member_log',
    'icon' => 'edit.svg',
];

$GLOBALS['TL_DCA']['tl_member']['palettes'] = [
    '__selector__' => ['membership'],
    'default' => 'membership',
    'active' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,tax_id,street,postal,city,state,country;{contact_legend:hide},phone,mobile,fax,website,language;{login_legend},email,password,membership;{account_legend},disable,start,stop;{log_legend},member_log_note',
    'passive' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,tax_id,street,postal,city,state,country;{contact_legend:hide},phone,mobile,fax,website,language;{login_legend},email,password,membership;{account_legend},disable,start,stop;{log_legend},member_log_note',
    'support' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,tax_id,street,postal,city,state,country;{contact_legend:hide},phone,mobile,fax,website,language;{login_legend},email,password,membership,membership_amount;{account_legend},disable,start,stop;{log_legend},member_log_note',
];

unset(
    $GLOBALS['TL_DCA']['tl_member']['fields']['assignDir'],
    $GLOBALS['TL_DCA']['tl_member']['fields']['homeDir'],
    $GLOBALS['TL_DCA']['tl_member']['fields']['language']['options_callback']
);

$GLOBALS['TL_DCA']['tl_member']['fields']['language']['options'] = ['de' => 'Deutsch', 'en' => 'English'];

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

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_amount'] = [
    'default' => '200',
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'rgxp' => 'digit', 'minval' => 200, 'feEditable' => true, 'feViewable' => true, 'feGroup' => 'membership', 'tl_class' => 'w50'],
    'sql' => "int(10) NOT NULL default '200'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['member_log_note'] = [
    'inputType' => 'textarea',
    'eval' => ['doNotSaveEmpty' => true],
    'save_callback' => [static function ($value, \Contao\DataContainer $dc) {
        $dc->createNewVersion = $dc->createNewVersion || !empty($value);
        return null;
    }],
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cashctrl_id'] = [
    'eval' => ['doNotShow' => true],
    'sql' => "int(10) unsigned NOT NULL default '0'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['cashctrl_invoice'] = [
    'eval' => ['doNotShow' => true],
    'sql' => "int(10) unsigned NOT NULL default '0'",
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
