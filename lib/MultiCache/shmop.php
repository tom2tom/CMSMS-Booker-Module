<?php
namespace MultiCache;

class Cache_shmop extends CacheBase implements CacheInterface  {

	function __construct($config = array()) {
		if($this->use_driver()) {
			parent::__construct($config);
		} else {
			throw new \Exception('no shared memory storage');
		}
	}

/*	function __destruct() {
		$this->_clean();
	}
*/
	function use_driver() {
		return extension_loaded('shmop');
	}

	function _newsert($keyword, $value, $time = FALSE) {
		if ($this->_has($keyword)) {
			return FALSE;
		}
		$ret = added_it(); //TODO
		return $ret;
	}

	function _upsert($keyword, $value, $time = FALSE) {
		if ($this->_has($keyword)) {
			$this->_delete($keyword);
		}
		//[re]set it 
		$sysid = md5(uniqid($keyword,TRUE));
		$size = strlen($value); // byte-size of the segment
		$shmid = shmop_open($sysid, 'c', 0666, $size); //don't know how server is running
		if($shmid !== FALSE) {
			if(shmop_write($shmid, $value, 0) !== FALSE) {
				$TODO = $shmid;
				return TRUE;
			}
		}
		return FALSE;
	}

	function _get($keyword) {
		$shmid = $TODO;
		$size = shmop_size($shmid);
		return shmop_read($shmid, 0, $size);
	}

	function _getall() {
		return $TODO;
	}

	function _has($keyword) {
		return $TODO;
	}

	function _delete($keyword) {
		shmop_delete($keyword);
		shmop_close($keyword);
	}

	function _clean() {
		//TODO
	}

}

?>
