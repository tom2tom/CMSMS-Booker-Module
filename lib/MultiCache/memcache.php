<?php

namespace MultiCache;

class Cache_memcache extends CacheBase implements CacheInterface
{
	protected $client;

	public function __construct($config = array())
	{
		if ($this->use_driver()) {
			parent::__construct($config);
			if ($this->connectServer()) {
				return;
			}
			unset($this->client);
		}
		throw new \Exception('no memcache storage');
	}

/*	public function __destruct()
	{
	}
*/
	public function use_driver()
	{
		return class_exists('Memcache') && function_exists('memcache_connect');
	}

	public function connectServer()
	{
		$this->client = new \Memcache(); //CHECKME data persistence ??
	
		$params = array_merge($this->config,
			array(array('host'=>'127.0.0.1','port'=>11211))
		);
		foreach($params as $server) {
			$name = $server['host'].'_'.$server['port'];
			if (!isset($this->checked[$name])) {
				try {
					if ($this->client->addserver($server['host'],(int)$server['port'])) {
						$this->checked[$name] = 1;
						return TRUE;
					}
				} catch(\Exception $e) {}
			}
		}
		return FALSE;
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		if ($this->_has($keyword)) {
			return FALSE;
		}
		if ($lifetime) {
			$ret = $this->client->add($keyword, $value, 0, time() + (int)$lifetime);
		} else {
			$ret = $this->client->add($keyword, $value, 0);
		}
		return $ret;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		if ($lifetime) {
			$expire = time() + (int)$lifetime;
			$ret = $this->client->add($keyword, $value, 0, $expire);
		} else {
			$ret = $this->client->add($keyword, $value, 0);
		}
		if (!$ret) {
			if ($lifetime) {
				$ret = $this->client->set($keyword, $value, 0, $expire);
			} else {
				$ret = $this->client->set($keyword, $value, 0);
			}
		}
		return $ret;
	}

	public function _get($keyword)
	{
		$data = $this->client->get($keyword);
		if ($data !== FALSE) {
			return $data;
		}
		return NULL;
	}

	public function _getall($filter)
	{
		$items = array();
		$info = $this->client->getStats('items');
		if ($info) {
			foreach($info as $keyword=>$value) {
				$again = is_object($value); //get it again, in case the filter played with it!
				if ($this->filterItem($filter,$keyword,$value)) {
					if ($again) {
						$value = $this->_get($keyword);
					}
					if ($value !== NULL) {
						$items[$keyword] = $value;
					}
				}
			}
		}
		return $items;
	}

	public function _has($keyword)
	{
		return ($this->_get($keyword) != NULL);
	}

	public function _delete($keyword) {
		return $this->client->delete($keyword);
	}

	public function _clean($filter)
	{
		$info = $this->client->getStats('items');
		if ($info) {
			$ret = TRUE;
			foreach($info as $keyword=>$value) {
				if ($this->filterItem($filter,$keyword,$value)) {
					$ret = $ret && $this->client->delete($keyword);
				}
			}
			return $ret;
		}
		return TRUE;
	}

}
