<?php
namespace MultiCache;

class Cache_xcache extends CacheBase implements CacheInterface
{
    protected $client;

	public function __construct($config=array())
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no xcache storage');
	}

/*	public function __destruct()
	{
	}
*/
	public function use_driver()
	{
		return (extension_loaded('xcache') && function_exists('xcache_get'));
	}

	public function connectServer()
	{
        $this->client = new \XCache();
//     $adbg = xcache_info(XC_TYPE_VAR, int id);
		return TRUE;  //TODO connect
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		if (xcache_isset($keyword)) {
			return FALSE;
		}
		if ($lifetime) {
			$ret = xcache_set($keyword,$value,(int)$lifetime);
		} else {
			$ret = xcache_set($keyword,$value);
		}
		return $ret;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		if ($lifetime) {
			$ret = xcache_set($keyword,$value,(int)$lifetime);
		} else {
			$ret = xcache_set($keyword,$value);
		}
		return $ret;
	}

	public function _get($keyword)
	{
		$data = xcache_get($keyword);
		if ($data !== FALSE) {
			return $data;
		}
		return NULL;
	}

	public function _getall($filter)
	{
		$items = array();
		$cnt = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i<$cnt; $i++) {
			$keyword = $TODO;
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
		return xcache_isset($keyword);
	}

	public function _delete($keyword)
	{
		return xcache_unset($keyword);
	}

	public function _clean($filter)
	{
		$ret = TRUE;
		$count = xcache_count(XC_TYPE_VAR);
		for ($i=0; $i<$count; $i++) {
			$keyword = $TODO;
			$value = $this->_get($keyword);
			if ($this->filterKey($filter,$keyword,$value)) {
				$ret = $ret && xcache_unset($keyword);
			}
		}
		return $ret;
	}

}
