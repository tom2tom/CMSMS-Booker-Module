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
		return ((extension_loaded('apc') || extension_loaded('apcu'))
			&& ini_get('apc.enabled'));
	}

	function connectServer() {
		return TRUE;  //TODO
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
		$data = apc_fetch($keyword,$bo);
		if($bo !== FALSE) {
			return $data;
		}
		return NULL;
	}

	function _getall() {
		return NULL; //TODO allitems;
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
