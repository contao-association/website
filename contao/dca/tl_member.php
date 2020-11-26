<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

$GLOBALS['TL_DCA']['tl_member']['config']['closed'] = true;
$GLOBALS['TL_DCA']['tl_member']['config']['ctable'][] = 'tl_member_log';

$GLOBALS['TL_DCA']['tl_member']['list']['operations']['log'] = [
    'href' => 'table=tl_member_log',
    'icon' => 'edit.svg',
];

PaletteManipulator::create()
    ->addLegend('log_legend', '')
    ->addField('member_log_note', 'log_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_member')
;

$GLOBALS['TL_DCA']['tl_member']['fields']['member_log_note'] = [
    'exclude' => true,
    'inputType' => 'textarea',
    'eval' => ['doNotSaveEmpty' => true],
    'save_callback' => [static function ($value, \Contao\DataContainer $dc) {
        $dc->createNewVersion = !empty($value);
        return null;
    }],
];
