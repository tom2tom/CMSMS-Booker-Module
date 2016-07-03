<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */
namespace FastCache;

class Cache_xcache extends CacheBase implements CacheInterface  {

	function __construct($config = array()) {
		if($this->checkdriver()) {
			$this->setup($config);
		} else {
			throw new \Exception('no xcache storage');
		}
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return (extension_loaded('xcache') && function_exists('xcache_get'));
	}

	function driver_set($keyword, $value = "", $time = 300, $option = array() ) {
		if(empty($option['skipExisting'])) {
			$ret = xcache_set($keyword,$value,$time);
		} else if(!$this->isExisting($keyword)) {
			$ret = xcache_set($keyword,$value,$time);
		} else {
			$ret = FALSE;
		}
		if($ret) {
			$this->index[$keyword] = 1;
		}
		return $ret;
	}

	// return cached value or null
	function driver_get($keyword, $option = array()) {
		$data = xcache_get($keyword);
		if($data === FALSE || $data == '') {
			return NULL;
		}
		return $data;
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		if(xcache_unset($keyword)) {
			unset($this->index[$keyword]);
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function driver_stats($option = array()) {
		$res = array(
			'info' => '',
			'size' => count($this->index)
		);
		try {
			$res['data'] = xcache_list(XC_TYPE_VAR,100);
		} catch(\Exception $e) {
			$res['data'] = array();
		}
		return $res;
	}

	function driver_clean($option = array()) {
		$cnt = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i<$cnt; $i++) {
			xcache_clear_cache(XC_TYPE_VAR, $i);
		}
		$this->index = array();
		return TRUE;
	}

	function driver_isExisting($keyword) {
		return xcache_isset($keyword);
	}

}

?>
