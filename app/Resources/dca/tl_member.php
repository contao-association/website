<?php

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    // apply the field "custom_field" after the field "username"
    ->addField('steuernummer', 'gender')

    // now the field is registered in the PaletteManipulator
    // but it still has to be registered in the globals array:
    ->applyToPalette('default', 'tl_member')
;

$GLOBALS['TL_DCA']['tl_member']['fields']['steuernummer'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_member']['steuernummer'],
    'exclude'                 => true,
    'search'                  => true,
    'inputType'               => 'text',
    'eval'                    => array('feEditable' => true,'feViewable' => true,'feGroup' => 'personal','tl_class' => 'w50', 'mandatory' => false, 'minlength'=>1, 'maxlength'=>32),
    'sql'                     => "varchar(255) NOT NULL default ''"
);

//$GLOBALS['TL_DCA']['tl_member']['fields']['street']['eval']['mandatory'] = true;
