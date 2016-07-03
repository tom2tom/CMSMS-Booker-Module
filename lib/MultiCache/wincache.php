<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */
namespace FastCache;

class Cache_wincache extends CacheBase implements CacheInterface {

	function __construct($config = array()) {
		if($this->checkdriver()) {
			$this->setup($config);
		} else {
			throw new \Exception('no wincache storage');
		}
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return (extension_loaded('wincache') && function_exists('wincache_ucache_set'));
	}

	function driver_set($keyword, $value = "", $time = 300, $option = array() ) {
		if(empty($option['skipExisting'])) {
			$ret = wincache_ucache_set($keyword, $value, $time);
		} else {
			$ret = wincache_ucache_add($keyword, $value, $time);
		}
		if($ret) {
			$this->index[$keyword] = 1;
		}
		return $ret;
	}

	// return cached value or null
	function driver_get($keyword, $option = array()) {
		$x = wincache_ucache_get($keyword,$suc);
		if($suc) {
			return $x;
		} else {
			return NULL;
		}
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		if(wincache_ucache_delete($keyword)) {
			unset($this->index[$keyword]);
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function driver_stats($option = array()) {
		$res = array(
			'info' => '',
			'size' => count($this->index),
			'data' => wincache_scache_info(),
		);
		return $res;
	}

	function driver_clean($option = array()) {
		wincache_ucache_clear();
		$this->index = array();
		return TRUE;
	}

	function driver_isExisting($keyword) {
		return wincache_ucache_exists($keyword);
	}

}

?>
