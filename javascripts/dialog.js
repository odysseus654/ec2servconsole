/* dialog.js
 * Displays modal dialog boxes
 * Depends on: dialog.css
 * 
 * Part of EC2 Server Console http://sourceforge.net/ec2servconsole
 * 
 * Copyright 2007-2008 Erik Anderson
 * Based on technique presented by Isaac Schlueter
 * at http://foohack.com/2007/11/css-modal-dialog-that-works-right/
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// Have to hack for Safari, due to poor support for the focus() function.
var isSafari;
try {
	isSafari = window.navigator.vendor.match(/Apple/);
} catch (ex) {
	isSafari = false;
}

function Modal(dialogObj)
{
	this.dialog = dialogObj;
	this.dialog.tabIndex = -1;
	this.getRootNode().modalDialog = this; // this assumes that the root node != dialogObj

	// Have to hack for Safari, due to poor support for the focus() function.
	this.dialogFocuser = dialogObj;
	if ( isSafari )
	{
		var focuser = document.createElement('a');
		focuser.href="#";
		focuser.style.display='block';
		focuser.style.height='0';
		focuser.style.width='0';
		focuser.style.position = 'absolute';
		this.dialog.insertBefore(focuser, this.dialog.firstChild);
		this.dialogFocuser = focuser;
	}
}
Modal.prototype = {};
Modal.prototype.prevDialog = null;
Modal.prototype.modalShowing = false;
Modal.prototype.onModalCovered = function(newFocus) {}	// hook for when this dialog is "covered" by a different one
Modal.prototype.onModalUncovered = function() {}		// hook for when this dialog is "uncovered" by a prior dialog

// static properties
Modal.modalShowing = false;		// not showing a dialog on startup
Modal.currentDialog = null;		// WebDialog objects do refer to the DOM, but only to dialog elements which we don''t reference here
Modal.focusedElement = null;
Modal.zindex = 2000;

Modal.prototype.isVisible = function()
{
	return this.modalShowing;
}

Modal.prototype.getRootNode = function()
{
	var root = this.dialog;
	while(root && root.className != 'modal') root = root.parentNode;
	return root;
};

Modal.prototype.show = function()	// uses: currentDialog, modalShowing, zIndex
{
	if(Modal.currentDialog === this || this.modalShowing) return;	// probably shouldn''t have happened
	//assert(!this.prevDialog)
	this.prevDialog = Modal.currentDialog;
	this.modalShowing = true;
	Modal.currentDialog = this;
	if(this.prevDialog) this.prevDialog.onModalCovered(this);
	
	var root = this.getRootNode();
	if(!root) alert('internal assert: couldn\'t find root of modal element!');
	if(root && root.style.display != 'block')
	{
		root.style.display = 'block';
		root.style.zIndex = Modal.zindex++;
	}
	Modal.modalShowing = true;
};

Modal.prototype.hide = function()		// uses: currentDialog, modalShowing, focusedElement
{
	if(!this.modalShowing)	return;		// probably shouldn''t have happened
	
	// hide our dialog element
	var root = this.getRootNode();
	if(!root) alert('internal assert: couldn\'t find root of modal element!');
	if(root)
	{
		root.style.display = 'none';
	}
	this.modalShowing = false;
	
	if(Modal.currentDialog !== this) return;	// possibly nested call?
	
	// unroll until we find the current dialog (if any)
	var lastDialog;
	while(Modal.currentDialog && !Modal.currentDialog.modalShowing)
	{
		lastDialog = Modal.currentDialog;
		Modal.currentDialog = Modal.currentDialog.prevDialog;
		lastDialog.prevDialog = null;
	}
	
	if(Modal.currentDialog)
	{
		// clean up the chain (prune any hidden elements from it)
		var lastKnownActive = Modal.currentDialog;
		var thisDialog = Modal.currentDialog.prevDialog;
		while(thisDialog)
		{
			lastDialog = thisDialog;
			thisDialog = thisDialog.prevDialog;
			if(!lastDialog.modalShowing)
			{
				lastDialog.prevDialog = null;
				lastKnownActive.prevDialog = thisDialog;
			} else {
				lastKnownActive = lastDialog;
			}
		}
		
		try {
			Modal.currentDialog.dialog.focus();
		} catch(ex) {}
		Modal.currentDialog.onModalUncovered();
	} else {
		Modal.modalShowing = false;
		try {
			Modal.focusedElement.focus();
		} catch(ex) {}
	}
};

Modal.activeModal = function(loc)
{
	while(loc && loc.className != 'modal') loc = loc.parentNode;
	return loc ? loc.modalDialog : null;
};

Modal.onfocus = function(event) {
	event = event || window.event;
	var el = event.target || event.srcElement;
	
	// save the last focused element when the modal is hidden.
	if ( !Modal.modalShowing ) {
		if(el == document.documentElement)
		{
			Modal.focusedElement = document.getElementById('body');	// we don''t EVER want an accidental reference to html, creating a reference loop
		} else {
			Modal.focusedElement = el;
		}
		return;
	}
	
	// if we''re focusing the dialog, then just clear the blurring flag.
	// else, focus the dialog and prevent the other event.
	var p = el.parentNode;
	while ( p && p.parentNode && p !== Modal.currentDialog.dialog ) {
		p=p.parentNode;
	}
	var dialogFocuser = Modal.currentDialog.dialog;
	if(isSafari) dialogFocuser = dialogFocuser.firstChild;
	if ( p !== Modal.currentDialog.dialog ) {
		try {
			dialogFocuser.focus();
		}
		catch(ex) {}
	}
};

Modal.onblur = function() {
	if ( !Modal.modalShowing ) {
		Modal.focusedElement = document.getElementById('body');
	}
};

(function ()	// evaluate the following after the page has finished loading
{
	// also catch and toggle focus events.
	var html = document.documentElement;
	html.tabIndex = -1;
	html.onfocus = html.onfocusin = Modal.onfocus;
	html.onblur = html.onfocusout = Modal.onblur;
	if ( isSafari ) {
		html.addEventListener('DOMFocusIn',Modal.onfocus);
		html.addEventListener('DOMFocusOut',Modal.onblur);
	}
	// focus and blur events are tricky to bubble.
	// need to do some special stuff to handle MSIE.
})();

window.setTimeout(function ()	// evaluate the following after the page has finished loading
{
	var body = document.getElementById('body');
	if(body) body.tabIndex = -1;
}, 0);

// --------------------------------------------------------------------------

function ModalWindow(content)
{
	if(content) this.create(content);
}
ModalWindow.prototype = {};
ModalWindow.prototype.modal = null;
ModalWindow.prototype.autoDestroy = false;
ModalWindow.prototype.wrapper = null;

ModalWindow.prototype.isVisible = function()
{
	return this.modal ? this.modal.isVisible() : false;
}

ModalWindow.prototype.create = function(content)
{
	if(this.modal) destroy();
	var wrapper = this.constructWrapper(content);
	document.getElementById('body').parentNode.appendChild(wrapper.outer);
	this.modal = new Modal(wrapper.outer);
	this.wrapper = wrapper;
	this.modal.window = this;
};

ModalWindow.prototype.destroy = function()
{
	if(!this.modal) return;
	if(this.isVisible()) this.hide();
	var wrapper = this.modal.getRootNode();
	wrapper.parentNode.removeChild(wrapper);
	this.modal.window = null;
	this.modal = null;
};

ModalWindow.prototype.show = function()
{
	if(!this.modal) return;
	this.modal.show();
};

ModalWindow.prototype.hide = function()
{
	if(!this.modal) return;
	this.modal.hide();
	if(this.autoDestroy) this.destroy();
};

ModalWindow.activeWindow = function(loc)
{
	var modal = Modal.activeModal(loc);
	return modal ? modal.window : null;
};

ModalWindow.prototype.constructWrapper = function(content, useOrig)
{
	function createDiv(cls, parent)
	{
		var div = document.createElement('div');
		div.className = cls;
		if(parent) parent.appendChild(div);
		return div;
	}

	var modal = createDiv('modal');
	modal.appendChild(createDiv('overlay-decorator'));
	
	var inner = createDiv('overlay-wrap', modal);
	
	inner = createDiv('overlay', inner);
	inner.appendChild(createDiv('modal-decorator'));
	
	inner = createDiv('modal-wrap', inner);
	
	inner = createDiv('modal-content', inner);
	
	if(content) 
	{
		inner.appendChild(this.prepareContentNode(content, useOrig));
	}
	return { inner: inner, outer: modal };
};

ModalWindow.prototype.prepareContentNode = function(content, useOrig)
{
	var newContent = content;
	if(content && !useOrig) 
	{
		newContent = content.cloneNode(true);
		if(newContent.style.display == 'none') newContent.style.display = 'block';
		if(newContent.id) newContent.id = '';
	}
	return newContent;
};

// --------------------------------------------------------------------------

function DialogWindow(content, caption)
{
	if(caption) this.caption = caption;
	ModalWindow.apply(this, arguments);
}
DialogWindow.prototype = new ModalWindow();
DialogWindow.prototype.caption = '';
DialogWindow.prototype.captionObj = null;

DialogWindow.prototype.destroy = function()
{
	ModalWindow.prototype.destroy.apply(this, arguments);
	this.captionObj = null;
};

DialogWindow.prototype.show = function()
{
	if(this.captionObj) this.captionObj.nodeValue = this.caption;
	ModalWindow.prototype.show.apply(this, arguments);
};

DialogWindow.prototype.createCloseLink = function()
{
	// mostly here to give some closure isolation to constructing the close link;
	var self = this;
	return function(event)
	{
		self.destroy();
	}
};

DialogWindow.prototype.constructWrapper = function(content, useOrig)
{
	function createElem(typ, cls, parent)
	{
		var div = document.createElement(typ);
		if(cls) div.className = cls;
		if(parent) parent.appendChild(div);
		return div;
	}

	var wrapper = createElem('div', 'dialog-wrap');

	var table = createElem('table', 'dialog-table', wrapper);
//	table.width = '100%';
//	table.cellspacing = 0;
//	table.cellpadding = 0;
	table = createElem('tbody', null, table);
	table = createElem('tr', null, table);

	var caption = createElem('td', 'dialog-caption', table);
	var close = createElem('td', 'dialog-close', table);
	
	var capText = document.createTextNode('-dummy caption here-');
	caption.appendChild(capText);
	this.captionObj = capText;

	var closeLink = createElem('a', 'dialog-close-link', close);
	closeLink.onclick = this.createCloseLink();
	var closeText = document.createTextNode('Close');
	closeLink.appendChild(closeText);

	var inner = createElem('div', null, wrapper);
	
	if(content) 
	{
		inner.appendChild(this.prepareContentNode(content, useOrig));
	}

	return { inner: inner, outer: ModalWindow.prototype.constructWrapper.apply(this, [wrapper, true]).outer};
};

// var someDialog = new WebDialog(document.getElementById('dialog'));
