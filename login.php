<?php
/* login.php
 * Handles account-related actions
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
require('php/common.php');
require('php/ec2.class.php');

// error handler function
$old_error_handler = set_error_handler('xmlError');
$session = new Session();

function handleLogin()
{
	global $session;
	
	if(!$session->createSession(arg('name'), arg('pass')))
	{
		badLogin();
	} else {
		setcookie('sessionID', $session->sessionID);
		returnXml('<success />');
	}
}

function ec2LikeError($code,$descr)
{
	returnXml('<Response><Errors><Error><Code>' . $code . '</Code><Message>' . $descr . '</Message></Error></Errors></Response>');
}

function errMissingParameter()
{
	ec2LikeError('MissingParameter', 'A required field was not entered.');
}

// actions that do not require an active session
switch(strtolower(arg('action')))
{
case 'login':
	handleLogin();
	return;
case 'createaccount':
	handleCreateAccount();
	return;
}

// Establish the session
if(!isset($_COOKIE['sessionID']) || !$session->retrieveSession($_COOKIE['sessionID'], false))
{
	badLogin();
}

// ----------------------------------------------------------------------------

function handleCreateAccount()
{
	global $session;
	
	// do basic sanity checks to make sure all the pieces are here
	if(!arg('login') || !arg('name') || !arg('email') || !arg('password') || !arg('organization') || !arg('ec2account') || !arg('ec2pass'))
	{
		errMissingParameter();
		return;
	}
	if(arg('password') != arg('pass2'))
	{
		// this should really have been checked by the client code before this request was sent
		ec2LikeError('InvalidParameterValue', 'The two passwords did not match.');
		return;
	}
	
	// check to see if this login has been used yet
	$query = mysql_query(
		'select `loginID` from `' . $session->DB_prefix . 'login` where name=\'' . mysql_real_escape_string(arg('login')) . '\'', $session->dbConnect())
	or $session->sqlError('handleCreateAccount', 'query for existing logins');
	
	if(mysql_fetch_assoc($query))
	{
		mysql_free_result($query);
		ec2LikeError('CreateAccount.LoginExists', 'The requested login is already in the system');
		return;
	}
	mysql_free_result($query);

	// check to see if the EC2 login is valid
	$ec2svc = new EC2();
	$ec2svc->keyId = arg('ec2account');
	$ec2svc->secretKey = arg('ec2pass');
	$ec2result = $ec2svc->describeSecurityGroups();
	if($ec2svc->getResponseCode() != 200)
	{
		ec2Response($ec2result);
		return;
	}
	
	// everything looks good, let's do it
	$cmd = 'insert into `account`(`descr`,`accessKeyId`,`secret`) '
		. 'values(\'' . mysql_real_escape_string(arg('organization')) . '\',\'' . mysql_real_escape_string(arg('ec2account'))
		. '\',\'' . mysql_real_escape_string(arg('ec2pass')) . '\')';
	$query = mysql_query($cmd, $session->dbConnect()) or $session->sqlError('handleCreateAccount', 'construct a new account');
	$accountID = mysql_insert_id($session->dbh);
	
	$cmd = 'insert into `login`(`name`,`pass`,`descr`,`email`,`accountID`,`createdBy`,`lastPassword`,`lastLogin`)'
		.'values(\'' . mysql_real_escape_string(arg('login')) . '\',\'' . mysql_real_escape_string($session->makeSecret(arg('password')))
		. '\',\'' . mysql_real_escape_string(arg('name')) . '\',\'' . mysql_real_escape_string(arg('email'))
		. '\',\'' . mysql_real_escape_string($accountID) . '\',-1,NOW(),NOW())';
	$query = mysql_query($cmd, $session->dbConnect()) or $session->sqlError('handleCreateAccount', 'construct a new login');
	$accountID = mysql_insert_id($session->dbh);

	// account should exist, can we do a login now?
	if(!$session->createSession(arg('login'), arg('password')))
	{
		badLogin();
	} else {
		setcookie('sessionID', $session->sessionID);
		returnXml('<success />');
	}
}

?>