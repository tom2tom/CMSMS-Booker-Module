<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Userops - functions for processing bookers
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Userops
{
	const DEFAULTPASS = 'changethis';

	private $dbhandle;
	private $table;
	private $history; //history-data table

	public function __construct (&$mod, &$db)
	{
		$this->dbhandle = $db;
		$this->table = $mod->BookerTable;
		$this->history = $mod->HistoryTable;
	}

	private function gettype($booker_id)
	{
		$r = $this->dbhandle->GetOne('SELECT type FROM '.$this->table.' WHERE booker_id=? AND active=1',
			array($booker_id));
		return ($r) ? $r:FALSE;
	}

	// adapted from PHP's password hashing framework
	private function encodeBytes($input, $ilen=16)
	{
		if (!$input)
			$input = 'pGJCu"F~p+>Q94je';	//ensure a hash for empty passwords
		while (strlen($input) < $ilen)
			$input .= strrev($input);
		$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$output = '';
		$i = 0;
		while (1) {
			$c1 = ord($input[$i++]);
			$output .= $itoa64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;
			if ($i >= $ilen) {
				$output .= $itoa64[$c1];
				break;
			}
			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $itoa64[$c1];
			if ($i >= $ilen) {
				break;
			}
			$c1 = ($c2 & 0x0f) << 2;
			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $itoa64[$c1];
			if ($i >= $ilen) {
				break;
			}
			$output .= $itoa64[$c2 & 0x3f];
		}
		return $output;
	}

	/**
	HashPassword:
	@passwd: string, any length or empty
	Returns: 48-byte encoded string
	 */
	public function HashPassword ($passwd)
	{
		$pubkey = $this->encodeBytes(str_shuffle(time().'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),8); //10-chars
		return $pubkey.$this->encodeBytes($passwd.$pubkey,28); //10+38-chars
	}

	private function matchpass ($hashed, $passwd)
	{
		if ($hashed) {
			$p = str_split($hashed,10); //separate pubkey & pw
			return strcmp($p[1],$this->encodeBytes($passwd.$p[0]),28);
		} else {
			return !$passwd;
		}
	}

	/**
	:
	@booker_id: numeric identifier
	Returns:
	*/
/*	public function ($booker_id)
	{
	}
*/
	/**
	AddUser:
	@name:
	@account: optional account identifier
	@passwd: optional plaintext password
	Returns: booker_id, OR FALSE if user exists and/or no password is provided
	*/
	public function AddUser ($name, $account=FALSE, $passwd=FALSE)
	{
		if (!($name || $account))
			return FALSE;
		if ($account) {
			if (!$passwd)
				return FALSE;
			$r = $this->dbhandle->GetOne('SELECT name FROM '.$this->table.' WHERE publicid=?',array($account));
			if ($r)
				return FALSE;
		}
		$fields = array();
		$args = array();
		$fields[] = 'booker_id';
		$bid = $this->dbhandle->GenId($this->table.'_seq');
		$args[] = $bid;

		if ($name) {
			$fields[] = 'name';
			$args[] = $name;
		}
		if ($account) {
			$fields[] = 'publicid';
			$args[] = $account;
			$fields[] = 'passhash';
			$args[] = $this->HashPassword($passwd);
		}

		$fields[] = 'addwhen';
		$dt = new \DateTime('now', new \DateTimeZone('UTC'));
		$args[] = $dt->getTimestamp();

		$sql = 'INSERT INTO '.$this->table.' ('.implode(',',$fields).
		') VALUES ('.str_repeat('?,',count($fields)-1).'?)';
		$r = $this->dbhandle->Execute($sql,$args);
		return ($r != FALSE) ? $bid : FALSE;
	}

	/**
	DeleteUser:
	@booker_id: numeric identifier
	Returns: boolean indicating success
	*/
	public function DeleteUser ($booker_id)
	{
		$r = $this->dbhandle->Execute('DELETE FROM '.$this->table.' WHERE booker_id=?',array($booker_id));
		if ($r) {
			$r = $this->dbhandle->Execute('DELETE FROM '.$this->history.' WHERE booker_id=?',array($booker_id));
		}
		return ($r != FALSE);
	}

	/**
	Register:
	Record account/login and password for an existing booker
	@account: account identifier
	@passwd: optional plaintext password
	 */
	public function Register ($booker_id, $account, $passwd=DEFAULTPASS)
	{
		if ($account) {
			if (!$paswd)
				return FALSE;
			$r = $this->dbhandle->GetOne('SELECT name FROM '.$this->table.' WHERE publicid=?',array($account));
			if ($r)
				return FALSE;
		}
		$r = $this->dbhandle->Execute('UPDATE '.$this->table.' SET publicid=? WHERE booker_id=?',
			array($account,booker_id));
		$this->SetPassword ($booker_id,'FORCE',$passwd);
	}

	/**
	SetPassword:
	@booker_id: numeric identifier
	@current: current password (plaintext), or string 'FORCE' to force the change
	@passwd: optional new plaintext password, default 'changethis'
	Returns: boolean indicating success
	*/
	public function SetPassword ($booker_id, $current, $passwd=DEFAULTPASS)
	{
		$row = $this->dbhandle->GetRow('SELECT passhash FROM '.$this->table.' WHERE booker_id=?',array($booker_id));
		if ($row) {
			if ($current == 'FORCE' || $this->matchpass($row['passhash'],$current)) {
				$r = $this->dbhandle->Execute('UPDATE '.$this->table.' SET passhash=? WHERE booker_id=?',
					array($this->HashPassword($passwd),$booker_id));
				return ($r != FALSE);
			}
		}
		return FALSE;
	}

	/**
	SetContact:
	@booker_id: numeric identifier
	@contact: new contact-address
	Returns: boolean indicating success
	*/
	public function SetContact ($booker_id, $contact)
	{
		$r = $this->dbhandle->Execute('UPDATE '.$this->table.' SET contact=? WHERE booker_id=?',
			array($contact,$booker_id));
		return ($r != FALSE);
	}

	/**
	:
	@booker_id: numeric identifier
	Returns: stored contact-address
	*/
	public function GetContact ($booker_id)
	{
		$r = $this->dbhandle->GetOne('SELECT contact FROM '.$this->table.' WHERE booker_id=? AND active=1',
			array($booker_id));
		if ($r == FALSE) $r = '';
		return $r;
	}

	/**
	IsKnown:
	@name: user name or account identifier
	Returns: booker_id or FALSE
	*/
	public function IsKnown ($name)
	{
		$rows = $this->dbhandle->GetAll('SELECT booker_id FROM '.$this->table.' WHERE publicid=? OR name=? AND active=1',
			array($name,$name));
		return ($rows != FALSE) ? reset($rows) : FALSE;
	}

	/**
	IsRegistered:
	@name:
	Returns: booker_id or FALSE
	*/
	public function IsRegistered ($name)
	{
		$r = $this->dbhandle->GetOne('SELECT booker_id FROM '.$this->table.
		' WHERE publicid=? AND passhash IS NOT NULL AND passhash<>\'\' AND ACTIVE=1',array($name));
		return ($r != FALSE) ? $r : FALSE;
	}

	/**
	IsAccepted:
	@name: login/account identifier, or recorded name
	@passwd: plaintext password, if @name is for an account
	Returns: booker_id or FALSE
	*/
	public function IsAccepted ($name, $passwd)
	{
		$rows = $this->dbhandle->GetAll('SELECT booker_id,publicid,name,passhash FROM '
			.$this->table.' WHERE publicid=? or name=? AND active=1',array($name,$name));
		if ($rows) {
			$row = reset($rows);
			$r = $row['publicid'];
			if ($r == '') { //hence $row['name'] == $name
				return (int)$row['booker_id'];
			} else //$row['publicid'] == $name
				if ($this->matchpass($row['passhash'],$passwd)) {
				return (int)$row['booker_id'];
			}
		}
		return FALSE;
	}

	/**
	SetRights:
	@booker_id: numeric identifier
	@rights: array of $name=>boolean
	@type: optional enumerator, to avoid lookup
	Returns: nothing
	*/
	public function SetRights ($booker_id, $rights, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($booker_id);
		}
		if ($type === FALSE)
			return;
		$base = $type % 10;
		$oldflags = (int)($type / 10);
		$newflags = 0;
		foreach ($rights as $right=>$val)
		{
			switch ($right) {
			 case 'prepay':
				break;
			 case 'postpay':
			 	if ($val)
					$newflags |= 0x01;
				else
					$oldflags &= ~0x01;
				break;
			 case 'record':
			 	if ($val)
					$newflags |= 0x02;
				else
					$oldflags &= ~0x02;
				break;
			}
		}
		$newtype = ($newflags | $oldflags) * 10 + $base;
		$r = $this->dbhandle->Execute('UPDATE '.$this->table.' SET type=? WHERE booker_id=?',
			array($newtype,$booker_id));
		return ($r != FALSE);
	}

	/**
	HasRight:
	@booker_id: numeric identifier
	@right: descriptor string, 'postpay' etc
	@type: optional enumerator, to avoid lookup
	Returns: boolean
	*/
	public function HasRight ($booker_id, $right, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($booker_id);
		}
		if ($type === FALSE)
			return FALSE;
		$flags = (int)($type / 10);
		switch ($right) {
		 case 'prepay':
		 	return TRUE;
		 case 'postpay':
			return (($flags & 0x01) > 0);
		 case 'record':
			return (($flags & 0x02) > 0);
		 default:
			return FALSE;
		}
	}

	/**
	GetRights:
	@booker_id: numeric identifier
	@rights: array of descriptor strings ('postpay' etc), or NULL
	@type: optional enumerator, to avoid lookup
	Returns: array, or FALSE
	*/
	public function GetRights ($booker_id, $rights=NULL, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($booker_id);
		}
		if ($type === FALSE)
			return FALSE;
		$flags = (int)($type / 10);
		if ($rights !== NULL) {
			$ret = array();
			foreach ($rights as $right) {
				switch ($right) {
				 case 'prepay':
					$val = TRUE;
					break;
				 case 'postpay':
					$val = (($flags & 0x01) > 0);
					break;
				 case 'record':
					$val = (($flags & 0x02) > 0);
					break;
				 default:
					$val = FALSE;
				}
				$ret[$right] = $val;
			}
		} else {
			$ret = array('prepay'=>TRUE);
			$ret['postpay'] = ($flags & 0x01) > 0;
			$ret['record'] = ($flags & 0x02) > 0;
		}
		return $ret;
	}

	/**
	GetBaseType:
	@booker_id: numeric identifier
	@type: optional enumerator, to avoid lookup
	Returns: enum 0..9, or FALSE
	*/
	public function GetBaseType ($booker_id, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($booker_id);
		}
		if ($type !== FALSE)
			return $type % 10;
		return FALSE;
	}

	/**
	GetDisplayClass:
	@booker_id: numeric identifier
	Returns: enum 0..5
	*/
	public function GetDisplayClass ($booker_id)
	{
		$r = $this->dbhandle->GetOne('SELECT displayclass FROM '.$this->table.' WHERE booker_id=? AND active=1',
			array($booker_id));
		if (!$r) $r = 0;
		return $r;
	}
}
