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

	private function gettype(&$mod, $bookerid)
	{
		$r = $mod->dbHandle->GetOne('SELECT type FROM '.$mod->BookerTable.' WHERE booker_id=?',
			array($bookerid));
		return ($r) ? $r:FALSE;
	}

	// adapted from PHP's password hashing framework
	private function encodeBytes($input, $ilen=16)
	{
		if (!$input)
			$input = 'pGJCu"F~p+>Q94je';	//ensure a hash for empty passwords
		while (strlen($input) < $ilen)
			$input .= strrev($input);
		$itoa64 = '~|ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
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
	public function HashPassword($passwd)
	{
		$pubkey = $this->encodeBytes(str_shuffle(time().'ABCDEFGHIJKLMNOPQRSTUVWXYZ'),8); //10-chars
		return $pubkey.$this->encodeBytes($passwd.$pubkey,28); //10+38-chars
	}

	private function matchpass($hashed, $passwd)
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
	@bookerid: numeric identifier
	Returns:
	*/
/*	public function($bookerid)
	{
	}
*/
	/**
	AddUser:
	@mod: reference to current Booker module object
	@name: user name
	@account: optional login/account identifier
	@passwd: optional plaintext password
	Returns: booker_id, OR FALSE if user exists and/or no password is provided
	*/
	public function AddUser(&$mod, $name, $account=FALSE, $passwd=FALSE)
	{
		if (!($name || $account))
			return FALSE;
		if ($account) {
			if (!$passwd)
				return FALSE;
			$r = $mod->dbHandle->GetOne('SELECT name FROM '.$mod->BookerTable.' WHERE publicid=?',array($account));
			if ($r)
				return FALSE;
		}
		$fields = array();
		$args = array();
		$fields[] = 'booker_id';
		$bookerid = $mod->dbHandle->GenId($mod->BookerTable.'_seq');
		$args[] = $bookerid;

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

		$sql = 'INSERT INTO '.$mod->BookerTable.' ('.implode(',',$fields).
		') VALUES ('.str_repeat('?,',count($fields)-1).'?)';
		//TODO $utils->SafeExec()
		$r = $mod->dbHandle->Execute($sql,$args);
		return ($r != FALSE) ? $bookerid : FALSE;
	}

	/**
	DeleteUser:
	@mod: reference to current Booker module object
	@bookerid: booker identifier, or array of them, or '*'
	Returns: boolean indicating success
	*/
	public function DeleteUser(&$mod, $bookerid)
	{
		$sql = 'DELETE FROM '.$mod->BookerTable;
		if (is_array($bookerid)) {
			$fillers = str_repeat('?,',count($bookerid)-1);
			$sql .= ' WHERE booker_id IN('.$fillers.'?)';
			$args = $bookerid;
		} elseif ($bookerid == '*') {
			$args = array();
		} else {
			$sql .= ' WHERE booker_id=?';
			$args = array($bookerid);
		}
		//TODO $utils->SafeExec()
		$r = $mod->dbHandle->Execute($sql,$args);
		if ($r) {
			$sql = str_replace($mod->BookerTable,$mod->HistoryTable,$sql);
			$r = $mod->dbHandle->Execute($sql,$args);
		}
		return ($r != FALSE);
	}

	/* *
	ExportUser:
	Export data for one or more bookers
	@mod: reference to current Booker module object
	@bookerid: booker identifier, or array of them, or '*'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
/*	public function ExportUser(&$mod, $bookerid)
	{
		$funcs = new Export();
		list($res,$key) = $funcs->ExportBookers($mod,$bookerid);
		if ($res)
			return array(TRUE,'');
		return array(FALSE,$mod->Lang($key));
	}
*/
	/**
	RegisterUser:
	Record account/login and password for an existing booker
	@mod: reference to current Booker module object
	@bookerid: booker identifier
	@account: account identifier
	@passwd: optional plaintext password
	 */
	public function RegisterUser(&$mod, $bookerid, $account, $passwd=DEFAULTPASS)
	{
		if ($account) {
			if (!$paswd)
				return FALSE;
			$r = $mod->dbHandle->GetOne('SELECT name FROM '.$mod->BookerTable.' WHERE publicid=?',array($account));
			if ($r)
				return FALSE;
		}
			//TODO $utils->SafeExec()
		$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET publicid=? WHERE booker_id=?',
			array($account,booker_id));
		$this->SetPassword($mod,$bookerid,'FORCE',$passwd);
	}

/* TODO support FEU-permissions
	$ob = \cms_utils::get_module('FrontEndUsers');
	if ($ob) {
		$uid = $ob->LoggedInID();
		if ($uid !== FALSE) {
			$t = (int)$idata['feugroup'];
			if ($t == -1) //any group
				$save = TRUE;
			elseif ($t != 0) { //none
				$gid = $ob->GetGroupID($t);
				$save = $ob->MemberOfGroup($uid,$gid);
			}
			if ($save) {
				'record' permitted
				'publicid' OR 'name' = $ob->GetUserName($uid); //default
			}
		}
		unset($ob);
	}
*/

	/**
	SetPassword:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@current: current password (plaintext), or string 'FORCE' to force the change
	@passwd: optional new plaintext password, default 'changethis'
	Returns: boolean indicating success
	*/
	public function SetPassword(&$mod, $bookerid, $current, $passwd=DEFAULTPASS)
	{
		$row = $mod->dbHandle->GetRow('SELECT passhash FROM '.$mod->BookerTable.' WHERE booker_id=?',array($bookerid));
		if ($row) {
			if ($current == 'FORCE' || $this->matchpass($row['passhash'],$current)) {
			//TODO $utils->SafeExec()
				$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET passhash=? WHERE booker_id=?',
					array($this->HashPassword($passwd),$bookerid));
				return ($r != FALSE);
			}
		}
		return FALSE;
	}

	/* *
	GetUserID:
	Get existing or new booker enumerator representing @main and @supp
	@mod: reference to current Booker module object
	@main: user name or account identifier
	@supp: email-address or phone-number for username, or password for account
	Returns: 2-member array, 1st is booker_id or FALSE, 2nd is boolean representing id-is-new
	*/
/*	private function GetUserID(&$mod, $main, $supp)
	{
		$bookerid = $this->IsKnown($mod,$main,$supp);
		if ($bookerid === FALSE) {
			if (empty($params['publicid'])) {
				$is_new = TRUE;
				$bookerid = $this->AddUser($mod,$main);
				$this->SetContact($mod,$bookerid,$supp);
			} else {
				$is_new = FALSE;
				$bookerid = FALSE; //report password failure
			}
		} else {
			$is_new = FALSE;
		}
		return array($bookerid,$is_new);
	}
*/
	/**
	GetParamsID:
	Get existing or new booker enumerator representing the relevant members of @params
	@mod: reference to current Booker module object
	@params: reference to array of parameters
	Returns: 2-member array, 1st is booker_id or FALSE(bad P/W), 2nd is boolean representing id-is-new
	*/
	public function GetParamsID(&$mod, &$params)
	{
		if (!empty($params['publicid'])) {
			$main = $params['publicid'];
			$supp = $params['passwd'];
		} else {
			if (!empty($params['name']))
				$main = $params['name'];
			else
				$main = FALSE;
			if (!empty($params['address']))
				$supp = $params['address'];
			elseif (!empty($params['phone']))
				$supp = $params['phone'];
			elseif (!empty($params['contact']))
				$supp = $params['contact'];
			else
				$supp = FALSE;
		}
//		return $this->GetID($main,$supp);
		//TODO support FEU-permissions
		$bookerid = $this->IsKnown($mod,$main,$supp);
		if ($bookerid === FALSE) {
			if (empty($params['publicid'])) {
				$is_new = TRUE;
				$bookerid = $this->AddUser($mod,$main);
				$this->SetContact($mod,$bookerid,$supp);
			} else {
				$is_new = FALSE;
				$bookerid = FALSE; //report password failure
			}
		} else {
			$is_new = FALSE;
		}
		return array($bookerid,$is_new);
	}

	/**
	GetName:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	Returns: recorded booker-name or ''
	*/
	public function GetName(&$mod, $bookerid)
	{
		$r = $mod->dbHandle->GetOne('SELECT name FROM '.$mod->BookerTable.' WHERE booker_id=?',
			array($bookerid));
		if (!$r)
			$r = '<'.$mod->Lang('noname').'>';
		return $r;
	}

	/**
	SetContact:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@contact: new email-address, or phone number, or array with email-address and/or phone-number
	Returns: boolean indicating success
	*/
	public function SetContact(&$mod, $bookerid, $contact)
	{
		if (is_array($contact)) {
			$fields = array();
			foreach ($contact as $val) {
				$val = trim($val);
				if (!$val || preg_match(\Booker::PATNADDRESS,$val)) {
					$fields['address=?'] = $val;
				} elseif (preg_match(\Booker::PATNPHONE,$val)) {
					$fields['phone=?'] = $val;
				}
			}
			if ($fields) {
				$sql2 = implode(',',array_keys($fields));
				$args = array_values($fields);
			} else
				return FALSE;
		} else {
			$val = trim($contact);
			if (!$val || preg_match(\Booker::PATNADDRESS,$val)) {
				$sql2 = 'address=?'; //clear address - BAD!
			} elseif (preg_match(\Booker::PATNPHONE,$val)) {
				$sql2 = 'phone=?';
			} else {
				return FALSE;
			}
			$args = array($val);
		}
		$sql = 'UPDATE '.$mod->BookerTable.' SET '.$sql2.' WHERE booker_id=?';
		$args[] = $bookerid;
		//TODO $utils->SafeExec()
		$r = $mod->dbHandle->Execute($sql,$args);
		return ($r != FALSE);
	}

	/**
	GetContact:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	Returns: 2-member associative array (stored contact-address and phone), or '';
	*/
	public function GetContact(&$mod, $bookerid)
	{
		$r = $mod->dbHandle->GetRow('SELECT address,phone '.$mod->BookerTable.' WHERE booker_id=?',
			array($bookerid));
		if ($r == FALSE) $r = '';
		return $r;
	}

	/**
	IsKnown:
	@mod: reference to current Booker module object
	@main: user name or account identifier
	@supp: email-address or phone-number for username, or password for account
	Returns: booker_id or FALSE if no match or password is wrong
	*/
	public function IsKnown(&$mod, $main, $supp)
	{
		$rows = $mod->dbHandle->GetArray('SELECT booker_id,publicid,passhash,address,phone FROM '.
			$mod->BookerTable.' WHERE (name=? OR publicid=?) ORDER BY addwhen DESC',
			array($main,$main));
		if ($rows) {
			foreach ($rows as $one) {
				if ($main == $one['publicid']) {
					if ($supp && $this->matchpass($one['passhash'],$supp))
						return $one['booker_id'];
				} elseif ($supp) {
					if ($supp == $one['address'] || $supp == $one['phone'])
						return $one['booker_id'];
				}
			}
		}
		return FALSE;
	}

	/**
	IsRegistered:
	@mod: reference to current Booker module object
	@login: identifier
	Returns: booker_id or FALSE
	*/
	public function IsRegistered(&$mod, $login)
	{
		$r = $mod->dbHandle->GetOne('SELECT booker_id FROM '.$mod->BookerTable.
		' WHERE publicid=? AND passhash IS NOT NULL AND passhash<>\'\' AND active=1',array($login));
		return ($r != FALSE) ? $r : FALSE;
	}

	/**
	IsAccepted:
	@mod: reference to current Booker module object
	@name: login/account identifier, or recorded name
	@passwd: plaintext password, if @name is for an account
	Returns: booker_id or FALSE
	*/
	public function IsAccepted(&$mod, $name, $passwd)
	{
		$rows = $mod->dbHandle->GetArray('SELECT booker_id,publicid,name,passhash FROM '
			.$mod->BookerTable.' WHERE publicid=? or name=? AND active=1',array($name,$name));
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
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@rights: array of $name=>boolean
	@type: optional enumerator, to avoid lookup
	Returns: nothing
	*/
	public function SetRights(&$mod, $bookerid, $rights, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$bookerid);
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
		//TODO $utils->SafeExec()
		$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET type=? WHERE booker_id=?',
			array($newtype,$bookerid));
		return ($r != FALSE);
	}

	/**
	HasRight:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@right: descriptor string, 'postpay' etc
	@type: optional enumerator, to avoid lookup
	Returns: boolean
	*/
	public function HasRight(&$mod, $bookerid, $right, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$bookerid);
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
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@rights: array of descriptor strings ('postpay' etc), or NULL
	@type: optional enumerator, to avoid lookup
	Returns: array, or FALSE
	*/
	public function GetRights(&$mod, $bookerid, $rights=NULL, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$bookerid);
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
	SetBaseType:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@type: enum 0..9
	Returns: boolean indicating success
	*/
	public function SetBaseType(&$mod, $bookerid, $type)
	{
		$current = $this->gettype($mod,$bookerid);
		if ($current !== FALSE) {
			$type = $type % 10 + (int)($current/10) * 10;
			$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET type=? WHERE booker_id=?',
				array($type,$bookerid));
			return ($r != FALSE);
		}
		return FALSE;
	}

	/**
	GetBaseType:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@type: optional enumerator, to avoid lookup
	Returns: enum 0..9, or FALSE
	*/
	public function GetBaseType(&$mod, $bookerid, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$bookerid);
		}
		if ($type !== FALSE)
			return $type % 10;
		return FALSE;
	}

	/**
	SetDisplayClass:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@class: enum 0 = set default, or 1..Booker::USERSTYLES
	Returns: boolean indicating success
	*/
	public function SetDisplayClass(&$mod, $bookerid, $class)
	{
		if ($class >= 0 && $class <= \Booker::USERSTYLES) {
			if ($class == 0)
				$class = 1; //set default
			$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET displayclass=? WHERE booker_id=?',
				array($class,$bookerid));
			return ($r != FALSE);
		}
		return FALSE;
	}

	/**
	GetDisplayClass:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	Returns: enum 1..Booker::USERSTYLES
	*/
	public function GetDisplayClass(&$mod, $bookerid)
	{
		$r = $mod->dbHandle->GetOne('SELECT displayclass FROM '.$mod->BookerTable.' WHERE booker_id=?',
			array($bookerid));
		if ($r)
			$r = (int)$r;
		else
			$r = 1;
		return $r;
	}

	/*
	ConformUserHistory:
	Conform tabled user value per @params value
	@mod: reference to current Booker module
	@params: reference to parameters array
	Returns: booker identifier indicating success, or FALSE
	*/
/*	private function ConformUserHistory(&$mod, &$params)
	{
/ * when updating a pending request, $params[] =
 'history_id' => string '42'
 'custmsg' => string '' (length=0)
 'when' => int 1474210800
 'until' => int 1474214399
 'name' => string 'Tester' (length=6)
 'comment' => string 'None' (length=4)
 'subgrpcount' => string '1' (length=1)
* /
		$sql = 'SELECT booker_id FROM '.$mod->HistoryTable.' WHERE history_id=?';
		$bookerid = $mod->dbHandle->GetOne($sql,array($params['history_id']));
		if (!$bookerid)
			return FALSE; //should never happen
		$sql = 'UPDATE '.$mod->BookerTable.' SET name=? WHERE booker_id=?';
		$mod->dbHandle->Execute($sql,array($params['name'],$bookerid));
		return $bookerid;
	}
*/
	/**
	ConformUserData:
	Conform tabled values: contact,displayclass and/or user according to @params values
	@mod: reference to current Booker module
	@params: reference to parameters array
	Returns: T/F indicating successful completion
	*/
	public function ConformUserData(&$mod, &$params)
	{
		$ret = FALSE;
		$utils = new Utils();
		if (isset($params['history_id'])) {
			$sql = <<<EOS
UPDATE $mod->BookerTable B
JOIN $mod->HistoryTable H ON B.booker_id = H.booker_id
SET B.name=?
WHERE H.history_id=?
EOS;
			$ret = $utils->SafeExec($sql,array($params['name'],$params['history_id']));
		}

		if (isset($params['bkg_id'])) {
			$sql2 = array();
			$args = array();
			foreach (array('publicid','name') as $k) {
				if (isset($params[$k])) {
					$sql2[] = 'B.'.$k.'=?';
					$args[] = trim($params[$k]);
				}
			}
			$k = 'passwd';
			if (isset($params[$k])) {
				$sql2[] = 'B.passhash=?';
				$args[] = $this->HashPassword(trim($params[$k]));
			}
			$k = 'contact';
			if (!empty($params[$k])) {
				$val = trim($params[$k]);
				if (preg_match(\Booker::PATNADDRESS,$val)) {
					$sql2[] = 'B.address=?';
					$args[] = $val;
				} elseif (preg_match(\Booker::PATNPHONE,$val)) {
					$sql2[] = 'B.phone=?';
					$args[] = $val;
				}
			}
			$k = 'displayclass';
			if (isset($params[$k])) {
				$val = (int)$params[$k];
				if ($val >= 1 && $val <= \Booker::USERSTYLES) {
					$sql2[] = 'B.'.$k.'=?';
					$args[] = $val;
				}
			}

			if ($sql2) {
				$table = (empty($params['repeat'])) ? $mod->DataTable : $mod->RepeatTable;
				$fields = implode(',',$sql2);
				$sql = <<<EOS
UPDATE $mod->BookerTable B
JOIN $table T ON B.booker_id = T.booker_id
SET {$fields}
WHERE T.bkg_id=?
EOS;
				$args[] = $params['bkg_id'];
				$ret = $utils->SafeExec($sql,$args);
			}
		}
		return $ret;
	}

}
