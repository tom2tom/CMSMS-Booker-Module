<?php
namespace MultiCache;

class Cache_wincache extends CacheBase implements CacheInterface {

	function __construct($config = []) {
		if($this->use_driver()) {
			parent::__construct($config);
			if($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no wincache storage');
	}

/*	function __destruct() {
	}
*/
	function use_driver() {
		return (extension_loaded('wincache') && function_exists('wincache_ucache_set'));
	}

	function connectServer() {
		return TRUE; //TODO connect
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
		return wincache_ucache_add($keyword, $value, (int)$lifetime);
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$ret = wincache_ucache_add($keyword, $value, (int)$lifetime);
		if(!$ret) {
			$ret = wincache_ucache_set($keyword, $value, (int)$lifetime);
		}
		return $ret;
	}

	function _get($keyword) {
		$value = wincache_ucache_get($keyword,$suxs);
		if($suxs) {
			return $value;
		}
		return NULL;
	}

	function _getall() {
		$items = [];
		$info = wincache_ucache_info();
		foreach($info['ucache_entries']) as $one {
			$keyword = $one['key_name'];
			if(1) { //TODO filter only 'ours' e.g. by namespace
				$value = $this->_get($keyword);
				if($value !== NULL) {
					$items[$keyword] = $value;
				}
			}
		}
		return $items;
	}

	function _has($keyword) {
		return wincache_ucache_exists($keyword);
	}

	function _delete($keyword) {
		return wincache_ucache_delete($keyword);
	}

	function _clean() {
		$info = wincache_ucache_info();
		$ret = TRUE;
		foreach($info['ucache_entries']) as $one {
			if(1) { //TODO filter only 'ours'
				$ret = $ret && wincache_ucache_delete($one['key_name']);
			}
		}
		return $ret;
	}

}

?>
