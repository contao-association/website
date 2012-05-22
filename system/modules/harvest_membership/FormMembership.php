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


class FormMembership extends FormRadioButton
{
	
	protected $strTemplate;
	
	
	public function __construct($arrAttributes=false)
	{
		parent::__construct($arrAttributes);
		
		$this->strTemplate = TL_MODE == 'BE' ? 'be_widget_rdo' : 'form_radio';
		
		// Generate options
		$strCurrency = 'EUR';
		
		$arrMemberships = array();
		
		foreach( deserialize($GLOBALS['TL_CONFIG']['harvest_memberships']) as $arrConfig )
		{
			$strPrice = $strCurrency . ' ' . number_format($arrConfig['price'], 2, $GLOBALS['TL_LANG']['MSC']['decimalSeparator'], $GLOBALS['TL_LANG']['MSC']['thousandsSeparator']);
			
			$arrMemberships[] = array
			(
				'value'			=> $arrConfig['group'],
				'label'			=> sprintf('%s (%s%s)', $arrConfig['label'], ($arrConfig['custom'] ? 'ab ' : ''), $strPrice),
				'default'		=> $arrConfig['default'],
				'price'			=> $arrConfig['price'],
				'custom'		=> $arrConfig['custom'],
				'formatted'		=> $strPrice,
				'company'		=> $arrConfig['company'],
			);
		}
		
		$this->arrOptions = $arrMemberships;
	}
	
	
	public function __set($strKey, $varValue)
	{
		switch( $strKey )
		{
			case 'options';
				break;
			
			default:
				parent::__set($strKey, $varValue);
		}
	}
	
	
	protected function validator($varInput)
	{
		foreach ($this->arrOptions as $i=>$arrOption)
		{
			if ($varInput['membership'] == $arrOption['value'] && $arrOption['custom'] && $varInput['custom_'.$i] < $arrOption['price'])
			{
				$this->addError(sprintf('Geben Sie mindestens %s ein.', $arrOption['formatted']));
			}
		}
		
		return $varInput;
	}
	
	
	public function generate()
	{
		$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/harvest_membership/html/membership.js';
		
		$arrOptions = array();

		foreach ($this->arrOptions as $i=>$arrOption)
		{
			$arrOptions[] = sprintf('<span><input type="radio" name="%s[membership]" id="opt_%s_membership" class="%sradio" value="%s"%s%s%s%s> <label for="opt_%s_membership">%s</label>%s</span>',
									 $this->strName,
									 $this->strId.'_'.$i,
									 (TL_MODE == 'BE' ? 'tl_' : ''),
									 specialchars($arrOption['value']),
									 $this->isChecked($arrOption),
									 ' onclick="$(\'ctrl_company\')'.($arrOption['company'] ? ".set('disabled', false)" : ".set('disabled', true).set('value', '')").'"',
									 $this->getAttributes(),
									 (TL_MODE == 'BE' ? ' onfocus="Backend.getScrollOffset();"' : ''),
									 $this->strId.'_'.$i,
									 $arrOption['label'],
									 ($arrOption['custom'] ? sprintf('</span><br><span class="custom_container" id="opt_%s_custom_container"><label for="opt_%s_custom">Eigener Betrag:</label> <input type="text" name="%s[custom_%s] id="opt_%s_custom class="%stext" value="%s">',
									 									$this->strId.'_'.$i,
									 									$this->strId.'_'.$i,
									 									$this->strName,
									 									$i,
									 									$this->strId.'_'.$i,
									 									(TL_MODE == 'BE' ? 'tl_' : ''),
									 									($this->varValue['custom_'.$i] ? $this->varValue['custom_'.$i] : $arrOption['price'])) : ''));
		}

		// Add a "no entries found" message if there are no options
		if (TL_MODE == 'BE' && !count($arrOptions))
		{
			$arrOptions[] = '<p class="tl_noopt">'.$GLOBALS['TL_LANG']['MSC']['noResult'].'</p>';
		}

		return sprintf('<fieldset id="ctrl_%s" class="%sradio_container%s"><legend>%s%s</legend>%s</fieldset>%s'."
<script>
window.addEvent('domready', function() {
	new Membership('ctrl_%s');" . ((empty($this->varValue) || $this->arrOptions[$this->varValue['membership']]['company']) ? "
	$('ctrl_company').set('disabled', true);" : '') . "
});
</script>",
						$this->strId,
						(TL_MODE == 'BE' ? 'tl_' : ''),
						(($this->strClass != '') ? ' ' . $this->strClass : ''),
						$this->strLabel,
						$this->xlabel,
						implode('<br>', $arrOptions),
						$this->wizard,
						$this->strId);
	}
}

