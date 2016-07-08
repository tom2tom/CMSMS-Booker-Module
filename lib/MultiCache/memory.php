<?php
namespace MultiCache;

class Cache_memory extends CacheBase implements CacheInterface {

	private $stored = array();

	function __construct($config = array())	{
	}

/*	function __destruct() {
		$this->_clean();
	}
*/
	function use_driver() {
		return TRUE;
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
		if(!array_key_exists($keyword, $this->stored)) {
			$this->stored[$keyword] = $value;
			return TRUE;
		}
		return FALSE;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$this->stored[$keyword] = $value;
		return TRUE;
	}

	function _get($keyword) {
		if(array_key_exists($keyword, $this->stored)) {
			return $this->stored[$keyword];
		}
		return NULL;
	}

	function _getall() {
		return array_values($this->stored);
	}

	function _has($keyword) {
		return array_key_exists($keyword, $this->stored);
	}

	function _delete($keyword) {
		if(isset($this->stored[$keyword])) {
			unset($this->stored[$keyword]);
			return TRUE;
		}
		return FALSE;
	}

	function _clean() {
		$this->stored = array();
	}

}

?>
