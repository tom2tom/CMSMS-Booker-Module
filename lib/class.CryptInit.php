<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: CryptInit
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class CryptInit Extends Crypter
{
	/**
	constructor:
	@mod: reference to current module object
	@method: optional openssl cipher type to use, default 'BF-CBC'
	@stretches: optional number of extension-rounds to apply, default 8192
	*/
	public function __construct(&$mod, $method='BF-CBC', $stretches=parent::STRETCHES)
	{
		parent::__construct($mod, $method, $stretches);
	}

	/**
	init_crypt:
	Must be called ONCE (during installation and/or after any localisation change)
	before any hash or preference-crypt
	@s: optional 'localisation' string, default ''
	*/
	final public function init_crypt($s='')
	{
		if (!$s) {
			$s = cmsms()->GetConfig()['ssl_url'].$this->mod->GetModulePath().parent::SKEY;
		}
		$value = str_shuffle(openssl_random_pseudo_bytes(9).microtime(TRUE));
		$value = $this->encrypt($value,
			hash_hmac('sha256', $s, parent::SKEY));
		$this->mod->SetPreference(hash('tiger192,3', $s),
			base64_encode($value));
	}
}
