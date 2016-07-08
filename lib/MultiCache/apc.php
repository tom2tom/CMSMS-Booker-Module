<?php
namespace MultiCache;

class Cache_apc extends CacheBase implements CacheInterface {

	function __construct($config = []) {
		if($this->use_driver()) {
			parent::__construct($config);
			if($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no APC storage');
	}

/*	function __destruct() {
	}
*/
	function use_driver() {
		return (extension_loaded('apc') && ini_get('apc.enabled'));
	}

	function connectServer() {
		return TRUE;  //TODO connect
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
		if($this->_has($keyword)) {
			return FALSE;
		}
		$ret = apc_add($keyword,$value,(int)$lifetime);
		return $ret;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$lifetime = (int)$lifetime;
		$ret = apc_add($keyword,$value,$lifetime);
		if(!$ret) {
			$ret = apc_store($keyword,$value,$lifetime);
		}
		return $ret;
	}

	function _get($keyword) {
		$value = apc_fetch($keyword,$suxs);
		if($suxs !== FALSE) {
			return $value;
		}
		return NULL;
	}

	function _getall() {
		$items = [];
		$iter = new \APCIterator();
		if($iter) {
			foreach($iter as $keyword=>$value) {
				if(1) { //TODO filter 'ours'
					$items[$keyword] = $value;
				}
			}
		}
		return $items;
	}

	function _has($keyword) {
		return apc_exists($keyword);
	}

	function _delete($keyword) {
		return apc_delete($keyword);
	}

	function _clean() {
		$iter = new \APCIterator();
		if($iter) {
			$data = [];
			foreach($iter as $keyword=>$value) {
				if(1) { //TODO filter 'ours'
					$data[] = $keyword;
				}
			}
			$ret = TRUE;
			foreach($data as $keyword) {
				$ret = $ret && apc_delete($keyword);
			}
			return $ret;
		}
		return FALSE;
	}

}

?>
