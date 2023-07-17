<?php

$GLOBALS['TL_DCA']['tl_member_log'] = [
    'config' => [
        'dataContainer' => 'Table',
        'ptable' => 'tl_member',
        'closed' => true,
        'notEditable' => true,
        'notDeletable' => true,
        'notCopyable' => true,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => 4,
            'fields' => ['dateAdded DESC'],
            'headerFields' => ['company', 'firstname', 'lastname', 'email'],
            'flag' => 8,
            'panelLayout' => 'filter;search,limit',
        ],
        'operations' => [
            'edit' => [
                'label' => &$GLOBALS['TL_LANG']['tl_member_log']['edit'],
                'href' => 'act=edit',
                'icon' => 'edit.gif',
            ],
        ],
    ],
    'palettes' => [
        'default' => '{text_legend},text',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'pid' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'dateAdded' => [
            'label' => &$GLOBALS['TL_LANG']['tl_member_log']['dateAdded'],
            'flag' => 8,
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'user' => [
            'label' => &$GLOBALS['TL_LANG']['tl_member_log']['user'],
            'filter' => true,
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'data' => [
            'label' => &$GLOBALS['TL_LANG']['tl_member_log']['data'],
            'sql' => 'mediumblob NULL',
        ],
        'type' => [
            'label' => &$GLOBALS['TL_LANG']['tl_member_log']['type'],
            'exclude' => true,
            'filter' => true,
            'options' => ['note', 'personal_data', 'registration'],
            'reference' => &$GLOBALS['TL_LANG']['tl_member_log']['type'],
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'text' => [
            'label' => &$GLOBALS['TL_LANG']['tl_member_log']['text'],
            'exclude' => true,
            'search' => true,
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true],
            'sql' => 'text NULL',
        ],
    ],
];
