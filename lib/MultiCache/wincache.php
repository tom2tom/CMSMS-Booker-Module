<?php
namespace MultiCache;

class Cache_wincache extends CacheBase implements CacheInterface
{
	public function __construct($config=array())
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no wincache storage');
	}

/*	public function __destruct()
	{
	}
*/
	public function use_driver()
	{
		return (extension_loaded('wincache') && function_exists('wincache_ucache_set'));
	}

	public function connectServer()
	{
		return TRUE; //TODO connect
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		return wincache_ucache_add($keyword, $value, (int)$lifetime);
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		$ret = wincache_ucache_add($keyword, $value, (int)$lifetime);
		if (!$ret) {
			$ret = wincache_ucache_set($keyword, $value, (int)$lifetime);
		}
		return $ret;
	}

	public function _get($keyword)
	{
		$value = wincache_ucache_get($keyword,$suxs);
		if ($suxs) {
			return $value;
		}
		return NULL;
	}

	public function _getall($filter)
	{
		$items = array();
		$info = wincache_ucache_info();
		foreach ($info['ucache_entries'] as $one) {
			$keyword = $one['key_name'];
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
		return $items;
	}

	public function _has($keyword)
	{
		return wincache_ucache_exists($keyword);
	}

	public function _delete($keyword)
	{
		return wincache_ucache_delete($keyword);
	}

	public function _clean($filter)
	{
		$ret = TRUE;
		$info = wincache_ucache_info();
		foreach ($info['ucache_entries'] as $one) {
			$keyword = $one['key_name'];
			$value = $this->_get($keyword);
			if ($this->filterKey($filter,$keyword,$value)) {
				$ret = $ret && wincache_ucache_delete($keyword);
			}
		}
		return $ret;
	}

}
