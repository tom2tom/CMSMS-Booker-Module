<?php
namespace MultiCache;

class Cache_apc extends CacheBase implements CacheInterface {

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
		} else {
			throw new \Exception('no APC storage');
		}
	}

/*	function __destruct() {
		$this->_clean();
	}
*/
	function use_driver() {
		return ((extension_loaded('apc') || extension_loaded('apcu'))
			&& ini_get('apc.enabled'));
	}

	function _newsert($keyword, $value, $time = FALSE) {
		if($this->_has($keyword)) {
			return FALSE;
		}
		$ret = apc_add($keyword,$value,(int)$time);
		return $ret;
	}

	function _upsert($keyword, $value, $time = FALSE) {
		$time = (int)$time;
		$ret = apc_add($keyword,$value,$time);
		if(!$ret) {
			$ret = apc_store($keyword,$value,$time);
		}
		return $ret;
	}

	function _get($keyword) {
		$data = apc_fetch($keyword,$bo);
		if($bo !== FALSE) {
			return $data;
		}
		return NULL;
	}

	function _getall() {
		return $TODO;
	}

	function _has($keyword) {
		return apc_exists($keyword);
	}

	function _delete($keyword) {
		return apc_delete($keyword);
	}

	function _clean() {
		@apc_clear_cache(); //TODO CHECKME too broad?
		@apc_clear_cache('user');
	}

}

?>
