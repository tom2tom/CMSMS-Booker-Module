<?php
namespace MultiCache;

class Cache_memcached extends CacheBase implements CacheInterface  {

	protected $instance;

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
			$this->instance = new Memcached(); //TODO CHECK data persistence??
			if($this->connectServer()) {
				return;
			}
			unset($this->instance);
		}
		throw new \Exception('no memcached storage');
	}

/*	function __destruct() {
	}
*/
	function use_driver() {
		return class_exists('Memcached');
	}

	function connectServer() {

		if(!$this->use_driver()) {
			return FALSE;
		}

		$s = $this->config['memcache']; //TODO CHECK memcached ?
		if(count($s) < 1) {
			$s = array(
				array('127.0.0.1',11211,100),
			);
		}

		foreach($s as $server) {
			$name = isset($server[0]) ? $server[0] : '127.0.0.1';
			$port = isset($server[1]) ? $server[1] : 11211;
			$sharing = isset($server[2]) ? $server[2] : 0;
			$checked = $name.'_'.$port;
			if(!isset($this->checked[$checked])) {
				try {
					if($sharing > 0) {
						if($this->instance->addServer($name,$port,$sharing)) {
							$this->checked[$checked] = 1;
							return TRUE;
						}
					} elseif($this->instance->addServer($name,$port)) {
						$this->checked[$checked] = 1;
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
			$ret = $this->instance->add($keyword, $value, time() + $time);
		} else {
			$ret = $this->instance->add($keyword, $value);
		}
		return $ret;
	}

	function _upsert($keyword, $value, $time = FALSE) {
		if ($time) {
			$expire = time() + (int)$time;
			$ret = $this->instance->add($keyword, $value, $expire);
		} else {
			$ret = $this->instance->add($keyword, $value);
		}
		if(!$ret) {
			if($time) {
				$ret = $this->instance->set($keyword, $value, $expire);
			} else {
				$ret = $this->instance->set($keyword, $value);
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
