<?php
namespace MultiCache;

class Cache_xcache extends CacheBase implements CacheInterface  {

    protected $instance;

	function __construct($config = []) {
		if($this->use_driver()) {
			parent::__construct($config);
			if($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no xcache storage');
	}

/*	function __destruct() {
	}
*/
	function use_driver() {
		return (extension_loaded('xcache') && function_exists('xcache_get'));
	}

	function connectServer() {
        $this->instance = new \XCache();
//     $adbg = xcache_info(XC_TYPE_VAR, int id);
		return TRUE;  //TODO connect
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
//TODO support xcache_clear_cache(XC_TYPE_VAR [, int id = -1])
		if(xcache_isset($keyword)) {
			return FALSE;
		}
		if($lifetime) {
			$ret = xcache_set($keyword,$value,(int)$lifetime);
		} else {
			$ret = xcache_set($keyword,$value);
		}
		return $ret;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
//TODO support xcache_clear_cache(XC_TYPE_VAR [, int id = -1])
		if($lifetime) {
			$ret = xcache_set($keyword,$value,(int)$lifetime);
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
//TODO xcache_list(XC_TYPE_VAR, int id)
		$items = [];
		$cnt = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i<$cnt; $i++) {
			$keyword = NULL;
			if(1) { //TODO filter 'ours'
				$value = NULL;
				$items[$keyword] = $value;
			}
		}
		return $items;
	}

	function _has($keyword) {
		return xcache_isset($keyword);
	}

	function _delete($keyword) {
		return xcache_unset($keyword);
	}

	function _clean() {
//TODO xcache_clear_cache(XC_TYPE_VAR [, int id = -1])
//xcache_unset_by_prefix(string prefix)
		$cnt = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i<$cnt; $i++) {
			$keyword = NULL;
			if(1) { //TODO filter 'ours'
				xcache_clear_cache(XC_TYPE_VAR, $i);
			}
		}
		return TRUE;
	}

}

?>
