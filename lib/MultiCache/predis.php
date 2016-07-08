<?php
/*
 * Predis extension:
 * https://github.com/nrk/predis
 */
namespace MultiCache;

class Cache_predis extends CacheBase implements CacheInterface {

	protected $instance;
	/*
	$config members: any of
	'host' => string
	'port'  => int
	'password' => string
	'database' => int
	'timeout' => float seconds
	'read_write_timeout' => float seconds
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
		if (extension_loaded('Redis')) {
			return FALSE; //native Redis extension is installed, prefer Redis to increase performance
		}
        return class_exists('Predis\Client');
	}

	function connectServer() {
		$params = array_merge(array(
			'host' => '127.0.0.1',
			'port'  => 6379,
			'password' => '',
			'database' => 0
			), $this->config);

		$c = array('host' => $params['host']);

		if($params['port']) {
			$c['port'] = (int)$params['port'];
		}

		if($params['password']) {
			$c['password'] = $params['password'];
		}

		if($params['database']) {
			$c['database'] = (int)$params['database'];
		}

		$p = isset($params['timeout']) ? $params['timeout'] : '';
		if($p) {
			$c['timeout'] = (float)$p;
		}

		$p = isset($params['read_write_timeout']) ? $params['read_write_timeout'] : '';
		if($p) {
			$c['read_write_timeout'] = (float)$p;
		}

		$this->instance = new Predis\Client($c);
		return $this->instance !== NULL;
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
		if(!$this->_has($keyword)) {
			$ret = $this->instance->set($keyword, $value, array('xx', 'ex' => $lifetime));
			return $ret;
		}
		return FALSE;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$ret = $this->instance->set($keyword, $value, array('xx', 'ex' => $lifetime));
		if ($ret === FALSE) {
			$ret = $this->instance->set($keyword, $value, $lifetime);
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
		return NULL; //TODO allitems;
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
