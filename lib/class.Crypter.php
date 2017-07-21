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
	const MKEY = 'masterpass';
	const SKEY = 'prefsalt';
	const STRETCHES = 8192;
	protected $mod;

	/**
	constructor:
	@mod: reference to current module object
	@method: optional openssl cipher type to use, default 'BF-CBC'
	@stretches: optional number of extension-rounds to apply, default 8192
	*/
	public function __construct(&$mod, $method='BF-CBC', $stretches=self::STRETCHES)
	{
		$this->mod = $mod;
		parent::__construct($method, 'default', $stretches);
	}

	/*
	localise:
	Get constant site/host-specific string.
	All hashes and crypted preferences depend on this
	*/
	protected function localise()
	{
		return cmsms()->GetConfig()['ssl_url'].$this->mod->GetModulePath();
	}

	/**
	init_crypt:
	Must be called ONCE (during installation and/or after any localisation change)
	before any hash or preference-crypt
	@s: optional 'localisation' string, default ''
	*/
	public function init_crypt($s='')
	{
		if (!$s) {
			$s = $this->localise().self::SKEY;
		}
		$value = str_shuffle(openssl_random_pseudo_bytes(9).microtime(TRUE));
		$value = parent::encrypt($value,
			hash_hmac('sha256', $s, self::SKEY));
		$this->mod->SetPreference(hash('tiger192,3', $s),
			base64_encode($value));
	}

	/**
	encrypt_preference:
	@value: value to be stored, normally a string
	@key: module-preferences key
	@s: optional 'localisation' string, default ''
	*/
	public function encrypt_preference($key, $value, $s='')
	{
		if (!$s) {
			$s = $this->localise();
		}
		$value = parent::encrypt(''.$value,
			hash_hmac('sha256', $s.$this->decrypt_preference(self::SKEY), $key));
		$this->mod->SetPreference(hash('tiger192,3', $s.$key),
			base64_encode($value));
	}

	/**
	decrypt_preference:
	@key: module-preferences key
	@s: optional 'localisation' string, default ''
	Returns: plaintext string, or FALSE
	*/
	public function decrypt_preference($key, $s='')
	{
		if (!$s) {
			$s = $this->localise();
		}
		if ($key != self::SKEY) {
			$value = base64_decode(
				$this->mod->GetPreference(hash('tiger192,3', $s.self::SKEY)));
			$p = parent::decrypt($value,
				hash_hmac('sha256', $s.self::SKEY, self::SKEY));
		} else {
			$p = $key;
		}
		$value = base64_decode(
			$this->mod->GetPreference(hash('tiger192,3', $s.$key)));
		return parent::decrypt($value,
			hash_hmac('sha256', $s.$p, $key));
	}

	/**
	hash_value:
	@value: value to be hashed, may be empty string
	@pw: optional password string, default FALSE (meaning use the module-default)
	@raw: optional boolean, whether to return raw binary data, default TRUE
	Returns: hashed @value, or just @value if it's empty or if password is empty
	*/
	public function hash_value($value, $pw=FALSE, $raw=TRUE)
	{
		if ($value) {
			if (!$pw) {
				$pw = self::decrypt_preference(self::MKEY);
			}
			if ($pw) {
				$key = $this->extendKey('sha512', $pw,
					$this->decrypt_preference(self::SKEY), $this->rounds,
					$this->getOpenSSLKeysize() * 2);
				$s = hash_hmac('sha512', ''.$value, $key, $raw);
				if ($raw) {
					return str_replace('\'', '\\\'', $s);
				}
				return $s;
			}
		}
		return $value;
	}

	/**
	encrypt_value:
	@value: value to encrypted, may be empty string
	@pw: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether to base64_encode the encrypted value, default FALSE
	@escaped: optional boolean, whether to escape single-quote chars in the (raw) encrypted value, default FALSE
	Returns: encrypted @value, or just @value if it's empty or if password is empty
	*/
	public function encrypt_value($value, $pw=FALSE, $based=FALSE, $escaped=FALSE)
	{
		if ($value) {
			if (!$pw) {
				$pw = self::decrypt_preference(self::MKEY);
			}
			if ($pw) {
				$value = parent::encrypt(''.$value, $pw);
				if ($based) {
					$value = base64_encode($value);
				} elseif ($escaped) {
					$value = str_replace('\'', '\\\'', $value); //facilitate db-field storage
				}
			}
		}
		return $value;
	}

	/**
	decrypt_value:
	@value: string to be decrypted, may be empty
	@pw: optional password string, default FALSE (meaning use the module-default)
	@based: optional boolean, whether @value is base64_encoded, default FALSE
	@escaped: optional boolean, whether single-quote chars in (raw) @value have been escaped, default FALSE
	Returns: decrypted @value, or just @value if it's empty or if password is empty
	*/
	public function decrypt_value($value, $pw=FALSE, $based=FALSE, $escaped=FALSE)
	{
		if ($value) {
			if (!$pw) {
				$pw = self::decrypt_preference(self::MKEY);
			}
			if ($pw) {
				if ($based) {
					$value = base64_decode($value);
				} elseif ($escaped) {
					$value = str_replace('\\\'', '\'', $value);
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
		$value = ''.$value;
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
