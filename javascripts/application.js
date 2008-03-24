/* application.js
 * Contains the application scaffold display logic
 * Depends on: dynamic.js, dialog.js,Sarissa
 * 
 * Part of EC2 Server Console http://sourceforge.net/ec2servconsole
 * 
 * Copyright 2007-2008 Erik Anderson
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
var APP_SCAFFOLD = 'scaffold.xslt';
var CMD_LOADMSG  = '<loading/>';
var CMD_RELOADMSG = '<reloading/>';
var CMD_FAILMSG  = '<failed/>';

var PANELS = {
	ec2_images: {
		templ: 'images.xslt',
		url: 'ec2.php',
		defaults: { panel: 'list' },
		actions: {
			list: { params: 'action=DescribeImages', title: 'Available Machine Images' }
//			detail: { params: 'action=DescribeImages&id=' },
//			add: { params: 'action=DescribeImages&id=', templParms: {action: 'addImage'} }
		}
	},
	ec2_securityGroups: {
		templ: 'securitygroups.xslt',
		url: 'ec2.php',
		defaults: { panel: 'list', add: 'addGroup' },
		exclusionStyles: { intMine: ['secInternal', 'secInternalMine'], intTheirs: ['secInternal', 'secInternalTheirs'], ext: ['secExternal'] },
		actions: {
			list:		{ params: 'action=DescribeSecurityGroups', title: 'Available Security Groups' },
			addGroup:	{ label: 'Add Group', cmd: '<addGroup />', title: 'New Security Group' },
			addRule:	{
				params: 'action=DescribeSecurityGroups&ignore=', title: 'Add Rule to Security Group', templParms: {action: 'addRule'},
				onInject: secCheckRuleGrp
			}
		},
		submitActions: {
			addGroup:		{ params: 'action=CreateSecurityGroup' },
			deleteGroup:	{ label: 'security group', params: 'action=DeleteSecurityGroup&id=' },
			addExtRule:		{ params: 'action=AuthExtSecGroup' },
			addIntRule:		{ params: 'action=AuthIntSecGroup' },
			delExtRule:		{ params: 'action=RevokeExtSecGroup' },
			delIntRule:		{ params: 'action=RevokeIntSecGroup' }
		}
	}
};
/*
instances:		runinstances, describeinstances, terminateinstances, confirmproductcode, rebootinstances, getconsoleoutput
keys:			createkeypair, describekeypairs, deletekeypair
images:			setproductcode, getprodctcode, modifylaunchpermissions, describelaunchpermissions, resetlaunchpermissions, registerimage, deregisterimage
*/
var ERRORS = {
	'AuthFailure':					'Permission denied',
	'InvalidManifest':				'Image is corrupt',
	'InvalidAMIID.Malformed':		'Image does not exist',
	'InvalidAMIID.NotFound':		'Image does not exist',
	'InvalidAMIID.Unavailable':		'Image is no longer available',
	'InvalidInstanceID.Malformed':	'Instance does not exist',
	'InvalidInstanceID.NotFound':	'Instance does not exist',
	'InvalidKeyPair.NotFound':		'Keypair does not exist',
	'InvalidKeyPair.Duplicate':		'Keypair with this name already exists',
	'InvalidGroup.NotFound':		'Security group does not exist',
	'InvalidGroup.Duplicate':		'Security group with this name already exists',
	'InvalidGroup.InUse':			'Security group is in use, must be idle to be deleted',
	'InvalidGroup.Reserved':		'You cannot use this name as a security group',
	'InvalidParameterValue': 		'Internal: EC2 submitted request was invalid',
	'InvalidPermission.Duplicate':	'You already granted this permission',
	'InvalidPermission.Malformed':	'This permission makes no sense',
	'InvalidReservationID.Malformed': 'Reservation ID does not exist',
	'InvalidReservationID.NotFound': 'Reservation ID does not exist',
	'InstanceLimitExceeded':		'You are attempting to launch more instances than you are permitted',
	'InvalidParameterCombination':	'The number of requested instances to start is nonsensical',
	'InvalidUserID.Malformed':		'The user ID does not exist',
	'InvalidAMIAttributeItemValue':	'Internal: EC2 submitted request was invalid',
	'MissingParameter':				'A required field was not entered',
	'UnknownParameter':				'Internal: EC2 submitted request was invalid',
	'InternalError':				'AWS Internal error occurred.  If you can reproduce it, please post a message on the AWS forums.',
	'InsufficientInstanceCapacity':	'Not enough free machines are available, please try again later or reduce the size of your request',
	'Unavailable':					'The AWS system is overloaded or otherwise unavailable.  Please try again later'
};

// -----------------------------------------------------------------------------------------------------
// Application-specific version of TransformedAjaxQuery that includes error checking

function AppTransformedAjaxQuery(xslt, req, handler)
{
	this.autoRetry = req.autoRetry;
	this.req = req;
	TransformedAjaxQuery.apply(this, arguments);
}
subclass(AppTransformedAjaxQuery, TransformedAjaxQuery);
AppTransformedAjaxQuery.prototype.autoRetry = false;
AppTransformedAjaxQuery.prototype.req = null;

AppTransformedAjaxQuery.prototype.onLoaded = function()
{
	var self = this;
	function retryQuery(panelObj)
	{
		if(!panelObj.returnValue)
		{
			self.implSetValue(null);
		} else {
			// retry this query
			self.refresh();
		}
	}
	
	if(this.xml && this.xslt)
	{
		switch(appResponseOkay(this.xmlSource.url, this.xml, this.autoRetry ? retryQuery : null))
		{
		case 'succeed':
			this.implSetValue(TemplateQuery.transform(this.xslt, this.xml, this.templParms, this.outputMethod));
			break;
		case 'fail':
			this.implSetValue(null);
			break;
		// case 'ignore':		<-- this technically be considered a failure of the promise to always return a result,
		//                          responsibility is basically transferred to appResponseOkay to call this.retryQuery
		//                          if it returns an 'ignore' response
		}
	} else {
		this.implSetValue(null);
	}
};

AppTransformedAjaxQuery.prototype.refresh = function()
{
	this.xmlSource = new XmlAjaxQuery(this.req, function(xml)
	{
		this.xml = xml;
	});
	this.compSource.addPromise(this.xmlSource);
};

// -----------------------------------------------------------------------------------------------------

var CMD_CACHE = {};
function retrieveTemplCommand(cmd,templ,handler)
{
	if(!templ) templ = APP_SCAFFOLD;
	if(!CMD_CACHE[templ]) CMD_CACHE[templ] = {};
	var templCache = CMD_CACHE[templ];

	// let's do a full cache of this query.  Method similar to how caching is done in dynamic.js
	var cacheVal = templCache[cmd];
	if(cacheVal)
	{
		if(typeof cacheVal == 'object' && cacheVal instanceof TransformedAjaxCommand)
		{		// a query is currently in progress, attach to that query
			cacheVal.setOnAvail(function(result)
			{
				if(result)
				{
					handler(result.cloneNode(true));
				} else {
					handler(null);
				}
			});
		} else {	// a query is complete, return the results of the previous query
			handler(cacheVal.cloneNode(true));
		}
		return null;
	} else {
		var query = new TransformedAjaxCommand('templates/' + templ, cmd, function(result)
		{		// store the results of this query before passing control forward
			if(result)
			{
				templCache[cmd] = result;
				handler(result.cloneNode(true));
			} else {
				handler(null);
			}
		});
		if(!query.isComplete)
		{		// this query is incomplete, store it in case others want to attach to it
			templCache[cmd] = query;
		}
		return query;
	}
}

function xlateAndReplace(target, req, templ, callback)
{
	function catchResults(result)
	{
		Sarissa.clearChildNodes(target);
		if(result && (result.nodeType != Node.DOCUMENT_FRAGMENT_NODE || result.childNodes.length))
		{
			target.appendChild(result);
			if(callback) callback(true);
		}
		else retrieveTemplCommand(CMD_FAILMSG, null, function(result)
		{
			if(result)
			{
				target.appendChild(result);
			}
			if(callback) callback(false);
		});
	}

	var query;
	if(req.url)
	{
		query = new AppTransformedAjaxQuery('templates/' + templ, req, catchResults);
	}
	else if(req.cmd)
	{
		query = retrieveTemplCommand(req.cmd, templ, catchResults);
	}
	else
	{
		internalAppError('a translate/replace query was submitted with no url and no cmd, what am i expected to do?', 'xlateAndReplace');
		if(callback) callback(false);
	}
	if(query && !query.isComplete)
	{
		retrieveTemplCommand(CMD_LOADMSG, null, function(result)
		{
			if(result && !query.isComplete)
			{
				Sarissa.clearChildNodes(target);
				target.appendChild(result);
			}
		});
	}
	return query;
}

function appResponseOkay(url, xml, retryHandler)
{
	var result = xmlToJS(xml);
	if(result.phpError)
	{
		var err = result.phpError;
		unexpectedResponse(url, 'PHP ' + err['@type'],
			err['@type'] + ' in ' + err['@file'] + ' line ' + err['@line'] + ': ' + err._body);
		return 'fail';
	}
	else if(result.emptyEc2Response)
	{
		unexpectedResponse(url, 'Empty EC2 response with status ' + result.emptyEc2Response['@status']);
		return 'fail';
	}
	else if(result.unknownRequest)
	{
		unexpectedResponse(url, 'Server did not understand request');
		return 'fail';
	}
	else if(result.Response && result.Response.Errors)
	{
		var code = result.Response.Errors.Error.Code._body;
		var msg = result.Response.Errors.Error.Message._body;
		var descr;
		if(this.def.errors && this.def.errors[code])
		{
			descr = this.def.errors[code];
		}
		else if(ERRORS[code])
		{
			descr = ERRORS[code];
		}
		else
		{
			descr = msg;
		}
		alert(descr + '\n\nDetails: [' + code + '] ' + msg);
		return 'fail';
	}
	
	return 'succeed';
}

// This function has been through a few development iterations.  One often-required feature of complex forms is the ability to show/hide
// sections of the form dynamically based on conditions elsewhere in the form.  The normal way to do this is to set class names on the
// appropriate section and programatically suppress those classes.  This requires a <style> tag in the HEAD tag for each potential ruleset.
// IE for various reasons will not permit STYLE elements to be transformed out of the XSLT files (and appears to require that they be
// constructed using DOM methods) and I'm not willing to place every potential ruleset for every pane in index.html, so this function
// attempts to simplify the problem and handle the DOM aspects as well.
//
// Arguments:
//   exclDesc - definition of the rulesets to maintain, taken from PANELS.*.exclusionStyles.
//              A hash of every valid exclusion rule mapped to an array of the CSS classnames to *display* (all other classnames mentioned
//              in other rules will be suppressed)
//   newExcl  - the exclusion rule to show.  If empty or nonmatching, then all classes are suppressed
var AVAIL_EXCLUSIONS = {};
function buildClassExclusion(exclDesc)
{
	function buildExclusionImpl(descMap)
	{
		var supMap = {};		// set of all classes mentioned in this map
		var supArray = [];		// array of all classes mentioned in this map
		var exclList = [];		// array of exclusion codes in this map
		var key, idx;
		
		// investigate and build up our support structure
		for(key in descMap)
		{
			exclList.push(key);
			var thisExcl = descMap[key];
			for(idx=0; idx < thisExcl.length; idx++)
			{
				if(!supMap[thisExcl[idx]])
				{
					supMap[thisExcl[idx]] = true;
					supArray.push(thisExcl[idx]);
				}
			}
		}
		
		// now go through and create the stylesheets
		for(key in descMap)
		{
			if(!AVAIL_EXCLUSIONS[key])
			{
				var thisExcl = descMap[key];
				
				// create the base stylesheets
				var sheet = new CSSStyleSheet();
				var node = sheet.sheet || sheet.styleElement;
				node.disabled = true;
				
				// build a map of the classes in this rule
				var thisExclMap = {};
				for(idx=0; idx < thisExcl.length; idx++)
				{
					thisExclMap[thisExcl[idx]] = true;
				}
				
				// now add every element not in the exclusion map
				for(idx=0; idx < supArray.length; idx++)
				{
					if(!thisExclMap[supArray[idx]])
					{
						sheet.addRule('.' + supArray[idx], 'display: none');
					}
				}
				AVAIL_EXCLUSIONS[key] = { sheet: sheet, exc: exclList };
			}
		}
	}
	
	// wipe all the previous stylesheets
	for(var key in AVAIL_EXCLUSIONS)
	{
		var sheet = AVAIL_EXCLUSIONS[key].sheet;
		var node = sheet.styleElement || sheet.sheet.owningElement;
		node.parentNode.removeChild(node);
	}
	AVAIL_EXCLUSIONS = {};

	// it's an array, so let's build each of the elements inside of it
	if(Array.prototype.isPrototypeOf(exclDesc))
	{
		for(var idx=0; idx < exclDesc.length; idx++)
		{
			buildExclusionImpl(exclDesc[idx]);
		}
	} else {
		buildExclusionImpl(exclDesc);
	}
}

function setClassExclusion(newExcl)
{
	var thisExcl = AVAIL_EXCLUSIONS[newExcl];
	if(!thisExcl)
	{
		internalAppError('An exclude rule was requested that was not found in the list of permitted rules', 'setClassExclusion');
		return;
	}
	
	for(var idx=0; idx < thisExcl.exc.length; idx++)
	{
		var oldStyle = AVAIL_EXCLUSIONS[thisExcl.exc[idx]].sheet;
		var node = oldStyle.sheet || oldStyle.styleElement;
		node.disabled = true;
	}
	
	var node = thisExcl.sheet.sheet || thisExcl.sheet.styleElement;
	node.disabled = false;
}

function reXlateAndReplace(target, query, templParms)
{
	if(templParms)
	{
		query.templParms = templParms;
	}
	var result = query.rerender();
	if(result)
	{
		Sarissa.clearChildNodes(target);
		target.appendChild(result);
	}
	return result;
}

// -----------------------------------------------------------------------------------------------------
// Application-optimized functions, these attempt to retrieve the current context
// from a DOM element somewhere in the parent of the element being focused on

var panelContext = {};

function Panel(panelName, action, target)
{
	if(!panelName)
	{
		internalAppError('attempt to construct a panel with no name', 'Panel');
		return null;
	}
	else if(typeof panelName == 'string')
	{
		this.name = panelName;
		this.def = PANELS[this.name];
		if(!this.def)
		{
			internalAppError('attempt to construct a panel with unknown name ' + panelName, 'Panel');
			return null;
		}
	}
	else
	{
		this.def = panelName;
		this.name = this.def.name;
	}
	if(action.substr(0, 1) == '@')
	{
		action = this.def.defaults ? this.def.defaults[action.substr(1)] : null;
	}
	this.actionName = action;
	this.action = this.def.actions[this.actionName];
	if(!this.action)
	{
		internalAppError('attempt to construct panel ' + this.name + ' with unknown action ' + action, 'Panel');
		return null;
	}
	this.target = target;
	this.templ = this.action.templ || this.def.templ || APP_SCAFFOLD;
	this.contextId = this.action.context || this.name;
}
Panel.prototype.name = null;
Panel.prototype.def = null;
Panel.prototype.contextId = null;
Panel.prototype.actionName = null;
Panel.prototype.action = null;
Panel.prototype.query = null;
Panel.prototype.templ = null;
Panel.prototype.target = null;		// DOM reference, avoid circular references!

// Construct the pane with the specified action and optional parameter
Panel.prototype.xlateAndReplace = function(param, autoRetry)
{
	var ctx = this.contextId ? panelContext[this.contextId] : null;
	var req = {};

	var key;
	var ctxSerialize = '';
	for(key in this.action)
	{
		if(typeof this.action[key] == 'string')
		{
			req[key] = this.action[key];
		}
	}
	if(!req.url && !req.cmd)
	{
		req.url = this.def.url;
	}
	req.templParms = {};
	req.headers = {};
	if(ctx)
	{
		for(key in ctx)
		{
			req.templParms[key] = ctx[key];
			if(ctxSerialize) ctxSerialize += '&';
			ctxSerialize += encodeURIComponent(key) + '=' + encodeURIComponent(ctx[key]);
		}
	}
	if(this.action.templParms)
	{
		for(key in this.action.templParms)
		{
			req.templParms[key] = this.action.templParms[key];
		}
	}
	if(param)
	{
		req.templParms['param'] = param;
		if(req.params)
		{
			req.params += param;
		} else {
			req.params = param;
		}
	}
	if(this.contextId && ctxSerialize)
	{
		if(ctxSerialize)
		{
			req.headers['X-App-Context'] = encodeURIComponent(this.contextId) + '/' + ctxSerialize;
		} else {
			req.headers['X-App-Request-Context'] = encodeURIComponent(this.contextId);
		}
	}
	if(autoRetry)
	{
		req.autoRetry = true;
	}

	if(!this.target)
	{
		internalAppError('unable to locate panel-target in shell', 'appXlateAndReplace');
		return null;
	}
	var self = this;
	this.query = xlateAndReplace(this.target, req, this.templ, function(succeeded)
	{
		if(succeeded)
		{
			if(self.action.onInject) self.action.onInject(self);
		}
	});
	return this.query;
};

// Do it again, without any actual query this time
Panel.prototype.rexlateAndReplace = function()
{
	var ctx = panelContext[this.contextId] || null;
	var templ = {};

	var key;
	if(ctx)
	{
		for(key in ctx)
		{
			templ[key] = ctx[key];
		}
	}
	if(this.action.templParms)
	{
		for(key in this.action.templParms)
		{
			templ[key] = this.action.templParms[key];
		}
	}

	if(!this.target)
	{
		internalAppError('unable to locate panel-target in shell', 'appRexlateAndReplace');
	}
	else if(!reXlateAndReplace(this.target, this.query, templ))
	{
		internalAppError('unable to re-render translation', 'appRexlateAndReplace');
	}
};

// called from a sortable TH element, examine the className to determine whether sorting is currently in progress on this element
Panel.prototype.sort = function(thElem, sort)
{
	if(!this.query || !this.query.isComplete)
	{
		internalAppError('attempt to sort with a nonexistant or non-rendable query', 'panelSort');
		return;
	}
	var sortdir = (thElem.className == 'sortup') ? 'd' : 'u';
	if(!panelContext[this.contextId]) panelContext[this.contextId] = {};
	var ctx = panelContext[this.contextId];
	ctx.sort = sort;
	ctx.sortdir = sortdir;

	this.rexlateAndReplace();
};

// Refresh the current pane with fresh data
Panel.prototype.refresh = function()
{
	this.query = this.xlateAndReplace(null, true);
};

// Check for any blocks, cancel any in-progress requests and prepare for this app to shut down
Panel.prototype.close = function()
{
	return true;
};

Panel.prototype.handleResponse = function(url, xmlhttp)
{
	if(xmlhttp.status != 200)
	{
		serverAppError(url, xmlhttp.status, xmlhttp.statusText, xmlhttp.responseText);
		return false;
	}
	var contentType = xmlhttp.getResponseHeader('Content-Type');
	if(!contentType || contentType.indexOf('xml') == -1)
	{
		unexpectedResponse(url, 'Unexpected content type "' + contentType + '"', xmlhttp.responseText);
		return false;
	}
	var xmlDoc;
	if(xmlhttp.responseXML && xmlhttp.responseXML.documentElement && xmlhttp.responseXML.documentElement.children
		 && xmlhttp.responseXML.documentElement.children.length)
	{
		xmlDoc = xmlhttp.responseXML;
	} else {
		xmlDoc = XmlAjaxQuery.parse(xmlhttp.responseText, url);
	}
	if(appResponseOkay(url, xmlDoc) != 'success') return false;

	// can we figure out whether this is the generic "succeeded" response?
	var result = xmlToJS(xmlDoc);
	var thisElem, oneElem = null, twoElem = null;
	for(thisElem in result)
	{
		if(!oneElem && !twoElem)
		{
			oneElem = thisElem;
		} else {
			oneElem = null;
			twoElem = thisElem;
		}
	}
	if(oneElem && result[oneElem]['return'])
	{
		var ret = result[oneElem]['return']._body;
		if(ret == 'true')
		{
			return true;
		} else {
			unexpectedResponse(url, 'EC2 returned failure without additional information');
			return false;
		}
	}
	else
	{
		unexpectedResponse(url, 'Unknown XML response from server', (new XMLSerializer()).serializeToString(xmlDoc));
		return false;
	}

	alert('ran off end of appHandleResponse?!?');
	return false;
};

Panel.prototype.submitAction = function(actionDef, content, formElem, callback)
{
	var self = this;
	var url = actionDef.url || this.def.url;
	if(!url)
	{
		internalAppError('attempt to submit a form without a destination', 'appSubmitActionImpl');
		return;
	}
	var nothing = new AjaxQuery({url: url, method: 'post', params: content}, function(xmlhttp)
	{
		if(!xmlhttp)
		{
			internalAppError('an unexpected error occurred submitting form data', 'appSubmitActionImpl');
		}
		else if(self.handleResponse(url, xmlhttp))
		{
			if(callback) callback(xmlhttp);
			if(actionDef.nextAction)
			{
				var newPane = new AppPane(actionDef.nextPane || self.name, actionDef.nextAction);
				if(newPane)
				{
					newPane.xlateAndReplace(param, false);
				}				
			} else {
				appRefreshPanel();
			}
		}
	});
};

Panel.prototype.submitFormAction = function(submitAction, formElem, param)
{
	if(!submitAction || !this.def.submitActions[submitAction])
	{
		internalAppError('attempt to submit a form in panel ' + this.name + ' with invalid or missing action ' + submitAction, 'appSubmitAction');
		return;
	}
	var actionDef = this.def.submitActions[submitAction];
	var content = formElem.elements ? Sarissa.formToQueryString(formElem) : '';
	if(actionDef.params) content = actionDef.params + '&' + content;
	if(param) content += param;

	return this.submitAction(actionDef, content, formElem);
};

Panel.prototype.deletePane = function(src, descr, id, delAction)
{
	if(!delAction) action = 'delete';
	if(!this.def.submitActions[delAction])
	{
		internalAppError('attempt to issue a delete action panel ' + this.name + ' with invalid or missing action ' + delAction, 'appDelete');
		return;
	}
	var actionDef = this.def.submitActions[delAction];

	var msg = 'Are you sure you wish to delete this ' + (actionDef.label || 'item') + ' ' + descr + '?';
	if(confirm(msg))
	{
		this.submitAction(actionDef, actionDef.params + id, src);
	}
};

// ----------------------------------------------------------------------------
// Represents an application pane
var currentAppPane = null;
function AppPane(panelName, action)
{
	if(!action) action = '@panel';
	var hasError = Panel.apply(this, [panelName, action, document.getElementById('panel-target')]) === null;
	if(hasError) return null;

	if(currentAppPane && currentAppPane !== this) // not sure how it could, but i guess check anyhow
	{
		if(!currentAppPane.close()) return null;	// not ready to transition yet, close() should throw the error or explanation
	}
	currentAppPane = this;

	if(this.def.exclusionStyles)
	{
		buildClassExclusion(this.def.exclusionStyles);
	}

	var titleObj = document.getElementById('panel-title');
	if(titleObj)
	{
		Sarissa.clearChildNodes(titleObj);
		titleObj.appendChild(document.createTextNode(this.action.title));
	}

	var addObj = document.getElementById('panel-add');
	if(addObj)
	{
		Sarissa.clearChildNodes(addObj);
		if(this.def.defaults && this.def.defaults.add)
		{
			var addAction = this.def.actions[this.def.defaults.add];
			var wrapObj = document.createElement('A');
			addObj.appendChild(wrapObj);
			wrapObj.appendChild(document.createTextNode(addAction.label || addAction.title));
			var self = this;
			wrapObj.onclick = this.createClickAction(this.def.defaults.add);
		}
	}
}
subclass(AppPane, Panel);

AppPane.prototype.createClickAction = function(action)
{
	// seperated out from the main body to help prevent circular references
	var name = this.def;
	return function() { appPopupAction(action, name); };
};

// ----------------------------------------------------------------------------
// Create a popup dialog with the contents of the specified action
function PopupPanel(panelName, action, onClose)
{
	var hasError = Panel.apply(this, [panelName, action]) === null;
	if(hasError) return null;
	this.onCloseCallback = onClose;

	var popup = new DialogWindow(null, this.action.title);
	popup.popupPanel = this;		// let's just sneak this in here, the only DOM reference we make is also one it makes as well
	popup.hide = this.hookClosePanel();
	popup.create();
	popup.show();
	this.target = popup.wrapper.inner;
}
subclass(PopupPanel, Panel);
PopupPanel.prototype.returnValue = false;
PopupPanel.prototype.onCloseCallback = null;

PopupPanel.prototype.submitFormAction = function(submitAction, formElem, param)
{
	if(!submitAction || !this.def.submitActions[submitAction])
	{
		internalAppError('attempt to submit a form in panel ' + this.name + ' with invalid or missing action ' + submitAction, 'appSubmitAction');
		return;
	}
	var actionDef = this.def.submitActions[submitAction];
	var content = formElem.elements ? Sarissa.formToQueryString(formElem) : '';
	if(actionDef.params) content = actionDef.params + '&' + content;
	if(param) content += param;
	
	var self = this;
	return this.submitAction(actionDef, content, formElem, function()
	{
		self.returnValue = true;
		var modal = ModalWindow.activeWindow(formElem);
		if(modal) modal.destroy();
	});
};

PopupPanel.prototype.hookClosePanel = function()
{
	var self = this;
	return function()	// this hooks DialogWindow.hide
	{
		debugger;
		DialogWindow.prototype.hide.apply(this);
		self.onHideDialog();
	}
};

PopupPanel.prototype.onHideDialog = function()
{
	if(this.onCloseCallback)
	{
		this.onCloseCallback(this);
	}
};

// Given a dialog-based pane and an element inside that pane, locate the object representing that pane
PopupPanel.activePanel = function(srcElem)
{
	var dialog = srcElem ? ModalWindow.activeWindow(srcElem) : null;
	return (dialog ? dialog.popupPanel : null) || currentAppPane;
};

// ----------------------------------------------------------------------------

// Set the active application pane to the specified panelName and optional action
function appSetPanel(panelName,action)
{
	if(currentAppPane)
	{
		if((currentAppPane.name == panelName || currentAppPane.def === panelName) && currentAppPanel.actionName == action)
		{
			// no change, just return
			return currentAppPane;
		}
	}
	var pane = new AppPane(panelName, action);
	if(pane)
	{
		pane.xlateAndReplace(null, true);
	}
}

function appPopupAction(action, panelName, param)
{
	var pane = new PopupPanel(panelName || (currentAppPane ? currentAppPane.def : null), action);
	if(pane)
	{
		pane.xlateAndReplace(param, true);
	}
}

// called from a sortable TH element, examine the className to determine whether sorting is currently in progress on this element
function panelSort(thElem, sort)
{
	var pane = PopupPanel.activePanel(thElem);
	if(!pane)
	{
		internalAppError('attempt to sort with a nonexistant panel', 'panelSort');
		return;
	}
	pane.sort(thElem, sort);
}

// Refresh the current pane with fresh data
function appRefreshPanel(srcElem)
{
	var pane = PopupPanel.activePanel(srcElem);
	if(!pane)
	{
		internalAppError('attempt to refresh a nonexistant panel', 'appRefreshPanel');
		return;
	}
	pane.refresh();
}

function appSubmitAction(formElem, action, param)
{
	var pane = PopupPanel.activePanel(formElem);
	if(!pane)
	{
		internalAppError('attempt to submit a nonexistant panel', 'appSubmitAction');
		return;
	}

	// firefox seems to really not like an XMLHTTP action inside of an onsubmit action, so let's break it out
	window.setTimeout(function()
	{
		pane.submitFormAction(action, formElem, param);
	}, 0);
}

function appDelete(src, descr, id, action, panelName)
{
	var pane = PopupPanel.activePanel(src);
	if(!pane)
	{
		internalAppError('attempt to delete using a nonexistant panel', 'appDelete');
		return;
	}
	
	return pane.deletePane(src, descr, id, action);
}

// ---------------------------------------------------------------------------- Security group-specific logic
function secCheckRuleGrp(pane)
{
	var extObj = document.getElementById('secExternal');
	if(extObj.checked)
	{
		setClassExclusion('ext');
	} else {
		var intMine = document.getElementById('secIntMine');
		if(intMine.checked)
		{
			setClassExclusion('intMine');
		} else {
			setClassExclusion('intTheirs');
		}
	}
}
