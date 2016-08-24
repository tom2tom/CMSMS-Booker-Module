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

	private function gettype(&$mod, $booker_id)
	{
		$r = $mod->dbHandle->GetOne('SELECT type FROM '.$mod->BookerTable.' WHERE booker_id=?',
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
	@booker_id: numeric identifier
	Returns:
	*/
/*	public function($booker_id)
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
		$bid = $mod->dbHandle->GenId($mod->BookerTable.'_seq');
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

		$sql = 'INSERT INTO '.$mod->BookerTable.' ('.implode(',',$fields).
		') VALUES ('.str_repeat('?,',count($fields)-1).'?)';
		//TODO $utils->SafeExec()
		$r = $mod->dbHandle->Execute($sql,$args);
		return ($r != FALSE) ? $bid : FALSE;
	}

	/**
	DeleteUser:
	@mod: reference to current Booker module object
	@booker_id: booker identifier, or array of them, or '*'
	Returns: boolean indicating success
	*/
	public function DeleteUser(&$mod, $booker_id)
	{
		$sql = 'DELETE FROM '.$mod->BookerTable;
		if (is_array($booker_id)) {
			$fillers = str_repeat('?,',count($booker_id)-1);
			$sql .= ' WHERE booker_id IN('.$fillers.'?)';
			$args = $booker_id;
		} elseif ($booker_id == '*') {
			$args = array();
		} else {
			$sql .= ' WHERE booker_id=?';
			$args = array($booker_id);
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
	@booker_id: booker identifier, or array of them, or '*'
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or error message
	*/
/*	public function ExportUser(&$mod, $booker_id)
	{
		$funcs = new Export();
		list($res,$key) = $funcs->ExportBookers($mod,$booker_id);
		if ($res)
			return array(TRUE,'');
		return array(FALSE,$mod->Lang($key));
	}
*/
	/**
	RegisterUser:
	Record account/login and password for an existing booker
	@mod: reference to current Booker module object
	@booker_id: booker identifier
	@account: account identifier
	@passwd: optional plaintext password
	 */
	public function RegisterUser(&$mod, $booker_id, $account, $passwd=DEFAULTPASS)
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
		$this->SetPassword($mod,$booker_id,'FORCE',$passwd);
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
	@booker_id: numeric identifier
	@current: current password (plaintext), or string 'FORCE' to force the change
	@passwd: optional new plaintext password, default 'changethis'
	Returns: boolean indicating success
	*/
	public function SetPassword(&$mod, $booker_id, $current, $passwd=DEFAULTPASS)
	{
		$row = $mod->dbHandle->GetRow('SELECT passhash FROM '.$mod->BookerTable.' WHERE booker_id=?',array($booker_id));
		if ($row) {
			if ($current == 'FORCE' || $this->matchpass($row['passhash'],$current)) {
			//TODO $utils->SafeExec()
				$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET passhash=? WHERE booker_id=?',
					array($this->HashPassword($passwd),$booker_id));
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
		$booker_id = $this->IsKnown($mod,$main,$supp);
		if ($booker_id === FALSE) {
			if (empty($params['publicid'])) {
				$is_new = TRUE;
				$booker_id = $this->AddUser($mod,$main);
				$this->SetContact($mod,$booker_id,$supp);
			} else {
				$is_new = FALSE;
				$booker_id = FALSE; //report password failure
			}
		} else {
			$is_new = FALSE;
		}
		return array($booker_id,$is_new);
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
			$main = $params['name'];
			$supp = array('address'=>$params['address'],'phone'=>$params['phone']);
		}
//		return $this->GetID($main,$supp);
		//TODO support FEU-permissions
		$booker_id = $this->IsKnown($mod,$main,$supp);
		if ($booker_id === FALSE) {
			if (empty($params['publicid'])) {
				$is_new = TRUE;
				$booker_id = $this->AddUser($mod,$main);
				$this->SetContact($mod,$booker_id,$supp);
			} else {
				$is_new = FALSE;
				$booker_id = FALSE; //report password failure
			}
		} else {
			$is_new = FALSE;
		}
		return array($booker_id,$is_new);
	}

	/**
	GetName:
	@mod: reference to current Booker module object
	@booker_id: numeric identifier
	Returns: recorded booker-name or ''
	*/
	public function GetName(&$mod, $booker_id)
	{
		$r = $mod->dbHandle->GetOne('SELECT name FROM '.$mod->BookerTable.' WHERE booker_id=?',
			array($booker_id));
		if (!$r) $r = '';
		return $r;
	}

	/**
	SetContact:
	@mod: reference to current Booker module object
	@booker_id: numeric identifier
	@contact: new email-address, or phone number, or associative array with email-address and/or phone-number
	Returns: boolean indicating success
	*/
	public function SetContact(&$mod, $booker_id, $contact)
	{
		$patn = '/^(\+\d{1,4} *)?[\d ]{5,15}$/';
		if (is_array($contact)) {
			$sql = '';
			$args = array();
			foreach ($contact as $k=>$val) {
	//TODO interpret & arrange values
			}
			$args[] = $booker_id;
		} else {
			$contact = trim($contact);
			if (!$contact) {
				$sql = 'address=?'; //clear address - BAD!
			} elseif (preg_match($patn,$contact)) {
				$sql = 'phone=?';
			} else { //TODO specific validity check(s)
				$sql = 'address=?';
			}
			$args = array($contact,$booker_id);
		}
		//TODO $utils->SafeExec()
		$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET '.$sql.' WHERE booker_id=?',
			$args);
		return ($r != FALSE);
	}

	/**
	GetContact:
	@mod: reference to current Booker module object
	@booker_id: numeric identifier
	Returns: 2-member associative array (stored contact-address and phone), or '';
	*/
	public function GetContact(&$mod, $booker_id)
	{
		$r = $mod->dbHandle->GetRow('SELECT address,phone '.$mod->BookerTable.' WHERE booker_id=?',
			array($booker_id));
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
			array($id,$id));
		if ($rows) {
			foreach ($rows as $one) {
				if ($id == $one['publicid']) {
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
	@booker_id: numeric identifier
	@rights: array of $name=>boolean
	@type: optional enumerator, to avoid lookup
	Returns: nothing
	*/
	public function SetRights(&$mod, $booker_id, $rights, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$booker_id);
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
			array($newtype,$booker_id));
		return ($r != FALSE);
	}

	/**
	HasRight:
	@mod: reference to current Booker module object
	@booker_id: numeric identifier
	@right: descriptor string, 'postpay' etc
	@type: optional enumerator, to avoid lookup
	Returns: boolean
	*/
	public function HasRight(&$mod, $booker_id, $right, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$booker_id);
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
	@booker_id: numeric identifier
	@rights: array of descriptor strings ('postpay' etc), or NULL
	@type: optional enumerator, to avoid lookup
	Returns: array, or FALSE
	*/
	public function GetRights(&$mod, $booker_id, $rights=NULL, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$booker_id);
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
	@booker_id: numeric identifier
	@type: enum 0..9
	Returns: boolean indicating success
	*/
	public function SetBaseType(&$mod, $booker_id, $type)
	{
		$current = $this->gettype($mod,$booker_id);
		if ($current !== FALSE) {
			$type = $type % 10 + (int)($current/10) * 10;
			$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET type=? WHERE booker_id=?',
				array($type,$booker_id));
			return ($r != FALSE);
		}
		return FALSE;
	}

	/**
	GetBaseType:
	@mod: reference to current Booker module object
	@booker_id: numeric identifier
	@type: optional enumerator, to avoid lookup
	Returns: enum 0..9, or FALSE
	*/
	public function GetBaseType(&$mod, $booker_id, $type=FALSE)
	{
		if ($type === FALSE) {
			$type = $this->gettype($mod,$booker_id);
		}
		if ($type !== FALSE)
			return $type % 10;
		return FALSE;
	}

	/**
	SetDisplayClass:
	@mod: reference to current Booker module object
	@booker_id: numeric identifier
	@class: enum 0 = set default, or 1..Booker::USERSTYLES
	Returns: boolean indicating success
	*/
	public function SetDisplayClass(&$mod, $booker_id, $class)
	{
		if ($class >= 0 && $class <= \Booker::USERSTYLES) {
			if ($class == 0)
				$class = 1; //set default
			$r = $mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET displayclass=? WHERE booker_id=?',
				array($class,$booker_id));
			return ($r != FALSE);
		}
		return FALSE;
	}

	/**
	GetDisplayClass:
	@mod: reference to current Booker module object
	@booker_id: numeric identifier
	Returns: enum 1..Booker::USERSTYLES
	*/
	public function GetDisplayClass(&$mod, $booker_id)
	{
		$r = $mod->dbHandle->GetOne('SELECT displayclass FROM '.$mod->BookerTable.' WHERE booker_id=?',
			array($booker_id));
		if ($r)
			$r = (int)$r;
		else
			$r = 1;
		return $r;
	}
}
