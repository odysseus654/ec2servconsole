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
		defaults: { panel: 'list' },
		actions: {
			list: { url: 'ec2.php', params: 'action=DescribeImages', title: 'Available Machine Images' }
//			detail: { url: 'ec2.php', params: 'action=DescribeImages&id=' },
//			add: { url: 'ec2.php', params: 'action=DescribeImages&id=', templParms: {action: 'addImage'} }
		}
	},
	ec2_securityGroups: {
		templ: 'securitygroups.xslt',
		addAction: { label: 'Add Group', action: 'addGroup' },
		defaults:  { panel: 'list' },
		actions: {
			list: { url: 'ec2.php', params: 'action=DescribeSecurityGroups', title: 'Available Security Groups' },
			addGroup: { cmd: '<addGroup />', title: 'New Security Group' },
			addRule: { url: 'ec2.php', params: 'action=DescribeSecurityGroups', title: 'Add Rule to Security Group', templParms: {action: 'addRule'} }
		},
		submitActions: {
			addGroup: { url: 'ec2.php', params: 'action=CreateSecurityGroup' },
			deleteGroup: { title: 'security group', url: 'ec2.php', params: 'action=DeleteSecurityGroup&id=' }
		}
	}
};
/*
instances:		runinstances, describeinstances, terminateinstances, confirmproductcode, rebootinstances, getconsoleoutput
keys:			createkeypair, describekeypairs, deletekeypair
securityGroups:	authextsecgroup, revokeextsecgroup, authintsecgroup, revokeintsecgroup
images:		setproductcode, getprodctcode, modifylaunchpermissions, describelaunchpermissions, resetlaunchpermissions, registerimage, deregisterimage
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

var panelContext = {};
var currentPanel = null;

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
				handler(result.cloneNode(true));
			});
		} else {	// a query is complete, return the results of the previous query
			handler(cacheVal.cloneNode(true));
		}
		return null;
	} else {
		var query = new TransformedAjaxCommand('templates/' + templ, cmd, function(result)
		{		// store the results of this query before passing control forward
			templCache[cmd] = result;
			handler(result.cloneNode(true));
		});
		if(!query.isComplete)
		{		// this query is incomplete, store it in case others want to attach to it
			templCache[cmd] = query;
		}
		return query;
	}
}

function xlateAndReplace(target, req, templ)
{
	function catchResults(result)
	{
		Sarissa.clearChildNodes(target);
		if(result && (result.nodeType != Node.DOCUMENT_FRAGMENT_NODE || result.childNodes.length))
		{
			target.appendChild(result);
		}
		else retrieveTemplCommand(CMD_FAILMSG, null, function(result)
		{
			if(result)
			{
				target.appendChild(result);
			}
		});
	}

	var query;
	if(req.url)
	{
		query = new TransformedAjaxQuery('templates/' + templ, req, catchResults);
	}
	else if(req.cmd)
	{
		query = retrieveTemplCommand(req.cmd, templ, catchResults);
	}
	else
	{
		internalAppError('a translate/replace query was submitted with no url and no cmd, what am i expected to do?', 'xlateAndReplace');
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

// Construct the pane with the specified action and optional parameter
function appXlateAndReplace(panelName,action,param,panelTarget)
{
	var panelDef = PANELS[panelName];
	var contextId = action.context || panelName;
	var templ = action.templ || panelDef.templ || APP_SCAFFOLD;

	var ctx = contextId ? panelContext[contextId] : null;
	var req = {};

	var key;
	var ctxSerialize = '';
	for(key in action)
	{
		if(typeof action[key] == 'string')
		{
			req[key] = action[key];
		}
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
	if(action.templParms)
	{
		for(key in action.templParms)
		{
			req.templParms[key] = action.templParms[key];
		}
	}
	if(param)
	{
		if(req.params)
		{
			req.params += param;
		} else {
			req.params = param;
		}
	}
	if(contextId && ctxSerialize)
	{
		req.headers['X-App-Context'] = encodeURIComponent(contextId) + '/' + ctxSerialize;
	}

	if(!panelTarget) panelTarget = document.getElementById('panel-target');
	if(!panelTarget)
	{
		internalAppError('unable to locate panel-target in shell', 'appXlateAndReplace');
		return null;
	}
	return xlateAndReplace(panelTarget, req, templ);
}

// Do it again, without any actual query this time
function appRexlateAndReplace(panelName, query, action, panelTarget)
{
	var contextId = action.context || panelName;
	var ctx = panelContext[contextId] || null;
	var templ = {};

	var key;
	if(ctx)
	{
		for(key in ctx)
		{
			templ[key] = ctx[key];
		}
	}
	if(action.templParms)
	{
		for(key in action.templParms)
		{
			templ[key] = action.templParms[key];
		}
	}

	if(!panelTarget) panelTarget = document.getElementById('panel-target');
	if(!panelTarget)
	{
		internalAppError('unable to locate panel-target in shell', 'appRexlateAndReplace');
	}
	else if(!reXlateAndReplace(panelTarget, query, templ))
	{
		internalAppError('unable to re-render translation', 'appRexlateAndReplace');
	}
}

// Check for any blocks, cancel any in-progress requests and prepare for this app to shut down
function appClosePanel(panelName)
{
	return true;
}

// Set the active application pane to the specified panelName and optional action
function appSetPanel(panelName,action)
{
	if(!panelName || !PANELS[panelName])
	{
		internalAppError('attempt to activate invalid or missing panel ' + panelName, 'appSetPanel');
		return;
	}
	if(panelName != currentPanel)
	{
		if(!appClosePanel(currentPanel)) return;
	}
	var panelDef = PANELS[panelName];
	if(!action && panelDef.defaults) action = panelDef.defaults.panel;
	if(!action || !panelDef.actions[action])
	{
		internalAppError('attempt to activate panel ' + panelName + ' with invalid or missing action ' + action, 'appSetPanel');
		return;
	}
	var actionDef = panelDef.actions[action];
	var titleObj = document.getElementById('panel-title');
	if(titleObj)
	{
		Sarissa.clearChildNodes(titleObj);
		titleObj.appendChild(document.createTextNode(actionDef.title));
	}
	var addObj = document.getElementById('panel-add');
	if(addObj)
	{
		Sarissa.clearChildNodes(addObj);
		if(panelDef.addAction)
		{
			var wrapObj = document.createElement('A');
			addObj.appendChild(wrapObj);
			wrapObj.appendChild(document.createTextNode(panelDef.addAction.label));
			if(panelDef.addAction.action)
			{
				wrapObj.onclick = function() { appPopupAction(panelDef.addAction.action); };
			}
		}
	}
	var query = appXlateAndReplace(panelName,actionDef);
	currentPanel = { name: panelName, action: action, query: query };
}

// Create a popup dialog with the contents of the specified action
function appPopupAction(action, panelName, param)
{
	if(!panelName && currentPanel) panelName = currentPanel.name;
	if(!panelName || !PANELS[panelName])
	{
		internalAppError('attempt to open popup with invalid or missing panel context ' + panelName, 'appPopupAction');
		return;
	}
	var panelDef = PANELS[panelName];
	if(!action || !panelDef.actions[action])
	{
		internalAppError('attempt to open popup in panel ' + panelName + ' with invalid or missing action ' + action, 'appPopupAction');
		return;
	}
	var actionDef = panelDef.actions[action];

	var popup = new DialogWindow(null, actionDef.title);
	popup.create();
	popup.show();
	appXlateAndReplace(panelName, actionDef, param, popup.wrapper.inner);
}

// called from a sortable TH element, examine the className to determine whether sorting is currently in progress on this element
function panelSort(thElem, sort)
{
	if(!currentPanel || !currentPanel.query || !currentPanel.query.isComplete)
	{
		internalAppError('attempt to sort with a nonexistant or non-rendable query', 'panelSort');
		return;
	}
	var sortdir = (thElem.className == 'sortup') ? 'd' : 'u';

	var panelDef = PANELS[currentPanel.name];
	var actionDef = panelDef.actions[currentPanel.action];
	var contextId = actionDef.context || currentPanel.name;

	if(!panelContext[contextId]) panelContext[contextId] = {};
	var ctx = panelContext[contextId];
	ctx.sort = sort;
	ctx.sortdir = sortdir;

	appRexlateAndReplace(currentPanel.name, currentPanel.query, actionDef);
}

// Refresh the current pane with fresh data
function appRefreshPanel()
{
	if(!currentPanel)
	{
		internalAppError('attempt to refresh a nonexistant panel', 'appRefreshPanel');
		return;
	}

	var panelDef = PANELS[currentPanel.name];
	var actionDef = panelDef.actions[currentPanel.action];
	currentPanel.query = appXlateAndReplace(currentPanel.name,actionDef);
}

function appHandleResponse(url, panelDef, xmlhttp)
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
	var result = XMLtoJS(xmlDoc);
	if(result.phpError)
	{
		var err = result.phpError;
		unexpectedResponse(url, 'PHP ' + err['@type'],
			err['@type'] + ' in ' + err['@file'] + ' line ' + err['@line'] + ': ' + err['_body']);
		return false;
	}
	else if(result.emptyEc2Response)
	{
		unexpectedResponse(url, 'Empty EC2 response with status ' + result.emptyEc2Response['@status']);
		return false;
	}
	else if(result.unknownRequest)
	{
		unexpectedResponse(url, 'Server did not understand request');
		return false;
	}
	else if(result.Response && result.Response.Errors)
	{
		var code = result.Response.Errors.Error.Code['_body'];
		var msg = result.Response.Errors.Error.Message['_body'];
		var descr;
		if(panelDef && panelDef.errors && panelDef.errors[code])
		{
			descr = panelDef.errors[code];
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
		return false;
	}
	else
	{
		// can we figure out whether this is the generic "succeeded" response?
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
			var ret = result[oneElem]['return']['_body'];
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
	}

	alert('ran off end of appHandleResponse?!?');
	return false;
}

function appSubmitActionImpl(actionDef, panelDef, panelName, formElem, content)
{
	new AjaxQuery({url: actionDef.url, method: 'post', params: content}, function(xmlhttp)
	{
		if(!xmlhttp)
		{
			internalAppError('an unexpected error occurred submitting form data', 'appSubmitActionImpl');
		}
		else if(appHandleResponse(actionDef.url, panelDef, xmlhttp))
		{
			var modal = ModalWindow.activeWindow(formElem);
			if(modal) modal.destroy();
			if(actionDef.nextAction)
			{
				appSetPanel(actionDef.nextPane || panelName, actionDef.nextAction);
			} else {
				appRefreshPanel();
			}
		}
	});
}

function appSubmitAction(formElem, action, panelName)
{
	if(!panelName && currentPanel) panelName = currentPanel.name;
	if(!panelName || !PANELS[panelName])
	{
		internalAppError('attempt to submit a form with invalid or missing panel context ' + panelName, 'appSubmitAction');
		return;
	}
	var panelDef = PANELS[panelName];
	if(!action || !panelDef.submitActions[action])
	{
		internalAppError('attempt to submit a form in panel ' + panelName + ' with invalid or missing action ' + action, 'appSubmitAction');
		return;
	}
	var actionDef = panelDef.submitActions[action];
	var content = Sarissa.formToQueryString(formElem);
	if(actionDef.params) content = actionDef.params + '&' + content;

	// firefox seems to really not like an XMLHTTP action inside of an onsubmit action, so let's break it out
	window.setTimeout(function()
	{
		appSubmitActionImpl(actionDef, panelDef, panelName, formElem, content);
	}, 0);
}

function appDelete(src, descr, id, action, panelName)
{
	if(!panelName && currentPanel) panelName = currentPanel.name;
	if(!action) action = 'delete';
	if(!panelName || !PANELS[panelName])
	{
		internalAppError('attempt to issue a delete action with invalid or missing panel context ' + panelName, 'appDelete');
		return;
	}
	var panelDef = PANELS[panelName];
	if(!action || !panelDef.submitActions[action])
	{
		internalAppError('attempt to issue a delete action panel ' + panelName + ' with invalid or missing action ' + action, 'appDelete');
		return;
	}
	var actionDef = panelDef.submitActions[action];

	var msg = 'Are you sure you wish to delete this ' + (actionDef.title || 'item') + ' ' + descr + '?';
	if(confirm(msg))
	{
		appSubmitActionImpl(actionDef, panelDef, panelName, src, actionDef.params + id);
	}
}
