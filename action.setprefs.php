<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: setprefs
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (isset($params['cancel'])) {
	$this->Redirect($id, 'defaultadmin');
}

//maybe-missing checkboxes
if (!isset($params['pref_cleargroup'])) {
	$params['pref_cleargroup'] = 0;
}
//$params['pref_exportfile']
//$params['pref_striponexport]

if (isset($params['stylesdelete'])) {
	$fn = $params['pref_stylesfile'];
	if ($fn) {
		$fp = $config['uploads_path'];
		if ($fp && is_dir($fp)) {
			$ud = $this->GetPreference('uploadsdir', '');
			if ($ud) {
				$fp = cms_join_path($fp, $ud, $fn);
			} else {
				$fp = cms_join_path($fp, $fn);
			}
			if (is_file($fp)) {
				unlink($fp);
			}
		}
		$params['pref_stylesfile'] = '';
	}
}

$updates = preg_grep('/^pref_.*/', array_keys($params));
foreach ($updates as $k) {
	$val = $params[$k];
	$k = substr($k, 5);
	switch ($k) {
	 case 'masterpass':
		$cfuncs = new Booker\Crypter($this);
		$oldpw = $cfuncs->decrypt_preference($k);
		$val = trim($val);
		if ($oldpw != $val) {
			//re-hash all relevant data
			$pref = cms_db_prefix();
			$sql = 'SELECT booker_id,name,address,phone FROM '.$this->BookerTable;
			$rst = $db->Execute($sql);
			if ($rst) {
				$sql = 'UPDATE '.$this->BookerTable.' SET name=?,address=?,phone=? WHERE booker_id=?';
				while (!$rst->EOF) {
					$name = ($rst->fields['name']) ? $cfuncs->decrypt_value($rst->fields['name'], $oldpw) : NULL;
					$address = ($rst->fields['address']) ? $cfuncs->decrypt_value($rst->fields['address'], $oldpw) : NULL;
					$phone = ($rst->fields['phone']) ? $cfuncs->decrypt_value($rst->fields['phone'], $oldpw) : NULL;
					if ($newpw) {
						if ($name) {
							$name = $cfuncs->encrypt_value($name, $newpw);
						}
						if ($address) {
							$address = $cfuncs->encrypt_value($address, $newpw);
						}
						if ($phone) {
							$phone = $cfuncs->encrypt_value($phone, $newpw);
						}
					}
					$db->Execute($sql, [$name, $address, $phone, $rst->fields['booker_id']]);
					if (!$rst->MoveNext()) {
						break;
					}
				}
				$rst->Close();
			}
			$sql = 'SELECT pay_id,original,latest FROM '.$this->CreditTable;
			$rst = $db->Execute($sql);
			if ($rst) {
				$sql = 'UPDATE '.$this->CreditTable.' SET original=?,latest=? WHERE pay_id=?';
				while (!$rst->EOF) {
					$orig = ($rst->fields['original']) ? $cfuncs->uncloak_value($rst->fields['original'], $oldpw) : NULL;
					$late = ($rst->fields['latest']) ? $cfuncs->uncloak_value($rst->fields['latest'], $oldpw) : NULL;
					if ($newpw) {
						if ($orig) {
							$orig = $cfuncs->cloak_value($orig, 16, $newpw);
						}
						if ($late) {
							$late = $cfuncs->cloak_value($late, 16, $newpw);
						}
					}
					$db->Execute($sql, [$orig, $late, $rst->fields['pay_id']]);
					if (!$rst->MoveNext()) {
						break;
					}
				}
				$rst->Close();
			}
			//TODO CHECK $pref.'module_bkr_cache' 'value' field
		}
		$cfuncs->encrypt_preference($k, $val);
		break;
	 case 'cleargroup':
		$this->SetPreference($k, (int)$val);
		break;
	 case 'timezone':
		if ($val == FALSE) {
			$val = 'UTC';
		}
		$this->SetPreference($k, trim($val));
		break;
	 case 'dateformat':
		if ($val == FALSE) {
			$val = 'j M Y';
		}
		$this->SetPreference($k, trim($val));
		break;
	 case 'timeformat':
		if ($val == FALSE) {
			$val = 'G:i';
		}
		$this->SetPreference($k, trim($val));
		break;
	 case 'smspattern':
		if ($val == FALSE) {
			$val = '^\d{6,15}$';
		}
		$this->SetPreference($k, trim($val));
		break;
/*	 case 'smsprefix':
		if ($val == FALSE)
			$val = ; TODO func(timezone)
		$this->SetPreference($k,trim($val));
		break;
*/
	 case 'authcontext':
		$oldval = $this->GetPreference($k, 0);
		$val += 0;
		if ($val != $oldval) {
			$all = []; //TODO get auther-id's of all registered users
			$funcs = new Auther\Utils();
			$funcs->MoveContextUsers($all, $oldval, $val);
			$this->SetPreference($k, $val);
		}
		break;
	 default:
		$this->SetPreference($k, trim($val));
	}
}

$this->Redirect($id, 'defaultadmin', '', ['active_tab' => 'settings']);
