<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Crypter
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Crypter Extends Encryption
{
	const STRETCHES = 8192;
	protected $mod;
	protected $custom;

	/*
	constructor:
	@mod: reference to current module object
	@method: optional openssl cipher type to use, default 'BF-CBC'
	@stretches: optional number of extension-rounds to apply, default 8192
	*/
	public function __construct(&$mod, $method='BF-CBC', $stretches=self::STRETCHES)
	{
		$this->mod = $mod;
		$this->custom = \cmsms()->GetConfig()['ssl_url'].$mod->GetModulePath(); //site&module-dependent
		parent::__construct($method, 'default', $stretches);
	}

	/**
	encrypt_preference:
	@value: value to be stored, normally a string
	@key: module-preferences key
	*/
	public function encrypt_preference($key, $value)
	{
		$pw = hash('crc32b', $this->mod->GetPreference('nQCeESKBr99A').$this->custom);
		$s = parent::encrypt($value, $pw);
		$this->mod->SetPreference($key, base64_encode($s));
	}

	/**
	decrypt_preference:
	@key: module-preferences key
	Returns: plaintext string, or FALSE
	*/
	public function decrypt_preference($key)
	{
		$s = base64_decode($this->mod->GetPreference($key));
		$pw = hash('crc32b', $this->mod->GetPreference('nQCeESKBr99A').$this->custom);
		return parent::decrypt($s, $pw);
	}

	/**
	encrypt_value:
	@value: string to encrypted, may be empty
	@pw: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether to base64_encode the encrypted value, default FALSE
	Returns: encrypted @value, or just @value if it's empty
	*/
	public function encrypt_value($value, $pw=FALSE, $based=FALSE)
	{
		if ($value) {
			if (!$pw) {
				$pw = self::decrypt_preference('masterpass');
			}
			if ($pw) {
				$value = parent::encrypt($value, $pw);
				if ($based) {
					$value = base64_encode($value);
				}
			}
		}
		return $value;
	}

	/**
	decrypt_value:
	@value: string to decrypted, may be empty
	@pw: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether @value is base64_encoded, default FALSE
	Returns: decrypted @value, or just @value if it's empty
	*/
	public function decrypt_value($value, $pw=FALSE, $based=FALSE)
	{
		if ($value) {
			if (!$pw) {
				$pw = self::decrypt_preference('masterpass');
			}
			if ($pw) {
				if ($based) {
					$value = base64_decode($value);
				}
				$value = parent::decrypt($value, $pw);
			}
		}
		return $value;
	}
}
