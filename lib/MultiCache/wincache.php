<?php
namespace MultiCache;

class Cache_wincache extends CacheBase implements CacheInterface {

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
		} else {
			throw new \Exception('no wincache storage');
		}
	}

/*	function __destruct() {
	}
*/
	function use_driver() {
		return (extension_loaded('wincache') && function_exists('wincache_ucache_set'));
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$ret = wincache_ucache_add($keyword, $value, $lifetime);
		if(!$ret) {
			$ret = wincache_ucache_set($keyword, $value, $lifetime);
		}
		return $ret;
	}

	// return cached value or null
	function _get($keyword) {
		$data = wincache_ucache_get($keyword,$suxs);
		if($suxs) {
			return $data;
		}
		return NULL;
	}

	function _getall() {
		return NULL; //TODO allitems;
	}

	function _has($keyword) {
		return wincache_ucache_exists($keyword);
	}

	function _delete($keyword) {
		return wincache_ucache_delete($keyword);
	}

	function _clean() {
		wincache_ucache_clear();
		return TRUE;
	}

}

?>
