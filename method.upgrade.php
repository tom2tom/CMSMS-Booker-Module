<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: upgrade
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!$this->_CheckAccess('admin')) return;

$dict = NewDataDictionary($db);
$pref = \cms_db_prefix();

switch ($oldversion) {
case '0.6':
	$t = 'nQCeESKBr99A';
	$this->SetPreference($t, hash('sha256', $t.microtime()));
	$pw = $this->GetPreference('masterpass');
	if ($pw) {
		$s = base64_decode(substr($pw,5));
		$pw = substr($s,5);
	}
	$cfuncs = new Booker\Crypter($this);
	$cfuncs->encrypt_preference('masterpass',$pw);

/*	$sqlarray = $dict->AddColumnSQL($this->BookerTable, 'auth_id I(4) DEFAULT 0');
	$dict->ExecuteSqlArray($sqlarray);
	$sqlarray = $dict->AlterColumnSQL($this->BookerTable, 'name B');
	$dict->ExecuteSqlArray($sqlarray);
	$sqlarray = $dict->AddColumnSQL($this->BookerTable, 'namehash B');
	$dict->ExecuteSqlArray($sqlarray);

	$sql = 'SELECT booker_id,name,publicid FROM '.$this->BookerTable;
	$rst = $db->Execute($sql);
	if ($rst) {
//ibid	$mpw = $cfuncs->decrypt_preference('masterpass');
		$amod = cms_utils::get_module('Auther');
		if ($amod) {
			$afuncs = new \Auther\Auth($amod,$this->GetPreference('authcontext',0));
			unset($amod);
		} else {
			$afuncs = NULL;
		}
		if (!function_exists('password_hash')) {
			include __DIR__.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'password.php';
		}
		$sql1 = 'UPDATE '.$pref.'module_bkr_bookers SET auth_id=?,namehash=NULL,name=NULL WHERE booker_id=?';
		$sql2 = 'UPDATE '.$pref.'module_bkr_bookers SET auth_id=0,namehash=?,name=? WHERE booker_id=?';
		while (!$rst->EOF) {
			if ($rst->fields['publicid'] && $afuncs) {
				$bid = $afuncs->GetUserID($rst->fields['publicid']);
				$db->Execute($sql1, [$bid, $rst->fields['booker_id']]);
			} else {
				$name = $cfuncs->encrypt_value($rst->fields['name'], $mpw);
				$hash = password_hash($rst->fields['name'], PASSWORD_DEFDAULT);
				$db->Execute($sql2, [$name, $hash, $rst->fields['booker_id']]);
			}
			if (!$rst->MoveNext()) {
				break;
			}
		}
		$rst->Close();
	}

	$sqlarray = $dict->DropColumnSQL($this->BookerTable, 'publicid');
	$dict->ExecuteSqlArray($sqlarray);
*/
/*
$sql = <<<EOS
SELECT booker_id,name FROM $this->BookerTable WHERE name IS NOT NULL
EOS;
$data = $db->GetArray($sql);
$sql = <<<EOS
UPDATE $this->BookerTable SET namehash=? WHERE booker_id=?
EOS;
$cfuncs = new Booker\Crypter($this);
$mpw = $cfuncs->decrypt_preference('masterpass');
foreach ($data as $one) {
	$name = $cfuncs->decrypt_value($one['name'], $mpw);
	$hash = password_hash($name, PASSWORD_DEFAULT);
	$db->Execute($sql,[$hash,$one['booker_id']]);
}
*/
}
