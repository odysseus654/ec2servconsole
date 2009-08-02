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

function quoteOrNull($str)
{
	if($str == null)
	{
		return 'null';
	} else {
		return "'" . mysql_real_escape_string($str) . "'";
	}
}

// ----------------------------------------------------------------------------

function syncImages()
{
	global $ec2svc, $session;
	set_time_limit(120);		// this can take a while...
	$ownerId = $session->getAccountId($ec2svc);
	
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
	$images = array();
	$item = $resptree['imagesSet']['item'];
	if(isset($item[0]))
	{
		foreach($item as $idx => $value)
		{
			if($value['imageType'] == 'machine')
			{
				$imageId = $value['imageId'];
				unset($value['imageId']);
				$value['status'] = '';
				if($value['isPublic'] == 'false' || $value['imageOwnerId'] == $ownerId) $value['status'] = 'added';
				$images[$imageId] = $value;
			}
			else if($value['imageState'] == 'available' && $value['isPublic'] == 'true')
			{
				$imageId = $value['imageId'];
				unset($value['isPublic']);
				unset($value['imageState']);
				unset($value['imageId']);
				$value['status'] = 'added';
				$kernels[$imageId] = $value;
			}
		}
	} else {
		if($item['imageType'] == 'machine')
		{
			$imageId = $item['imageId'];
			unset($item['imageId']);
			$item['status'] = '';
			if($item['isPublic'] == 'false' || $item['imageOwnerId'] == $ownerId) $item['status'] = 'added';
			$images[$imageId] = $item;
		}
		else if($item['imageState'] == 'available' && $item['isPublic'] == 'true')
		{
			$imageId = $item['imageId'];
			unset($item['isPublic']);
			unset($item['imageState']);
			unset($item['imageId']);
			$item['status'] = 'added';
			$kernels[$imageId] = $item;
		}
	}

	// retrieve the kernels we have
	$query = mysql_query(
		'select `amazonID`, `location`, `attributes`, `imageType` from `' . $session->DB_prefix . 'kernels`', $session->dbConnect())
	or $session->sqlError('syncImages', 'query the list of kernels');

	while($row = mysql_fetch_assoc($query))
	{
		$imageId = $row['amazonID'];
		if($kernels[$imageId])
		{
			$kernels[$imageId]['status'] = 'modified';
			$item = $kernels[$imageId];
			if($item['imageLocation'] == $row['location']
				&& (!!stristr($row['attributes'], 'x86_64') == ($item['architecture'] == 'x86_64'))
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
	mysql_free_result($query) or $session->sqlError('syncImages', 'free query of kernels');

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
		if(isset($item['architecture']) && $item['architecture'] == 'x86_64')
		{
			if($attr != '') $attr .= ',';
			$attr .= 'x86_64';
		}
		
		switch($item['status'])
		{
			case 'deleted':
				$cmd = 'delete from `' . $session->DB_prefix . 'kernels` where `amazonID`=' . quoteOrNull($amazonId);
				break;
			case 'added':
				$cmd = 'insert into `' . $session->DB_prefix . 'kernels`(`amazonID`,`location`,`attributes`,`imageType`) ' .
					'values(' . quoteOrNull($amazonId) . ',' . quoteOrNull($item['imageLocation']) .
					',' . ($attr == '' ? 'null' : quoteOrNull($attr)) . ',' . quoteOrNull($item['imageType']) . ');';
				break;
			case 'modified':
				$cmd = 'update `' . $session->DB_prefix . 'kernels` set `location`=' . quoteOrNull($item['imageLocation']) .
					',`attributes`=' . ($attr == '' ? 'null' : quoteOrNull($attr)) . ',`imageType`=' . quoteOrNull($item['imageType']) .
					' where `amazonId`=' . quoteOrNull($amazonId);
				break;
		}
		$query = mysql_query($cmd, $session->dbConnect()) or $session->sqlError('syncImages', 'synchronize a kernel');
	}

	// check the registered images to make sure they are all still valid
	$query = mysql_query(
		'select `amazonID`, `location`, `attributes` from `' . $session->DB_prefix . 'images` where `accountID`=' . quoteOrNull($session->accountID), $session->dbConnect())
	or $session->sqlError('syncImages', 'query the list of images');

	while($row = mysql_fetch_assoc($query))
	{
		$imageId = $row['amazonID'];
		if($images[$imageId])
		{
			$item = $images[$imageId];
			if($item['imageLocation'] != $row['location']
				|| (!!stristr($row['attributes'], 'x86_64') != ($item['architecture'] == 'x86_64'))
				|| (!!stristr($row['attributes'], 'amazon') != ($item['imageOwnerId'] == 'amazon'))
				|| (!!stristr($row['attributes'], 'self') != ($item['imageOwnerId'] == $ownerId))
				|| (!!stristr($row['attributes'], 'paid') != isset($item['productCodes']))
				|| (!!stristr($row['attributes'], 'public') != ($item['isPublic'] == 'true'))
				|| (!!stristr($row['attributes'], 'invalid') != ($item['imageState'] != 'available'))
				|| (!!stristr($row['attributes'], 'windows') != ($item['platform'] == 'windows')))
			{
				$images[$imageId]['status'] = 'modified';
			}
		} else {
			$images[$imageId] = array( 'status' => 'deleted' );
		}
	}
	mysql_free_result($query) or $session->sqlError('syncImages', 'free query of images');

	foreach($images as $amazonId => $item)
	{
		$attr = '';
		if($item['imageOwnerId'] == 'amazon')
		{
			if($attr != '') $attr .= ',';
			$attr .= 'amazon';
		}
		else if($item['imageOwnerId'] == $ownerId)
		{
			if($attr != '') $attr .= ',';
			$attr .= 'self';
		}
		if(isset($item['productCodes']))
		{
			if($attr != '') $attr .= ',';
			$attr .= 'paid';
		}
		if($item['isPublic'] == 'true')
		{
			if($attr != '') $attr .= ',';
			$attr .= 'public';
		}
		if(isset($item['plaform']) && $item['platform'] == 'windows')
		{
			if($attr != '') $attr .= ',';
			$attr .= 'windows';
		}
		if(isset($item['imageState']) && $item['imageState'] != 'available')
		{
			if($attr != '') $attr .= ',';
			$attr .= 'invalid';
		}
		if(isset($item['architecture']) && $item['architecture'] == 'x86_64')
		{
			if($attr != '') $attr .= ',';
			$attr .= 'x86_64';
		}
		if(!isset($item['kernelId'])) $item['kernelId'] = null;
		if(!isset($item['ramdiskId'])) $item['ramdiskId'] = null;
		
		switch($item['status'])
		{
			case 'deleted':
				$cmd = 'delete from `' . $session->DB_prefix . 'images` where `amazonID`=' . quoteOrNull($amazonId) . ' and `accountID`=' . quoteOrNull($session->accountID);
				break;
			case 'modified':
				$cmd = 'update `' . $session->DB_prefix . 'images` set `location`=' . quoteOrNull($item['imageLocation']) .
					',`attributes`=' . ($attr == '' ? 'null' : quoteOrNull($attr)) .
					' where `amazonId`=' . quoteOrNull($amazonId) . ' and `accountID`=' . quoteOrNull($session->accountID);
				break;
			case 'added':
				$cmd = 'insert into `' . $session->DB_prefix . 'images`(`amazonID`,`location`,`attributes`,`accountID`,`kernelId`,`ramdiskId`) ' .
					'values(' . quoteOrNull($amazonId) . ',' . quoteOrNull($item['imageLocation']) . ',' . ($attr == '' ? 'null' : quoteOrNull($attr)) .
					',' . quoteOrNull($session->accountID) . ',' . quoteOrNull($item['kernelId']) . ',' . quoteOrNull($item['ramdiskId']) . ');';
				break;
			default:
				$cmd = '';
				break;
		}
		if($cmd != '')
		{
			$query = mysql_query($cmd, $session->dbConnect()) or $session->sqlError('syncImages', 'synchronize an image');
		}
	}

	header('Content-Type: text/xml');
	echo '<?xml version="1.0" ?>' . "\n";
	echo '<Response name="' . arg('action') . '">success</Response>';
}

function listImages($id)
{
	global $ec2svc, $session;

	$cmd = 'select `amazonID`, `location`, `attributes`, `label`, `descr`, `kernelId`, `ramdiskId` from `' . $session->DB_prefix . 'images` where `accountID`=' . quoteOrNull($session->accountID);
	if($id != null)
	{
		$cmd = $cmd . ' and `amazonID`=' . quoteOrNull($id);
	}
	$query = mysql_query($cmd, $session->dbConnect()) or $session->sqlError('listImages', 'query the list of images');
	
	echo '<?xml version="1.0" ?>' . "\n" .
		'<ListImagesResponse xmlns="http://ec2servconsole.sourceforge.net/2009/DataStore"';
	$ownerId = $session->getAccountId($ec2svc);
	if($ownerId != null)
	{
		echo ' ownerId="' . $ownerId . '"';
	}
	echo ">\n";
	while($row = mysql_fetch_assoc($query))
	{
		echo '<image><imageId>' . $row['amazonID'] . '</imageId><imageLocation>' . $row['location'] . '</imageLocation><architecture>' .
			(!!stristr($row['attributes'], 'x86_64') ? 'x86_64' : 'i386') . '</architecture><kernelId>' . $row['kernelId'] . '</kernelId><ramdiskId>' .
			$row['ramdiskId'] . '</ramdiskId>';
		if($row['descr']) echo '<descr>' . $row['descr'] . '</descr>';
		if($row['label']) echo '<name>' . $row['label'] . '</name>';
		if(!!stristr($row['attributes'], 'paid')) echo '<isPaid />';
		if(!!stristr($row['attributes'], 'amazon')) echo '<isAmazon />';
		if(!!stristr($row['attributes'], 'self')) echo '<isOwner />';
		if(!!stristr($row['attributes'], 'public')) echo '<isPublic />';
		if(!!stristr($row['attributes'], 'invalid')) echo '<isInvalid />';
		if(!!stristr($row['attributes'], 'windows')) echo '<platform>windows</platform>';
		echo '</image>';
	}
	mysql_free_result($query) or $session->sqlError('listImages', 'free query of images');
	echo '</ListImagesResponse>';
}

switch(strtolower(arg('action')))
{
case 'syncImages':
	syncImages();
	break;
case 'images':
	listImages(arg('id'));
	break;
default:
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" ?>' . "\n";
	echo '<unknownRequest name="' . arg('action') . '"/>';
}

?>