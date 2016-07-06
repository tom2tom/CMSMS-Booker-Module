<?php
namespace MultiCache;

class Cache_memcache extends CacheBase implements CacheInterface {

	protected $instance;

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
			$this->instance = new Memcache(); //CHECKME data persistence ??
			if($this->connectServer()) {
				return;
			}
			unset($this->instance);
		}
		throw new \Exception('no memcache storage');
	}

/*	function __destruct() {
	}
*/
	function use_driver() {
		return class_exists('Memcache') && function_exists('memcache_connect');
	}

	function connectServer() {
		$settings = isset($this->config['memcache']) ? $this->config['memcache'] : array();
		$server = array_merge($settings, array(
				array('127.0.0.1',11211)
				));
		foreach($server as $s) {
			$name = $s[0].'_'.$s[1];
			if(!isset($this->checked[$name])) {
				try {
					if($this->instance->addserver($s[0],$s[1])) {
						$this->checked[$name] = 1;
						return TRUE;
					}
				} catch(\Exception $e) {}
			}
		}
		return FALSE;
	}

	function _newsert($keyword, $value, $time = FALSE) {
		if($this->_has($keyword)) {
			return FALSE;
		}
		if ($time) {
			$ret = $this->instance->add($keyword, $value, 0, time() + (int)$time);
		} else {
			$ret = $this->instance->add($keyword, $value, 0);
		}
		return $ret;
	}

	function _upsert($keyword, $value, $time = FALSE) {
		if ($time) {
			$expire = time() + (int)$time;
			$ret = $this->instance->add($keyword, $value, 0, $expire);
		} else {
			$ret = $this->instance->add($keyword, $value, 0);
		}
		if(!$ret) {
			if($time) {
				$ret = $this->instance->set($keyword, $value, 0, $expire);
			} else {
				$ret = $this->instance->set($keyword, $value, 0);
			}
		}
		return $ret;
	}

	function _get($keyword) {
		$data = $this->instance->get($keyword);
		if($data !== FALSE) {
			return $data;
		}
		return NULL;
	}

	function _getall() {
		return $TODO;
	}

	function _has($keyword) {
		return ($this->_get($keyword) != NULL);
	}

	function _delete($keyword) {
		$this->instance->delete($keyword);
		return TRUE;
	}

	function _clean() {
		$this->instance->flush();
	}

}

?>
