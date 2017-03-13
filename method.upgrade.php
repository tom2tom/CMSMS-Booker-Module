<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: upgrade
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!$this->_CheckAccess('admin')) return;

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
}
