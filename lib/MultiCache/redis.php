<?php
/*
 * Redis extension:
 * https://github.com/phpredis/phpredis
 */
namespace MultiCache;

class Cache_redis extends CacheBase implements CacheInterface {

	protected $instance;
	/*
	$config members: any of
	'host' => string
	'port'  => int
	'password' => string
	'database' => int
	'timeout' => float seconds
	*/
	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
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
		$params = array_merge(array(
			'host' => '127.0.0.1',
			'port'  => 6379,
			'password' => '',
			'database' => 0,
			'timeout' => 0.0,
			), $this->config);

		$this->instance = new Redis();
		if(!$this->instance->connect($params['host'],(int)$params['port'],(float)$params['timeout'])) {
			return FALSE;
		} elseif ($params['password'] && !$this->instance->auth($params['password'])) {
			return FALSE;
		}
		if($params['database']) {
			return $this->instance->select((int)$params['database']);
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
		return $TODOallitems;
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
