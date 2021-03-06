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
require('php/common.php');
require('php/ec2.class.php');

// error handler function
$old_error_handler = set_error_handler('xmlError');

// Establish the session
$session = new Session();
if(!isset($_COOKIE['sessionID']) || !$session->retrieveSession($_COOKIE['sessionID'], false))
{
	badLogin();
}
$ec2svc = new EC2();
$ec2svc->keyId = $session->AWSAccessKeyID;
$ec2svc->secretKey = $session->AWSSecret;

switch(strtolower(arg('action')))
{
case 'describeimages':
	ec2Response($ec2svc, $ec2svc->describeImages(arg('id')));
	break;
case 'setproductcode':
	ec2Response($ec2svc, $ec2svc->setProductCode(arg('id'), arg('code')));
	break;
case 'getproductcode':
	ec2Response($ec2svc, $ec2svc->describeImageAttribute(arg('id'), 'productCodes'));
	break;
case 'modifylaunchpermissions':
	ec2Response($ec2svc, $ec2svc->modifyLaunchPermissions(arg('id'), arg('op'), arg('user'), arg('group')));
	break;
case 'describelaunchpermissions':
	ec2Response($ec2svc, $ec2svc->describeImageAttribute(arg('id'), 'launchPermission'));
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
case 'describeavailabilityzones':
	ec2Response($ec2svc, $ec2svc->describeAvailabilityZones(arg('zone')));
	break;
case 'describeregions':
	ec2Response($ec2svc, $ec2svc->describeRegions(arg('region')));
	break;
case 'describebundletasks':
	ec2Response($ec2svc, $ec2svc->describeBundleTasks(arg('id')));
	break;
case 'allocateaddress':
	ec2Response($ec2svc, $ec2svc->allocateAddress());
	break;
case 'describeaddresses':
	ec2Response($ec2svc, $ec2svc->describeAddresses(arg('ip')));
	break;
case 'associateaddress':
	ec2Response($ec2svc, $ec2svc->associateAddress(arg('id'), arg('ip')));
	break;
case 'disassociateaddress':
	ec2Response($ec2svc, $ec2svc->disassociateAddress(arg('ip')));
	break;
case 'releaseaddress':
	ec2Response($ec2svc, $ec2svc->releaseAddress(arg('ip')));
	break;
case 'createvolume':
	ec2Response($ec2svc, $ec2svc->createVolume(arg('size'), arg('zone'), arg('snapid')));
	break;
case 'describevolumes':
	ec2Response($ec2svc, $ec2svc->describeVolumes(arg('volid')));
	break;
case 'attachvolume':
	ec2Response($ec2svc, $ec2svc->attachVolume(arg('instid'), arg('volid'), arg('device')));
	break;
case 'detachvolume':
	ec2Response($ec2svc, $ec2svc->detachVolume(arg('volid'), arg('force'), arg('instid'), arg('device')));
	break;
case 'deletevolume':
	ec2Response($ec2svc, $ec2svc->deleteVolume(arg('volid')));
	break;
case 'createsnapshot':
	ec2Response($ec2svc, $ec2svc->createSnapshot(arg('volid')));
	break;
case 'describesnapshots':
	ec2Response($ec2svc, $ec2svc->describeSnapshots(arg('snapid')));
	break;
case 'deletesnapshot':
	ec2Response($ec2svc, $ec2svc->deleteSnapshot(arg('snapid')));
	break;
case 'bundleinstance':
	ec2Response($ec2svc, $ec2svc->bundleInstance(arg('id'), $ec2svc->keyId, arg('bucket'), arg('prefix'), $ec2svc->secretKey));
	break;
case 'cancelbundletask':
	ec2Response($ec2svc, $ec2svc->cancelBundleTask(arg('taskid')));
	break;
case 'describereservedinstancesoffering':
	ec2Response($ec2svc, $ec2svc->describeReservedInstancesOffering(arg('offid')));
	break;
case 'describereservedinstances':
	ec2Response($ec2svc, $ec2svc->describeReservedInstances(arg('instid')));
	break;
case 'purchasereservedinstances':
	ec2Response($ec2svc, $ec2svc->purchaseReservedInstances(arg('offid')));
	break;
case 'monitorinstance':
	ec2Response($ec2svc, $ec2svc->monitorInstance(arg('id')));
	break;
case 'unmonitorinstance':
	ec2Response($ec2svc, $ec2svc->unmonitorInstance(arg('id')));
	break;
default:
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" ?>' . "\n";
	echo '<unknownRequest name="' . arg('action') . '"/>';
}
?>