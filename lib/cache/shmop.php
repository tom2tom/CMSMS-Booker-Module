<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */

class pwfCache_shmop extends pwfCacheBase implements pwfCache  {

	function __construct($config = array()) {
		if($this->checkdriver()) {
			$this->setup($config);
		} else {
			throw new Exception('no shared memory storage');
		}
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		return extension_loaded('shmop');
	}

	function driver_set($keyword, $value = '', $time = 300, $option = array() ) {
		if (driver_isExisting($keyword)) {
			if(empty($option['skipExisting'])) {
				driver_delete($keyword, $option);
			} else {
				return false;
			}
		}
		$sysid = md5(uniqid($keyword,TRUE));
		$size = strlen($value); // byte-size of the segment
		$shmid = shmop_open($sysid, 'c', 0644, $size);
		if($shmid !== FALSE) {
			if(shmop_write($shmid, $value, 0) !== FALSE) {
				$this->index[$keyword] = $shmid;
				return true;
			}
		}
		return false;
	}

	function driver_get($keyword, $option = array()) {
		if(array_key_exists($keyword, $this->index)) {
			$shmid = $this->index[$keyword];
			$size = shmop_size($shmid);
			return shmop_read($shmid, 0, $size);
		}
		return null;
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		if (array_key_exists($keyword, $this->index)) {
			$shmid = $this->index[$keyword];
			shmop_delete($shmid);
			shmop_close($shmid);
			unset($this->index[$keyword]);
		}
	}

	function driver_stats($option = array()) {
		return array(
			'info' => 'Number of cached items',
			'size' => count($this->index),
			'data' => ''
		);
	}

	function driver_clean($option = array()) {
		foreach($this->index as $key=>$item) {
			$this->driver_delete($key, $option);
		}
	}

	function driver_isExisting($keyword) {
		return array_key_exists($keyword, $this->index);
	}

}

?>
