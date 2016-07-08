<?php
/*
 * Refer to https://github.com/laruence/yac#yac---yet-another-cache
 */
namespace MultiCache;

class Cache_yac extends CacheBase implements CacheInterface  {

	protected $instance;

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
			if($this->connectServer()) {
				return;
			}
			unset($this->instance);
		}
		throw new \Exception('no yac storage');
	}

	function use_driver() {
		return extension_loaded('yac');
	}

	function connectServer() {
		if(!empty($this->config['prefix'])) {
			$this->instance = new Yac($this->config['prefix']);
		} else {
			$this->instance = new Yac();
		}
		return TRUE;
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
		if($this->_has($keyword)) {
			return FALSE;
		}
		$this->instance->set($keyword, $value, (int)$lifetime);
		return TRUE;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$this->instance->set($keyword, $value, (int)$lifetime);
		return TRUE;
	}

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
		return ($this->_get($keyword) !== NULL);
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
