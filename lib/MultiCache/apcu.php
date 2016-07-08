<?php
namespace MultiCache;

class Cache_apc extends CacheBase implements CacheInterface {

	function __construct($config = array()) {
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
		return (extension_loaded('apcu') && ini_get('apc.enabled'));
	}

	function connectServer() {
		return TRUE;  //TODO
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
		if($this->_has($keyword)) {
			return FALSE;
		}
		$ret = apcu_add($keyword,$value,(int)$lifetime);
		return $ret;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$lifetime = (int)$lifetime;
		$ret = apcu_add($keyword,$value,$lifetime);
		if(!$ret) {
			$ret = apcu_store($keyword,$value,$lifetime);
		}
		return $ret;
	}

	function _get($keyword) {
		$value = apcu_fetch($keyword,$suxs);
		if($suxs !== FALSE) {
			return $value;
		}
		return NULL;
	}

	function _getall() {
		$items = [];
		$iter = new \APCUIterator();
		if($iter) {
			foreach($iter as $key=>$val) {
				if(1) { //TODO filter 'ours'
					$items[$key] = $val;
				}
			}
		}
		return $items;
	}

	function _has($keyword) {
		return apcu_exists($keyword);
	}

	function _delete($keyword) {
		return apcu_delete($keyword);
	}

	function _clean() {
		$iter = new \APCUIterator();
		if($iter) {
			$data = [];
			foreach($iter as $key=>$val) {
				if(1) { //TODO filter 'ours'
					$data[] = $key;
				}
			}
			$ret = TRUE;
			foreach($data as $key) {
				$ret = $ret && apcu_delete($key);
			}
			return $ret;
		}
		return FALSE;
	}

}

?>
