<?php
namespace MultiCache;

class Cache_database extends CacheBase implements CacheInterface {

//	protected $stored = array();
	protected $table;

	function __construct($config = array()) {
		$this->table = $config['table'];
		if($this->use_driver()) {
			parent::__construct($config);
			//TODO populate $this->stored[] from table
		} else {
			throw new \Exception('no database storage');
		}
	}

/*	function __destruct() {
		//TODO transfer $this->stored[] into table
	}
*/
	function use_driver() {
		$db = cmsms()->GetDb();
		$rs = $db->Execute("SHOW TABLES LIKE '".$this->table."'");
		if($rs) {
			$ret = ($rs->RecordCount() == 1);
			$rs->Close();
			return $ret;
		}
		return FALSE;
	}

	function _newsert($keyword, $value , $lifetime = FALSE) {
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id FROM '.$this->table.' WHERE keyword=?';
		$id = $db->GetOne($sql,array($keyword));
		if(!$id)
		{
			$value = serialize($value);
			if(!$lifetime)
				$lifetime = NULL;
			else
				$lifetime = (int)$lifetime;
			$sql = 'INSERT INTO '.$this->table.' (keyword,value,save_time,lifetime) VALUES (?,?,NOW(),?)';
			$ret = $db->Execute($sql,array($keyword,$value,$lifetime));
			return $ret;
		}
		return FALSE;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id FROM '.$this->table.' WHERE keyword=?';
		$id = $db->GetOne($sql,array($keyword));
		$value = serialize($value);
		if(!$lifetime)
			$lifetime = NULL;
		else
			$lifetime = (int)$lifetime;
		//upsert, sort-of
		if($id)
		{
			$sql = 'UPDATE '.$this->table.' SET value=?,lifetime=? WHERE cache_id=?';
			$ret = $db->Execute($sql,array($value,$lifetime,$id));
		}
		else
		{
			$sql = 'INSERT INTO '.$this->table.' (keyword,value,save_time,lifetime) VALUES (?,?,NOW(),?)';
			$ret = $db->Execute($sql,array($keyword,$value,$lifetime));
		}
		return ($ret != FALSE);
	}

	function _get($keyword) {
	//TODO retention-time excess >> ignore
		$db = cmsms()->GetDb();
		$value = $db->GetOne('SELECT value FROM '.$this->table.' WHERE keyword=?',array($keyword));
		if ($value !== FALSE) {
			return unserialize($value);
		}
		return NULL;
	}

	function _getall() {
		return NULL; //TODO allitems;
	}

	function _has($keyword) {
	//TODO retention-time excess >> ignore
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id FROM '.$this->table.' WHERE keyword=?';
		$id = $db->GetOne($sql,array($keyword));
		return $id !== FALSE;
	}

	function _delete($keyword) {
		$db = cmsms()->GetDb();
		if($db->Execute('DELETE FROM '.$this->table.' WHERE keyword=?',array($keyword))) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	function _clean() {
		$db = cmsms()->GetDb();
		$db->Execute('DELETE FROM '.$this->table);
	}

}

?>
