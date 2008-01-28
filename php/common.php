<?php

class Session
{
	var $DB_server = "localhost";
	var $DB_database = "odysseus_slevents";
	var $DB_login = "odysseus_slevents";
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
	  $this->dbh=mysql_connect ($this->DB_server, $this->DB_login, $this->DB_pass) or $this->sqlError('dbConnect', 'connect to the database');
	  mysql_select_db($this->DB_database, $this->dbh) or $this->sqlError('dbConnect', 'change to the proper database');
	  return $this->dbh;
	}
	
	function makeSecret($password)
	{	// adapted from http://www.openldap.org/faq/data/cache/347.html
	    $salt = os.urandom(4);
	    $h = hashlib.sha1($password . $salt, true);
	    return base64_encode($h . $salt);
	}

	function checkPassword($challenge_password, $password)
	{	// adapted from http://www.openldap.org/faq/data/cache/347.html
	    $challenge_bytes = base64_decode($challenge_password);
	    $digest = substr($challenge_bytes, 0, 20);
	    $salt = substr($challenge_bytes, 20);
	    $hr = sha1($password . $salt, true);
	    return $digest == $hr;
	}
	
	function retrieveSession($sessID, $isHeart)
	{
		$query = mysql_query(
			'select `loginID` from `' . $this->DB_prefix . 'session` where `source_ip`=\'' .
			mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . '\' and `sessionID`=\'' .
			mysql_real_escape_string($sessID) . '\'', $this->dbh)
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
	    $query = mysql_query($cmd, $this->dbh) or $this->sqlError('retrieveSession', 'refresh the session timestamps');
		mysql_free_result($query) or $this->sqlError('retrieveSession', 'free refresh of session timestamps');
		
		return $this->onLogin();
	}

	function createSession($login, $pass)
	{
		$query = mysql_query(
			'select `loginID`, `pass` from `' . $this->DB_prefix . 'login` where name=\'' . mysql_real_escape_string($login) . '\'', $this->dbh)
		or $this->sqlError('createSession', 'query the login');
		
		$row = mysql_fetch_assoc($query);
		if(!$row) return false;
		if(!$this->checkPassword($row['pass'], $pass)) return false;
		$loginID = $row['loginID'];
		mysql_free_result($query) or $this->sqlError('createSession', 'free query of logins');
		
		$cmd = 'insert into `' . $this->DB_prefix . 'session`(`sessionID`,`loginID`,`source_ip`,`lastAction`,`lastHeartbeat`) '
			. 'values(UUID(), \'' . mysql_real_escape_string($loginID) . '\', \'' . mysql_real_escape_string($_SERVER['REMOTE_ADDR'])
			. '\', NOW(), NOW())';
		$query = mysql_query($cmd, $this->dbh) or $this->sqlError('createSession', 'construct a new session');
		mysql_free_result($query) or $this->sqlError('createSession', 'free construction of new session');
		
		$this->sessionID = mysql_insert_id($this->dbh);
		$this->loginID = $loginID;
		
		return $this->onLogin();
	}
	
	function onLogin()
	// Retrieve some properties that are releavant for this login, called whether this is a new session or a resumed session
	{
		$query = mysql_query(
			'select `accountID` from `' . $this->DB_prefix . 'login` where `loginID`=\'' . mysql_real_escape_string($this->loginID) . '\'', $this->dbh)
		or $this->sqlError('onLogin', 'query the login');
		
		$row = mysql_fetch_assoc($query);
		if(!$row) return false;
		$this->accountID = $row['accountID'];
		mysql_free_result($query) or $this->sqlError('onLogin', 'free query of logins');
		
		$query = mysql_query(
			'select `accessKeyID`,`secret` from `' . $this->DB_prefix . 'account` where `accountID`=\'' . mysql_real_escape_string($this->accountID) . '\'', $this->dbh)
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
?>