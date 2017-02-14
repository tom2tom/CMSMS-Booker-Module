<?php
/**
 * A class to handle secure encryption and decryption of arbitrary data
 *
 * Note that this is not just straight encryption.  It also has a few other
 *  features to make the encrypted data far more secure.  Note that any other
 *  implementations used to decrypt data will have to do the same exact
 *  operations.
 *
 * Security Benefits:
 *  Uses Key stretching
 *  Hides the Initialization Vector
 *  Does HMAC verification of source data
 *
 * See http://stackoverflow.com/questions/5089841/two-way-encryption-i-need-to-store-passwords-that-can-be-retrieved
 *
 * Usage example:
 * $e = new Encryption('BF-CBC'); //aka blowfish
 * $encryptedData = $e->encrypt($data,$key);
 * Then, to decrypt:
 * $e = new Encryption('BF-CBC');
 * $data = $e->decrypt($encryptedData,$key);
 *
 * Requires:
 * PHP 5.4+
 * hash extension
 * openssl extension
 * use of an openssl cipher with blocksize <= 2040 bits
 */
namespace Booker;

final class Encryption
{
	/*
	Maximum value of $rounds which will be exponentiated to 2**$rounds
	*/
	const POWERMAX = 31;

	/**
	 * @var string $method the openssl cipher method to use for this instance
	 */
	protected $method;
	/**
	 * @var string $method the hash cipher method to use for this instance
	 */
	protected $hasher;
	/**
	 * @var int $rounds the number of rounds to feed into PBKDF2 for key generation
	 * This can be 0 (in which case 2 will be applied) or 1..POWERMAX which will
	 * be exponentiated, or any explicit number > POWERMAX which will be rounded
	 * up to a power of 2
	 */
	protected $rounds;

	/**
	 * Constructor
	 *
	 * @param string $method the openssl cipher type to use for this instance
	 *  see http://php.net/manual/en/function.openssl-get-cipher-methods.php for examples
	 * @param string $hasher the algorithm to use for HMAC hashing, default 'sha256', may actually be 'default'
	 * @param int	 $rounds the number of extension-rounds to apply to the key, default = 1024
	 */
	public function __construct($method, $hasher='sha256', $rounds=10)
	{
		$this->method = $method;
		if ($hasher == 'default') {
			$hasher = 'sha256';
		}
		$this->hasher = $hasher;
		if ($rounds > self::POWERMAX) {
			$rounds = ceil($rounds / 100) * 100;
		} elseif ($rounds > 0) {
			$rounds = 1 << $rounds; //exponential
		} else {
			$rounds = 2; //quickie !
		}
		$this->rounds = $rounds;
	}

	/**
	 * Encrypt the supplied data using the supplied key
	 *
	 * @param string $data the data to encrypt
	 * @param string $key  the key to encrypt with
	 *
	 * @returns string the encrypted data
	 */
	public function encrypt($data, $key)
	{
		//salt size - see https://www.owasp.org/index.php/Password_Storage_Cheat_Sheet
		$salt = openssl_random_pseudo_bytes(64);
		list($cipherKey, $macKey, $iv) = $this->getKeys($salt, $key);

		$data = $this->pad($data);

		$enc = openssl_encrypt($data, $this->method, $cipherKey, OPENSSL_RAW_DATA, $iv);

		$mac = hash_hmac($this->hasher, $enc, $macKey, TRUE);
		return $salt . $enc . $mac;
	}

	/**
	 * Decrypt the data with the provided key
	 *
	 * @param string $data The encrypted datat to decrypt
	 * @param string $key  The key to use for decryption
	 *
	 * @returns string|FALSE The returned string if decryption is successful
	 *						   FALSE if it is not
	 */
	public function decrypt($data, $key)
	{
		$salt = $this->byte_substr($data, 0, 64);
		$enc = $this->byte_substr($data, 64, -32);
		$mac = $this->byte_substr($data, -32);

		list($cipherKey, $macKey, $iv) = $this->getKeys($salt, $key);

		if ($mac !== hash_hmac($this->hasher, $enc, $macKey, TRUE)) {
			return FALSE;
		}

		$dec = openssl_decrypt($enc, $this->method, $cipherKey, OPENSSL_RAW_DATA, $iv);

		$data = $this->unpad($dec);

		return $data;
	}

	/*
	 * Generates a set of keys given a random salt and a master key
	 *
	 * @param string $salt A random string to change the keys each encryption
	 * @param string $key  The supplied key to encrypt with
	 *
	 * @returns array of keys [cipher key, mac key, IV]
	 */
	protected function getKeys($salt, $key)
	{
		$ivSize = openssl_cipher_iv_length($this->method);
		$keySize = $this->getOpenSSLKeysize();
		$length = 2 * $keySize + $ivSize;

		$key = $this->extendKey($this->hasher, $key, $salt, $this->rounds, $length); //TODO stretch replacement

		$cipherKey = $this->byte_substr($key, 0, $keySize);
		$macKey = $this->byte_substr($key, $keySize, $keySize);
		$iv = $this->byte_substr($key, 2 * $keySize);
		return [$cipherKey, $macKey, $iv];
	}

	/*
	 * https://www.keylength.com/en/4 recommends for 2016-2030 >= 256 bits
	 * http://etutorials.org/Programming/secure+programming/Chapter+5.+Symmetric+Encryption/5.18+Using+Variable+Key-Length+Ciphers+in+OpenSSL
	 * AES 128, 192, or 256 bits
	 * Blowfish up to 256 bits in 8-multiples
	 */
	protected function getOpenSSLKeysize()
	{
		$limit = 32; //openSSL keylength byte-limit
		if (preg_match('/-(\d{3})/', $this->method, $matches) === 1) {
			return max($limit, $matches[1]/8);
		} elseif (strpos($this->method,'CAST5') === 0) {
			return 16;
		} else {
			return $limit;
		}
	}

/* TODO replacement or alternate key-extender with later algorithm e.g.
	protected function hash_bcrypt($key, $salt, $rounds, $length)
	{
		if ($rounds > 31) {
			$rounds = ((log($rounds,2)-0.0001)|0) + 1; //ceil-equivalent
		}
		$rounds = 1 << $rounds;
		if ($rounds < BCRYPT_MINROUNDS) {
			throw new Exception('Invalid rounds');
		}

		$state = blowfish.initstate()

		$csalt = explode('', $salt); //TODO length ok?

		$ckey = explode('', $key);
		foreach ($ckey as &$ch) {
			$ch = ord($ch);
		}
		unset($ch);

		// Setup S-Boxes and subkeys
		blowfish.expandstate($state, $csalt, $ckey)

		for ($r = 0; $r < $rounds; $r++) {
		    blowfish.expand0state($state, $ckey);
		    blowfish.expand0state($state, $csalt);
		}
		return $this->byte_substr(FUNC($state)), 0, $length);
	}
*/
	/*
	 * Stretch the key and, more-importantly, the time needed to attack
	 * the key, using (approximately) the PBKDF2 algorithm
	 * @see http://en.wikipedia.org/wiki/PBKDF2
	 *
	 * @param string $algo The algorithm to use
	 * @param string $key	 The key to stretch
	 * @param string $salt A random salt
	 * @param int	$rounds  The number of rounds to derive
	 * @param int	$length  The length of the output key
	 *
	 * @returns string, the derived key
	 */
	protected function extendKey($algo, $key, $salt, $rounds, $length)
	{
		$tmp = hash_hmac($algo, $salt . pack('N', 1), $key, TRUE);
		$res = $tmp;
		for ($i = 1; $i < $rounds; $i++) {
			$tmp = hash_hmac($algo, $tmp, $key, TRUE);
			$res ^= $tmp;
		}

		$times = ceil($length / $this->bytelen($tmp));
		for ($i = 1; $i < $times; $i++) {
			$tmp ^= hash_hmac($algo, $salt . pack('N', $i+1), $key, TRUE);
			$tmp ^= hash_hmac($algo, $tmp, $key, TRUE);
			$res .= $tmp;
		}
		return $this->byte_substr($res, 0, $length);
	}

	/*
	padding is mostly bytes that will seem sort-of random to a cracker
	c.f. (abandoned) ISO 10126
	NOT like the more-common PKCS7 style
	1-byte recorded block-size means <= 255 bytes/2040 bits TODO support 2-byte blocksize
	*/
	protected function pad($data)
	{
		$length = openssl_cipher_iv_length($this->method);
		if ($length > 0) {
			//CBC-mode (at least) requires data to be a multiple of block length
			$datalen = ($data) ? $this->bytelen($data) : 0;
			$padAmount = $length - $datalen % $length;
			if ($padAmount == 0) {
				$padAmount = $length;
			}
			$n = ($data) ? ord($data[$datalen - 1]) : mt_rand(0, 255);
			$pad = str_repeat(chr($padAmount), $padAmount);
			$padAmount--;
			for ($i = 0; $i < $padAmount; $i++,$n++) {
				if ($n > 255) {
					$n -= 256;
				}
				if ($i % 2) {
					$pad[$i] = $pad[$i]+$n+2;
				} else {
					$pad[$i] = $pad[$i]-$n-3;
				}
			}
		} else {
			$pad = '\0';
		}
		return $data . $pad;
	}

	protected function unpad($data)
	{
		$last = ord($data[$this->bytelen($data) - 1]);
		$last = ($last > 0) ? -$last : -1;
		return $this->byte_substr($data, 0, $last);
	}

	/*
	 * Count the number of bytes in the supplied string
	 *
	 * Vanilla strlen() might be shadowed by the mbstring extension, in which
	 * case, the former will count the number of characters, not bytes.
	 *
	 * @param string $str The input string
	 *
	 * @returns int, the number of bytes
	 */
	protected function bytelen($str)
	{
		static $lcheck = 0;
		if ($lcheck === 0) {
			$lcheck = (extension_loaded('mbstring')) ? 1:-1;
		}
		return ($lcheck > 0) ? mb_strlen($str, '8bit') : strlen($str);
	}

	/*
	 * ibid, for substr
	 */
	function byte_substr($str, $start, $length=NULL)
	{
		static $scheck = 0;
		if ($scheck === 0) {
			$scheck = (extension_loaded('mbstring')) ? 1:-1;
		}
		if (is_null($length)) {
			$length = strlen($str) - $start;
		}
		return ($scheck > 0) ? mb_substr($str, $start, $length, '8bit') :
			substr($str, $start, $length);
	}
}
