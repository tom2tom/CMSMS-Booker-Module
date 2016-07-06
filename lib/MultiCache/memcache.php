<?php
namespace MultiCache;

class Cache_memcache extends CacheBase implements CacheInterface {

	protected $instance;

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
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
		$this->instance = new Memcache(); //CHECKME data persistence ??
	
		$params = array_merge($this->config,
			array(array('host'=>'127.0.0.1','port'=>11211))
		);
		foreach($params as $server) {
			$name = $server['host'].'_'.$server['port'];
			if(!isset($this->checked[$name])) {
				try {
					if($this->instance->addserver($server['host'],(int)$server['port'])) {
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
		return $TODOallitems;
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
