<?php

namespace MultiCache;

abstract class CacheBase
{
	protected $config;	//array of runtime options, merged into driver-specific options (if any)
	protected $keyspace; //string prepended to cache keys (kinda cache-namespace)

	public function __construct($config=array())
	{
		$this->config = $config;
		$name = (isset($config['namespace'])) ? $config['namespace'] : '';
		$this->keyspace = $this->setKeySpace($name);
	}

	/* Core Functions */
	/*
	Add $keyword:$value to cache if $keyword not found in cache
	$lifetime (seconds) 0 >> perpetual, <0 >> 5 yrs, tho' server-based caches won't survive restart
	*/
	public function newsert($keyword, $value, $lifetime=0)
	{
		if ((int)$lifetime < 0) {
			$lifetime = 157680000; //3600*24*365*5
		}
		return $this->_newsert($this->getKey($keyword),$value,$lifetime);
	}

	/*
	If $keyword not found in cache, add $keyword:$value to cache, otherwise update to $value
	$lifetime (seconds) 0 >> perpetual, <0 >> 5 yrs, tho' server-based caches won't survive restart
	*/
	public function upsert($keyword, $value, $lifetime=0)
	{
		if ((int)$lifetime < 0) {
			$lifetime = 157680000; //3600*24*365*5
		}
		return $this->_upsert($this->getKey($keyword),$value,$lifetime);
	}

	public function get($keyword)
	{
		return $this->_get($this->getKey($keyword));
	}

	/*
	See filterKey() for info about $filter
	*/
	public function getall($filter=NULL)
	{
		return $this->_getall($filter);
	}
	
	public function has($keyword)
	{
		if (method_exists($this,'_has')) {
			return $this->_has($this->getKey($keyword));
		}
		$value = $this->_get($this->getKey($keyword));
		return ($value !== NULL);
	}

	public function delete($keyword)
	{
		return $this->_delete($this->getKey($keyword));
	}

	/*
	See filterKey() for info about $filter
	*/
	public function clean($filter=NULL)
	{
		return $this->_clean($filter);
	}

	public function setKeySpace($name)
	{
		if ($name)
			$name = trim($name,'\\/ \t');
		if (!$name)
			$name = __NAMESPACE__;
		$this->keyspace = $name.'\\';
	}

	public function getKeySpace()
	{
		return substr($this->keyspace,0,strlen($this->keyspace)-1);
	}
	/* Support */
	protected function getKey($keyword)
	{
		return $this->keyspace.$keyword;
	}

   	/*
	$filter may be:
	 FALSE
	 a regex to match against keyword, must NOT be end-user supplied (injection-risk)
	 a string which is the prefix of wanted keywords or a whole keyword
	 a callable with arguments (keyword,value), and returning boolean representing wanted,
	  must NOT be end-user supplied (due to injection-risk)
	 Returns TRUE by default
	*/
	protected function filterKey($filter, $keyword, $value)
	{
		if ($filter) {
			//strip 'cache-namespace'
			$offs = strlen($this->keyspace);
			$keyword = substr($keyword,$offs);
			if (is_string($filter)) {
				if (@preg_match($filter,'') !== FALSE) {
					return preg_match($filter,$keyword);
				} else {
					return (strpos($keyword,$filter) === 0);
				}
			} elseif (is_callable($filter)) {
				return $filter($keyword,$value);
			}
		}
		return TRUE;
	}

/*	protected function flatten($value)
	{
		if ($value != NULL) {
			if (is_scalar($value) || !is_null(@get_resource_type($value))) {
				return (string)$value;
			}
		}
		return serialize($value);
	}

	protected function unflatten($flatvalue)
	{
//		if ($$flatvalue == 'b:0;') {
//			$value = FALSE;
//		} else
		if ($$flatvalue == '_REALNULL_') {
			$value = NULL;
		} elseif (is_string($$flatvalue) && strpos('Resource id', $$flatvalue) === 0) {
			$value = NULL; //can't usefully reinstate a (string'd)resource
		} else {
/ *			$conv = @unserialize($$flatvalue);
			if ($conv === FALSE) {
				$value = $flatvalue;
			} else {
				$flatvalue = $conv;
			}
* /
		return $value;
	}
 */
}
