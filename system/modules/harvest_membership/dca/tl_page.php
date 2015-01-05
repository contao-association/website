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
 * @copyright  Contao Association 2011-2013
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @license    commercial
 */


/**
 * Palettes
 */
$GLOBALS['TL_DCA']['tl_page']['palettes']['root'] .= ';{harvest_legend:hide},harvest_due,harvest_category,harvest_format,harvest_notes,harvest_mail_new,harvest_mail_recurring,harvest_mail_activated';

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_due'] = array
(
    'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_due'],
    'exclude'              => true,
    'inputType'            => 'text',
    'eval'                 => array('mandatory'=>true, 'maxlength'=>3, 'rgxp'=>'digit', 'tl_class'=>'w50'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_category'] = array
(
    'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_category'],
    'exclude'              => true,
    'inputType'            => 'text',
    'eval'                 => array('mandatory'=>true, 'maxlength'=>32, 'tl_class'=>'w50'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_format'] = array
(
    'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_format'],
    'exclude'              => true,
    'inputType'            => 'text',
    'eval'                 => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'clr long'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_notes'] = array
(
    'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_notes'],
    'exclude'              => true,
    'inputType'            => 'textarea',
    'eval'                 => array('style'=>'height:80px', 'decodeEntities'=>true, 'tl_class'=>'clr'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_mail_new'] = array
(
	'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_mail_new'],
	'inputType'            => 'select',
	'options_callback'     => array('EmailTemplateHelper', 'getMailTemplates'),
	'eval'                 => array('mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_mail_recurring'] = array
(
	'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_mail_recurring'],
	'inputType'            => 'select',
	'options_callback'     => array('EmailTemplateHelper', 'getMailTemplates'),
	'eval'                 => array('mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_mail_activated'] = array
(
	'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_mail_activated'],
	'inputType'            => 'select',
	'options_callback'     => array('EmailTemplateHelper', 'getMailTemplates'),
	'eval'                 => array('mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'),
);

$GLOBALS['TL_DCA']['tl_page']['fields']['harvest_mail_paid'] = array
(
    'label'                => &$GLOBALS['TL_LANG']['tl_page']['harvest_mail_paid'],
    'inputType'            => 'select',
    'options_callback'     => array('EmailTemplateHelper', 'getMailTemplates'),
    'eval'                 => array('mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'),
);
