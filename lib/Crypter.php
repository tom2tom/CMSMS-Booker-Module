<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Crypter
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Crypter
{
	const MODNAME = 'Booker';
	private $mod;

	public function __construct(&$mod=NULL)
	{
		if ($mod)
			$this->mod = $mod;
		else
			$this->mod = cms_utils::get_module(self::MODNAME);
	}

	public function fusc($str)
	{
		if ($str) {
			$s = substr(base64_encode(md5(microtime())),0,5);
			return $s.base64_encode($s.$str);
		}
		return '';
	}

	public function unfusc($str)
	{
		if ($str) {
			$s = base64_decode(substr($str,5));
			return substr($s,5);
		}
		return '';
	}

	public function encrypt_value($value, $passwd=FALSE)
	{
		if ($value) {
			if ($this->mod->havemcrypt) {
				if (!$passwd) {
					$passwd = $this->mod->GetPreference('pref_masterpass');
					if ($passwd)
						$passwd = self::unfusc($passwd);
				}
				if ($passwd) {
					$e = new Encryption(\MCRYPT_BLOWFISH,\MCRYPT_MODE_CBC,10);
					$value = $e->encrypt($value,$passwd);
				}
			}
		}
		return $value;
	}

	public function decrypt_value($value, $passwd=FALSE)
	{
		if ($value) {
			if ($this->mod->havemcrypt) {
				if (!$passwd) {
					$passwd = $this->mod->GetPreference('pref_masterpass');
					if ($passwd)
						$passwd = self::unfusc($passwd);
				}
				if ($passwd) {
					$e = new Encryption(\MCRYPT_BLOWFISH,\MCRYPT_MODE_CBC,10);
					$value = $e->decrypt($value,$passwd);
				}
			}
		}
		return $value;
	}
}
