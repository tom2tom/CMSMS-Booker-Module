<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */
namespace FastCache;

class FastCache_session extends CacheBase implements CacheInterface {

	function __construct($config = array())	{
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return TRUE;
	}
		 
	function driver_set($keyword, $value = '', $time = 300, $option = array()) {
		if(empty($option['skipExisting']) ||
			!array_key_exists($keyword, $this->index)) {
			$this->index[$keyword] = $value;
			return TRUE;
		}
		return FALSE;
	}

	function driver_get($keyword, $option = array()) {
		if(array_key_exists($keyword, $this->index)) {
			return $this->index[$keyword];
		}
		return NULL;
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		if(array_key_exists($keyword, $this->index)) {
			unset($this->index[$keyword]);
			return TRUE;
		}
		return FALSE;
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
