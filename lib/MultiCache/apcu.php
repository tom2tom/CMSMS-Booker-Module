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

	function _getall($filter) {
		$items = [];
		$iter = new \APCUIterator();
		if($iter) {
			foreach($iter as $keyword=>$value) {
				if(!$filter || $this->filterKey($filter,$keyword)) {
					$items[$keyword] = $value;
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

	function _clean($filter) {
		$iter = new \APCUIterator();
		if($iter) {
			$items = [];
			foreach($iter as $keyword=>$value) {
				if(!$filter || $this->filterKey($filter,$keyword)) {
					$items[] = $key;
				}
			}
			$ret = TRUE;
			foreach($items as $key) {
				$ret = $ret && apcu_delete($key);
			}
			return $ret;
		}
		return FALSE;
	}

}

?>
