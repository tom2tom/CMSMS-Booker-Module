<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */

class FastCache_ram extends FastCacheBase implements FastCache {
	
	function __construct($config = array())	{
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return true;
	}
		 
	function driver_set($keyword, $value = '', $time = 300, $option = array()) {
		if(empty($option['skipExisting']) ||
			!array_key_exists($keyword, $this->index)) {
			$this->index[$keyword] = $value;
			return true;
		}
		return false;
	}

	function driver_get($keyword, $option = array()) {
		if(array_key_exists($keyword, $this->index)) {
			return $this->index[$keyword];
		}
		return null;
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		if(array_key_exists($keyword, $this->index)) {
			unset($this->index[$keyword]);
			return true;
		}
		return false;
	}

	function driver_stats($option = array()) {
		return array(
			'info' => 'Number of cached items',
			'size' => count($this->index),
			'data' => ''
		);
	}

	function driver_clean($option = array()) {
		$this->index = array();
	}

	function driver_isExisting($keyword) {
		return array_key_exists($keyword, $this->index);
	}

}

?>
