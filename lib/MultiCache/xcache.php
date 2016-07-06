<?php
namespace MultiCache;

class Cache_xcache extends CacheBase implements CacheInterface  {

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
		} else {
			throw new \Exception('no xcache storage');
		}
	}

/*	function __destruct() {
	}
*/
	function use_driver() {
		return (extension_loaded('xcache') && function_exists('xcache_get'));
	}

	function _newsert($keyword, $value, $time = FALSE) {
//TODO support xcache_clear_cache(int type [, int id = -1])
		if(xcache_isset($keyword)) {
			return FALSE;
		}
		if($time !== FALSE) {
			$ret = xcache_set($keyword,$value,(int)$time);
		} else {
			$ret = xcache_set($keyword,$value);
		}
		return $ret;
	}

	function _upsert($keyword, $value, $time = FALSE) {
//TODO support xcache_clear_cache(int type [, int id = -1])
		if($time !== FALSE) {
			$ret = xcache_set($keyword,$value,(int)$time);
		} else {
			$ret = xcache_set($keyword,$value);
		}
		return $ret;
	}

	function _get($keyword) {
		$data = xcache_get($keyword);
		if($data !== FALSE) {
			return $data;
		}
		return NULL;
	}

	function _getall() {
		$vals = array();
		$cnt = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i<$cnt; $i++) {
			$vals[] = $TODO;
		}
		return $vals;
	}

	function _has($keyword) {
		return xcache_isset($keyword);
	}

	function _delete($keyword) {
		return xcache_unset($keyword);
	}

	function _clean() {
//TODO xcache_clear_cache(int type [, int id = -1])
		$cnt = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i<$cnt; $i++) {
			xcache_clear_cache(XC_TYPE_VAR, $i);
		}
		return TRUE;
	}

}

?>
