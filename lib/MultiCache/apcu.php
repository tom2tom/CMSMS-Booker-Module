<?php

namespace MultiCache;

class Cache_apcu extends CacheBase implements CacheInterface
{
	protected $nativeiter; //which iter-API applies

	public function __construct($config = array())
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
		}
		throw new \Exception('no APCu storage');
	}

/*	public function __destruct()
	{
	}
*/
	public function use_driver()
	{
		return (extension_loaded('apcu') && ini_get('apc.enabled'));
	}

	public function connectServer()
	{
		if (class_exists('APCUIterator')) {
			$this->nativeiter = TRUE;
		}	elseif (class_exists('APCIterator')) {
			$this->nativeiter = FALSE;
		} else {
			return FALSE;
		}
		return TRUE;  //TODO connect
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		if ($this->_has($keyword)) {
			return FALSE;
		}
		$ret = apcu_add($keyword,$value,(int)$lifetime);
		return $ret;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		$lifetime = (int)$lifetime;
		$ret = apcu_add($keyword,$value,$lifetime);
		if (!$ret) {
			$ret = apcu_store($keyword,$value,$lifetime);
		}
		return $ret;
	}

	public function _get($keyword)
	{
		$value = apcu_fetch($keyword,$suxs);
		if ($suxs !== FALSE) {
			return $value;
		}
		return NULL;
	}

	public function _getall($filter)
	{
		$items = array();
		if ($this->nativeiter) {
			$iter = new \APCUIterator();
		} else {
			$iter = new \APCIterator('user');
		}
		if ($iter) {
			foreach ($iter as $keyword=>$value) {
				$again = is_object($value); //get it again, in case the filter played with it!
				if ($this->filterKey($filter,$keyword,$value)) {
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
		return apcu_exists($keyword);
	}

	public function _delete($keyword)
	{
		return apcu_delete($keyword);
	}

	public function _clean($filter)
	{
		if ($this->nativeiter) {
			$iter = new \APCUIterator();
		} else {
			$iter = new \APCIterator('user');
		}
		if ($iter) {
			$items = array();
			foreach ($iter as $keyword=>$value) {
				if ($this->filterKey($filter,$keyword,$value)) {
					$items[] = $keyword;
				}
			}
			$ret = TRUE;
			foreach ($items as $keyword) {
				$ret = $ret && apcu_delete($keyword);
			}
			return $ret;
		}
		return FALSE;
	}
}
