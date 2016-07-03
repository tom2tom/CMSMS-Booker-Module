<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */
namespace FastCache;

class Cache_memcache extends CacheBase implements CacheInterface {

	var $instant;

	function __construct($config = array()) {
		if($this->checkdriver()) {
			$this->instant = new Memcache();
			$this->setup($config);
			if($this->connectServer()) {
				return;
			}
			unset($this->instant);
		}
		throw new \Exception('no memcache storage');
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return class_exists('Memcache') && function_exists('memcache_connect');
	}

	function connectServer() {
		$settings = isset($this->option['memcache']) ? $this->option['memcache'] : array();
		$server = array_merge($settings, array(
				array('127.0.0.1',11211)
				));
		foreach($server as $s) {
			$name = $s[0].'_'.$s[1];
			if(!isset($this->checked[$name])) {
				try {
					if($this->instant->addserver($s[0],$s[1])) {
						$this->checked[$name] = 1;
						return TRUE;
					}
				} catch(\Exception $e) {}
			}
		}
		return FALSE;
	}

	function driver_set($keyword, $value = '', $time = 300, $option = array() ) {
		if(empty($option['skipExisting'])) {
			$ret = $this->instant->set($keyword, $value, FALSE, $time );
		} else {
			$ret = $this->instant->add($keyword, $value, FALSE, $time );
		}
		if($ret) {
			$this->index[$keyword] = 1;
		}
		return $ret;
	}

	// return cached value or NULL
	function driver_get($keyword, $option = array()) {
		$x = $this->instant->get($keyword);
		if($x) {
			return $x;
		} else {
			return NULL;
		}
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		$this->instant->delete($keyword);
		unset($this->index[$keyword]);
		return TRUE;
	}

	function driver_stats($option = array()) {
		return array(
			'info' => '',
			'size' => count($this->index),
			'data' => $this->instant->getStats(),
		);
	}

	function driver_clean($option = array()) {
		$this->instant->flush();
		$this->index = array();
	}

	function driver_isExisting($keyword) {
		return ($this->get($keyword) != NULL);
	}

}

?>
