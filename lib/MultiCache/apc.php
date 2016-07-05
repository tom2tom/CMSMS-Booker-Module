<?php
namespace MultiCache;

class Cache_apc extends CacheBase implements CacheInterface {

	function __construct($config = array()) {
		if($this->checkdriver()) {
			$this->setup($config);
		} else {
			throw new \Exception('no apc storage');
		}
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return (extension_loaded('apc') && ini_get('apc.enabled'));
	}

	function driver_set($keyword, $value = '', $time = 300, $option = array()) {
		if(empty($option['skipExisting'])) {
			$ret = apc_store($keyword,$value,$time);
		} else {
			$ret = apc_add($keyword,$value,$time);
		}
		if($ret) {
			$this->index[$keyword] = 1;
		}
		return $ret;
	}

	function driver_get($keyword, $option = array()) {
		$data = apc_fetch($keyword,$bo);
		if($bo !== FALSE) {
			return $data;
		}
		return NULL;
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		if(apc_delete($keyword)) {
			unset($this->index[$keyword]);
			return TRUE;
		}
		return FALSE;
	}

	function driver_stats($option = array()) {
		$res = array(
			'info' => '',
			'size' => count($this->index)
		);
		try {
			$res['data'] = apc_cache_info('user');
		} catch(\Exception $e) {
			$res['data'] = array();
		}
		return $res;
	}

	function driver_clean($option = array()) {
		@apc_clear_cache();
		@apc_clear_cache('user');
		$this->index = array();
	}

	function driver_isExisting($keyword) {
		return apc_exists($keyword);
	}

}

?>
