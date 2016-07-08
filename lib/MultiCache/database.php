<?php
namespace MultiCache;

class Cache_database extends CacheBase implements CacheInterface {

	protected $table;

	function __construct($config = []) {
		$this->table = $config['table'];
		if($this->use_driver()) {
			parent::__construct($config);
		} else {
			throw new \Exception('no database storage');
		}
	}

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
			$lifetime = (int)$lifetime;
			if($lifetime <= 0) {
				$lifetime = NULL;
			}
			$sql = 'INSERT INTO '.$this->table.' (keyword,value,savetime,lifetime) VALUES (?,?,?,?)';
			$ret = $db->Execute($sql,array($keyword,$value,time(),$lifetime));
			return $ret;
		}
		return FALSE;
	}

	function _upsert($keyword, $value, $lifetime = FALSE) {
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id FROM '.$this->table.' WHERE keyword=?';
		$id = $db->GetOne($sql,array($keyword));
		$value = serialize($value);
		$lifetime = (int)$lifetime;
		if($lifetime <= 0) {
			$lifetime = NULL;
		}
		//upsert, sort-of
		if($id)
		{
			$sql = 'UPDATE '.$this->table.' SET value=?,savetime=?,lifetime=? WHERE cache_id=?';
			$ret = $db->Execute($sql,array($value,time(),$lifetime,$id));
		}
		else
		{
			$sql = 'INSERT INTO '.$this->table.' (keyword,value,savetime,lifetime) VALUES (?,?,?,?)';
			$ret = $db->Execute($sql,array($keyword,$value,time(),$lifetime));
		}
		return ($ret != FALSE);
	}

	function _get($keyword) {
		$db = cmsms()->GetDb();
		$row = $db->GetRow('SELECT value,savetime,lifetime FROM '.$this->table.' WHERE keyword=?',array($keyword));
		if($row) {
			if(is_null($row['lifetime']) ||
				 time() <= $row['savetime'] + $row['lifetime']) {
				if(!is_null($row['value'])) {
					return unserialize($row['value']);
				}
			}
		}
		return NULL;
	}

	function _getall() {
		$items = [];
		$db = cmsms()->GetDb();
		$info = $db->GetAll('SELECT * FROM '.$this->table);
		if($info) {
			$foreach($info as $row) {
				if(1) { //TODO filter 'ours'
					$items[$row['keyword']] = unserialize($row['value']);
				}
			}
		}
		return $items[];
	}

	function _has($keyword) {
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id,savetime,lifetime FROM '.$this->table.' WHERE keyword=?';
		$row = $db->GetRow($sql,array($keyword));
		if($row) {
			if(is_null($row['lifetime']) ||
			  time() <= $row['savetime'] + $row['lifetime'])
					return TRUE;
			}
		}
		return FALSE;
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
	//TODO filter 'ours'
		$db->Execute('DELETE FROM '.$this->table);
		return TRUE;
	}

}

?>
