<?php
namespace MultiCache;

abstract class CacheBase {

	var $config;	//array of runtime options, may be merged into driver-specific options
	var $crypt;

	public function __construct($config) {
		/*
		 * Parameters for driver(s)
		 */
		$this->config = $config;
		$this->crypt = new X(); //TODO encryption setup
	}

	/*
	 * Basic Functions
	 */

	public function newsert($keyword, $value = '', $time = 0) {
		$ob = new namespace\flatter($value);
		$value = $ob->serialize();//TODO encryption

		if((int)$time < 0) {
			// server-based caches will gone upon restart
			$time = 3600*24*365*5; //5 years maybe useful for for database,file
		}
		return $this->_newsert(__NAMESPACE__.'\\'.$keyword,$value,$time);
	}

	public function upsert($keyword, $value = '', $time = 0) {
		$ob = new namespace\flatter($value);
		$value = $ob->serialize();//TODO encryption

		if((int)$time < 0) {
			// server-based caches will gone upon restart
			$time = 3600*24*365*5; //5 years maybe useful for for database,file
		}
		return $this->_upsert(__NAMESPACE__.'\\'.$keyword,$value,$time);
	}

	public function get($keyword) {
		$value = $this->_get(__NAMESPACE__.'\\'.$keyword);
		if($value !== NULL) {
			$ob = new namespace\flatter();
			$ob->unserialize($value);
			return $ob->getData(); //TODO decrypt
		}
		return NULL;
	}

	function delete($keyword) {
		return $this->_delete(__NAMESPACE__.'\\'.$keyword);
	}

	function clean() {
		return $this->_clean();
	}

	function has($keyword) {
		if(method_exists($this,'_has')) {
			return $this->_has(__NAMESPACE__.'\\'.$keyword);
		}
		$data = $this->_get(__NAMESPACE__.'\\'.$keyword);
		return ($data != NULL);
	}

	/*
	 * Magic Functions
	 */
	public function function_get($name) {
		return $this->get($name);
	}

	public function function_set($name, $val) {
		if(isset($val[1]) && is_numeric($val[1])) {
			return $this->set($name,$val[0],$val[1], isset($val[2]) ? $val[2] : array() );
		} else {
			throw new \Exception("Example ->$name = array('VALUE', 300);",98);
		}
	}

	public function function_call($name, $args) {
		$str = implode(',',$args);
		eval('return $this->instance->$name('.$str.');');
	}
}

class flatter implements Serializable {

    private $data;

    public function __construct($data = NULL) {
        $this->data = $data;
    }

    public function serialize() {
		if($this->data != NULL) {
			if(is_scalar($this->data) || !is_null(@get_resource_type($this->data))) {
				return (string)$this->data;
			}
			return serialize($this->data);
		}
		return '_REALNULL_'; //prevent '' equivalent to FALSE
    }

    public function unserialize($data) {
		if ($data == 'b:0;') {
			$this->data = FALSE;
		} elseif($data == '_REALNULL_') {
			$this->data = NULL;
		} elseif(strpos('Resource id', $data) === 0) {
			$this->data = NULL; //can't usefully reinstate a (string'd)resource
		} else {
			$conv = @unserialize($data);
			if($conv === FALSE) {
				$this->data = $data;
			} else {
				$this->data = $conv;
			}
		}
    }

    public function getData() {
        return $this->data;
    }
}

?>
