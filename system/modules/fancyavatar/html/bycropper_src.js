/*

The MIT License

ByCropper (http://www.byscripts.info/mootools/bycropper)
Copyright (c) 2008 ByScripts.info (http://www.byscripts.info)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

var ByCropper = new Class({
	
	Implements: Options,
	
	options: {
		keepRatio: true,
		strictRatio: false,
		ratio: 0,
		handleSize: 14,
		minWidth: 40,
		minHeight: 40,
		maxWidth: 0,
		maxHeight: 0,
		borderPath: '.',
		maskColor: '#000000',
		maskOpacity: 0.7
	},
	
	initialize: function(picture, form, options){
		
		this.elements = {};
		this.infos = {
			picture: {
				position: {},
				size: {}
			},
			cropper: {
				position: {},
				positionRef: {},
				positionMax: {},
				size: {},
				sizeMax: {},
				sizeMin: {},
				sizeRef: {}
			},
			background: {
				position: {}
			},
			ratio: $H()
		};
		
		if(!form && $type(picture) == 'string')
			form = picture + '_form';
		
		this.setOptions(options);
		this.elements.picture = $($pick(picture, 'bycropper'));
		this.elements.form = $($pick(form, 'bycropper_form'));
		this.storePictureInfos();
		this.createMask();
		this.createCropper();
		this.createHandles();
		
		if(this.options.keepRatio)
			this.setRatio(this.options.ratio, null, this.options.strictRatio);
		else
			this.setMinSize(1, 1);
			
		this.addDocumentEvents();
	},
	
	// Store picture size, position and source
	storePictureInfos: function(){
		this.infos.picture.position.top = this.elements.picture.getTop();
		this.infos.picture.position.left = this.elements.picture.getLeft();
		this.infos.picture.size.width = this.elements.picture.getWidth();
		this.infos.picture.size.height = this.elements.picture.getHeight();
		this.infos.picture.source = this.elements.picture.get('src');
	},
	
	// Set the option minHeight and/or minWidth
	setMinSize: function(minWidth, minHeight, force){
		
		if(minWidth && (force || !this.options.minWidth))
			this.options.minWidth = minWidth;
		
		if(minHeight && (force || !this.options.minHeight))
			this.options.minHeight = minHeight;
	},
	
	// Return to default ratio
	defaultRatio: function(){
		if(!this.options.keepRatio)
			this.noRatio();
		else
			this.setRatio(this.options.ratio, null, this.options.strictRatio);
	},
	
	// Disable ratio
	noRatio: function(){
		return this.infos.ratio.active = false;
	},
	
	// Set a new ratio
	setRatio: function(ratioX, ratioY, strict){

		// Array passed in
		if($type(ratioX) == 'array')
			return this.setRatio(ratioX[0], ratioX[1], strict);
		
		// Object passed in
		if($type(ratioX) == 'object')
			return this.setRatio(ratioX.width, ratioX.height, strict);

		// If ratioX is not a number, or is smaller than 0, we can't set a ratio
		if($type(ratioX) != 'number' || ratioX < 0)
			return false;
		
		// If ratioY is not a number, or is smaller than 1, set it to 1
		if($type(ratioY) != 'number' || ratioY < 1)
			ratioY = 1;
		
		// Activate ratio
		this.infos.ratio.active = true;

		// Set strict mode
		if($type(strict) == 'boolean')
			this.infos.ratio.strict = strict;
		
		// If ratioX is 0, set ratio to the picture ratio
		if(ratioX == 0)
			ratio = {width: this.infos.picture.size.width, height: this.infos.picture.size.height};
		else
			ratio = {width: ratioX.toInt(), height: ratioY.toInt()};

		// Set the new ratio
		this.infos.ratio.extend(ratio);
		
		// Get the greater common divisor
		var gcd = this.getGCD(this.infos.ratio.width, this.infos.ratio.height);
		
		// The make sure that ratio is as small as possible
		this.infos.ratio.width /= gcd;
		this.infos.ratio.height /= gcd;
		
		// Cropper can not be smaller than base ratio (which would be 0*0)
		this.setMinSize(this.infos.ratio.width, this.infos.ratio.height);
			
		// Move & Fix the cropper to top left
		this.fixToTopLeft(true);
		
		// Redefine bound, and update cropper
		this.defineBound(true);
	},
	
	// Add events on document
	addDocumentEvents: function(){
		document.addEvent('mousedown', function(e){
			// Store the cursor reference position
			this.storeCursorReference(e);
			
			// Store the cropper reference size & position
			this.storeCropperReference();
			
			// Redefine cropper min & max bound
			this.defineBound();
		}.bindWithEvent(this)).addEvent('mousemove', function(e){
			if(this.move)
				this.moveCropper(e.page);
			else
				this.resizeCropper(e.page);
		}.bindWithEvent(this)).addEvent('mouseup', function(){
			// Disable all actions
			this.resizeX = false;
			this.resizeY = false;
			this.move = false;
		}.bind(this));
	},
	
	// Create the mask
	createMask: function(){
		this.elements.mask = new Element('div', {
			styles: {
				zIndex: 5000,
				position: 'absolute',
				left: this.infos.picture.position.left,
				top: this.infos.picture.position.top,
				width: this.infos.picture.size.width,
				height: this.infos.picture.size.height,
				backgroundColor: this.options.maskColor,
				opacity: this.options.maskOpacity
			}
		});
		
		$(document.body).adopt(this.elements.mask);
	},
	
	// Create the cropper
	createCropper: function(){
			
		// Start with a container
		this.elements.cropperContainer = this.elements.mask.clone().setStyles({
			zIndex: 5010,
			opacity: 1,
			backgroundColor: 'transparent'
		});
		
		// The cropper itself
		this.elements.cropper = new Element('div', {
			styles: {
				zIndex: 5020,
				position: 'absolute',
				backgroundImage: 'url(' + this.infos.picture.source + ')'
			}
		});
		
		this.infos.cropper.size.width = this.infos.picture.size.width;
		this.infos.cropper.size.height = this.infos.picture.size.height;
		this.infos.cropper.position.bottom = this.infos.picture.size.height - this.infos.cropper.size.height;
		this.infos.cropper.position.right = this.infos.picture.size.width - this.infos.cropper.size.width;
		
		// Fix the cropper to top left
		this.fixToTopLeft();
		
		// Please dad, adopt me
		$(document.body).adopt(this.elements.cropperContainer);
		this.elements.cropperContainer.adopt(this.elements.cropper);
		
		// Ok son, but there is some bound to not excess
		this.defineBound(true);
	},

	// And now, ladies and gentlemens the handles creation
	createHandles: function(){
		
		this.elements.handles = $H();
		
		var handle = new Element('div', {
			styles: {
				fontSize: 1, 			// For this little poor IE6 
				position: 'absolute',
				width: 20,
				height: 20
			}
		});
		
		// North West handle
		this.elements.handles.nw_handle = handle.clone().setStyles({
			backgroundColor: '#ffffff', // For this stupid IE, which doesn't like to trigger events on transparent backgrounds
			opacity: 0.01,				// Cause we don't want to see the stupid background color
			left: -10,
			top: -10,
			cursor: 'nw-resize',
			zIndex: 5050
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToBottomRight();
			this.updateCropper();
			this.resizeX = true;
			if(!this.infos.ratio.active)
				this.resizeY = true;
		}.bind(this));
		
		// North East handle
		this.elements.handles.ne_handle = handle.clone().setStyles({
			backgroundColor: '#ffffff',
			opacity: 0.01,
			right: -10,
			top: -10,
			cursor: 'ne-resize',
			zIndex: 5050
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToBottomLeft();
			this.updateCropper();
			this.resizeX = true;
			if(!this.infos.ratio.active)
				this.resizeY = true;
		}.bind(this));
		
		// South West handle
		this.elements.handles.sw_handle = handle.clone().setStyles({
			backgroundColor: '#ffffff',
			opacity: 0.01,
			left: -10,
			bottom: -10,
			cursor: 'sw-resize',
			zIndex: 5050
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToTopRight();
			this.updateCropper();
			this.resizeX = true;
			if(!this.infos.ratio.active)
				this.resizeY = true;
		}.bind(this));
		
		// South East handle
		this.elements.handles.se_handle = handle.clone().setStyles({
			backgroundColor: '#ffffff',
			opacity: 0.01,
			right: -10,
			bottom: -10,
			cursor: 'se-resize',
			zIndex: 5050
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToTopLeft();
			this.updateCropper();
			this.resizeX = true;
			if(!this.infos.ratio.active)
				this.resizeY = true;
		}.bind(this));
		
		// North handle
		this.elements.handles.n_handle = handle.clone().setStyles({
			backgroundImage: 'url(' + this.options.borderPath + '/cropperBorderH.gif)',
			backgroundRepeat: 'repeat-x',
			backgroundPosition: [0, 10],
			width: '100%',
			left: 0,
			top: -10,
			cursor: 'n-resize',
			zIndex: 5040
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToBottomLeft();
			this.updateCropper();
			this.resizeY = true;
		}.bind(this));
		
		// East handle
		this.elements.handles.e_handle = handle.clone().setStyles({
			backgroundImage: 'url(' + this.options.borderPath + '/cropperBorderV.gif)',
			backgroundRepeat: 'repeat-y',
			backgroundPosition: [10, 0],
			height: '100%',
			top: 0,
			right: -10,
			cursor: 'e-resize',
			zIndex: 5040
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToTopLeft();
			this.updateCropper();
			this.resizeX = true;
		}.bind(this));
		
		// West handle
		this.elements.handles.w_handle = handle.clone().setStyles({
			backgroundImage: 'url(' + this.options.borderPath + '/cropperBorderV.gif)',
			backgroundRepeat: 'repeat-y',
			backgroundPosition: [10, 0],
			height: '100%',
			top: 0,
			left: -10,
			cursor: 'w-resize',
			zIndex: 5040
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToTopRight();
			this.updateCropper();
			this.resizeX = true;
		}.bind(this));
		
		// South handle
		this.elements.handles.s_handle = handle.clone().setStyles({
			backgroundImage: 'url(' + this.options.borderPath + '/cropperBorderH.gif)',
			backgroundRepeat: 'repeat-x',
			backgroundPosition: [0, 10],
			width: '100%',
			left: 0,
			bottom: -10,
			cursor: 's-resize',
			zIndex: 5040
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToTopLeft();
			this.updateCropper();
			this.resizeY = true;
		}.bind(this));
		
		// Middle handle
		this.elements.handles.mid_handle = handle.clone().setStyles({
			backgroundColor: '#ffffff',
			opacity: 0.01,
			width: '100%',
			height: '100%',
			left: 0,
			top: 0,
			cursor: 'move',
			zIndex: 5030
		}).addEvent('mousedown', function(e){
			e.preventDefault();
			this.fixToTopLeft();
			this.updateCropper();
			this.move = true;
		}.bind(this));
		
		// Again, a fix for the greeaaaaaat IE
		if(Browser.Engine.trident)
		{
			this.elements.handles.each(function(handle){
				handle.addEvent('selectstart', $lambda(false));
			});
		}
		
		// And now, make the adoptions, in the right order, it's important
		this.elements.cropper.adopt(
			this.elements.handles.mid_handle,
			
			this.elements.handles.n_handle,
			this.elements.handles.w_handle,
			this.elements.handles.e_handle,
			this.elements.handles.s_handle,
			
			this.elements.handles.nw_handle,
			this.elements.handles.ne_handle,
			this.elements.handles.sw_handle,
			this.elements.handles.se_handle
		);
	},
	
	
	// Get the correct background position
	computeBackgroundPosition: function(){
		if(this.fixedLeft)
			this.infos.background.position.left = -this.infos.cropper.position.left;
		else
			this.infos.background.position.left = -(this.infos.picture.size.width - this.infos.cropper.position.right - this.infos.cropper.size.width);
		
		if(this.fixedTop)
			this.infos.background.position.top = -this.infos.cropper.position.top;
		else
			this.infos.background.position.top = -(this.infos.picture.size.height - this.infos.cropper.position.bottom - this.infos.cropper.size.height);	
	},
	
	// Store the cursor click position
	storeCursorReference: function(e){
		this.cursorReference = e.page;
	},
	
	// Get the cursor position, relative to the click position
	getCursorRelative: function(mousePos){
		return {
			x: mousePos.x - this.cursorReference.x,
			y: mousePos.y - this.cursorReference.y
		};
	},
	
	// Store the cropper reference, to know how to resize or move it
	storeCropperReference: function(){
		this.infos.cropper.positionRef.top = this.elements.cropper.getStyle('top').toInt();
		this.infos.cropper.positionRef.left = this.elements.cropper.getStyle('left').toInt();
		this.infos.cropper.sizeRef.width = this.elements.cropper.getWidth();
		this.infos.cropper.sizeRef.height = this.elements.cropper.getHeight();
	},
	
	// Fix the cropper to left. If reset = true, the cropper will be moved to left = 0
	fixToLeft: function(reset){
		
		if(reset)
			this.infos.cropper.position.left = 0;
		
		// Already fixed to left
		if(this.fixedLeft)
			return;
		
		if(!reset)
			this.infos.cropper.position.left = this.infos.picture.size.width - this.infos.cropper.position.right - this.infos.cropper.size.width;
			
		this.infos.cropper.position.right = null;
		this.fixedLeft = true;
	},
	
	// Fix the cropper to right. If reset = true, the cropper will be moved to right = 0
	fixToRight: function(reset){
		
		if(reset)
			this.infos.cropper.position.right = 0;
		
		// Already fixed right
		if(!this.fixedLeft)
			return;

		if(!reset)
			this.infos.cropper.position.right = this.infos.picture.size.width - this.infos.cropper.position.left - this.infos.cropper.size.width;

		this.infos.cropper.position.left = null;
		this.fixedLeft = false;
	},
	
	// Fix the cropper to top. If reset = true, the cropper will be moved to top = 0
	fixToTop: function(reset){
		if(reset)
			this.infos.cropper.position.top = 0;
		
		if(this.fixedTop)
			return;
		
		if(!reset)
			this.infos.cropper.position.top = this.infos.picture.size.height - this.infos.cropper.position.bottom - this.infos.cropper.size.height;

		this.infos.cropper.position.bottom = null;
		this.fixedTop = true;
	},
	
	// Fix the cropper to bottom. If reset = true, the cropper will be moved to bottom = 0
	fixToBottom: function(reset){
		
		if(reset)
			this.infos.cropper.position.bottom = 0;
			
		if(!this.fixedTop)
			return;
		
		
		if(!reset)
			this.infos.cropper.position.bottom = this.infos.picture.size.height - this.infos.cropper.position.top - this.infos.cropper.size.height;

		this.infos.cropper.position.top = null;
		this.fixedTop = false;
	},
	
	// Shortcut
	fixToTopLeft: function(reset){
		this.fixToTop(reset);
		this.fixToLeft(reset);
	},
	
	// Shortcut
	fixToTopRight: function(reset){
		this.fixToTop(reset);
		this.fixToRight(reset);
	},
	
	// Shortcut
	fixToBottomLeft: function(reset){
		this.fixToBottom(reset);
		this.fixToLeft(reset);
	},
	
	// Shortcut
	fixToBottomRight: function(reset){
		this.fixToBottom(reset);
		this.fixToRight(reset);
	},
	
	// Update the cropper size & position
	updateCropper: function(){
		
		this.computeBackgroundPosition();
		
		this.elements.cropper.setStyles({
			top: this.infos.cropper.position.top,
			right: this.infos.cropper.position.right,
			bottom: this.infos.cropper.position.bottom,
			left: this.infos.cropper.position.left,
			width: this.infos.cropper.size.width,
			height: this.infos.cropper.size.height,
			backgroundPosition: [this.infos.background.position.left, this.infos.background.position.top]
		});
		
		// Update the form
		this.updateForm();
	},
	
	// Get the greater common divisor
	getGCD: function(a, b){
		if(!b)
			return a;
		
		var mod = a % b;
		return this.getGCD(b, mod);
	},

	// Get a correct mouse horizontal value, according to ratio
	// Ex: if width ratio is 4, and mouse x is 14, then the correct value is 12
	mouseXToRatio: function(x){
		if(this.infos.ratio.strict)
			return x - x % this.infos.ratio.width;
		
		return x;
	},
	
	// Get a correct mouse vertical value, according to ratio
	// Ex: if height ratio is 3, and mouse y is 8, then the correct value is 6
	mouseYToRatio: function(y){
		if(this.infos.ratio.strict)
			return y - y % this.infos.ratio.height;
		
		return y;
	},
	
	// Resize the cropper, according to mouse position
	resizeCropper: function(mousePos){

		// If we don't ask to resize anything...
		if(!this.resizeX && !this.resizeY)
			return;
		
		// Get the relative mouse position
		var relative = this.getCursorRelative(mousePos);

		// If we want to resize width
		if(this.resizeX)
		{
			if(this.infos.ratio.active)
				relative.x = this.mouseXToRatio(relative.x);

			this.resizeWidth(relative.x);
		}
		
		// And if we want to resize height
		if(this.resizeY)
		{
			if(this.infos.ratio.active)
				relative.y = this.mouseYToRatio(relative.y);
			
			this.resizeHeight(relative.y);
		}
		
		// And now, update the cropper
		this.updateCropper();
	},
	
	// Resize the cropper width
	resizeWidth: function(mouseRelativeX){
		
		// If the cropper is fixed to right, invert mouse relative position
		if(!this.fixedLeft)
			mouseRelativeX = -mouseRelativeX;
		
		var width = this.infos.cropper.sizeRef.width + mouseRelativeX;
		
		// Don't go over size limits
		this.infos.cropper.size.width = width.limit(this.infos.cropper.sizeMin.width, this.infos.cropper.sizeMax.width);
		
		// If ratio is used, the resize height according to width
		if(this.infos.ratio.active)
			this.infos.cropper.size.height = ((this.infos.cropper.size.width / this.infos.ratio.width) * this.infos.ratio.height).toInt();
	},
	
	// Resize the cropper height
	resizeHeight: function(mouseRelativeY){
		
		// If the cropper is fixed to bottom, invert mouse relative position
		if(!this.fixedTop)
			mouseRelativeY = -mouseRelativeY;
			
		// If using ratio, we don't really resize height, we resize width which will resize height
		if(this.infos.ratio.active)
			return this.resizeWidth(((mouseRelativeY / this.infos.ratio.height) * this.infos.ratio.width).toInt());
		
		var height = this.infos.cropper.sizeRef.height + mouseRelativeY;
		
		// Don't go over size limit
		this.infos.cropper.size.height = height.limit(this.infos.cropper.sizeMin.height, this.infos.cropper.sizeMax.height);
	},
	
	// Move the cropper
	moveCropper: function(mousePos){
		
		var relative = this.getCursorRelative(mousePos);
		
		this.infos.cropper.position.left = this.infos.cropper.positionRef.left + relative.x;
		this.infos.cropper.position.top = this.infos.cropper.positionRef.top + relative.y;

		// Don't move over the position limits
		this.infos.cropper.position.left = this.infos.cropper.position.left.limit(0, this.infos.cropper.positionMax.left);
		this.infos.cropper.position.top = this.infos.cropper.position.top.limit(0, this.infos.cropper.positionMax.top);

		// Update the cropper
		this.updateCropper();
	},
	
	// Define the min/max size/position bounds
	defineBound: function(setBound){
		this.infos.cropper.sizeMax.width = this.getMaxWidth();
		this.infos.cropper.sizeMax.height = this.getMaxHeight();
		this.infos.cropper.sizeMin.width = this.getMinWidth();
		this.infos.cropper.sizeMin.height = this.getMinHeight();
		this.infos.cropper.positionMax.top = this.getMaxTop();
		this.infos.cropper.positionMax.left = this.getMaxLeft();
		
		if(setBound)
			this.setBound();
	},
	
	// Resize cropper according to max size
	setBound: function(){
		this.infos.cropper.size.width = this.infos.cropper.sizeMax.width;
		this.infos.cropper.size.height = this.infos.cropper.sizeMax.height;

		this.updateCropper();
	},
	
	// Calculate the maximum possible cropper width
	getMaxWidth: function(width) {
		
		// First, start with either given width or picture width
		var baseWidth = $pick(width, this.infos.picture.size.width);
		
		// We need to work with integer
		baseWidth = baseWidth.toInt();
		
		// If width is greater than picture width
		if(baseWidth > this.infos.picture.size.width)
			baseWidth = this.infos.picture.size.width;
			
		// If width is greater than maxWidth option
		if(this.options.maxWidth > 0 && baseWidth > this.options.maxWidth)
			baseWidth = this.options.maxWidth;
		
		// Get the correct cropper position
		if(!this.fixedLeft)
			var cropperPos = this.infos.cropper.position.right;
		else
			var cropperPos = this.infos.cropper.position.left;

		// If width is greater than place available
		if(baseWidth > this.infos.picture.size.width - cropperPos)
			baseWidth = this.infos.picture.size.width - cropperPos;
		
		// If not using ratio, the width should be correct
		if(!this.infos.ratio.active)
			return baseWidth;
		
		// If using ratio, we need to get a width which is correct
		// when associated to its height
		return this.getMaxWidthRatio(baseWidth);
	},
	
	// Calculate the maximum possible cropper height
	getMaxHeight: function(baseHeight){

		// If we are using ratio, the maxHeight is simply maxWidth passed to ratio
		if(this.infos.ratio.active)
			return (this.infos.cropper.sizeMax.width / this.infos.ratio.width) * this.infos.ratio.height;
		
		// First, start with either given height or picture height
		baseHeight = $pick(baseHeight, this.infos.picture.size.height);

		// We need to work with integer
		baseHeight = baseHeight.toInt();
		
		// If height is greater than picture height
		if(baseHeight > this.infos.picture.size.height)
			baseHeight = this.infos.picture.size.height;

		// If height is greater than maxHeight option
		if(this.options.maxHeight > 0 && baseHeight > this.options.maxHeight)
			baseHeight = this.options.maxHeight;

		// Get the correct cropper position
		if(!this.fixedTop)
			var cropperPos = this.infos.cropper.position.bottom;
		else
			var cropperPos = this.infos.cropper.position.top;
			
		// If width is greater than place available
		if(baseHeight > this.infos.picture.size.height - cropperPos)
			baseHeight = this.infos.picture.size.height - cropperPos;

		return baseHeight;
	},
	
	// Calculate the minimum possible cropper width
	getMinWidth: function(width) {
		
		var baseWidth = $pick(width, 0);
		
		// We need to work with integer
		baseWidth = baseWidth.toInt();
			
		// If width is smaller than minWidth option
		if(baseWidth < this.options.minWidth)
			baseWidth = this.options.minWidth;

		// If not using ratio, the width should be correct
		if(!this.infos.ratio.active)
			return baseWidth;
		
		// Now, as we are using ratio, we need to get a width which is correct
		// when associated to its height
		return this.getMinWidthRatio(baseWidth);
	},
	
	// Calculate the minimum possible cropper height
	getMinHeight: function(baseHeight){
		
		// If we are using ratio, the minHeight is simply minWidth passed to ratio
		if(this.infos.ratio.active)
			return (this.infos.cropper.sizeMin.width / this.infos.ratio.width) * this.infos.ratio.height;
		
		baseHeight = $pick(baseHeight, 0);
		
		// We need to work with integer
		baseHeight = baseHeight.toInt();
		
		// If height is smaller than minHeight option
		if(baseHeight < this.options.minHeight)
			baseHeight = this.options.minHeight;
		
		// This should be correct
		return baseHeight;
	},

	// KEEP IT
	getMaxWidthRatio: function(width){
		
		var height = (width / this.infos.ratio.width) * this.infos.ratio.height;

		// If height is greater than picture height
		if(height > this.infos.picture.size.height)
			return this.getMaxWidthRatio(width - 1);

		// If height is greater than maxHeight option
		if(this.options.maxHeight > 0 && height > this.options.maxHeight)
			return this.getMaxWidthRatio(width - 1);

		// Get the correct cropper position
		if(!this.fixedTop)
			var cropperPos = this.infos.cropper.position.bottom;
		else
			var cropperPos = this.infos.cropper.position.top;

		// If width is greater than place available
		if(height > this.infos.picture.size.height - cropperPos)
			return this.getMaxWidthRatio(width - 1);

		// If height is int, then width should be correct
		if(!this.infos.ratio.strict || height.toInt() == height)
			return width;
		
		// Try again
		return this.getMaxWidthRatio(width - 1);
	},
	
	// KEEP IT
	getMinWidthRatio: function(width){
		
		var height = (width / this.infos.ratio.width) * this.infos.ratio.height;
		
		// If height is smaller than minHeight option
		if(height < this.options.minHeight)
			return this.getMinWidthRatio(width + 1);
		
		// If height is int, then width should be correct
		if(!this.infos.ratio.strict || height.toInt() == height)
			return width;
		
		// Try again
		return this.getMinWidthRatio(width + 1);
	},
	
	// KEEP IT
	getMaxLeft: function(){
		return this.infos.picture.size.width - this.infos.cropper.size.width;
	},
	
	// KEEP IT
	getMaxTop: function(){
		return this.infos.picture.size.height - this.infos.cropper.size.height;
	},

	updateForm: function(){
		this.elements.form.x.value = $pick(this.infos.cropper.position.left, (this.infos.picture.size.width - this.infos.cropper.position.right - this.infos.cropper.size.width));
		this.elements.form.y.value = $pick(this.infos.cropper.position.top, (this.infos.picture.size.height - this.infos.cropper.position.bottom - this.infos.cropper.size.height));
		this.elements.form.w.value = this.infos.cropper.size.width;
		this.elements.form.h.value = this.infos.cropper.size.height;
	}
});