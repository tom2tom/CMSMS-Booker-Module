<?php
namespace MultiCache;

abstract class CacheBase {

	protected $config;	//array of runtime options, merged into driver-specific options (if any)
	protected $keyspace = ''; //string prepended to cache keys (kinda cache-namespace)

	public function __construct($config = []) {
		$this->config = $config;
		if(!empty($config['namespace'])) {
			$this->keyspace = $config['namespace'];
		}
	}

	/* Core Functions */
	/*
	Add $keyword:$value to cache if $keyword not found in cache
	$lifetime (seconds) 0 >> perpetual, <0 >> 5 yrs, tho' server-based caches won't survive restart
	*/
	public function newsert($keyword, $value, $lifetime = 0) {
		if($value === NULL) {
			$value = '_REALNULL_';
		}
		if((int)$lifetime < 0) {
			$lifetime = 157680000; //3600*24*365*5
		}
		return $this->_newsert($this->getKey($keyword),$value,$lifetime);
	}

	/*
	If $keyword not found in cache, add $keyword:$value to cache, otherwise update to $value
	$lifetime (seconds) 0 >> perpetual, <0 >> 5 yrs, tho' server-based caches won't survive restart
	*/
	public function upsert($keyword, $value, $lifetime = 0) {
		if($value === NULL) {
			$value = '_REALNULL_';
		}
		if((int)$lifetime < 0) {
			$lifetime = 157680000; //3600*24*365*5
		}
		return $this->_upsert($this->getKey($keyword),$value,$lifetime);
	}

	public function get($keyword) {
		$value = $this->_get($this->getKey($keyword));
		if($value == '_REALNULL_') {
			$value = NULL;
		}
		return $value;
	}

	public function getall($filter=NULL) {
		return $this->_getall($filter);
	}
	
	public function has($keyword) {
		if(method_exists($this,'_has')) {
			return $this->_has($this->getKey($keyword));
		}
		$value = $this->_get($this->getKey($keyword));
		return ($value !== NULL);
	}

	public function delete($keyword) {
		return $this->_delete($this->getKey($keyword));
	}

	public function clean($filter=NULL) {
		return $this->_clean($filter);
	}

	public function setKeySpace($name) {
		$this->keyspace = $name;
	}

	public function getKeySpace() {
		return $this->keyspace;
	}
	/* Support */
	protected function getKey ($keyword) {
		$pre = ($this->keyspace) ? $this->keyspace.'\\' : '';
		return $pre.__NAMESPACE__.'\\'.$keyword;
	}

/*	private function is_regex($string) {
		if(@preg_match('/^([^[:alnum:][:space:]\\]).+(\1)[agmijsux]{0,6}$/i',$string)) {
			if(@preg_match($string,'ABC') !== FALSE) {
				 return TRUE;
			}
		}
		return FALSE;
	}
*/
   	/*
	$filter may be:
	 a regex to match against keyword, must NOT be end-user definable (injection-risk)
	 the prefix of keyword or all of it
	 a callable with keyword as argument and returning TRUE if keyword is wanted,
	  must NOT be end-user definable (injection-risk)
	 Returns TRUE by default
	*/
	protected function filterKey($filter,$keyword) {
		if($filter) {
			if(is_string($filter)) {
				if(@preg_match($filter,'') !== FALSE) {
					return preg_match($filter,$keyword);
				} else {
					return (strpos($keyword,$filter) === 0);
				}
			} elseif(is_callable($filter)) {
				return $filter($keyword);
			}
		}
		return TRUE;
	}
}

/* class flatter implements \Serializable {

	private $data;

	public function __construct($data = NULL) {
		$this->data = $data;
	}

	public function serialize() {
		if($this->data != NULL) {
			if(is_scalar($this->data) || !is_null(@get_resource_type($this->data))) {
				return (string)$this->data;
			}
//			return serialize($this->data);
			$value = var_export($this->data,TRUE);
			// HHVM fails at __set_state, so just use object-cast for now
			return str_replace('stdClass::__set_state','(object)',$value);
		}
		return '_REALNULL_'; //prevent '' equivalent to FALSE
	}

	public function unserialize($data) {
//		if ($data == 'b:0;') {
//			$this->data = FALSE;
//		} else
		if($data == '_REALNULL_') {
			$this->data = NULL;
		} elseif(is_string($data) && strpos('Resource id', $data) === 0) {
			$this->data = NULL; //can't usefully reinstate a (string'd)resource
		} else {
/ *			$conv = @unserialize($data);
			if($conv === FALSE) {
				$this->data = $data;
			} else {
				$this->data = $conv;
			}
* /
			$this->data = $data;
		}
	}

	public function getData() {
		return $this->data;
	}
}
*/

?>
