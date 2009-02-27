<?php
/* datastore.php
 * MySQL database (not strictly EC2) operations
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

// ----------------------------------------------------------------------------

function syncAmazon()
{
	global $ec2svc, $session;
	
	// retrieve the list of all public images
//	$response = $ec2svc->describeImages(null, 'amazon');
	$response = $ec2svc->describeImages(null, null);
	if($ec2svc->getResponseCode() != 200)
	{
		ec2Response($ec2svc, $response);
		return false;
	}
	$resptree = xml2php($response);
	
	// cleanup and filter the results a bit
	$kernels = array();
	$item = $resptree['imagesSet']['item'];
	if(isset($item[0]))
	{
		foreach($item as $idx => $value)
		{
			if($value['imageState'] == 'available' && $value['isPublic'] == 'true' && $value['imageType'] != 'machine')
			{
				$imageId = $value['imageId'];
				unset($value['isPublic']);
				unset($value['imageState']);
				unset($value['imageId']);
//				unset($value['imageOwnerId']);
				$value['status'] = 'added';
				$kernels[$imageId] = $value;
			}
		}
	} else {
		if($item['imageState'] == 'available' && $item['isPublic'] == 'true' && $item['imageType'] != 'machine')
		{
			$imageId = $item['imageId'];
			unset($item['isPublic']);
			unset($item['imageState']);
			unset($item['imageId']);
//			unset($item['imageOwnerId']);
			$item['status'] = 'added';
			$kernels[$imageId] = $item;
		}
	}

	// retrieve the kernels we have
	$query = mysql_query(
		'select `amazonID`, `location`, `attributes`, `arch`, `imageType` from `' . $session->DB_prefix . 'kernels`', $session->dbConnect())
	or $session->sqlError('syncAmazon', 'query the list of kernels');

	while($row = mysql_fetch_assoc($query))
	{
		$imageId = $row['amazonID'];
		if($kernels[$imageId])
		{
			$kernels[$imageId]['status'] = 'modified';
			$item = $kernels[$imageId];
			if($item['imageLocation'] == $row['location'] && $item['architecture'] == $row['arch']
				&& (!!stristr($row['attributes'], 'amazon') == ($item['imageOwnerId'] == 'amazon'))
				&& (!!stristr($row['attributes'], 'paid') == isset($item['productCodes']))
				&& $item['imageType'] == $row['imageType'])
			{
				unset($kernels[$imageId]);
			}
		} else {
			$kernels[$imageId] = array( 'status' => 'deleted' );
		}
	}
	mysql_free_result($query) or $session->sqlError('syncAmazon', 'free query of kernels');

	foreach($kernels as $amazonId => $item)
	{
		$attr = '';
		if($item['imageOwnerId'] == 'amazon')
		{
			if($attr != '') $attr .= ',';
			$attr .= 'amazon';
		}
		if(isset($item['productCodes']))
		{
			if($attr != '') $attr .= ',';
			$attr .= 'paid';
		}
		
		switch($item['status'])
		{
			case 'deleted':
				$cmd = 'delete from `' . $session->DB_prefix . 'kernels` where `amazonID`=\'' . mysql_real_escape_string($amazonId) . '\'';
				break;
			case 'added':
				$cmd = 'insert into `' . $session->DB_prefix . 'kernels`(`amazonID`,`location`,`attributes`,`arch`,`imageType`) ' .
					'values(\'' . mysql_real_escape_string($amazonId) . '\',\'' . mysql_real_escape_string($item['imageLocation']) .
					'\',\'' . ($attr == '' ? 'null' : mysql_real_escape_string($attr)) . '\',\'' . mysql_real_escape_string($item['architecture']) .
					'\',\'' . mysql_real_escape_string($item['imageType']) . '\');';
				break;
			case 'modified':
				$cmd = 'update `' . $session->DB_prefix . 'kernels` set `location`=\'' . mysql_real_escape_string($item['imageLocation']) .
					'\',`attributes`=\'' . ($attr == '' ? 'null' : mysql_real_escape_string($attr)) .
					'\',`arch`=\'' . mysql_real_escape_string($item['architecture']) .
					'\',`imageType`=\'' . mysql_real_escape_string($item['imageType']) .
					'\' where `amazonId`=\'' . mysql_real_escape_string($amazonId) . '\'';
				break;
		}
		$query = mysql_query($cmd, $session->dbConnect()) or $session->sqlError('syncAmazon', 'synchronize a kernel');
	}
	
	print_r($kernels);
}

syncAmazon();
?>