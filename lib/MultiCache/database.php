<?php
namespace MultiCache;

class Cache_database extends CacheBase implements CacheInterface
{
	protected $table;

	public function __construct($config=array())
	{
		$this->table = $config['table'];
		if ($this->use_driver()) {
			parent::__construct($config);
		} else {
			throw new \Exception('no database storage');
		}
	}

	public function use_driver()
	{
		$db = cmsms()->GetDb();
		$rs = $db->Execute("SHOW TABLES LIKE '".$this->table."'");
		if ($rs) {
			$ret = ($rs->RecordCount() == 1);
			$rs->Close();
			return $ret;
		}
		return FALSE;
	}

	public function _newsert($keyword, $value, $lifetime=FALSE)
	{
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id FROM '.$this->table.' WHERE keyword=?';
		$id = $db->GetOne($sql,array($keyword));
		if (!$id) {
			$value = serialize($value);
			$lifetime = (int)$lifetime;
			if ($lifetime <= 0) {
				$lifetime = NULL;
			}
			$sql = 'INSERT INTO '.$this->table.' (keyword,value,savetime,lifetime) VALUES (?,?,?,?)';
			$ret = $db->Execute($sql,array($keyword,$value,time(),$lifetime));
			return $ret;
		}
		return FALSE;
	}

	public function _upsert($keyword, $value, $lifetime=FALSE)
	{
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id FROM '.$this->table.' WHERE keyword=?';
		$id = $db->GetOne($sql,array($keyword));
		$value = serialize($value);
		$lifetime = (int)$lifetime;
		if ($lifetime <= 0) {
			$lifetime = NULL;
		}
		//upsert, sort-of
		if ($id) {
			$sql = 'UPDATE '.$this->table.' SET value=?,savetime=?,lifetime=? WHERE cache_id=?';
			$ret = $db->Execute($sql,array($value,time(),$lifetime,$id));
		} else {
			$sql = 'INSERT INTO '.$this->table.' (keyword,value,savetime,lifetime) VALUES (?,?,?,?)';
			$ret = $db->Execute($sql,array($keyword,$value,time(),$lifetime));
		}
		return ($ret != FALSE);
	}

	public function _get($keyword)
	{
		$db = cmsms()->GetDb();
		$row = $db->GetRow('SELECT value,savetime,lifetime FROM '.$this->table.' WHERE keyword=?',array($keyword));
		if ($row) {
			if (is_null($row['lifetime']) ||
				 time() <= $row['savetime'] + $row['lifetime']) {
				if (!is_null($row['value'])) {
					return unserialize($row['value']);
				}
			}
		}
		return NULL;
	}

	public function _getall($filter)
	{
		$items = array();
		$db = cmsms()->GetDb();
		$info = $db->GetAll('SELECT * FROM '.$this->table);
		if ($info) {
			foreach ($info as $row) {
				$keyword = $row['keyword'];
				$value = (!is_null($row['value'])) ? unserialize($row['value']) : NULL;
				$again = is_object($value); //get it again, in case the filter played with it!
				if ($this->filterKey($filter,$keyword,$value)) {
					if ($again) {
						$value = unserialize($row['value']);
					}
					if (!is_null($value)) {
						$items[$keyword] = $value;
					}
				}
			}
		}
		return $items[];
	}

	public function _has($keyword)
	{
		$db = cmsms()->GetDb();
		$sql = 'SELECT cache_id,savetime,lifetime FROM '.$this->table.' WHERE keyword=?';
		$row = $db->GetRow($sql,array($keyword));
		if ($row) {
			if (is_null($row['lifetime']) ||
			  time() <= $row['savetime'] + $row['lifetime']) {
				return TRUE;
			}
		}
		return FALSE;
	}

	public function _delete($keyword)
	{
		$db = cmsms()->GetDb();
		if ($db->Execute('DELETE FROM '.$this->table.' WHERE keyword=?',array($keyword))) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	public function _clean($filter)
	{
		$ret = TRUE;
		$info = $db->GetAll('SELECT cache_id,keyword,value FROM '.$this->table);
		if ($info) {
			$sql = 'DELETE FROM '.$this->table.' WHERE cache_id=?';
			foreach ($info as $row) {
				$keyword = $row['keyword'];
				$value = (!is_null($row['value'])) ? unserialize($row['value']) : NULL;
				if ($this->filterKey($filter,$keyword,$value)) {
					$ret = $ret && $db->Execute($sql,array($row['cache_id']));
				}
			}
		}
		return $ret;
	}

}
