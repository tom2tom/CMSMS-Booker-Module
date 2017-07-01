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
	protected $afuncs;
	protected $cfuncs;

	//TODO - extra parameter (context) if resource-specific contexts are supported
	public function __construct(&$mod)
	{
		$amod = \cms_utils::get_module('Auther');
		if ($amod) {
			$this->afuncs = new \Auther\Auth($amod,$mod->GetPreference('authcontext',0));
			unset($amod);
		} else {
			$this->afuncs = NULL;
			$X=$Y; //crash
		}
		$this->cfuncs = new Crypter($mod);
	}

	protected function gettype(&$mod, $bookerid)
	{
		//TODO $utils->SafeGet()
		$r = $mod->dbHandle->GetOne('SELECT type FROM '.$mod->BookerTable.' WHERE booker_id=?',
			[$bookerid]);
		return ($r) ? $r:FALSE;
	}

	/**
	AddUser:
	@mod: reference to current Booker module object
	@name: user name
	@address: user contact
	@phone: user phone no.
	@active: optional active-state default = 1
	@login: optional login/account identifier default = FALSE
	@passwd: optional plaintext password default = FALSE
	@force: optional boolean, whether to force-update Booker table, even if Auther update failed, default FALSE
	Returns: booker_id, OR FALSE if user exists and/or no password is provided or is not acceptable
	 */
	public function AddUser(&$mod, $name, $address, $phone, $active=1, $login=FALSE, $passwd=FALSE, $force=FALSE)
	{
		if ($login) {
			if (!$passwd) {
				return FALSE; //default should have been accepted in UI
			}
		} elseif ($name) {
			//duplication check (no need for AuthTable check)
			//TODO limit to pertinent context $this->afuncs->GetContext()
			//TODO $utils->SafeGet()
			$r = $mod->dbHandle->GetOne('SELECT 1 FROM '.$mod->BookerTable.' WHERE name=?',[$name]);
			if ($r) {
				return FALSE;
			}
		} else {
			return FALSE;
		}

		$fields = [];
		$args = [];
		$fields[] = 'booker_id';
		$bookerid = $mod->dbHandle->GenId($mod->BookerTable.'_seq');
		$args[] = $bookerid;

		if (preg_match(\Booker::PATNPHONE,$address) && !$phone) {
			$phone = $address;
			$address = NULL;
		}

		if ($login) {
			if (!$address) {
				$address = $phone;
			}

			$res = $this->afuncs->AddUserReal($login,$passwd,$name,$address,$active,[]);
			if (!($res[0] || $force)) {
				return FALSE;
			}
			$fields[] = 'publicid';
			$args[] = $login;
		} else { //$name
			$fields[] = 'name';
			$args[] = $name;
			$fields[] = 'address';
			$args[] = $address ? $this->cfuncs->encrypt_value($address):NULL;
		}

		$fields[] = 'phone';
		$args[] = $phone ? $this->cfuncs->encrypt_value($phone):NULL;
		$fields[] = 'active';
		$args[] = (int)$active;
		$fields[] = 'addwhen';
		$args[] = time();
		$sql = 'INSERT INTO '.$mod->BookerTable.' ('.implode(',',$fields).
		') VALUES ('.str_repeat('?,',count($fields)-1).'?)';
		//TODO $utils->SafeExec()
		$mod->dbHandle->Execute($sql,$args);
		return ($mod->dbHandle->Affected_Rows() > 0) ? $bookerid : FALSE;
//		return $bookerid;
	}

	/**
	ChangeUser:
	Doesn't affect password, active, type or displayclass
	@mod: reference to current Booker module object
	@userid: user enumerator per BookerTable
	@name: optional replacement user name, maybe same as @oldname, default FALSE = no change (=@oldname)
	@address: optional replacement user address, default FALSE = no change
	@phone: optional replacement user phone no., default FALSE = no change
	@active: optional integer replacement active status, default FALSE = no change
	@oldlogin: optional current login/account identifier, default FALSE
	@login: optional new login/account identifier, default FALSE = no change
	@passwd: optional plaintext current password, default FALSE
	@force: optional boolean, whether to force-update Booker table, even if Auther update failed, default FALSE
	Returns: boolean indicating success
	 */
	public function ChangeUser(&$mod, $userid, $name=FALSE, $address=FALSE, $phone=FALSE, $active=FALSE, $oldlogin=FALSE, $login=FALSE, $passwd=FALSE, $force=FALSE)
	{
		//c.f. Utils::SetUserProperties($mod,$bookerid,$alldata);
		if ($oldlogin === $login) {
			$login = FALSE;
		}
		if ($oldlogin || $login) {
			if ($address !== FALSE && !$address && $phone) {
				$address = $phone;
			}
			if (!$oldlogin && $login) { //newly registered
				$res = $this->afuncs->AddUserReal($login,$passwd,$name,$address,$active);
				$passwd = FALSE;
				$name = NULL;
				$address = NULL;
				$phone = NULL;
			} elseif ($oldlogin && !$login) { //newly de-registered
				$login = NULL;
				$passwd = FALSE;
				$data = $this->afuncs->GetUserProperties($oldlogin,'address',FALSE);
				if ($data) {
					$t = $data[0]['address'];
					if ($t) {
						if (!$phone && preg_match(\Booker::PATNPHONE,$t)) {
							$phone = $t;
						} elseif (!$address) {
							$address = $t; //anything will do
						}
					}
				}
				$res = $this->afuncs->DeleteUserReal($oldlogin);
			} else { //$login == $oldlogin
				//no change to active, or extra params
				$res = $this->afuncs->ChangeUserReal($oldlogin,$login,$name,$address,FALSE);
			}
			if ($res[0] || $force) {
				if ($passwd) {
					if (!$login) {
						$login = $oldlogin;
					}
					$uid = $this->afuncs->GetUserID($login);
					$this->afuncs->ChangePasswordReal($uid,$passwd);
				}
				$fields = [];
				$args = [];
				if ($login !== FALSE) {
					$fields[] = 'publicid';
					$args[] = $login ? $login:NULL;
				}
				if ($name !== FALSE) {
					$fields[] = 'name';
					$args[] = $name ? $name:NULL;
				}
				if ($address !== FALSE) {
					$fields[] = 'address';
					$args[] = $address ? $this->cfuncs->encrypt_value($address):NULL;
				}
				if ($phone !== FALSE) {
					$fields[] = 'phone';
					$args[] = $phone ? $this->cfuncs->encrypt_value($phone):NULL;
				}
				if ($active !== FALSE) {
					$fields[] = 'active';
					$args[] = (int)$active;
				}
				if ($fields) {
					$args[] = $userid;
					$sql = 'UPDATE '.$mod->BookerTable.' SET '.implode('=?,',$fields).'=? WHERE booker_id=?';
					//TODO $utils->SafeExec()
					$mod->dbHandle->Execute($sql,$args);
					return ($mod->dbHandle->Affected_Rows() > 0); //racy??
				}
				return TRUE;
			}
			return FALSE;
		} else { //remains un-registered
			$fields = [];
			$args = [];
			if ($name !== FALSE) {
				$fields[] = 'name';
				$args[] = $name ? $name:NULL;
			}
			if ($address !== FALSE) {
				$fields[] = 'address';
				$args[] = $address ? $this->cfuncs->encrypt_value($address):NULL;
			}
			if ($phone !== FALSE) {
				$fields[] = 'phone';
				$args[] = $phone ? $this->cfuncs->encrypt_value($phone):NULL;
			}
			if ($active !== FALSE) {
				$fields[] = 'active';
				$args[] = (int)$active;
			}
			$fields[] = 'publicid';
			$args[] = NULL;
			$args[] = $userid;
			$sql = 'UPDATE '.$mod->BookerTable.' SET '.implode('=?,',$fields).'=? WHERE booker_id=?';
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute($sql,$args);
			return ($mod->dbHandle->Affected_Rows() > 0); //racy??
		}
	}

	/**
	DeleteUser:
	Mark for deletion or actually delete if already marked
	@mod: reference to current Booker module object
	@bookerid: booker identifier, or array of them, or '*'
	@force: optional boolean, whether to force-update Booker table, even if Auther update failed, default FALSE
	Returns: boolean indicating success
	 */
	public function DeleteUser(&$mod, $bookerid, $force=FALSE)
	{
		if (is_array($bookerid)) {
			$fillers = str_repeat('?,',count($bookerid)-1);
			$cond = ' WHERE booker_id IN ('.$fillers.'?)';
			$args = $bookerid;
		} elseif ($bookerid == '*') {
			$cond = ' WHERE 1=1'; //something TRUE
			$args = [];
		} else {
			$cond = ' WHERE booker_id=?';
			$args = [(int)$bookerid];
		}
		$sql = 'SELECT booker_id,publicid,active FROM '.$mod->BookerTable.$cond.' AND publicid IS NOT NULL AND publicid!=\'\'';
		//TODO $utils->SafeGet()
		$xr = $mod->dbHandle->GetArray($sql,$args);

		//CHECKME process Auther first, then Booker upon success or $force
/*		if ($xr) {
			//delete users if already flagged
			foreach ($xr as $row) {
				if ($row['active'] == -1) {
					$res = $this->afuncs->DeleteUserReal($row['publicid']);
					if ($res[0] || $force) {
						//TODO $utils->SafeExec()
						$mod->dbHandle->Execute($sql,$args);
					}
				} else {
				}
			}
		}
*/
		//delete users if already flagged
		$sql = 'DELETE FROM '.$mod->BookerTable.$cond.' AND active=-1';
		//TODO $utils->SafeExec()
		$mod->dbHandle->Execute($sql,$args);
		//otherwise, flag as delete-pending
		$sql = 'UPDATE '.$mod->BookerTable.' SET active=-1'.$cond;
		//TODO $utils->SafeExec()
		$mod->dbHandle->Execute($sql,$args);
//		$res = ($mod->dbHandle->Affected_Rows() > 0); //racy?? WRONG result for successful update!
//		if ($res) {
			$sql = 'UPDATE '.$mod->OnceTable.' SET removed=?,status=?'.$cond.' AND status!=?';
//TODO update DispTable too
//TODO process RepeatsTable & related DispTable too
			array_unshift($args,time(),\Booker::STATGONE);
			$args[] = \Booker::STATGONE;
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute($sql,$args); //don't care if this does nothing
//		}
		if (/*$res && */$xr) {
			foreach ($xr as $row) {
				if ($row['active'] >= 0) {
					$this->afuncs->ChangeUserReal($row['publicid'],FALSE,FALSE,FALSE,-1);
				} else {
					$this->afuncs->DeleteUserReal($row['publicid']);
				}
			}
		}
//		return $res;
		return TRUE;
	}

	/* *
	ExportUser:
	Export data for one or more bookers
	@mod: reference to current Booker module object
	@bookerid: booker identifier, or array of them, or '*'
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = '' or error message
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
	/* *
	GetUserID:
	Get existing or new booker enumerator representing @main and @supp
	@mod: reference to current Booker module object
	@main: user name or account identifier
	@supp: email-address or phone-number for username, or password for account
	Returns: 2-member array,
	 [0] = booker_id or FALSE
	 [1] = boolean representing id-is-new
	 */
/*	private function GetUserID(&$mod, $main, $supp)
	{
		$bookerid = $this->GetKnown($mod,$main,$supp);
		if ($bookerid === FALSE) {
			if (empty($params['publicid'])) {
				$is_new = TRUE;
				$bookerid = $this->AddUser($mod,$main,$phoneTODO, $activeTODO=1, $loginTODO=FALSE, $passwdTODO=FALSE)
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
	specifically: some of account (was publicid),passwd,name,address,phone,contact
	@mod: reference to current Booker module object
	@params: reference to array of parameters
	Returns: 2-member array, [0]=booker_id or FALSE(bad P/W), [1]=boolean representing id-is-new
	 */
	public function GetParamsID(&$mod, &$params)
	{
/*$params[] include e.g.
 'account' => string ''
 'passwd' => string ''
 'contactnew' => string ''
 'bookertype' => int 2
 'name' => string 'Roger'
 'contact' => int 417394479
*/
		if (!empty($params['account'])) {
			$main = $params['account'];
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
		$bookerid = $this->GetKnown($mod,$main,$supp);
		if ($bookerid === FALSE) {
			if (empty($params['account'])) {
				$bookerid = $this->AddUser($mod,$main,$supp,'',1);
				if ($bookerid !== FALSE) {
					$this->SetContact($mod,$bookerid,$supp);
					$is_new = TRUE;
				} else {
					$is_new = FALSE; //report failure to add e.g. duplicate name
				}
			} else {
				$is_new = FALSE;
				$bookerid = FALSE; //report password failure
			}
		} else {
			$is_new = FALSE;
		}
		return [$bookerid,$is_new];
	}

	/**
	SanitizeName:
	@name: booker name string
	Returns: cleaned-up @name
	 */
	public function SanitizeName($name)
	{
		$t = trim($name);
		$t = preg_replace('/\s{1,}/', ' ', $t);
		//stet what may be a short capitalised acronym
		if (strpos($t,' ') !== FALSE || strlen($t) > 5) {
			if (extension_loaded('mbstring')) {
				$t = mb_convert_case($t, MB_CASE_TITLE, 'UTF-8');
			} else {
				$t = ucwords($t);
			}
		}
		return $t;
	}

	/**
	GetName:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	Returns: recorded booker-name or ''
	 */
	public function GetName(&$mod, $bookerid)
	{
		$sql = <<<EOS
SELECT COALESCE(A.name,B.name,'') AS name,B.publicid
FROM $mod->BookerTable B
LEFT JOIN $mod->AuthTable A ON B.publicid=A.publicid
WHERE B.booker_id=?
EOS;
		//TODO $utils->SafeGet()
		$r = $mod->dbHandle->GetRow($sql, [$bookerid]);
		if ($r) {
			$utils = new Utils();
			$utils->GetUserProperties($mod,$r);
		}
		if ($r) {
			return $r['name'];
		}
		return '<'.$mod->Lang('noname').'>';
	}

	/**
	SetContact:
	Sets BookerTable values, not Auther-module value(s)
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@contact: new email-address, or phone number, or array with email-address and/or phone-number
	Returns: boolean indicating success
	 */
	public function SetContact(&$mod, $bookerid, $contact)
	{
		if (is_array($contact)) {
			$fields = [];
			foreach ($contact as $val) {
				$val = trim($val);
				if (!$val || preg_match(\Booker::PATNADDRESS,$val)) {
					$fields['address=?'] = $this->cfuncs->encrypt_value($val);
				} else {
					$val = str_replace(' ','',$val);
					if (preg_match(\Booker::PATNPHONE,$val)) {
						$fields['phone=?'] = $this->cfuncs->encrypt_value($val);
					}
				}
			}
			if ($fields) {
				$sql2 = implode(',',array_keys($fields));
				$args = array_values($fields);
			} else {
				return FALSE;
			}
		} else {
			$val = trim($contact);
			if (!$val || preg_match(\Booker::PATNADDRESS,$val)) {
				$sql2 = 'address=?';
			} else {
				$val = str_replace(' ','',$val);
				if (preg_match(\Booker::PATNPHONE,$val)) {
					$sql2 = 'phone=?';
				} else {
					return FALSE;
				}
			}
			$args = [$this->cfuncs->encrypt_value($val)];
		}
//TODO $utils->SetUserProperties($mod,$bookerid,$alldata);
		if (0) {
//OR $this->afuncs->ChangeUser($oldlogin,$password,$login,$name,$address,$active=FALSE,$params=[],$check=TRUE) if relevant
		} else {
			$sql = 'UPDATE '.$mod->BookerTable.' SET '.$sql2.' WHERE booker_id=?';
			$args[] = $bookerid;
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute($sql,$args);
			return ($mod->dbHandle->Affected_Rows() > 0); //racy??
		}
	}

	/**
	GetContact:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	Returns: 2-member associative array (stored contact-address and phone), or '';
	 */
	public function GetContact(&$mod, $bookerid)
	{
		$sql = <<<EOS
SELECT COALESCE(A.address,B.address,'') AS address,B.publicid,B.phone
FROM $mod->BookerTable B
LEFT JOIN $mod->AuthTable A ON B.publicid=A.publicid
WHERE booker_id=?
EOS;
		//TODO $utils->SafeGet()
		$r = $mod->dbHandle->GetRow($sql,[$bookerid]);
		if ($r) {
			$utils = new Utils();
			$utils->GetUserProperties($mod,$r);
		}
		if ($r) {
			return ['address'=>$r['address'],'phone'=>$r['phone']];
		}
		return '';
	}

	/**
	GetKnown:
 	Check for [un-]registered user identifiable by @main and, if the user is
	unregistered or @supp is not exactly FALSE, @supp
	c.f. GetRegistered() (which calls this & GetAccepted()), GetAccepted()
	@mod: reference to current Booker module object
	@main: username, or login/account identifier
	@supp: email-address or phone-number for username, or password for account, or FALSE
	Returns: booker_id or FALSE if no match or password is wrong
	 */
	public function GetKnown(&$mod, $main, $supp)
	{
		$sql = <<<EOS
SELECT booker_id,name,address,publicid,phone
FROM $mod->BookerTable
WHERE name=? OR publicid=? ORDER BY addwhen DESC
EOS;
		//TODO $utils->SafeGet()
		$rows = $mod->dbHandle->GetArray($sql,[$main,$main]);
		if ($rows) {
//TODO reverse cryption & compare fails !??
//			$esupp = ($supp) ? $this->cfuncs->encrypt_value($supp):NULL;
			foreach ($rows as $one) {
				//TODO multibyte caseless comparison here
				if (!$one['publicid'] && strcasecmp($one['name'],$main) == 0) {
					$val = $one['address'] ? $this->cfuncs->decrypt_value($one['address']):NULL;
					$val2 = $one['phone'] ? $this->cfuncs->decrypt_value($one['phone']):NULL;
//					if ($one['address'] == $esupp || $one['phone'] == $esupp) {
					if ($val == $supp || $val2 == $supp) {
						return (int)$one['booker_id'];
					}
				} elseif ($one['publicid']) {
					if ($supp && $this->afuncs->IsKnown($main,$supp)) {
						return (int)$one['booker_id'];
					} elseif ($supp === FALSE) {
						return (int)$one['booker_id'];
					}
				}
			}
		}
		return FALSE;
	}

	/**
	GetRegistered:
	Check for registered user identifiable by @login
	@mod: reference to current Booker module object
	@login: identifier
	Returns: booker_id or FALSE
	 */
	public function GetRegistered(&$mod, $login)
	{
		if ($this->afuncs->IsKnown($login,FALSE)) {
			$bookerid = $this->GetAccepted($mod, $login);
			return $bookerid;
		}
		return FALSE;
	}

	/**
	GetAccepted:
	Check for [un-]registered user identifiable by @name and, if the user is
	registered and @passwd is not exactly FALSE, @passwd
	@mod: reference to current Booker module object
	@name: username, or login/account identifier
	@passwd: plaintext password, if @name is a login
	Returns: booker_id or FALSE
	 */
	public function GetAccepted(&$mod, $name, $passwd=FALSE)
	{
		$sql = <<<EOS
SELECT B.booker_id,COALESCE(A.name,B.name,'') AS name,B.publicid
FROM $mod->BookerTable B
LEFT JOIN $mod->AuthTable A ON B.publicid=A.publicid
WHERE (name=? OR publicid=?) AND B.active>0 ORDER BY name
EOS;
		//TODO $utils->SafeGet()
		$rows = $mod->dbHandle->GetArray($sql,[$name,$name]);
		if ($rows) {
			foreach ($rows as $one) {
				$login = $one['publicid'];
				if (!$login && $one['name'] == $name) {
					return (int)$one['booker_id'];
				} elseif ($login) {
					if ($this->afuncs->IsKnown($login,$passwd)) {
						return (int)$one['booker_id'];
					}
				}
			}
		}
		return FALSE;
	}

	/**
	GetForced:
	Check whether registered user identifiable by @login is flagged for
	 compulsory password-change
	@mod: reference to current Booker module object
	@login: identifier
	Returns: boolean
	 */
	public function GetForced(&$mod, $login)
	{
		if ($this->afuncs->IsKnown($login,FALSE)) {
			$amod = \cms_utils::get_module('Auther');
			$vfuncs = new \Auther\Validate($amod,$this->afuncs,$this->cfuncs);
			unset($amod);
			return $vfuncs->IsForced(FALSE,$login,$mod->GetPreference('authcontext',0));
		}
		return FALSE;
	}

	/**
	SetActive:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@newstate: boolean indicating the active-state to be applied
	Returns: boolean indicating success
	 */
	public function SetActive(&$mod, $bookerid, $newstate)
	{
		$newstate = ($newstate) ? 1:0;
		//TODO $utils->SafeExec()
		$mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET active=? WHERE booker_id=?',
			[$newstate,$bookerid]);
		return ($mod->dbHandle->Affected_Rows() > 0); //racy??
	}

	/**
	SetRights:
	@mod: reference to current Booker module object
	@bookerid: numeric identifier
	@rights: array of $name=>boolean
	@type: optional enumerator, to avoid lookup
	Returns: boolean indicating success
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
		$mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET type=? WHERE booker_id=?',
			[$newtype,$bookerid]);
		return ($mod->dbHandle->Affected_Rows() > 0); //racy??
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
			$ret = [];
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
			$ret = ['prepay'=>TRUE];
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
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET type=? WHERE booker_id=?',
				[$type,$bookerid]);
			return ($mod->dbHandle->Affected_Rows() > 0); //racy??
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
			if ($class == 0) {
				$class = 1; //set default
			}
			//TODO $utils->SafeExec()
			$mod->dbHandle->Execute('UPDATE '.$mod->BookerTable.' SET displayclass=? WHERE booker_id=?',
				[$class,$bookerid]);
			return ($mod->dbHandle->Affected_Rows() > 0); //racy??
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
		//TODO $utils->SafeGet()
		$r = $mod->dbHandle->GetOne('SELECT displayclass FROM '.$mod->BookerTable.' WHERE booker_id=?',
			[$bookerid]);
		if ($r) {
			return (int)$r;
		} else {
			return 1;
		}
	}

	/*
	ConformUserExtraData:
	Conform tabled user value per @params value
	@mod: reference to current Booker module
	@params: reference to parameters array
	Returns: booker identifier indicating success, or FALSE
	 */
/*	private function ConformUserExtraData(&$mod, &$params)
	{
/when updating a pending request, $params[] =
 'bkg_id' => string '42'
 'custmsg' => string '' (length=0)
 'when' => int 1474210800
 'until' => int 1474214399
 'name' => string 'Tester' (length=6)
 'comment' => string 'None' (length=4)
 'subgrpcount' => string '1' (length=1)
* /
		$sql = 'SELECT booker_id FROM '.$mod->OnceTable.' WHERE bkg_id=?';
		$bookerid = $mod->dbHandle->GetOne($sql,array($params['bkg_id']));
		if (!$bookerid) {
			return FALSE; //should never happen
		}
//TODO $utils->SetUserProperties($mod,$bookerid,array('name'=>$params['name']));
		if (0) {
//OR $this->afuncs->ChangeUser($oldlogin,$password,$login,$name,$address,$active=FALSE,$params=[],$check=TRUE) if relevant
		} else {
			$sql = 'UPDATE '.$mod->BookerTable.' SET name=? WHERE booker_id=?';
			$mod->dbHandle->Execute($sql,array($params['name'],$bookerid));
		}
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
		if (isset($params['bkg_id'])) {
			$sql = <<<EOS
UPDATE $mod->BookerTable B
JOIN $mod->OnceTable O ON B.booker_id = O.booker_id
SET B.name=?
WHERE O.bkg_id=?
EOS;
			$ret = $utils->SafeExec($sql,[$params['name'],$params['bkg_id']]);
		}

//TODO $this->afuncs->ChangeUser($oldlogin,$password,$login,$name,$address,$active=FALSE,$params=[],$check=TRUE) if relevant
//OR $utils->SetUserProperties($mod,$bookerid,$alldata); for BookerTable only

		if (isset($params['bkg_id'])) {
			$sql2 = [];
			$args = [];
			foreach (['publicid','name'] as $k) {
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

//TODO both tables $mod->OnceTable, $mod->RepeatTable
			if ($sql2) {
				$table = (empty($params['repeat'])) ? $mod->OnceTable : $mod->RepeatTable;
				$fields = implode(',',$sql2);
				$sql = <<<EOS
UPDATE $mod->BookerTable B
JOIN $table T ON B.booker_id=T.booker_id
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
