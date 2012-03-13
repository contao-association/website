var ByCropper=new Class({Implements:Options,options:{keepRatio:true,strictRatio:false,ratio:0,handleSize:14,minWidth:40,minHeight:40,maxWidth:0,maxHeight:0,borderPath:".",maskColor:"#000000",maskOpacity:0.7},initialize:function(c,b,a){this.elements={};this.infos={picture:{position:{},size:{}},cropper:{position:{},positionRef:{},positionMax:{},size:{},sizeMax:{},sizeMin:{},sizeRef:{}},background:{position:{}},ratio:$H()};if(!b&&$type(c)=="string"){b=c+"_form"}this.setOptions(a);this.elements.picture=$($pick(c,"bycropper"));this.elements.form=$($pick(b,"bycropper_form"));this.storePictureInfos();this.createMask();this.createCropper();this.createHandles();if(this.options.keepRatio){this.setRatio(this.options.ratio,null,this.options.strictRatio)}else{this.setMinSize(1,1)}this.addDocumentEvents()},storePictureInfos:function(){this.infos.picture.position.top=this.elements.picture.getTop();this.infos.picture.position.left=this.elements.picture.getLeft();this.infos.picture.size.width=this.elements.picture.getWidth();this.infos.picture.size.height=this.elements.picture.getHeight();this.infos.picture.source=this.elements.picture.get("src")},setMinSize:function(a,c,b){if(a&&(b||!this.options.minWidth)){this.options.minWidth=a}if(c&&(b||!this.options.minHeight)){this.options.minHeight=c}},defaultRatio:function(){if(!this.options.keepRatio){this.noRatio()}else{this.setRatio(this.options.ratio,null,this.options.strictRatio)}},noRatio:function(){return this.infos.ratio.active=false},setRatio:function(a,d,b){if($type(a)=="array"){return this.setRatio(a[0],a[1],b)}if($type(a)=="object"){return this.setRatio(a.width,a.height,b)}if($type(a)!="number"||a<0){return false}if($type(d)!="number"||d<1){d=1}this.infos.ratio.active=true;if($type(b)=="boolean"){this.infos.ratio.strict=b}if(a==0){ratio={width:this.infos.picture.size.width,height:this.infos.picture.size.height}}else{ratio={width:a.toInt(),height:d.toInt()}}this.infos.ratio.extend(ratio);var c=this.getGCD(this.infos.ratio.width,this.infos.ratio.height);this.infos.ratio.width/=c;this.infos.ratio.height/=c;this.setMinSize(this.infos.ratio.width,this.infos.ratio.height);this.fixToTopLeft(true);this.defineBound(true)},addDocumentEvents:function(){document.addEvent("mousedown",function(a){this.storeCursorReference(a);this.storeCropperReference();this.defineBound()}.bindWithEvent(this)).addEvent("mousemove",function(a){if(this.move){this.moveCropper(a.page)}else{this.resizeCropper(a.page)}}.bindWithEvent(this)).addEvent("mouseup",function(){this.resizeX=false;this.resizeY=false;this.move=false}.bind(this))},createMask:function(){this.elements.mask=new Element("div",{styles:{zIndex:5000,position:"absolute",left:this.infos.picture.position.left,top:this.infos.picture.position.top,width:this.infos.picture.size.width,height:this.infos.picture.size.height,backgroundColor:this.options.maskColor,opacity:this.options.maskOpacity}});$(document.body).adopt(this.elements.mask)},createCropper:function(){this.elements.cropperContainer=this.elements.mask.clone().setStyles({zIndex:5010,opacity:1,backgroundColor:"transparent"});this.elements.cropper=new Element("div",{styles:{zIndex:5020,position:"absolute",backgroundImage:"url("+this.infos.picture.source+")"}});this.infos.cropper.size.width=this.infos.picture.size.width;this.infos.cropper.size.height=this.infos.picture.size.height;this.infos.cropper.position.bottom=this.infos.picture.size.height-this.infos.cropper.size.height;this.infos.cropper.position.right=this.infos.picture.size.width-this.infos.cropper.size.width;this.fixToTopLeft();$(document.body).adopt(this.elements.cropperContainer);this.elements.cropperContainer.adopt(this.elements.cropper);this.defineBound(true)},createHandles:function(){this.elements.handles=$H();var a=new Element("div",{styles:{fontSize:1,position:"absolute",width:20,height:20}});this.elements.handles.nw_handle=a.clone().setStyles({backgroundColor:"#ffffff",opacity:0.01,left:-10,top:-10,cursor:"nw-resize",zIndex:5050}).addEvent("mousedown",function(b){b.preventDefault();this.fixToBottomRight();this.updateCropper();this.resizeX=true;if(!this.infos.ratio.active){this.resizeY=true}}.bind(this));this.elements.handles.ne_handle=a.clone().setStyles({backgroundColor:"#ffffff",opacity:0.01,right:-10,top:-10,cursor:"ne-resize",zIndex:5050}).addEvent("mousedown",function(b){b.preventDefault();this.fixToBottomLeft();this.updateCropper();this.resizeX=true;if(!this.infos.ratio.active){this.resizeY=true}}.bind(this));this.elements.handles.sw_handle=a.clone().setStyles({backgroundColor:"#ffffff",opacity:0.01,left:-10,bottom:-10,cursor:"sw-resize",zIndex:5050}).addEvent("mousedown",function(b){b.preventDefault();this.fixToTopRight();this.updateCropper();this.resizeX=true;if(!this.infos.ratio.active){this.resizeY=true}}.bind(this));this.elements.handles.se_handle=a.clone().setStyles({backgroundColor:"#ffffff",opacity:0.01,right:-10,bottom:-10,cursor:"se-resize",zIndex:5050}).addEvent("mousedown",function(b){b.preventDefault();this.fixToTopLeft();this.updateCropper();this.resizeX=true;if(!this.infos.ratio.active){this.resizeY=true}}.bind(this));this.elements.handles.n_handle=a.clone().setStyles({backgroundImage:"url("+this.options.borderPath+"/cropperBorderH.gif)",backgroundRepeat:"repeat-x",backgroundPosition:[0,10],width:"100%",left:0,top:-10,cursor:"n-resize",zIndex:5040}).addEvent("mousedown",function(b){b.preventDefault();this.fixToBottomLeft();this.updateCropper();this.resizeY=true}.bind(this));this.elements.handles.e_handle=a.clone().setStyles({backgroundImage:"url("+this.options.borderPath+"/cropperBorderV.gif)",backgroundRepeat:"repeat-y",backgroundPosition:[10,0],height:"100%",top:0,right:-10,cursor:"e-resize",zIndex:5040}).addEvent("mousedown",function(b){b.preventDefault();this.fixToTopLeft();this.updateCropper();this.resizeX=true}.bind(this));this.elements.handles.w_handle=a.clone().setStyles({backgroundImage:"url("+this.options.borderPath+"/cropperBorderV.gif)",backgroundRepeat:"repeat-y",backgroundPosition:[10,0],height:"100%",top:0,left:-10,cursor:"w-resize",zIndex:5040}).addEvent("mousedown",function(b){b.preventDefault();this.fixToTopRight();this.updateCropper();this.resizeX=true}.bind(this));this.elements.handles.s_handle=a.clone().setStyles({backgroundImage:"url("+this.options.borderPath+"/cropperBorderH.gif)",backgroundRepeat:"repeat-x",backgroundPosition:[0,10],width:"100%",left:0,bottom:-10,cursor:"s-resize",zIndex:5040}).addEvent("mousedown",function(b){b.preventDefault();this.fixToTopLeft();this.updateCropper();this.resizeY=true}.bind(this));this.elements.handles.mid_handle=a.clone().setStyles({backgroundColor:"#ffffff",opacity:0.01,width:"100%",height:"100%",left:0,top:0,cursor:"move",zIndex:5030}).addEvent("mousedown",function(b){b.preventDefault();this.fixToTopLeft();this.updateCropper();this.move=true}.bind(this));if(Browser.Engine.trident){this.elements.handles.each(function(b){b.addEvent("selectstart",$lambda(false))})}this.elements.cropper.adopt(this.elements.handles.mid_handle,this.elements.handles.n_handle,this.elements.handles.w_handle,this.elements.handles.e_handle,this.elements.handles.s_handle,this.elements.handles.nw_handle,this.elements.handles.ne_handle,this.elements.handles.sw_handle,this.elements.handles.se_handle)},computeBackgroundPosition:function(){if(this.fixedLeft){this.infos.background.position.left=-this.infos.cropper.position.left}else{this.infos.background.position.left=-(this.infos.picture.size.width-this.infos.cropper.position.right-this.infos.cropper.size.width)}if(this.fixedTop){this.infos.background.position.top=-this.infos.cropper.position.top}else{this.infos.background.position.top=-(this.infos.picture.size.height-this.infos.cropper.position.bottom-this.infos.cropper.size.height)}},storeCursorReference:function(a){this.cursorReference=a.page},getCursorRelative:function(a){return{x:a.x-this.cursorReference.x,y:a.y-this.cursorReference.y}},storeCropperReference:function(){this.infos.cropper.positionRef.top=this.elements.cropper.getStyle("top").toInt();this.infos.cropper.positionRef.left=this.elements.cropper.getStyle("left").toInt();this.infos.cropper.sizeRef.width=this.elements.cropper.getWidth();this.infos.cropper.sizeRef.height=this.elements.cropper.getHeight()},fixToLeft:function(a){if(a){this.infos.cropper.position.left=0}if(this.fixedLeft){return}if(!a){this.infos.cropper.position.left=this.infos.picture.size.width-this.infos.cropper.position.right-this.infos.cropper.size.width}this.infos.cropper.position.right=null;this.fixedLeft=true},fixToRight:function(a){if(a){this.infos.cropper.position.right=0}if(!this.fixedLeft){return}if(!a){this.infos.cropper.position.right=this.infos.picture.size.width-this.infos.cropper.position.left-this.infos.cropper.size.width}this.infos.cropper.position.left=null;this.fixedLeft=false},fixToTop:function(a){if(a){this.infos.cropper.position.top=0}if(this.fixedTop){return}if(!a){this.infos.cropper.position.top=this.infos.picture.size.height-this.infos.cropper.position.bottom-this.infos.cropper.size.height}this.infos.cropper.position.bottom=null;this.fixedTop=true},fixToBottom:function(a){if(a){this.infos.cropper.position.bottom=0}if(!this.fixedTop){return}if(!a){this.infos.cropper.position.bottom=this.infos.picture.size.height-this.infos.cropper.position.top-this.infos.cropper.size.height}this.infos.cropper.position.top=null;this.fixedTop=false},fixToTopLeft:function(a){this.fixToTop(a);this.fixToLeft(a)},fixToTopRight:function(a){this.fixToTop(a);this.fixToRight(a)},fixToBottomLeft:function(a){this.fixToBottom(a);this.fixToLeft(a)},fixToBottomRight:function(a){this.fixToBottom(a);this.fixToRight(a)},updateCropper:function(){this.computeBackgroundPosition();this.elements.cropper.setStyles({top:this.infos.cropper.position.top,right:this.infos.cropper.position.right,bottom:this.infos.cropper.position.bottom,left:this.infos.cropper.position.left,width:this.infos.cropper.size.width,height:this.infos.cropper.size.height,backgroundPosition:[this.infos.background.position.left,this.infos.background.position.top]});this.updateForm()},getGCD:function(d,c){if(!c){return d}var e=d%c;return this.getGCD(c,e)},mouseXToRatio:function(a){if(this.infos.ratio.strict){return a-a%this.infos.ratio.width}return a},mouseYToRatio:function(a){if(this.infos.ratio.strict){return a-a%this.infos.ratio.height}return a},resizeCropper:function(a){if(!this.resizeX&&!this.resizeY){return}var b=this.getCursorRelative(a);if(this.resizeX){if(this.infos.ratio.active){b.x=this.mouseXToRatio(b.x)}this.resizeWidth(b.x)}if(this.resizeY){if(this.infos.ratio.active){b.y=this.mouseYToRatio(b.y)}this.resizeHeight(b.y)}this.updateCropper()},resizeWidth:function(b){if(!this.fixedLeft){b=-b}var a=this.infos.cropper.sizeRef.width+b;this.infos.cropper.size.width=a.limit(this.infos.cropper.sizeMin.width,this.infos.cropper.sizeMax.width);if(this.infos.ratio.active){this.infos.cropper.size.height=((this.infos.cropper.size.width/this.infos.ratio.width)*this.infos.ratio.height).toInt()}},resizeHeight:function(b){if(!this.fixedTop){b=-b}if(this.infos.ratio.active){return this.resizeWidth(((b/this.infos.ratio.height)*this.infos.ratio.width).toInt())}var a=this.infos.cropper.sizeRef.height+b;this.infos.cropper.size.height=a.limit(this.infos.cropper.sizeMin.height,this.infos.cropper.sizeMax.height)},moveCropper:function(a){var b=this.getCursorRelative(a);this.infos.cropper.position.left=this.infos.cropper.positionRef.left+b.x;this.infos.cropper.position.top=this.infos.cropper.positionRef.top+b.y;this.infos.cropper.position.left=this.infos.cropper.position.left.limit(0,this.infos.cropper.positionMax.left);this.infos.cropper.position.top=this.infos.cropper.position.top.limit(0,this.infos.cropper.positionMax.top);this.updateCropper()},defineBound:function(a){this.infos.cropper.sizeMax.width=this.getMaxWidth();this.infos.cropper.sizeMax.height=this.getMaxHeight();this.infos.cropper.sizeMin.width=this.getMinWidth();this.infos.cropper.sizeMin.height=this.getMinHeight();this.infos.cropper.positionMax.top=this.getMaxTop();this.infos.cropper.positionMax.left=this.getMaxLeft();if(a){this.setBound()}},setBound:function(){this.infos.cropper.size.width=this.infos.cropper.sizeMax.width;this.infos.cropper.size.height=this.infos.cropper.sizeMax.height;this.updateCropper()},getMaxWidth:function(b){var a=$pick(b,this.infos.picture.size.width);a=a.toInt();if(a>this.infos.picture.size.width){a=this.infos.picture.size.width}if(this.options.maxWidth>0&&a>this.options.maxWidth){a=this.options.maxWidth}if(!this.fixedLeft){var c=this.infos.cropper.position.right}else{var c=this.infos.cropper.position.left}if(a>this.infos.picture.size.width-c){a=this.infos.picture.size.width-c}if(!this.infos.ratio.active){return a}return this.getMaxWidthRatio(a)},getMaxHeight:function(a){if(this.infos.ratio.active){return(this.infos.cropper.sizeMax.width/this.infos.ratio.width)*this.infos.ratio.height}a=$pick(a,this.infos.picture.size.height);a=a.toInt();if(a>this.infos.picture.size.height){a=this.infos.picture.size.height}if(this.options.maxHeight>0&&a>this.options.maxHeight){a=this.options.maxHeight}if(!this.fixedTop){var b=this.infos.cropper.position.bottom}else{var b=this.infos.cropper.position.top}if(a>this.infos.picture.size.height-b){a=this.infos.picture.size.height-b}return a},getMinWidth:function(b){var a=$pick(b,0);a=a.toInt();if(a<this.options.minWidth){a=this.options.minWidth}if(!this.infos.ratio.active){return a}return this.getMinWidthRatio(a)},getMinHeight:function(a){if(this.infos.ratio.active){return(this.infos.cropper.sizeMin.width/this.infos.ratio.width)*this.infos.ratio.height}a=$pick(a,0);a=a.toInt();if(a<this.options.minHeight){a=this.options.minHeight}return a},getMaxWidthRatio:function(b){var a=(b/this.infos.ratio.width)*this.infos.ratio.height;if(a>this.infos.picture.size.height){return this.getMaxWidthRatio(b-1)}if(this.options.maxHeight>0&&a>this.options.maxHeight){return this.getMaxWidthRatio(b-1)}if(!this.fixedTop){var c=this.infos.cropper.position.bottom}else{var c=this.infos.cropper.position.top}if(a>this.infos.picture.size.height-c){return this.getMaxWidthRatio(b-1)}if(!this.infos.ratio.strict||a.toInt()==a){return b}return this.getMaxWidthRatio(b-1)},getMinWidthRatio:function(b){var a=(b/this.infos.ratio.width)*this.infos.ratio.height;if(a<this.options.minHeight){return this.getMinWidthRatio(b+1)}if(!this.infos.ratio.strict||a.toInt()==a){return b}return this.getMinWidthRatio(b+1)},getMaxLeft:function(){return this.infos.picture.size.width-this.infos.cropper.size.width},getMaxTop:function(){return this.infos.picture.size.height-this.infos.cropper.size.height},updateForm:function(){this.elements.form.x.value=$pick(this.infos.cropper.position.left,(this.infos.picture.size.width-this.infos.cropper.position.right-this.infos.cropper.size.width));this.elements.form.y.value=$pick(this.infos.cropper.position.top,(this.infos.picture.size.height-this.infos.cropper.position.bottom-this.infos.cropper.size.height));this.elements.form.w.value=this.infos.cropper.size.width;this.elements.form.h.value=this.infos.cropper.size.height}});