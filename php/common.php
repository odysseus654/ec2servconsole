<?php

function urandom($numBytes)
{
	// get 128 pseudorandom bits in a string of 16 bytes
	$pr_bits = '';

	// Unix/Linux platform?
	if(file_exists('/dev/urandom'))
	{
		$fp = @fopen('/dev/urandom','rb');
		if ($fp !== FALSE)
		{
			$pr_bits .= @fread($fp,$numBytes);
			@fclose($fp);
		}
	}

	// MS-Windows platform?
	if (@class_exists('COM'))
	{
		// http://msdn.microsoft.com/en-us/library/aa388176(VS.85).aspx
//		try {
			$CAPI_Util = new COM('CAPICOM.Utilities.1');
			$pr_bits .= $CAPI_Util->GetRandom(16,0);
			$pr_bits = substr($pr_bits, 0, $numBytes);

			// if we ask for binary data PHP munges it, so we
			// request base64 return value.  We squeeze out the
			// redundancy and useless ==CRLF by hashing...
//			if ($pr_bits) { $pr_bits = pack('H*', md5($pr_bits)); }
//		} catch (Exception $ex) {
//			// echo 'Exception: ' . $ex->getMessage();
//		}
	}

	while(strlen($pr_bits) < $numBytes)
	{
		$pr_bits .= chr(mt_rand(1, 255));
	}
	
	return $pr_bits;
}

class Session
{
	var $DB_server = "localhost";
	var $DB_database = "servconsole";
	var $DB_login = "servconsole";
	var $DB_pass = "old!University";
	var $DB_prefix = "";

	var $dbh = null;
	var $sessionID = 0;
	var $loginID = 0;
	var $accountID = 0;
	var $AWSAccessKeyID = '';
	var $AWSSecret = '';

	function dbConnect()
	{
	  if(!$this->dbh)
	  {
		$this->dbh=mysql_connect ($this->DB_server, $this->DB_login, $this->DB_pass) or $this->sqlError('dbConnect', 'connect to the database');
		mysql_select_db($this->DB_database, $this->dbh) or $this->sqlError('dbConnect', 'change to the proper database');
	  }
	  return $this->dbh;
	}
	
	function makeSecret($password)
	{	// adapted from http://www.openldap.org/faq/data/cache/347.html
	    $salt = urandom(4);
	    $h = pack('H*', sha1($password . $salt));
	    return base64_encode($h . $salt);
	}

	function checkPassword($challenge_password, $password)
	{	// adapted from http://www.openldap.org/faq/data/cache/347.html
	    $challenge_bytes = base64_decode($challenge_password);
	    $digest = substr($challenge_bytes, 0, 20);
	    $salt = substr($challenge_bytes, 20);
	    $hr = pack('H*', sha1($password . $salt));
	    return $digest == $hr;
	}
	
	function retrieveSession($sessID, $isHeart)
	{
		$query = mysql_query(
			'select `loginID` from `' . $this->DB_prefix . 'session` where `source_ip`=\'' .
			mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . '\' and `sessionID`=\'' .
			mysql_real_escape_string($sessID) . '\'', $this->dbConnect())
		or $this->sqlError('retrieveSession', 'query the active session list');
		
		$row = mysql_fetch_assoc($query);
		if(!$row) return false;
		$this->sessionID = $sessID;
		$this->loginID = $row['loginID'];
		mysql_free_result($query) or $this->sqlError('retrieveSession', 'free query of active session list');
		
		if($isHeart)
		{
			$cmd = 'update `' . $this->DB_prefix . 'session` set `lastHeartbeat`=NOW() where `sessionID`=\'' .
				mysql_real_escape_string($sessID) . '\'';
		} else {
			$cmd = 'update `' . $this->DB_prefix . 'session` set `lastHeartbeat`=NOW(),lastAction=NOW() where `sessionID`=\'' .
				mysql_real_escape_string($sessID) . '\'';
		}
	    $query = mysql_query($cmd, $this->dbConnect()) or $this->sqlError('retrieveSession', 'refresh the session timestamps');
		
		return $this->onLogin();
	}

	function createSession($login, $pass)
	{
		$query = mysql_query(
			'select `loginID`, `pass` from `' . $this->DB_prefix . 'login` where name=\'' . mysql_real_escape_string($login) . '\'', $this->dbConnect())
		or $this->sqlError('createSession', 'query the login');
		
		$row = mysql_fetch_assoc($query);
		if(!$row) return false;
		if(!$this->checkPassword($row['pass'], $pass)) return false;
		$loginID = $row['loginID'];
		mysql_free_result($query) or $this->sqlError('createSession', 'free query of logins');
		
		$cmd = 'insert into `' . $this->DB_prefix . 'session`(`sessionID`,`loginID`,`source_ip`,`lastAction`,`lastHeartbeat`) '
			. 'values(UUID(), \'' . mysql_real_escape_string($loginID) . '\', \'' . mysql_real_escape_string($_SERVER['REMOTE_ADDR'])
			. '\', NOW(), NOW())';
		$query = mysql_query($cmd, $this->dbConnect()) or $this->sqlError('createSession', 'construct a new session');
		
		$this->sessionID = mysql_insert_id($this->dbh);
		$this->loginID = $loginID;
		
		return $this->onLogin();
	}
	
	function onLogin()
	// Retrieve some properties that are releavant for this login, called whether this is a new session or a resumed session
	{
		$query = mysql_query(
			'select `accountID` from `' . $this->DB_prefix . 'login` where `loginID`=\'' . mysql_real_escape_string($this->loginID) . '\'', $this->dbConnect())
		or $this->sqlError('onLogin', 'query the login');
		
		$row = mysql_fetch_assoc($query);
		if(!$row) return false;
		$this->accountID = $row['accountID'];
		mysql_free_result($query) or $this->sqlError('onLogin', 'free query of logins');
		
		$query = mysql_query(
			'select `accessKeyID`,`secret` from `' . $this->DB_prefix . 'account` where `accountID`=\'' . mysql_real_escape_string($this->accountID) . '\'', $this->dbConnect())
		or $this->sqlError('onLogin', 'query the account');
		
		$row = mysql_fetch_assoc($query);
		if(!$row) return false;
		$this->AWSAccessKeyID = $row['accessKeyID'];
		$this->AWSSecret = $row['secret'];
		mysql_free_result($query) or $this->sqlError('onLogin', 'free query of account');
		
		return true;
	}

	function sqlError($func, $ctx)
	// Called whenever an sql error occurs, throw a descriptive error and get out
	{
		die('fail,Could not ' . $ctx . ': ' . mysql_error($this->dbh));
	}
}

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

function arg($name)
{
	if($_SERVER['REQUEST_METHOD'] == 'POST')
	{
		if(isset($_POST[$name])) return $_POST[$name]; else return NULL;
	} else {
		if(isset($_GET[$name])) return $_GET[$name]; else return NULL;
	}
}

function returnXml($xml)
{
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" ?>' . "\n";
	echo $xml;
	exit(0);
}

function badLogin()
{
	returnXml('<badLogin />');
}

function xml2php($xml)		// I *really* miss domxml_xmltree!
{
//	$doc = DOMDocument::loadXML($xml);
	$doc = new DOMDocument();
	$doc->loadXML($xml);
	return xml2phpHelper($doc->documentElement);
}

function xml2phpHelper($node)
{
	$result = array();
	$hasContent = false;
	foreach ($node->attributes as $attrName => $attrNode)
	{
		$result['@'.$attrName] = $attrNode->nodeValue;
		$hasContent = true;
	}
	$children = $node->childNodes;
	$body = null;
	if($children)
	{
		for($i=0; $i < $children->length; $i++)
		{
			$child = $children->item($i);
			if($child->nodeType == XML_TEXT_NODE)
			{
				$childResult = trim($child->wholeText);
				if($childResult != '')
				{
					$body = $childResult;
				}
			}
			else if($child->nodeType == XML_ELEMENT_NODE)
			{
				$childResult = xml2phpHelper($child);
				$hasContent = true;
				$childName = $child->nodeName;
				if(!isset($result[$childName]))
				{
					$result[$childName] = $childResult;
				}
				else if(!is_array($result[$childName]) || !isset($result[$childName][0]))
				{
					$subResult = array();
					$subResult[] = $result[$childName];
					$subResult[] = $childResult;
					$result[$childName] = $subResult;
				}
				else
				{
					$result[$childName][] = $childResult;
				}
			}
		}
	}
	if($body != null)
	{
		if($hasContent)
		{
			$result['_body'] = $body;
		} else {
			$result = $body;
		}
	}
	return $result;
}
?>