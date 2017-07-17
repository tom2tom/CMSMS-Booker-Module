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
		$s = parent::encrypt($value,
			hash_hmac('sha1', $this->mod->GetPreference('nQCeESKBr99A').$this->custom, $key));
		$this->mod->SetPreference(
			hash('sha1', $key.$this->custom), base64_encode($s));
	}

	/**
	decrypt_preference:
	@key: module-preferences key
	Returns: plaintext string, or FALSE
	*/
	public function decrypt_preference($key)
	{
		$s = base64_decode($this->mod->GetPreference(
			hash('sha1', $key.$this->custom)));
		return parent::decrypt($s,
			hash_hmac('sha1', $this->mod->GetPreference('nQCeESKBr99A').$this->custom, $key));
	}

	/**
	encrypt_value:
	@value: value to encrypted, may be empty string
	@pw: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether to base64_encode the encrypted value, default FALSE
	Returns: encrypted @value, or just @value if it's empty or if password is empty
	*/
	public function encrypt_value($value, $pw=FALSE, $based=FALSE)
	{
		$value .= '';
		if ($value) {
			if (!$pw) {
				$pw = self::decrypt_preference('masterpass');
			}
			if ($pw) {
				$value = parent::encrypt($value, $pw);
				if ($based) {
					$value = base64_encode($value);
//				} else {
//					$value = str_replace('\'', '\\\'', $value); //facilitate db-field storage
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
	Returns: decrypted @value, or just @value if it's empty or if password is empty
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
//				} else {
//					$value = str_replace('\\\'', '\'', $value);
				}
				$value = parent::decrypt($value, $pw);
			}
		}
		return $value;
	}

	/**
	cloak_value:
	@value: value to encrypted, may be empty string
	@minsize: optional minimum byte-length of cloak, default FALSE (hence @value-length + 8)
	@pw: optional password string, default FALSE (hence use the module-default)
	@based: optional boolean, whether to base64_encode the encrypted value, default FALSE
	Returns: encrypted @value
	*/
	public function cloak_value($value, $minsize=FALSE, $pw=FALSE, $based=FALSE)
	{
		$value .= '';
		$lv = strlen($value);
		$lc = $lv + 8;
		if ($minsize != FALSE && $minsize > $lc) {
			$lc = $minsize;
		}
		try {
			include __DIR__.DIRECTORY_SEPARATOR.'random'.DIRECTORY_SEPARATOR.'random.php';
			$cloak = random_bytes($lc);
		} catch (\Error $e) {
			//required, if you do not need to do anything just rethrow
			throw $e;
		} catch (\Exception $e) {
			$strong = TRUE;
			$cloak = openssl_random_pseudo_bytes($lc, $strong);
		}
		$c = chr(0);
		$p = 1;
		for ($i = 0; $i < $lc; $i++) {
			if ($cloak[$i] === $c) {
				$cloak[$i] = chr($p);
				$p++;
			}
		}
		$p = mt_rand(0, $lc - $lv - 3);
		$cloak[$p++] = $c;
		for ($i = 0; $i < $lv; $i++) {
			$cloak[$p++] = $value[$i];
		}
		$cloak[$p] = $c;
		return self::encrypt_value($cloak, $pw, $based);
	}

	/**
	uncloak_value:
	@value: string to decrypted, may be empty
	@pw: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether @value is base64_encoded, default FALSE
	Returns: decrypted @value (string), or FALSE
	*/
	public function uncloak_value($value, $pw=FALSE, $based=FALSE)
	{
		$cloak = self::decrypt_value($value, $pw, $based);
		if ($cloak) {
			$c = chr(0);
			$p = strpos($cloak, $c);
			if ($p !== FALSE) {
				$p++;
				$i = strpos($cloak, $c, $p);
				if ($i !== FALSE) {
					return substr($cloak, $p, $i - $p);
				}
			}
		}
		return FALSE;
	}
}
