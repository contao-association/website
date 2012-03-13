<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2010 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  Andreas Schempp 2009-2010
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 * @version    $Id: FancyAvatar.php 124 2010-07-08 17:05:11Z aschempp $
 */


class FancyAvatar extends Widget
{
	
	/**
	 * Template
	 * @var string
	 */
	protected $strTemplate = 'be_widget';
	
	
	/**
	 * Make sure we know the ID for ajax upload session data
	 * @param array
	 */
	public function __construct($arrAttributes=false)
	{
		if (!$arrAttributes)
			parent::__construct(false);
		
		$this->strId = $arrAttributes['id'];
		$_SESSION['AJAX-FFL'][$this->strId] = array('type'=>'avatar');
		
		parent::__construct($arrAttributes);
	}
	
	
	/**
	 * Store config for ajax upload.
	 * 
	 * @access public
	 * @param string $strKey
	 * @param mixed $varValue
	 * @return void
	 */
	public function __set($strKey, $varValue)
	{
		$_SESSION['AJAX-FFL'][$this->strId][$strKey] = $varValue;
		parent::__set($strKey, $varValue);
	}
	
	
	public function generate()
	{
		$this->filename = $this->filename ? $this->filename : $this->strTable.'_%s';
		$this->maxdims = $this->maxdims ? $this->maxdims : deserialize($GLOBALS['TL_CONFIG']['avatar_maxdims'], true);
		$this->maxsize = $this->maxsize ? $this->maxsize : $GLOBALS['TL_CONFIG']['avatar_maxsize'];
		
		if (!$this->maxdims[1])
		{
			$arrSize = $this->maxdims;
			$arrSize[1] = $arrSize[0];
			$this->maxdims = $arrSize;
		}
		
		
		$GLOBALS['TL_CSS'][] = 'system/modules/fancyavatar/html/fancyavatar.css';
		$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/fancyavatar/html/bycropper.js';
		$GLOBALS['TL_JAVASCRIPT'][] = 'plugins/fancyupload/js/fancyupload.js';
		
		
		$strBuffer = sprintf('<div id="fancyavatar_%s" class="fancyavatar" style="background-image:url(%s); height:%spx; width:%spx"><a href="#" id="select-%s" class="upload">%s</a><a class="delete" href="#"><img src="system/themes/default/images/delete.gif"></a></div>',
						$this->strId,
						(is_file(TL_ROOT . '/' . $this->varValue) ? $this->getImage($this->varValue, $this->maxdims[0], $this->maxdims[1]) : $this->getDefaultAvatar()),
						($this->maxdims[1] + 25),
						($this->maxdims[0] + 2),
						$this->strId,
						$GLOBALS['TL_LANG']['MSC']['avatar_upload']);
						
						
		
		$strBuffer .= "
<script type=\"text/javascript\">
<!--//--><![CDATA[//><!--
" . (TL_MODE == 'FE' ? "var REQUEST_TOKEN = '".REQUEST_TOKEN."';" : '') . "
window.addEvent('domready', function() {
 
	var link = $('select-" . $this->strId . "');
	var linkIdle = link.get('html');
 
	function linkUpdate() {
		if (!swf.uploading) return;
		var size = Swiff.Uploader.formatUnit(swf.size, 'b');
		link.set('html', '<span class=\"small\">' + swf.percentLoaded + '% of ' + size + '</span>');
	}
 
	// Uploader instance
	var swf = new Swiff.Uploader({
		path: '" . $this->Environment->base . "plugins/fancyupload/Swiff.Uploader.swf',
		url: 'ajax.php?action=ffl&id=" . $this->strId . "&do=upload&" . session_name() . "=" . session_id() . (FE_USER_LOGGED_IN ? "&FE_USER_AUTH=" . $this->Input->cookie('FE_USER_AUTH') : '') . "&language=" . $GLOBALS['TL_LANGUAGE'] . "&bypassToken=1',
		data: ('REQUEST_TOKEN='+REQUEST_TOKEN),
		queued: false,
		multiple: false,
		target: link,
		instantStart: true,
		fieldName: '" . $this->strName . "',
		typeFilter: {
			'Images (*." . implode(', *.', trimsplit(',', $GLOBALS['TL_CONFIG']['validImageTypes'])) . ")': '*." . implode('; *.', trimsplit(',', $GLOBALS['TL_CONFIG']['validImageTypes'])) . ")'
		},
		fileSizeMax: " . $this->maxsize . ",
		onSelectSuccess: function(files) {
			this.setEnabled(false);
		},
		onSelectFail: function(files) {
			if( files[0].validationError == 'sizeLimitMax' ) {
				alert('" . sprintf($GLOBALS['TL_LANG']['ERR']['avatar_size'], round(($this->maxsize/1024), 2)) . "');
			}
			else {
				alert('" . $GLOBALS['TL_LANG']['ERR']['avatar_general'] . " (#' + files[0].validationError + ')');
			}
		},
		appendCookieData: true,
		onQueue: linkUpdate,
		onFileComplete: function(file) {

			if (file.response.error) {
				alert('" . $GLOBALS['TL_LANG']['ERR']['avatar_upload'] . "');
				console.log(file.response);
			} else {

				var json = JSON.decode(file.response.text);
				
				// Automatically set the new request token
				if (json.token)
				{
					REQUEST_TOKEN = json.token;

					// Update all forms
					$$('input[type=\"hidden\"]').each(function(el)
					{
						if (el.name == 'REQUEST_TOKEN')
						{
							el.value = json.token;
						}
					});
				}

				new Asset.image(json.content, {
					id: 'bycropper_" . $this->strId . "',
					onload: function() {
						new Element('form', {
							id: 'form_" . $this->strId . "',
							action: 'ajax.php?action=ffl&id=" . $this->strId . "&do=crop',
							method: 'post',
							send: {
								onComplete: function() { window.location.reload() }
							},
							html: '<input type=\"hidden\" name=\"REQUEST_TOKEN\" value=\"" . REQUEST_TOKEN . "\"><input type=\"hidden\" name=\"x\"><input type=\"hidden\" name=\"y\"><input type=\"hidden\" name=\"w\"><input type=\"hidden\" name=\"h\"><input type=\"submit\" value=\"" . $GLOBALS['TL_LANG']['MSC']['avatar_save'] . "\"> <input type=\"button\" value=\"" . $GLOBALS['TL_LANG']['MSC']['avatar_cancel'] . "\" onclick=\"window.location.reload()\">'
						}).injectAfter($('bycropper_" . $this->strId . "')).addEvent('click', function() { this.send(); return false; });
						
						new ByCropper('bycropper_" . $this->strId . "', 'form_" . $this->strId . "', {
							borderPath: 'system/modules/fancyavatar/html/',
							minWidth: " . $this->maxdims[0] . ",
							minHeight: " . $this->maxdims[1] . ",
							ratio: [" . $this->maxdims[0] . ", " . $this->maxdims[1] . "],
							maskColor: '#000000',
							maskOpacity: 0.8
						});
					}
				}).replaces($('fancyavatar_" . $this->strId . "'));

			}
		},
		onComplete: function() {
			link.set('html', linkIdle);
		}
	});
 
	// Button state
	link.addEvents({
		click: function() {
			return false;
		},
		mouseenter: function() {
			this.addClass('hover');
			swf.reposition();
		},
		mouseleave: function() {
			this.removeClass('hover');
			this.blur();
		},
		mousedown: function() {
			this.focus();
		}
	});
	
	$$('#fancyavatar_" . $this->strId . " .delete')[0].addEvent('click', function() {
		if (confirm('" . $GLOBALS['TL_LANG']['MSC']['avatar_confirm'] . "'))
		{
			new Request.JSON({
				url: 'ajax.php?action=ffl&id=" . $this->strId . "&do=delete',
				onComplete: function(json) {
					if (json.token)
					{
						REQUEST_TOKEN = json.token;
	
						// Update all forms
						$$('input[type=\"hidden\"]').each(function(el)
						{
							if (el.name == 'REQUEST_TOKEN')
							{
								el.value = json.token;
							}
						});
					}
					$('fancyavatar_" . $this->strId . "').setStyle('background-image', 'url(' + json.content + ')');
				}
			}).post({'REQUEST_TOKEN':REQUEST_TOKEN});
		}
		return false;
	});
 
});
//--><!]]>
</script>";
		
						
		return $strBuffer;
	}
	
	
	/**
	 * This is where all the magic happens!
	 */
	public function generateAjax()
	{
		switch( $this->Input->get('do') )
		{
			case 'upload':
				$strImage = $this->uploadAvatar();
				
				if ($strImage === false)
					return 'fehler';
					
				return $strImage;
				break;
				
			case 'crop':
				$this->import('Database');
				$this->import('Files');
				
				// Alle bisherigen Avatar-Dateien löschen
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.jpg');
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.jpeg');
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.png');
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.gif');
				
				foreach( scandir(TL_ROOT . '/system/html/') as $file )
				{
					if (strpos($file, sprintf($this->filename, $this->currentRecord)) !== false)
					{
						$this->Files->delete('system/html/' . $file);
					}
				}
				
				$strCroppedImage = $this->cropImage($_SESSION['FILES'][$this->strName]['tmp_name'], $this->Input->post('w'), $this->Input->post('h'), $this->Input->post('x'), $this->Input->post('y'));
				$strNewImage = $GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.' . pathinfo($strCroppedImage, PATHINFO_EXTENSION);
				
				$this->Files->rename($strCroppedImage, $strNewImage);
				
				$this->Database->prepare("UPDATE " . $this->strTable . " SET " . $this->strName . "=? WHERE id=?")->execute($strNewImage, $this->currentRecord);
				$this->log('File cropped successfully', 'FancyAvatar generateAjax()', TL_FILES);
				break;
				
			case 'delete':
				$this->import('Files');

				// Alle bisherigen Avatar-Dateien löschen
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.jpg');
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.jpeg');
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.png');
				$this->Files->delete($GLOBALS['TL_CONFIG']['avatar_dir'] . '/' . sprintf($this->filename, $this->currentRecord) . '.gif');
				
				return $this->getDefaultAvatar();
				break;
		}
	}
	
	
	protected function uploadAvatar()
	{
		if ($_FILES && array_key_exists($this->strName, $_FILES) && strlen($_FILES[$this->strName]['name']))
		{
			$file = $_FILES[$this->strName];
	
			// Romanize the filename
			$file['name'] = utf8_romanize($file['name']);
	
			// File was not uploaded
			if (!is_uploaded_file($file['tmp_name']))
			{
				unset($_FILES[$this->strName]);
				
				if (in_array($file['error'], array(1, 2)))
				{
					$this->log('File "'.$file['name'].'" exceeds the maximum file size of '.$maxlength_kb.' kB', 'FormFancyUpload generateAjax()', TL_ERROR);
					
					return false;
				}
	
				if ($file['error'] == 3)
				{
					$this->log('File "'.$file['name'].'" was only partially uploaded', 'FormFancyUpload generateAjax()', TL_ERROR);
					
					return false;
				}
			}
		
	
			$_SESSION['FILES'][$this->strName] = $_FILES[$this->strName];

			$strUploadFolder = 'system/tmp';
			move_uploaded_file($file['tmp_name'], TL_ROOT . '/' . $strUploadFolder . '/' . $file['name']);
			
			$intWidth = $GLOBALS['TL_CONFIG']['avatar_preview'] ? $GLOBALS['TL_CONFIG']['avatar_preview'] : 500;
			$imgSize = getimagesize(TL_ROOT . '/' . $strUploadFolder . '/' . $file['name']);
			
			if ($imgSize[0] < $intWidth)
				$intWidth = $imgSize[0];
			
			$_SESSION['FILES'][$this->strName]['tmp_name'] = $this->getImage($strUploadFolder . '/' . $file['name'], $intWidth, 0);
			
			$this->log('File "'.$file['name'].'" uploaded successfully', 'FancyAvatar uploadAvatar()', TL_FILES);
					
			return $_SESSION['FILES'][$this->strName]['tmp_name'];
		}
		
		return false;
	}
	
	
	protected function cropImage($image, $width, $height, $x, $y, $target=null)
	{
		if (!strlen($image))
		{
			return null;
		}

		$image = urldecode($image);

		// Check whether file exists
		if (!file_exists(TL_ROOT . '/' . $image))
		{
			$this->log('Image "' . $image . '" could not be found', 'FancyAvatar cropImage()', TL_ERROR);
			return null;
		}

		$objFile = new File($image);
		$arrAllowedTypes = trimsplit(',', strtolower($GLOBALS['TL_CONFIG']['validImageTypes']));

		// Check file type
		if (!in_array($objFile->extension, $arrAllowedTypes))
		{
			$this->log('Image type "' . $objFile->extension . '" was not allowed to be processed', 'FancyAvatar cropImage()', TL_ERROR);
			return null;
		}

		$strCacheName = 'system/html/' . $objFile->filename . '-' . substr(md5('-w' . $width . '-h' . $height . '-' . $image), 0, 8) . '.' . $objFile->extension;

		// Resize original image
		if ($target)
		{
			$strCacheName = $target;
		}

		// Return the path of the new image if it exists already
		elseif (file_exists(TL_ROOT . '/' . $strCacheName))
		{
			return $strCacheName;
		}

		// Return the path to the original image if GDlib cannot handle it
		if (!extension_loaded('gd') || !$objFile->isGdImage || (!$width && !$height) || $width > 1200 || $height > 1200)
		{
			return $image;
		}

		$intPositionX = $x;
		$intPositionY = $y;
		$intWidth = $width;
		$intHeight = $height;

		$strNewImage = imagecreatetruecolor($intWidth, $intHeight);

		$arrGdinfo = gd_info();
		$strGdVersion = preg_replace('/[^0-9\.]+/', '', $arrGdinfo['GD Version']);

		switch ($objFile->extension)
		{
			case 'gif':
				if ($arrGdinfo['GIF Read Support'])
				{
					$strSourceImage = imagecreatefromgif(TL_ROOT . '/' . $image);
					$intTranspIndex = imagecolortransparent($strSourceImage);

					// Handle transparency
					if ($intTranspIndex >= 0)
					{
						$arrColor = imagecolorsforindex($strSourceImage, $intTranspIndex);
						$intTranspIndex = imagecolorallocate($strNewImage, $arrColor['red'], $arrColor['green'], $arrColor['blue']);
						imagefill($strNewImage, 0, 0, $intTranspIndex);
						imagecolortransparent($strNewImage, $intTranspIndex);
					}
				}
				break;

			case 'jpg':
			case 'jpeg':
				if ($arrGdinfo['JPG Support'] || $arrGdinfo['JPEG Support'])
				{
					$strSourceImage = imagecreatefromjpeg(TL_ROOT . '/' . $image);
				}
				break;

			case 'png':
				if ($arrGdinfo['PNG Support'])
				{
					$strSourceImage = imagecreatefrompng(TL_ROOT . '/' . $image);

					// Handle transparency (GDlib >= 2.0 required)
					if (version_compare($strGdVersion, '2.0', '>='))
					{
						imagealphablending($strNewImage, false);
						$intTranspIndex = imagecolorallocatealpha($strNewImage, 0, 0, 0, 127);
						imagefill($strNewImage, 0, 0, $intTranspIndex);
						imagesavealpha($strNewImage, true);
					}
				}
				break;
		}

		// New image could not be created
		if (!$strSourceImage)
		{
			$this->log('Image "' . $image . '" could not be processed', 'FancyAvatar cropImage()', TL_ERROR);
			return null;
		}

		imagecopyresampled($strNewImage, $strSourceImage, 0, 0, $intPositionX, $intPositionY, $intWidth, $intHeight, $intWidth, $intHeight);

		// Fallback to PNG if GIF ist not supported
		if ($objFile->extension == 'gif' && !$arrGdinfo['GIF Create Support'])
		{
			$objFile->extension = 'png';
		}

		// Create new image
		switch ($objFile->extension)
		{
			case 'gif':
				imagegif($strNewImage, TL_ROOT . '/' . $strCacheName);
				break;

			case 'jpg':
			case 'jpeg':
				imagejpeg($strNewImage, TL_ROOT . '/' . $strCacheName, (!$GLOBALS['TL_CONFIG']['jpgQuality'] ? 80 : $GLOBALS['TL_CONFIG']['jpgQuality']));
				break;

			case 'png':
				imagepng($strNewImage, TL_ROOT . '/' . $strCacheName);
				break;
		}

		// Destroy temporary images
		imagedestroy($strSourceImage);
		imagedestroy($strNewImage);

		// Return path to new image
		return $strCacheName;
	}
	
	
	protected function getAvatarDirectory()
	{
		$strDir = strlen($GLOBALS['TL_CONFIG']['avatar_dir']) ? $GLOBALS['TL_CONFIG']['avatar_dir'] : ($GLOBALS['TL_CONFIG']['uploadPath'] . '/avatars');
		
		if (!is_dir(TL_ROOT . '/' . $strDir))
		{
			$this->import('Files');
			$this->Files->mkdir($strDir);
		}
		
		return $strDir;
	}
	
	
	protected function getDefaultAvatar($arrSize=false)
	{
		if (is_file(TL_ROOT . '/' . $GLOBALS['TL_CONFIG']['avatar_default']))
		{
			if (!$arrSize)
			{
				$arrSize = deserialize($GLOBALS['TL_CONFIG']['avatar_maxdims'], true);
				
				if (!$arrSize[1])
				{
					$arrSize[1] = $arrSize[0];
				}
			}

			return $this->getImage($GLOBALS['TL_CONFIG']['avatar_default'], $arrSize[0], $arrSize[1]);
		}
			
		return '';
	}
	
	
	public function replaceTags($strTag)
	{
		$arrTag = trimsplit('::', $strTag);
		
		if( $arrTag[0] == 'avatar' )
		{
			$strImage = '';
			
			// Avatar for current user
			if (count($arrTag) == 1)
			{
				if (TL_MODE == 'FE' && FE_USER_LOGGED_IN)
				{
					$this->import('FrontendUser', 'User');
					$strImage = $this->User->avatar;
				}
				elseif (TL_MODE == 'BE' && BE_USER_LOGGED_IN)
				{
					$this->import('BackendUser', 'User');
					$strImage = $this->User->avatar;
				}
				else
				{
					return '';
				}
			}
			else
			{
				$this->import('Database');
				
				$objUser = $this->Database->prepare("SELECT * FROM tl_" . ($arrTag[2] == 'be' ? 'user' : 'member') . " WHERE id=?")->limit(1)->execute($arrTag[1]);
				
				if ($objUser->numRows)
				{
					$strImage = $objUser->avatar;
				}
			}
			
			$arrSize = deserialize($GLOBALS['TL_CONFIG']['avatar_maxdims'], true);
			
			if (!$arrSize[1])
			{
				$arrSize[1] = $arrSize[0];
			}
			
			if (strlen($arrTag[3]))
			{
				$arrSize[0] = $arrTag[3];
				$arrSize[1] = $arrTag[3];
			}
			
			if (strlen($arrTag[4]))
			{
				$arrSize[1] = $arrTag[4];
			}

			return sprintf('<img src="%s" alt="" height="%s" width="%s" class="avatar">', 
							(is_file(TL_ROOT . '/' . $strImage) ? $this->getImage($strImage, $arrSize[0], $arrSize[1]) : $this->getDefaultAvatar($arrSize)),
							$arrSize[1],
							$arrSize[0]);
		}
		
		return false;
	}
}

