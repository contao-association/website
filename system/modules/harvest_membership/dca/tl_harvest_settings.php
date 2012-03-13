<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 *
 * PHP version 5
 * @copyright  Contao Verein Schweiz 2011
 * @author     Andreas Schempp <andreas.schempp@iserv.ch>
 * @license    commercial
 * @version    $Id: $
 */


/**
 * System configuration for Harvest
 */
$GLOBALS['TL_DCA']['tl_harvest_settings'] = array
(

	// Config
	'config' => array
	(
		'dataContainer'               => 'File',
	),

	// Palettes
	'palettes' => array
	(
		'default'                     => '{account_legend:hide},harvest_account,harvest_user,harvest_password;{memberships_legend},harvest_memberships',
	),

	// Fields
	'fields' => array
	(
		'harvest_account' => array
		(
			'label'			=> &$GLOBALS['TL_LANG']['tl_harvest_settings']['harvest_account'],
			'inputType'		=> 'text',
			'eval'			=> array('mandatory'=>true, 'rgxp'=>'alnum', 'tl_class'=>'clr'),
		),
		'harvest_user' => array
		(
			'label'			=> &$GLOBALS['TL_LANG']['tl_harvest_settings']['harvest_user'],
			'inputType'		=> 'text',
			'eval'			=> array('mandatory'=>true, 'rgxp'=>'email', 'tl_class'=>'w50'),
		),
		'harvest_password' => array
		(
			'label'			=> &$GLOBALS['TL_LANG']['tl_harvest_settings']['harvest_password'],
			'inputType'		=> 'text',
			'eval'			=> array('mandatory'=>true, 'encrypt'=>true, 'hideInput'=>true, 'tl_class'=>'w50'),
		),
		'harvest_memberships' => array
		(
			'label'					=> &$GLOBALS['TL_LANG']['tl_harvest_settings']['harvest_memberships'],
			'exclude'				=> true,
			'inputType'				=> 'multiColumnWizard',
			'eval'					=> array
			(
				'tl_class'			=> 'clr',
				'style'				=> 'width:100%;',
				'blnSaveInLocalConfig'	=> true,
				'columnFields' 		=> array
				(
					'group' => array
					(
						'label'					=> array('Mitgliedergruppe'),
						'inputType'				=> 'select',
						'foreignKey'			=> 'tl_member_group.name',
						'eval'					=> array('includeBlankOption'=>true, 'style'=>'width:120px'),
					),
					'label' => array
					(
						'label'					=> array('Bezeichnung'),
						'inputType'				=> 'text',
						'eval'					=> array('style'=>'width:140px'),
					),
					'price' => array
					(
						'label'					=> array('Preis'),
						'inputType'				=> 'text',
						'eval'					=> array('rgxp'=>'digit', 'style'=>'width:40px;text-align:center'),
					),
					'default' => array
					(
						'label'					=> array(''),
						'inputType'				=> 'checkbox',
						'options'				=> array('1'=>'Standardwert'),
					),
					'custom' => array
					(
						'label'					=> array(''),
						'inputType'				=> 'checkbox',
						'options'				=> array('1'=>'Mindestpreis'),
					),
					'company' => array
					(
						'label'					=> array(''),
						'inputType'				=> 'checkbox',
						'options'				=> array('1'=>'Firmen OK'),
					),
				),
			),
		),
	),
);

