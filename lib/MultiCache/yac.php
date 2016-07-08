<?php
/*
 * Refer to https://github.com/laruence/yac#yac---yet-another-cache
 */
namespace MultiCache;

class Cache_yac extends CacheBase implements CacheInterface  {

	protected $instance;

	function __construct($config = []) {
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
			$this->instance = new \Yac($this->config['prefix']);
		} else {
			$this->instance = new \Yac();
		}
		return TRUE;
	}

	function _newsert($keyword, $value, $lifetime = FALSE) {
		if($this->_has($keyword)) {
			return FALSE;
		}
		return $this->instance->set($keyword, serialize($value), (int)$lifetime);
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		return $this->instance->set($keyword, serialize($value), (int)$lifetime);
	}

	function _get($keyword) {
		$value = $this->instance->get($keyword);
		if($value !== FALSE) {
			return unserialize($value);
		}
		return NULL;
	}

	function _getall($filter) {
		$items = [];
		$info = $this->instance->info();
		$count = (int)$info['slots_used'];
		if($count) {
			$info = $this->instance->dump($count);
			if($info) {
				$items = [];
				foreach($info as $one) {
					$keyword = $one['key'];
					if(!$filter || $this->filterKey($filter,$keyword)) {
						$value = $this->_get($keyword);
						if($value !== NULL) {
							$items[$keyword] = $value;
						}
					}
				}
			}
		}
		return $items;
	}

	function _has($keyword) {
		return ($this->_get($keyword) !== NULL);
	}

	function _delete($keyword) {
		return $this->instance->delete($keyword);
	}
	
	function _clean($filter) {
		$info = $this->instance->info();
		$count = (int)$info['slots_used'];
		if($count) {
			$info = $this->instance->dump($count);
			if($info) {
				$ret = TRUE;
				foreach($info as $one) {
					$keyword = $one['key'];
					if(!$filter || $this->filterKey($filter,$keyword)) {
						$ret = $ret && $this->instance->delete($keyword);
					}
				}
				return $ret;
			}
			return FALSE;
		}
		return TRUE;
	}

}

?>
