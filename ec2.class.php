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
class ec2 {

	// The API access point URL
	var $EC2_URL = "http://ec2.amazonaws.com/";
	
	// set to true to echo debug info
	var $_debug = false;

	// -----------------------------------------
	// -----------------------------------------
	// your API key ID
	var $keyId = "00000000000000000000";
	// your API Secret Key
	var $secretKey = "0000000000000000000000000000000000000000";
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
		define('DATE_ISO8601', 'Y\-m\-d\TH\:i\:s\Z');
		$this->httpDate = gmdate(DATE_ISO8601);
		
		// REQUIRES PEAR PACKAGE
		// get with "pear install Crypt_HMAC"
		require_once 'Crypt/HMAC.php';
		
		$this->hasher =& new Crypt_HMAC($this->secretKey, "sha1");
		
		// REQUIRES PEAR PACKAGE
		// get with "pear install --onlyreqdeps HTTP_Request"
		
		require_once 'HTTP/Request.php';
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
		if ($verb == NULL) {
			$verb = $this->verb;
		}
		
		// update date / time on each request
		$this->httpDate = gmdate(DATE_ISO8601);
		$httpDate = $this->httpDate;

		// the parts of the request that we do ourselves		
		$request = array('Action' => $action,
			'AWSAccessKeyId' => $this->keyId,
			'SignatureVersion' => '1',
			'Timestamp' => $httpDate,
			'Version' => '2007-01-03');
		
		if (is_array($parms))
		{
			foreach ($parms as $key => $value)
			{
				$this->debug_text("Parameter: $key => $value");
				$request[$key] = $parms[$key];
			}

		}

		uksort($request, 'strcasecmp');

		$queryString = '';
		$delimiter = '?';
		$signTarget = '';

		foreach($request as $key => $value)
		{
			$signTarget .= $key . $value;
			$queryString .= $delimiter . $key . '=' . urlencode($value);
			$delimiter = '&';
		}

		$this->debug_text("HTTP Request sent to: " . $this->EC2_URL . ':' . $action);
		
		$this->debug_text("Signing String: ".var_export($signTarget,true));
		$signature = $this->hex2b64($this->hasher->hash($signTarget));
		$this->debug_text("Signature: $signature");
		$queryString .= '&Signature=' . urlencode($signature);
		$this->debug_text("Query: " . $queryString);
						
		$req =& new HTTP_Request($this->EC2_URL . $queryString);
		$req->setMethod($verb);
		$req->sendRequest();		
		
		$this->_responseContentType = $req->getResponseHeader("Content-Type");
		if($this->_responseContentType == '') $this->_responseContentType = 'text/xml';
		$this->_responseCode = $req->getResponseCode();
		$this->debug_text('code: ' . $this->_responseCode);
		$this->debug_text('type: ' . $this->_responseContentType);
		$this->debug_text($req->getResponseBody());

		return $req->getResponseBody();
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
	* Method: getProductCode
	* Retrieves the product code for an AMI image
	*
	* Parameters:
	* imageId - AMI image to query
	*/
	function getProductCode($imageID)
	{
		return $this->sendRequest('DescribeImageAttribute', 'GET', array('ImageId' => $imageID, 'Attribute' => 'productCodes'));
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
		$this->addParmArray($parms, 'UserGroup', $usergroups);

		return $this->sendRequest('ModifyImageAttribute', 'POST', $parms);
	}

	/*
	* Method: describeLaunchPermissions
	* Retrieves the launch permissions for an AMI image
	*
	* Parameters:
	* imageId - AMI image to query
	*/
	function describeLaunchPermissions($imageID)
	{
		return $this->sendRequest('DescribeImageAttribute', 'GET', array('ImageId' => $imageID, 'Attribute' => 'launchPermission'));
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
			$parms['UserData'] = base64_encode($options['userData']);
		}
		if(isset($options['type']))
		{
			$parms['InstanceType'] = $options['type'];
		}

		if(isset($options['group']))
		{
			$this->addParmArray($parms, 'SecurityGroup', $options['group']);
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
		return $this->sendRequest('ConfirmPRoductInstance', 'GET',
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
