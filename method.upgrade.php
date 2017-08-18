<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: upgrade
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!$this->_CheckAccess('admin')) {
	return;
}

function name_rehash(&$mod, &$cfuncs)
{
	global $db;
	$sql = 'SELECT booker_id,name FROM '.$mod->BookerTable.' WHERE auth_id=0';
	$rst = $db->Execute($sql);
	if ($rst) {
		$sql = 'UPDATE '.$mod->BookerTable.' SET namehash=? WHERE booker_id=?';
		$pw = $cfuncs->decrypt_preference(Booker\Crypter::MKEY);
		while (!$rst->EOF) {
			$name = $cfuncs->uncloak_value($rst->fields['name'], $pw);
			$hash = ($name) ? $cfuncs->hash_value($name, $pw) : NULL;
			$db->Execute($sql, [$hash, $rst->fields['booker_id']]);
			if (!$rst->MoveNext()) {
				break;
			}
		}
		$rst->Close();
	}
}

$dict = NewDataDictionary($db);

switch ($oldversion) {
/* case '0.6':
	$t = 'nQCeESKBr99A';
	$this->SetPreference($t, hash('sha256', $t.microtime()));
	$pw = $this->GetPreference('masterpass');
	if ($pw) {
		$s = base64_decode(substr($pw,5));
		$pw = substr($s,5);
	}
	$cfuncs = new Booker\Crypter($this);
	$cfuncs->encrypt_preference('masterpass');
*/
case '0.6':
	//TODO tailored blob-sizes c.f. method.install
	$sqlarray = $dict->AlterColumnSQL($this->BookerTable, 'name B');
	$dict->ExecuteSqlArray($sqlarray);
	$sqlarray = $dict->AddColumnSQL($this->BookerTable, 'auth_id I(4) DEFAULT 0');
	$dict->ExecuteSqlArray($sqlarray);
	$sqlarray = $dict->AddColumnSQL($this->BookerTable, 'namehash B');
	$dict->ExecuteSqlArray($sqlarray);

	$cfuncs = new Booker\CryptInit($this);
	$t = $this->GetPreference('nQCeESKBr99A');
	if ($t) {
		$val = hash('crc32b', $t.$config['ssl_url'].$this->GetModulePath());
		$this->RemovePreference('nQCeESKBr99A');

		$key = 'masterpass';
		$s = base64_decode($this->GetPreference($key));
		$pw = $cfuncs->decrypt($s, $val);
		if (!$pw) {
			$pw = base64_decode('V09PIEhPTyB0aGlzIGlzIE5FVw==');
		}
		$this->RemovePreference($key);
		$cfuncs->init_crypt();
		$cfuncs->encrypt_preference(Booker\Crypter::MKEY, $pw);

/*		foreach ([] as $key) {
			$s = base64_decode($this->GetPreference($key));
			$t = $cfuncs->decrypt($s, $val);
			$this->RemovePreference($key);
			$cfuncs->encrypt_preference($key, $t);
		}
*/
	} else {
		$pw = $cfuncs->decrypt_preference(Booker\Crypter::MKEY);
	}

	$sql = 'SELECT publicid FROM '.$this->BookerTable;
	$rst = $db->Execute($sql);
	if ($rst && !$rst->EOF) {
		$rst->Close();
		$sql = 'SELECT booker_id,publicid,name,address,phone FROM '.$this->BookerTable;
		$rst = $db->Execute($sql);
		if ($rst) {
			$afuncs = new Auther\Auth(NULL, $this->GetPreference('authcontext', 0));
			if ($afuncs) {
				$sql1 = 'UPDATE '.$this->BookerTable.' SET auth_id=?,namehash=NULL,name=NULL WHERE booker_id=?';
				$sql2 = 'DELETE FROM '.$this->BookerTable.' WHERE booker_id=?';
				$sql3 = 'UPDATE '.$this->BookerTable.' SET auth_id=0,namehash=?,name=?,address=?,phone=? WHERE booker_id=?';

				while (!$rst->EOF) {
					if ($rst->fields['publicid']) {
						$aid = $afuncs->GetUserID($rst->fields['publicid']);
						if ($aid) {
							$db->Execute($sql1, [$aid, $rst->fields['booker_id']]);
						} else {
							$db->Execute($sql2, [$rst->fields['booker_id']]);
						}
					} else {
						$t = $rst->fields['name'];
						if ($t) {
							$name = $cfuncs->decrypt_value($t, $pw);
							if ($name) {
								$t = $name;
							}
						}
						$hash = ($t) ? $cfuncs->hash_value($t, $pw) : NULL;
						$name = ($t) ? $cfuncs->cloak_value($t, 0, $pw) : NULL;
						$t = $rst->fields['address'];
						if ($t) {
							$address = $cfuncs->decrypt_value($t, $pw);
							if ($address) {
								$t = $address;
							}
						}
						$address = ($t) ? $cfuncs->cloak_value($t, 0, $pw) : NULL;
						$t = $rst->fields['phone'];
						if ($t) {
							$phone = $cfuncs->decrypt_value($t, $pw);
							if ($phone) {
								$t = $phone;
							}
						}
						$phone = ($t) ? $cfuncs->cloak_value($t, 16, $pw) : NULL;

						$db->Execute($sql3, [$hash, $name, $address, $phone, $rst->fields['booker_id']]);
					}
					if (!$rst->MoveNext()) {
						break;
					}
				}
			}
			$rst->Close();
		}
		$sqlarray = $dict->DropColumnSQL($this->BookerTable, 'publicid');
		$dict->ExecuteSqlArray($sqlarray);
	} else {
		$sql = 'SELECT booker_id,name,address,phone FROM '.$this->BookerTable.' WHERE auth_id=0';
		$rst = $db->Execute($sql);
		if ($rst) {
			$sql3 = 'UPDATE '.$this->BookerTable.' SET name=?,address=?,phone=? WHERE booker_id=?';
			while (!$rst->EOF) {
				$t = $rst->fields['name'];
				if ($t) {
					$name = $cfuncs->decrypt_value($t, $pw);
					if ($name) {
						$t = $name;
					}
				}
				$name = ($t) ? $cfuncs->cloak_value($t, 0, $pw) : NULL;
				$t = $rst->fields['address'];
				if ($t) {
					$address = $cfuncs->decrypt_value($t, $pw);
					if ($address) {
						$t = $address;
					}
				}
				$address = ($t) ? $cfuncs->cloak_value($t, 0, $pw) : NULL;
				$t = $rst->fields['phone'];
				if ($t) {
					$phone = $cfuncs->decrypt_value($t, $pw);
					if ($phone) {
						$t = $phone;
					}
				}
				$phone = ($t) ? $cfuncs->cloak_value($t, 16, $pw) : NULL;

				$db->Execute($sql3, [$name, $address, $phone, $rst->fields['booker_id']]);

				if (!$rst->MoveNext()) {
					break;
				}
			}
			$rst->Close();
		}
	}
// case '':
//	name_rehash($this,$cfuncs);
}
