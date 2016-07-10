<?php
/*
 * Refer to https://github.com/laruence/yac#yac---yet-another-cache
 */
namespace MultiCache;

class Cache_yac extends CacheBase implements CacheInterface
{
	protected $client;

	function __construct($config=array())
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
			unset($this->client);
		}
		throw new \Exception('no yac storage');
	}

	function use_driver()
	{
		return extension_loaded('yac');
	}

	function connectServer()
	{
		if (!empty($this->config['prefix'])) {
			$this->client = new \Yac($this->config['prefix']);
		} else {
			$this->client = new \Yac();
		}
		return TRUE;
	}

	function _newsert($keyword, $value, $lifetime=FALSE)
	{
		if ($this->_has($keyword)) {
			return FALSE;
		}
		return $this->client->set($keyword, serialize($value), (int)$lifetime);
	}

	function _upsert($keyword, $value, $lifetime=FALSE)
	{
		return $this->client->set($keyword, serialize($value), (int)$lifetime);
	}

	function _get($keyword)
	{
		$value = $this->client->get($keyword);
		if ($value !== FALSE) {
			return unserialize($value);
		}
		return NULL;
	}

	function _getall($filter)
	{
		$items = array();
		$info = $this->client->info();
		$count = (int)$info['slots_used'];
		if ($count) {
			$info = $this->client->dump($count);
			if ($info) {
				$items = array();
				foreach ($info as $one) {
					$keyword = $one['key'];
					$value = $this->_get($keyword);
					$again = is_object($value); //get it again, in case the filter played with it!
					if ($this->filterKey($filter,$keyword,$value)) {
						if ($again) {
							$value = $this->_get($keyword);
						}
						if ($value !== NULL) {
							$items[$keyword] = $value;
						}
					}
				}
			}
		}
		return $items;
	}

	function _has($keyword)
	{
		return ($this->_get($keyword) !== NULL);
	}

	function _delete($keyword)
	{
		return $this->client->delete($keyword);
	}
	
	function _clean($filter)
	{
		$info = $this->client->info();
		$count = (int)$info['slots_used'];
		if ($count) {
			$info = $this->client->dump($count);
			if ($info) {
				$ret = TRUE;
				foreach ($info as $one) {
					$keyword = $one['key'];
					$value = $this->_get($keyword);
					if ($this->filterKey($filter,$keyword,$value)) {
						$ret = $ret && $this->client->delete($keyword);
					}
				}
				return $ret;
			}
			return FALSE;
		}
		return TRUE;
	}

}
