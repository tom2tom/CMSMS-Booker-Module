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

/*
	$dict = NewDataDictionary($db);

//	$sqlarray = $dict->AddColumnSQL($this->BookerTable, 'auth_id I(4) DEFAULT 0');
//	$dict->ExecuteSqlArray($sqlarray);
//	$sqlarray = ['ALTER TABLE '.$this->BookerTable.' CHANGE auth_id auth_id int(4) NULL AFTER booker_id'];
//	$dict->ExecuteSqlArray($sqlarray,FALSE);
//	$sqlarray = $dict->AddColumnSQL($this->BookerTable, 'namehash B');
//	$dict->ExecuteSqlArray($sqlarray);
//	$sqlarray = ['ALTER TABLE '.$this->BookerTable.' CHANGE namehash namehash longblob NULL AFTER auth_id'];
//	$dict->ExecuteSqlArray($sqlarray,FALSE);
	$sqlarray = $dict->AlterColumnSQL($this->BookerTable, 'name B');
	$dict->ExecuteSqlArray($sqlarray);

	$cfuncs = new Booker\Crypter($this);
	$mpw = $this->GetPreference('masterpass');
	if ($mpw) {
		$mpw = $cfuncs->olddecrypt_preference('masterpass');
		$cfuncs->encrypt_preference('masterpass',$mpw);
		$this->RemovePreference('masterpass');
	} else {
		$mpw = $cfuncs->decrypt_preference('masterpass');
		if (!$mpw) {
			$mpw = 'Suck it up, crackers!';
			$cfuncs->encrypt_preference('masterpass',$mpw);
		}
	}

	$sql = 'SELECT booker_id,publicid,name FROM '.$this->BookerTable;
	$rst = $db->Execute($sql);
	if ($rst) {
		$afuncs = new Auther\Auth(NULL,$this->GetPreference('authcontext',0));
		if ($afuncs) {
			$sql1 = 'UPDATE '.$this->BookerTable.' SET auth_id=?,namehash=NULL,name=NULL WHERE booker_id=?';
			$sql2 = 'DELETE FROM '.$this->BookerTable.' WHERE booker_id=?';
			$sql3 = 'UPDATE '.$this->BookerTable.' SET auth_id=0,namehash=?,name=? WHERE booker_id=?';

			while (!$rst->EOF) {
				if ($rst->fields['publicid']) {
					$aid = $afuncs->GetUserID($rst->fields['publicid']);
					if ($aid) {
						$db->Execute($sql1,[$aid,$rst->fields['booker_id']]);
					} else {
						$db->Execute($sql2,[$rst->fields['booker_id']]);
					}
				} else {
					$hash = $cfuncs->hash_value($rst->fields['name'],$mpw);
					$name = $cfuncs->encrypt_value($rst->fields['name'],$mpw);
					$db->Execute($sql3,[$hash,$name,$rst->fields['booker_id']]);
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
	$hash = $cfuncs->hash_value($name, $mpw);
	$db->Execute($sql,[$hash,$one['booker_id']]);
}
*/
}
