<?php
/* ec2.php
 * Amazon EC2 web thunk
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

// error handler function
function xmlError($errno, $errstr, $errfile, $errline)
{
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" ?>' . "\n";

	switch ($errno)
	{
	case E_ERROR:
		echo '<phpError type="error" ';
		break;
	case E_WARNING:
		echo '<phpError type="warning" ';
		break;
	case E_NOTICE:
		echo '<phpError type="notice" ';
		break;
	case E_USER_ERROR:
		echo '<phpError type="user-error" ';
		break;
	case E_USER_WARNING:
		echo '<phpError type="user-warning" ';
		break;
	case E_USER_NOTICE:
		echo '<phpError type="user-notice" ';
		break;
	case E_STRICT:
		echo '<phpError type="strict" ';
		break;
	case E_RECOVERABLE_ERROR:
		echo '<phpError type="recoverable" ';
		break;
	default:
		echo '<phpError type="' . $errno . '" ';
		break;
	}

	echo 'file="' . $errfile . '" line="' . $errline . '">';
	echo $errstr;
	echo '</phpError>';

	/* Don't execute PHP internal error handler */
	exit(1);
}
$old_error_handler = set_error_handler('xmlError');

require('ec2.class.php');
$ec2svc = new EC2();

function arg($name)
{
	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_POST[$name])) return $_POST[$name]; else return NULL;
	} else {
		if(isset($_GET[$name])) return $_GET[$name]; else return NULL;
	}
}

function ec2Response(&$ec2svc, $text)
{
	if($text)
	{
		header('Content-Type: ' . $ec2svc->getResponseContentType());
		echo $text;
		return;
	}

	header('Content-Type: text/xml');
	echo '<?xml version="1.0" ?>' . "\n";
	echo '<emptyEc2Response status="' . $ec2svc->getResponseCode() . '"/>';
}

switch(strtolower(arg('action')))
{
case 'describeimages':
	ec2Response($ec2svc, $ec2svc->describeImages(arg('id')));
	break;
case 'setproductcode':
	ec2Response($ec2svc, $ec2svc->setProductCode(arg('id'), arg('code')));
	break;
case 'getproductcode':
	ec2Response($ec2svc, $ec2svc->getProductCode(arg('id')));
	break;
case 'modifylaunchpermissions':
	ec2Response($ec2svc, $ec2svc->modifyLaunchPermissions(arg('id'), arg('op'), arg('user'), arg('group')));
	break;
case 'describelaunchpermissions':
	ec2Response($ec2svc, $ec2svc->describeLaunchPermissions(arg('id')));
	break;
case 'resetlaunchpermissions':
	ec2Response($ec2svc, $ec2svc->resetLaunchPermissions(arg('id')));
	break;
case 'registerimage':
	ec2Response($ec2svc, $ec2svc->registerImage(arg('path')));
	break;
case 'deregisterimage':
	ec2Response($ec2svc, $ec2svc->deregisterImage(arg('id')));
	break;
case 'runinstances':
	$options = array();
	if(arg('count'))
	{
		$options['count'] = arg('count');
	}
	else if(arg('mincount') && arg('maxcount'))
	{
		$options['minCount'] = arg('minCount');
		$options['maxCount'] = arg('maxCount');
	}

	if(arg('key')) $options['keyName'] = arg('key');
	if(arg('data')) $options['userData'] = arg('data');
	if(arg('type')) $options['type'] = arg('type');
	if(arg('group')) $options['group'] = arg('group');

	ec2Response($ec2svc, $ec2svc->runInstances(arg('id'), $options));
	break;

case 'describeinstances':
	ec2Response($ec2svc, $ec2svc->describeInstances(arg('id')));
	break;
case 'terminateinstances':
	ec2Response($ec2svc, $ec2svc->terminateInstances(arg('id')));
	break;
case 'confirmproductcode':
	ec2Response($ec2svc, $ec2svc->confirmProductInstance(arg('code'), arg('id')));
	break;
case 'rebootinstances':
	ec2Response($ec2svc, $ec2svc->rebootInstances(arg('id')));
	break;
case 'getconsoleoutput':
	ec2Response($ec2svc, $ec2svc->getConsoleOutput(arg('id')));
	break;
case 'createkeypair':
	ec2Response($ec2svc, $ec2svc->createKeyPair(arg('name')));
	break;
case 'describekeypairs':
	ec2Response($ec2svc, $ec2svc->describeKeyPairs(arg('id')));
	break;
case 'deletekeypair':
	ec2Response($ec2svc, $ec2svc->deleteKeyPair(arg('id')));
	break;
case 'describesecuritygroups':
	ec2Response($ec2svc, $ec2svc->describeSecurityGroups(arg('id')));
	break;
case 'createsecuritygroup':
	ec2Response($ec2svc, $ec2svc->createSecurityGroup(arg('name'), arg('descr')));
	break;
case 'deletesecuritygroup':
	ec2Response($ec2svc, $ec2svc->deleteSecurityGroup(arg('id')));
	break;
case 'authextsecgroup':
	ec2Response($ec2svc, $ec2svc->authExternalSecGroupIngress(arg('id'), arg('proto'), arg('ip'), arg('from'), arg('to')));
	break;
case 'revokeextsecgroup':
	ec2Response($ec2svc, $ec2svc->revokeExternalSecGroupIngress(arg('id'), arg('proto'), arg('ip'), arg('from'), arg('to')));
	break;
case 'authintsecgroup':
	ec2Response($ec2svc, $ec2svc->authInternalSecGroupIngress(arg('id'), arg('user'), arg('group')));
	break;
case 'revokeintsecgroup':
	ec2Response($ec2svc, $ec2svc->revokeInternalSecGroupIngress(arg('id'), arg('user'), arg('group')));
	break;
default:
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" ?>' . "\n";
	echo '<unkownRequest name="' . arg('action') . '"/>';
}

?>