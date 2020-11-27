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
    'active' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,street,postal,city,state,country;{contact_legend:hide},phone,mobile,website,language;{login_legend},email,password,membership;{account_legend},disable,start,stop;{log_legend},member_log_note',
    'passive' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,street,postal,city,state,country;{contact_legend:hide},phone,mobile,website,language;{login_legend},email,password,membership;{account_legend},disable,start,stop;{log_legend},member_log_note',
    'support' => '{personal_legend},firstname,lastname,dateOfBirth,gender;{address_legend:hide},company,street,postal,city,state,country;{contact_legend:hide},phone,mobile,website,language;{login_legend},email,password,membership,membership_amount;{account_legend},disable,start,stop;{log_legend},member_log_note',
];

unset(
    $GLOBALS['TL_DCA']['tl_member']['fields']['fax'],
    $GLOBALS['TL_DCA']['tl_member']['fields']['assignDir'],
    $GLOBALS['TL_DCA']['tl_member']['fields']['homeDir'],
);

$GLOBALS['TL_DCA']['tl_member']['fields']['membership'] = [
    'inputType' => 'radio',
    'filter' => true,
    'options' => ['active', 'passive', 'support'],
    'reference' => &$GLOBALS['TL_LANG']['tl_member']['membership'],
    'eval' => ['mandatory' => true, 'submitOnChange' => true, 'tl_class' => 'w50'],
    'sql' => "varchar(16) NOT NULL default 'active'",
];

$GLOBALS['TL_DCA']['tl_member']['fields']['membership_amount'] = [
    'inputType' => 'text',
    'eval' => ['mandatory' => true, 'minval' => 200, 'tl_class' => 'w50'],
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

$GLOBALS['TL_DCA']['tl_member']['fields']['street']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['postal']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['city']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['phone']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['country']['eval']['mandatory'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['country']['default'] = 'ch';

$GLOBALS['TL_DCA']['tl_member']['fields']['city']['filter'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['login']['filter'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['groups']['filter'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['email']['search'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['search'] = false;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['sorting'] = false;

$GLOBALS['TL_DCA']['tl_member']['fields']['login']['eval']['doNotShow'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['groups']['eval']['doNotShow'] = true;
$GLOBALS['TL_DCA']['tl_member']['fields']['username']['eval']['doNotShow'] = true;
