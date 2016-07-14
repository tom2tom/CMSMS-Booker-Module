<?php

namespace MultiCache;

class Cache_apc extends CacheBase implements CacheInterface
{
	public function __construct($config = array())
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no APC storage');
	}

/*	public function __destruct()
	{
	}
*/
	public function use_driver()
	{
		return (extension_loaded('apc') && ini_get('apc.enabled'));
	}

	public function connectServer()
	{
		return TRUE;  //TODO connect
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		if ($this->_has($keyword)) {
			return FALSE;
		}
		$ret = apc_add($keyword,$value,(int)$lifetime);
		return $ret;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		$lifetime = (int)$lifetime;
		$ret = apc_add($keyword,$value,$lifetime);
		if (!$ret) {
			$ret = apc_store($keyword,$value,$lifetime);
		}
		return $ret;
	}

	public function _get($keyword)
	{
		$value = apc_fetch($keyword,$suxs);
		if ($suxs !== FALSE) {
			return $value;
		}
		return NULL;
	}

	public function _getall($filter)
	{
		$items = array();
		$iter = new \APCIterator();
		if ($iter) {
			foreach ($iter as $keyword=>$value) {
				$again = is_object($value); //get it again, in case the filter played with it!
				if ($this->filterItem($filter,$keyword,$value)) {
					if ($again) {
						$value = $this->_get($keyword);
					}
					if (!is_null($value)) {
						$items[$keyword] = $value;
					}
				}
			}
		}
		return $items;
	}

	public function _has($keyword)
	{
		return apc_exists($keyword);
	}

	public function _delete($keyword)
	{
		return apc_delete($keyword);
	}

	public function _clean($filter)
	{
		$iter = new \APCIterator('user');
		if ($iter) {
			$items = array();
			foreach ($iter as $keyword=>$value) {
				if ($this->filterItem($filter,$keyword,$value)) {
					$items[] = $keyword;
				}
			}
			$ret = TRUE;
			foreach ($items as $keyword) {
				$ret = $ret && apc_delete($keyword);
			}
			return $ret;
		}
		return FALSE;
	}

}
