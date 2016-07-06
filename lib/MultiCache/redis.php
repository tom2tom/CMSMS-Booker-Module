<?php
/*
 * Redis Extension with:
 * http://pecl.php.net/package/redis
 */
namespace MultiCache;

class Cache_redis extends CacheBase implements CacheInterface {

	protected $instance;
	protected $checked_redis = FALSE;

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
			$this->instance = new Redis(); //TODO CHECK data persistence?
			if($this->connectServer()) {
				return;
			}
			unset($this->instance);
		}
		throw new \Exception('no redis storage');
	}

	function use_driver() {
		return class_exists('Redis');
	}

	function connectServer() {
		if(!$this->checked_redis) {
			$settings = $this->config; //TODO API
			$server = array_merge(array(
				'host' => '127.0.0.1',
				'port'  => 6379,
				'password' => '',
				'database' => '',
				'timeout' => 1,
				), $settings);

			$host = $server['host'];

			$port = isset($server['port']) ? (int)$server['port'] : '';
			if($port!='') {
				$c['port'] = $port;
			}

			$password = isset($server['password']) ? $server['password'] : '';
			if($password!='') {
				$c['password'] = $password;
			}

			$database = isset($server['database']) ? $server['database'] : '';
			if($database!='') {
				$c['database'] = $database;
			}

			$timeout = isset($server['timeout']) ? $server['timeout'] : '';
			if($timeout!='') {
				$c['timeout'] = $timeout;
			}

			$read_write_timeout = isset($server['read_write_timeout']) ? $server['read_write_timeout'] : '';
			if($read_write_timeout!='') {
				$c['read_write_timeout'] = $read_write_timeout;
			}

			if(!$this->instance->connect($host,(int)$port,(int)$timeout)) {
				$this->checked_redis = TRUE;
				return FALSE;
			} else {
				if($database != '') {
					$this->instance->select((int)$database);
				}
				$this->checked_redis = TRUE;
				return TRUE;
			}
		}
		return TRUE;
	}

	function _newsert($keyword, $value, $time = FALSE) {
		if(!$this->_has($keyword)) {
			$ret = $this->instance->set($keyword, $value, array('xx', 'ex' => $time));
			return $ret;
		}
		return FALSE;
	}

	function _upsert($keyword, $value, $time = FALSE) {
		$ret = $this->instance->set($keyword, $value, array('xx', 'ex' => $time));
		if ($ret === FALSE) {
			$ret = $this->instance->set($keyword, $value, $time);
		}
		return $ret;
	}

	// return cached value or null
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
		return ($this->instance->exists($keyword) != NULL);
	}

	function _delete($keyword) {
		$this->instance->delete($keyword);
		return TRUE;
	}

	function _clean() {
		$this->instance->flushDB();
	}

}

?>
