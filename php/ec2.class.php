<?php
/* ec2.class.php
 * Amazon EC2 Query API Implementation
 * 
 * Part of EC2 Server Console http://sourceforge.net/ec2servconsole
 * 
 * Copyright 2007-2008 Erik Anderson
 * Based on the S3 REST API by Geoffrey P. Gaudreault (http://www.neurofuzzy.net)
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
 *
 *	NOTE: ENTER YOUR API ID AND SECRET KEY BELOW!!!
 *	NOTE: DON'T BE STUPID AND POST YOUR KEY PUBLICALLY, LIKE I AND AT LEAST ONE OTHER PERSON HAS
 *
 */

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

class ec2 {

	// The API access point URL
	var $EC2_METHOD = 'http';				// use https if your php installation supports it
	var $EC2_HOST = 'ec2.amazonaws.com';
	var $EC2_VERSION = '2009-04-04';

	// set to true to echo debug info
	var $_debug = false;

	// -----------------------------------------
	// -----------------------------------------
	// The Amazon Secret (set before making requests)
	var $keyId;
	var $secretKey;
	// -----------------------------------------
	// -----------------------------------------
		
	// default response content type
	var $_responseContentType = "text/xml";

	// default response code
	var $_responseCode = 0;
	
	/*
	* Constructor: Amazon S3 REST API implementation
	*/
	function ec2($options = NULL)
	{
		if(!defined('DATE_AWS'))
		{
			define('DATE_AWS', 'Y\-m\-d\TH\:i\:s\Z');
		}
		$this->httpDate = gmdate(DATE_AWS);
		
		// REQUIRES PEAR PACKAGE
		// get with "pear install Crypt_HMAC"
		require_once 'Crypt/HMAC.php';
		
//		$this->hasher =& new Crypt_HMAC($this->secretKey, "sha1");
		
		// REQUIRES PEAR PACKAGE
		// get with "pear install --onlyreqdeps HTTP_Request2"
		
		require_once 'HTTP/Request2.php';
	}
	
	function getResponseContentType()
	{
		return $this->_responseContentType;
	}

	function getResponseCode()
	{
		return $this->_responseCode;
	}
	
	/*
	* Method: sendRequest
	* Sends the request to EC2
	* 
	* Parameters:
	* action - the action to perform
	* verb - the action to apply to the resource (GET, or POST)
	* parameters - an array of additional parameters (if any)
	*/
	function sendRequest($action, $verb = NULL, $parms = NULL)
	{
		// update date / time on each request
		$this->httpDate = gmdate(DATE_ISO8601);
		$httpDate = $this->httpDate;

		// the parts of the request that we do ourselves		
		$request = array('Action' => $action,
			'AWSAccessKeyId' => $this->keyId,
			'SignatureVersion' => '2',
			'SignatureMethod' => 'HmacSHA1',
			'Timestamp' => $httpDate,
			'Version' => $this->EC2_VERSION);
		
		if (is_array($parms))
		{
			foreach ($parms as $key => $value)
			{
				$this->debug_text("Parameter: $key => $value");
				$request[$key] = $parms[$key];
			}

		}

		uksort($request, 'strcmp');

		$queryString = '';
		$delimiter = '';

		foreach($request as $key => $value)
		{
			$queryString .= $delimiter . $key . '=' . urlencode($value);
			$delimiter = '&';
		}

		$signTarget = $verb . "\n" . $this->EC2_HOST . "\n/\n" . $queryString;
		$this->debug_text("Signing String: ".var_export($signTarget,true));
		$hasher = new Crypt_HMAC($this->secretKey, "sha1");
		$signature = $this->hex2b64($hasher->hash($signTarget));
		$this->debug_text("Signature: $signature");
		$queryString .= '&Signature=' . urlencode($signature);
		$this->debug_text("Query: " . $queryString);
						
		$req =& new HTTP_Request2($this->EC2_METHOD . '://' . $this->EC2_HOST . '/?' . $queryString);
		$req->setMethod($verb);
		$resp = $req->send();
		
		$this->_responseContentType = $resp->getHeader("Content-Type");
		if($this->_responseContentType == '') $this->_responseContentType = 'text/xml';
		$this->_responseCode = $resp->getStatus();
		$this->debug_text('code: ' . $this->_responseCode);
		$this->debug_text('type: ' . $this->_responseContentType);
		$this->debug_text($resp->getBody());

		return $resp->getBody();
	}
	

	/*
	* Method: describeImages
	* Returns a list of all available AMI images
	* 
	* Parameters:
	* imageId - single image or array of images
	* owner - single owner or array of owners, 'amazon' and 'self' are valid values
	* execBy - single user or array of users, 'self' and 'all' are valid values
	*/
	function describeImages($imageId = NULL, $owner = NULL, $execBy = NULL)
	{
		$parms = array();

		$this->addParmArray($parms, 'ImageId', $imageId);
		$this->addParmArray($parms, 'Owner', $owner);
		$this->addParmArray($parms, 'ExecutableBy', $execBy);

		return $this->sendRequest('DescribeImages', 'GET', $parms);
	}

	/*
	* Method: describeImageAttribute
	* Retrieves an individual attribute of a registered AMI
	*
	* Parameters:
	* imageId - AMI image to query
	* attr - attribute to retrieve (launchPermission, productCodes, kernel, ramdisk, blockDeviceMapping,platform)
	*/
	function describeImageAttribute($imageID, $attr)
	{
		return $this->sendRequest('DescribeImageAttribute', 'GET', array('ImageId' => $imageID, 'Attribute' => $attr));
	}

	/*
	* Method: setProductCode
	* Associates a product code with an AMI image
	*
	* Parameters:
	* imageId - AMI image to set the code on
	* productCode - product code to set
	*/
	function setProductCode($imageID, $productCode)
	{
		return $this->sendRequest('ModifyImageAttribute', 'POST',
			array('ImageId' => $imageID, 'Attribute' => 'productCodes', 'ProductCode.1' => $productCode));
	}

	/*
	* Method: modifyLaunchPermissions
	* Adds or removes users or user groups as having permission to launch images
	*
	* Parameters:
	* imageId - AMI image to set
	* optype - 'add' or 'remove'
	* users - individual user ID or list of users
	* usergroups - 'all' will declare the image public
	*/
	function modifyLaunchPermissions($imageID, $optype = 'add', $users = NULL, $usergroups = NULL)
	{
		$parms = array('ImageID' => $imageID, 'Attribute' => 'launchPermission', 'OperationType' => $optype);

		$this->addParmArray($parms, 'UserId', $users);
		$this->addParmArray($parms, 'Group', $usergroups);

		return $this->sendRequest('ModifyImageAttribute', 'POST', $parms);
	}

	/*
	* Method: resetLaunchPermissions
	* Resets launch permissions for the specified AMI image to their default values
	*
	* Parameters:
	* imageId - AMI image to query
	*/
	function resetLaunchPermissions($imageID)
	{
		return $this->sendRequest('ResetImageAttribute', 'POST', array('ImageId' => $imageID, 'Attribute' => 'launchPermission'));
	}

	/*
	* Method: registerImage
	* Registers the specified AMI in S3 storage as a launchable EC2 machine image
	*
	* Parameters:
	* imagePath - full path to the AMI manifest in S3 storage
	*/
	function registerImage($imagePath)
	{
		return $this->sendRequest('RegisterImage', 'POST', array('ImageLocation' => $imagePath));
	}

	/*
	* Method: deregisterImage
	* Deregisters the specified Image ID, preventing it from being used for future launches
	*
	* Parameters:
	* imageID - the AMI image to deregister
	*/
	function deregisterImage($imageID)
	{
		return $this->sendRequest('DeregisterImage', 'POST', array('ImageId' => $imageID));
	}

	/*
	* Method: runInstances
	* start one or more instances
	*
	* Parameters:
	*   imageID - AMI ID of image to launch
	*   options - array of options to pass
	*
	* Valid options:
	*   count - number of instances to launch.  If count, minCount, maxCount aren't specified, this is 1
	*   minCount - minimum number of instances to launch.  Don't specify count if you use this.
	*   maxCount - maximum number of instances to launch.  Don't specify count if you use this.
	*   keyName - the access key to use to permit access
	*   group - the security group the instance will be a part of (single or array), default is 'default' group
	*   userData - user data available to the launched instance
	*   type - 'small', 'medium', or 'large', the instance type to launch
	*/
	function runInstances($imageID, $options)
	{
		$parms = array('ImageID' => $imageID);

		// instance count
		if(isset($options['count']))
		{
			$parms['MinCount'] = $options['count'];
			$parms['MaxCount'] = $options['count'];
		}
		else if(isset($options['minCount']) && isset($options['maxCount']))
		{
			$parms['MinCount'] = $options['minCount'];
			$parms['MaxCount'] = $options['maxCount'];
		}
		else
		{
			$parms['MinCount'] = '1';
			$parms['MaxCount'] = '1';
		}

		if(isset($options['keyName']))
		{
			$parms['KeyName'] = $options['keyName'];
		}
		if(isset($options['userData']))
		{
			$parms['Data'] = base64_encode($options['userData']);
		}
		if(isset($options['type']))
		{
			$parms['InstanceType'] = $options['type'];
		}
		if(isset($options['zone']))
		{
			$parms['Placement.AvailabilityZone'] = $options['zone'];
		}
		if(isset($options['kernel']))
		{
			$parms['KernelId'] = $options['kernel'];
		}
		if(isset($options['ramdisk']))
		{
			$parms['RamdiskId'] = $options['ramdisk'];
		}

		if(isset($options['group']))
		{
			$this->addParmArray($parms, 'groupId', $options['group']);
		}
		if(isset($options['monitor']))
		{
			if($options['monitor'])
			{
				$parms['Monitoring.Enabled'] = 'true';
			}
		}

		return $this->sendRequest('RunInstances', 'POST', $parms);
	}

	/*
	* Method: describeInstances
	* Returns a list of all current instances
	* 
	* Parameters:
	* instanceID - single instance ID or array of IDs
	*/
	function describeInstances($instanceID = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'InstanceId', $instanceID);
		return $this->sendRequest('DescribeInstances', 'GET', $parms);
	}

	/*
	* Method: terminateInstances
	* Terminates the requested instances
	* 
	* Parameters:
	* instanceID - single instance ID or array of IDs
	*/
	function terminateInstances($instanceID = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'InstanceId', $instanceID);
		return $this->sendRequest('TerminateInstances', 'POST', $parms);
	}

	/*
	* Method: confirmProductInstance
	* Determines whether the specified product code has been attached to the specified instance
	*
	* Parameters:
	* productCode - the product code
	* instanceID - the instance ID
	*/
	function confirmProductInstance($productCode, $instanceID)
	{
		return $this->sendRequest('ConfirmProductInstance', 'GET',
			array('ProductCode' => $productCode, 'InstanceId' => $instanceID));
	}

	/*
	* Method: rebootInstances
	* Sends a request to reboot the requested instances
	* 
	* Parameters:
	* instanceID - single instance ID or array of IDs
	*/
	function rebootInstances($instanceID = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'InstanceId', $instanceID);
		return $this->sendRequest('RebootInstances', 'POST', $parms);
	}

	/*
	* Method: getConsoleOutput
	* Retrieves the stored console output for the specified instance
	* 
	* Parameters:
	* instanceID - the instance to query
	*/
	function getConsoleOutput($instanceID)
	{
		return $this->sendRequest('GetConsoleOutput', 'GET', array('InstanceId' => $instanceID));
	}
	
	/*
	* Method: createKeyPair
	* Construct a new access key pair and return the public/private key
	* 
	* Parameters:
	* name - the new key to create
	*/
	function createKeyPair($name)
	{
		return $this->sendRequest('CreateKeyPair', 'POST', array('KeyName' => $name));
	}

	/*
	* Method: describeKeyPairs
	* Returns a list of all defined access key pairs
	* 
	* Parameters:
	* names - single key name or array of keys
	*/
	function describeKeyPairs($names = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'KeyName', $names);
		return $this->sendRequest('DescribeKeyPairs', 'GET', $parms);
	}

	/*
	* Method: deleteKeyPair
	* Delete an access key
	* 
	* Parameters:
	* name - the key to delete
	*/
	function deleteKeyPair($name)
	{
		return $this->sendRequest('DeleteKeyPair', 'POST', array('KeyName' => $name));
	}

	/*
	* Method: describeSecurityGroups
	* Returns a list of all available security groups
	* 
	* Parameters:
	* groups - single group name or array of images
	*/
	function describeSecurityGroups($groups = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'GroupName', $groups);
		return $this->sendRequest('DescribeSecurityGroups', 'GET', $parms);
	}

	/*
	* Method: createSecurityGroup
	* Creates a new network security group for the current user
	* 
	* Parameters:
	* name - name for the new group
	* descr - description for the new group
	*/
	function createSecurityGroup($name, $descr)
	{
		$parms = array('GroupName' => $name, 'GroupDescription' => $descr);
		return $this->sendRequest('CreateSecurityGroup', 'POST', $parms);
	}

	/*
	* Method: deleteSecurityGroup
	* Deletes an existing network security group for the current user
	* 
	* Parameters:
	* name - group name
	*/
	function deleteSecurityGroup($name)
	{
		return $this->sendRequest('DeleteSecurityGroup', 'POST', array('GroupName' => $name));
	}

	/*
	* Method: authExternalSecGroupIngress
	* Authorizes instances in the specified security group to be contacted by the specified IP and port range
	* 
	* Parameters:
	* name - security group name
	* proto - 'tcp', 'udp', 'icmp'
	* ipaddr - CIDR IP range to authorize
	* fromPort - lower port range to authorize (or icmp message type, -1 for all icmp types)
	* toPort - upper port range to authorize (or icmp message type, -1 for all icmp types
	*/
	function authExternalSecGroupIngress($name, $proto, $ipaddr, $fromPort = -1, $toPort = -1)
	{
		return $this->sendRequest('AuthorizeSecurityGroupIngress', 'POST',
			array('GroupName' => $name, 'IpProtocol' => $proto, 'CidrIp' => $ipaddr,
				'FromPort' => $fromPort, 'ToPort' => $toPort));
	}

	/*
	* Method: revokeExternalSecGroupIngress
	* Revokes permission for instances in the specified security group to be contacted by the specified IP and port range
	* 
	* Parameters:
	* name - security group name
	* proto - 'tcp', 'udp', 'icmp'
	* ipaddr - CIDR IP range to authorize
	* fromPort - lower port range to authorize (or icmp message type, -1 for all icmp types)
	* toPort - upper port range to authorize (or icmp message type, -1 for all icmp types
	*/
	function revokeExternalSecGroupIngress($name, $proto, $ipaddr, $fromPort = -1, $toPort = -1)
	{
		return $this->sendRequest('RevokeSecurityGroupIngress', 'POST',
			array('GroupName' => $name, 'IpProtocol' => $proto, 'CidrIp' => $ipaddr,
				'FromPort' => $fromPort, 'ToPort' => $toPort));
	}

	/*
	* Method: authInternalSecGroupIngress
	* Authorizes instances in the specified security group to be contacted by instances in the specified security group
	* 
	* Parameters:
	* name - security group name
	* srcUser - owner user ID of sec group to authorize
	* srcGroup - name of sec group to authorize
	*/
	function authInternalSecGroupIngress($name, $srcUser, $srcGroup)
	{
		return $this->sendRequest('AuthorizeSecurityGroupIngress', 'POST',
			array('GroupName' => $name, 'SourceSecurityGroupOwnerId' => $srcUser,
				'SourceSecurityGroupName' => $srcGroup));
	}

	/*
	* Method: revokeInternalSecGroupIngress
	* Revokes permission for instances in the specified security group to be contacted by instances in the specified security group
	* 
	* Parameters:
	* name - security group name
	* srcUser - owner user ID of sec group to authorize
	* srcGroup - name of sec group to authorize
	*/
	function revokeInternalSecGroupIngress($name, $srcUser, $srcGroup)
	{
		return $this->sendRequest('RevokeSecurityGroupIngress', 'POST',
			array('GroupName' => $name, 'SourceSecurityGroupOwnerId' => $srcUser,
				'SourceSecurityGroupName' => $srcGroup));
	}
	
	/*
	* Method: describeAvailabilityZones
	* Describes the availability zones currently available to the user
	*
	* Parameters:
	* zones (optional) - zone or list of zones to display
	*/
	function describeAvailabilityZones($zones = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'ZoneName', $zones);
		return $this->sendRequest('DescribeAvailabilityZones', 'GET', $parms);
	}

	/*
	* Method: describeRegions
	* Describes the regions currently available to the user
	*
	* Parameters:
	* regions (optional) - region or list of regions to display
	*/
	function describeRegions($regions = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'RegionName', $regions);
		return $this->sendRequest('DescribeRegions', 'GET', $parms);
	}
	
	/*
	* Method: describeBundleTasks
	* Describes the state of the current Windows AMI bundling tasks in progress
	*
	* Parameters:
	* bundleId (optional) - the ID of the bundle operation to describe
	*/
	function describeBundleTasks($bundleId = NULL)
	{
		$parms = array();
		if($bundleId != NULL)
		{
			$parms['BundleId'] = $bundleId;
		}
		return $this->sendRequest('DescribeBundleTasks', 'GET', $parms);
	}

	/*
	* Method: allocateAddress
	* Acquires an elastic IP address for use with your account
	*/
	function allocateAddress()
	{
		return $this->sendRequest('AllocateAddress', 'POST', array());
	}

	/*
	* Method: describeAddresses
	* lastic IP addresses assigned to your account or provides information about a specific address
	*
	* Parameters:
	* publicIp (optional) - IP or list of IPs to display
	*/
	function describeAvailabilityZones($publicIp = NULL)
	{
		$parms = array();
		$this->addParmArray($parms, 'PublicIp', $publicIp);
		return $this->sendRequest('DescribeAddresses', 'GET', $parms);
	}

	/*
	* Method: associateAddress
	* Associates an elastic IP address with an instance
	*
	* Parameters:
	* instanceID - the instance to associate
	* publicIp - the public IP to associate
	*/
	function associateAddress($instanceID, $publicIp)
	{
		return $this->sendRequest('AssociateAddress', 'POST',
			array('InstanceId' => $instanceID, 'PublicIp' => $publicIp));
	}

	/*
	* Method: disassociateAddress
	* Disassociates the specified elastic IP address from the instance to which it is assigned
	*
	* Parameters:
	* publicIp - the public IP to associate
	*/
	function disassociateAddress($publicIp)
	{
		return $this->sendRequest('DisassociateAddress', 'POST', array('PublicIp' => $publicIp));
	}

	/*
	* Method: releaseAddress
	* Releases an elastic IP address associated with your account
	*
	* Parameters:
	* publicIp - the public IP to associate
	*/
	function releaseAddress($publicIp)
	{
		return $this->sendRequest('ReleaseAddress', 'POST', array('PublicIp' => $publicIp));
	}

	/*
	* Method: createVolume
	* Creates a new Amazon EBS volume to which any Amazon EC2 instance can attach within the same Availability Zone
	*
	* Parameters:
	* size - size of the volume (in GB)
	* snapshotID - the snapshot from which to create the volume (required?!?)
	* zone - the availability zone to place the volume
	*/
	function createVolume($size, $zone, $snapshotID = null)
	{
		$parms = array();
		$parms['Size'] = $size;
		$parms['AvailabilityZone'] = $zone
		if($snapshotID != NULL)
		{
			$parms['SnapshotID'] = $snapshotID;
		}
		return $this->sendRequest('CreateVolume', 'POST', $parms);
	}

	/*
	* Method: describeVolumes
	* Describes the specified Amazon EBS volumes that you own
	*
	* Parameters:
	* volumeId (optional) - the volume to describe
	*/
	function describeVolumes($volumeID = null)
	{
		$parms = array();
		if($volumeID != NULL)
		{
			$parms['VolumeID'] = $volumeID;
		}
		return $this->sendRequest('DescribeVolumes', 'GET', $parms);
	}

	/*
	* Method: attachVolume
	* Attaches an Amazon EBS volume to a running instance and exposes it as the specified device
	*
	* Parameters:
	* instanceID - the instance to attach to
	* volumeID - the volume to attach
	* device - the device on the instance to attach to
	*/
	function attachVolume($instanceID, $volumeID, $device)
	{
		return $this->sendRequest('AttachVolume', 'POST',
			array('InstanceId' => $instanceID, 'VolumeId' => $volumeID, 'Device' => $device));
	}

	/*
	* Method: detachVolume
	* Detaches an Amazon EBS volume from an instance
	*
	* Parameters:
	* volumeID - the volume to detach
	* force (optional) - force the detachment, perhaps causing dataloss
	* instanceID (optional) - the instance to detach from
	* device (optional) - the device on the instance to detach from
	*/
	function detachVolume($volumeID, $force = false, $instanceID = null, $device = null)
	{
		$parms = array();
		$parms['VolumeId'] = $volumeID;
		if($force)
		{
			$parms['Force'] = 'true';
		}
		if($instanceID != null)
		{
			$parms['InstanceId'] = $instanceID;
		}
		if($device != null)
		{
			$parms['Device'] = $device;
		}
		return $this->sendRequest('DetachVolume', 'POST', $parms);
	}

	/*
	* Method: deleteVolume
	* Deletes an Amazon EBS volume that you own
	*
	* Parameters:
	* volumeID - the volume to delete
	*/
	function deleteVolume($volumeID)
	{
		return $this->sendRequest('DeleteVolume', 'POST', array('VolumeId' => $volumeID));
	}

	/*
	* Method: createSnapshot
	* Creates a snapshot of an Amazon EBS volume and stores it in Amazon S3
	*
	* Parameters:
	* volumeID - the volume to snapshot
	*/
	function createSnapshot($volumeID)
	{
		return $this->sendRequest('CreateSnapshot', 'POST', array('VolumeId' => $volumeID));
	}

	/*
	* Method: describeSnapshots
	* Describes the status of Amazon EBS snapshots
	*
	* Parameters:
	* snapshotID (optional) - the snapshot or list of snapshots to describe
	*/
	function describeSnapshots($snapshotID = null)
	{
		$parms = array();
		$this->addParmArray($parms, 'SnapshotId', $publicIp);
		return $this->sendRequest('DescribeSnapshots', 'GET', $parms);
	}

	/*
	* Method: deleteSnapshot
	* Deletes a snapshot of an Amazon EBS volume that you own
	*
	* Parameters:
	* snapshotID - the snapshot to delete
	*/
	function deleteSnapshot($snapshotID)
	{
		return $this->sendRequest('DeleteSnapshot', 'POST', array('SnapshotId' => $snapshotID));
	}

	/*
	* Method: bundleInstance
	* Bundles the Windows instance
	*
	* Parameters:
	* instanceID - the instance to bundle
	* accessKey - the S3 access key of the bucket to store the instance
	* secretKey - the S3 secret key of the bucket to store the instance
	* bucket - the S3 bucket to store the instance into
	* prefix - the S3 prefix to prepend to the instance name
	*/
	function bundleInstance($instanceID, $accessKey, $bucket, $prefix, $secretKey)
	{
		$parms = array();
		$parms['InstanceId'] = $instanceID;
		$parms['Storage.S3.AWSAccessKeyId'] = $accessKey;
		$parms['Storage.S3.Bucket'] = $bucket;
		$parms['Storage.S3.Prefix'] = $prefix;
		
		$policy = '{"expiration":"' . gmdate(DATE_AWS, time()+43200) . '","conditions":[{"acl":"ec2-bundle-read"},{"bucket":"' . addslashes($bucket) . '"},["starts-with","$key","' . addslashes($prefix) . '"]]}';

		$signTarget = $this->hex2b64($policy);
		$this->debug_text("Signing String: ".var_export($signTarget,true));
		$hasher = new Crypt_HMAC($secretKey, "sha1");
		$signature = $this->hex2b64($hasher->hash($signTarget));

		$parms['Storage.S3.UploadPolicy'] = $signTarget;
		$parms['Storage.S3.UploadPolicySignature'] = $signature;
		return $this->sendRequest('BundleInstance', 'POST', $parms);
	}

	/*
	* Method: cancelBundleTask
	* Cancels an Amazon EC2 bundling operation
	*
	* Parameters:
	* bundleID - the bundle operation to cancel
	*/
	function cancelBundleTask($bundleID)
	{
		return $this->sendRequest('CancelBundleTask', 'POST', array('BundleId' => $bundleID));
	}

	/*
	* Method: describeReservedInstancesOffering
	* Describes Reserved Instance offerings that are available for purchase
	*
	* Parameters:
	* offeringID (optional) - the reserved instance offering to describe
	* instanceType (optional) - the instance type that the offering can be used on
	* zone (optional) - the zone that the offering can be used on
	* description ( optional) - the instance description
	*/
	function describeReservedInstancesOffering($offeringID = null, $instanceType = null, $zone = null, $description = null)
	{
		$parms = array();
		if($offeringID != null)
		{
			$parms['ReservedInstancesOfferingId'] = $offeringID;
		}
		if($instanceType != null)
		{
			$parms['InstanceType'] = $instanceType;
		}
		if($zone != null)
		{
			$parms['AvailabilityZone'] = $zone;
		}
		if($description != null)
		{
			$parms['ProductDescription'] = $description;
		}
		return $this->sendRequest('DescribeReservedInstancesOfferings', 'GET', $parms);
	}

	/*
	* Method: describeReservedInstances
	* Describes Reserved Instances that you purchased
	*
	* Parameters:
	* instancesID (optional) - instance or list of instances to describe
	*/
	function describeReservedInstances($instanceID = null)
	{
		$parms = array();
		$this->addParmArray($parms, 'ReservedInstancesId', $instanceID);
		return $this->sendRequest('DescribeReservedInstances', 'GET', $parms);
	}

	/*
	* Method: purchaseReservedInstances
	* Purchases a Reserved Instance for use with your account
	*
	* Parameters:
	* offeringID - the reserved instance offering to purchase
	* count (optional) - the number of instances to purchase
	*/
	function purchaseReservedInstances($offeringID, $count = 1)
	{
		$parms = array();
		$parms['ReservedInstancesOfferingId.1'] = $offeringID;
		if($count != 1)
		{
			$parms['InstanceCount.1'] = $count;
		}
		return $this->sendRequest('PurchaseReservedInstancesOffering', 'POST', $parms);
	}

	/*
	* Method: monitorInstance
	* Enables monitoring for a running instance
	*
	* Parameters:
	* instanceID - the instance to enable monitoring for
	*/
	function monitorInstance($instanceID)
	{
		$parms = array();
		$this->addParmArray($parms, 'InstanceId', $instanceID);
		return $this->sendRequest('MonitorInstances', 'POST', $parms);
	}

	/*
	* Method: unmonitorInstance
	* Disables monitoring for a running instance
	*
	* Parameters:
	* instanceID - the instance to disable monitoring for
	*/
	function unmonitorInstance($instanceID)
	{
		$parms = array();
		$this->addParmArray($parms, 'InstanceId', $instanceID);
		return $this->sendRequest('UnmonitorInstances', 'POST', $parms);
	}

	/*
	* Method: hex2b64
	* Utility function for constructing signatures
	*/
	function hex2b64($str)
	{
		$raw = '';
		for ($i=0; $i < strlen($str); $i+=2) {
			$raw .= chr(hexdec(substr($str, $i, 2)));
		}
		return base64_encode($raw);
	}
	
	/*
	* Method: debug_text
	* Echoes debug information to the browser.  Set this->debug to false for production use
	*/
	function debug_text($text)
	{
		if ($this->_debug) {
			print_r($text);
			print( "\n" );
		}

		return true;
	}

	/*
	* Method: addParmArray
	* Translates a single value or array of values into an EC2-specified enumerated parameter list
	*
	* Parameters:
	* dest - EC2 parameter list to output to
	* name - base name to add
	* options - single value or array of values to add
	*/
	function addParmArray(&$dest, $name, $options)
	{
		if($options != NULL)
		{
			if(is_array($options))
			{
				$idx = 1;
				foreach($options as $line)
				{
					$dest[$name . '.' . $idx++] = $line;
					$this->debug_text("Setting $name to $line");
				}
			} else {
				$dest[$name . '.1'] = $options;
				$this->debug_text("Setting $name to $options");
			}
		}
	}

}

?>
