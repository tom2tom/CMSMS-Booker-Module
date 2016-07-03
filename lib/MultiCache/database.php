<?php
/*
 * khoaofgod@gmail.com
 * Website: http://www.phpfastcache.com
 * Example at our website, any bugs, problems, please visit http://faster.phpfastcache.com
 */
namespace FastCache;

class Cache_database extends CacheBase implements CacheInterface {

	var $table;

	function __construct($config = array()) {
		$this->table = $config['database']['table'];
		if($this->checkdriver()) {
			$this->setup($config);
		} else {
			throw new \Exception('no database storage');
		}
	}

/*	function __destruct() {
		$this->driver_clean();
	}
*/
	function checkdriver() {
		$db = cmsms()->GetDb();
		$rs = $db->Execute("SHOW TABLES LIKE '".$this->table."'");
		if($rs) {
			$ret = ($rs->RecordCount() == 1);
			$rs->Close();
			return $ret;
		}
		return FALSE;
	}

	function driver_set($keyword, $value = '', $time = 300, $option = array()) {
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id FROM '.$this->table.' WHERE keyword=?';
		$id = $db->GetOne($sql,array($keyword));
		$ret = FALSE;
		if(empty($option['skipExisting'])) {
			//upsert, sort-of
			if($id)
			{
				$sql = 'UPDATE '.$this->table.' SET value=? WHERE cache_id=?';
				$ret = $db->Execute($sql,array($value,$id));
			}
			else
			{
				$sql = 'INSERT INTO '.$this->table.' (keyword,value,save_time) VALUES (?,?,NOW())';
				$ret = $db->Execute($sql,array($keyword,$value));
			}
		} else {
			// skip driver
			if(!$id)
			{
				$sql = 'INSERT INTO '.$this->table.' (keyword,value,save_time) VALUES (?,?,NOW())';
				$ret = $db->Execute($sql,array($keyword,$value));
			}
		}
		if($ret) {
			$this->index[$keyword] = 1;
		}
		return ($ret != FALSE);
	}

	function driver_get($keyword, $option = array()) {
		$db = cmsms()->GetDb();
		$val = $db->GetOne('SELECT value FROM '.$this->table.' WHERE keyword=?',array($keyword));
		if ($val !== FALSE) {
			return $val;
		}
		return NULL;
	}

	function driver_getall($option = array()) {
		return array_keys($this->index);
	}

	function driver_delete($keyword, $option = array()) {
		$db = cmsms()->GetDb();
		if($db->Execute('DELETE FROM '.$this->table.' WHERE keyword=?',array($keyword))) {
			unset($this->index[$keyword]);
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function driver_stats($option = array()) {
		return array(
			'info' => '',
			'size' => count($this->index),
			'data' => '',
		);
	}

	function driver_clean($option = array()) {
		if($this->index) {
			$db = cmsms()->GetDb();
//ADODB BUG prevents this
//			$fillers = str_repeat('?,',count($this->index)-1).'?';
//			$args = array_keys($this->index);
//			$db->Execute('DELETE FROM '.$this->table.' WHERE keyword IN('.$fillers.')',$args);
			$fillers = implode(',',array_keys($this->index));
			$db->Execute('DELETE FROM '.$this->table.' WHERE keyword IN('.$fillers.')');
			$this->index = array();
		}
	}

	function driver_isExisting($keyword) {
		return array_key_exists($keyword, $this->index);
	}

}

?>
