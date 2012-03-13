<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * TYPOlight webCMS
 * Copyright (C) 2005 Leo Feyer
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at http://www.gnu.org/licenses/.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2009
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 * @version    $Id: ModuleFancyAvatar.php 56 2010-03-04 22:08:54Z aschempp $
 */


class ModuleFancyAvatar extends Module
{
	protected $strTemplate = 'mod_fancyavatar';
	
	
	public function generate()
	{
		if (TL_MODE == 'BE')
		{
			$objTemplate = new BackendTemplate('be_wildcard');

			$objTemplate->wildcard = '### FANCY AVATAR ###';
			$objTemplate->title = $this->headline;
			$objTemplate->id = $this->id;
			$objTemplate->link = $this->name;
			$objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

			return $objTemplate->parse();
		}
		
		if (!FE_USER_LOGGED_IN)
			return '';
			
		$this->import('FrontendUser', 'User');
		
		return parent::generate();
	}
	
	
	protected function compile()
	{
		$objAvatar = new FormFancyAvatar(array('id'=>'avatar', 'name'=>'avatar', 'tableless'=>true, 'strTable'=>'tl_member', 'filename'=>'member_%s', 'currentRecord'=>$this->User->id, 'value'=>$this->User->avatar));
		
		$this->Template->fields = $objAvatar->parse();
	}
}

