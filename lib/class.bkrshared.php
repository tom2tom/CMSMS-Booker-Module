<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: shared
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

class bkritem_namecmp
{
	var $coll;
	function __contruct($collator)
	{
		$this->coll = $collator;
	}
	function namecmp($a,$b)
	{
		return $this->coll->compare($a,$b);
	}
}

class bkrshared
{
	/**
	SafeGet:
	Execute SQL command(s) with minimal chance of data-race
	@sql: SQL command
	@args: array of arguments for @sql
	@mode: optional type of get - 'one','row','col','assoc' or 'all', default 'all'
	Returns: boolean indicating successful completion
	*/
	public function SafeGet($sql,$args,$mode='all')
	{
		$db = cmsms()->GetDb();
		$nt = 10;
		while($nt > 0)
		{
			$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
			$db->StartTrans();
			switch($mode)
			{
			 case 'one':
				$ret = $db->GetOne($sql,$args);
				break;
			 case 'row':
				$ret = $db->GetRow($sql,$args);
				break;
			 case 'col':
				$ret = $db->GetCol($sql,$args);
				break;
			 case 'assoc':
				$ret = $db->GetAssoc($sql,$args);
				break;
			 default:
				$ret = $db->GetAll($sql,$args);
				break;
			}
			if($db->CompleteTrans())
				return $ret;
			else
				$nt--;
		}
		return FALSE;
	}

	/**
	SafeExec:
	Execute SQL command(s) with minimal chance of data-race
	@sql: SQL command, or array of them
	@args: array of arguments for @sql, or array of them
	Returns: boolean indicating successful completion
	*/
	public function SafeExec($sql,$args)
	{
		$db = cmsms()->GetDb();
		$nt = 10;
		while($nt > 0)
		{
			$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); //this isn't perfect!
			$db->StartTrans();
			if(is_array($sql))
			{
				foreach($sql as $i=>$cmd)
					$db->Execute($cmd,$args[$i]);
			}
			else
				$db->Execute($sql,$args);
			if($db->CompleteTrans())
				return TRUE;
			else
				$nt--;
		}
		return FALSE;
	}

	/**
	GetItemID:
	Get resource/group id corresponding to @clue
	@mod reference to current module-object
	@clue: identifier of some sort (item id number|alias|name)
	*/
	public function GetItemID(&$mod,$clue)
	{
		$db = $mod->dbHandle;
		if(is_numeric($clue))
		{
			if($db->GetOne('SELECT 1 FROM '.$mod->ItemTable.' WHERE item_id=?',array($clue)))
				return (int)$clue;
		}
		$t = $db->GetOne('SELECT item_id FROM '.$mod->ItemTable.' WHERE alias=?',array($clue));
		if($t)
			return (int)$t;
		$t = $db->GetOne('SELECT item_id FROM '.$mod->ItemTable.' WHERE name=?',array($clue));
		if($t)
			return (int)$t;
		return FALSE;
	}

	/**
	GetBookingItemID:
	Get resource/group id to which @bkg_id applies
	@mod: reference to Booker module object
	@bkg_id: identfier of booking
	*/
	public function GetBookingItemID(&$mod,$bkg_id)
	{
		$sql = 'SELECT item_id FROM '.$mod->DataTable.' WHERE bkg_id=?';
		$t = self::SafeGet($sql,array($bkg_id),'one');
		if($t)
			return (int)$t;
		return FALSE;
	}

	/* *
	GetGroups(&$mod, $id=0, $returnid=0, $full=false, $anyowner=true)

	Create associative array of group-data, sorted by field 'likeorder',
	each array member's key is the group id, value is an object

	@id used in link, when $full is true
	@returnid ditto
	@full false	return group_id and name only
	@full true return all table 'raw' data for the group, plus a link TODO describe
	@anyowner true return all groups
	@anyowner false return groups whose owner is 0 or matches the current user
	*/
/*	function GetGroups(&$mod,$id=0,$returnid=0,$full=false,$anyowner=true)
	{
		$grparray = array();

		$db = $mod->dbHandle;
		if ($anyowner)
		{
			$sql = "SELECT group_id,name,likeorder FROM $mod->GroupTable ORDER BY likeorder ASC";
			$rows = $db->GetAssoc($sql);
		}
		else
		{
			$sql = "SELECT group_id,name,likeorder FROM $mod->GroupTable WHERE owner IN (0,?) ORDER BY likeorder ASC";
			$uid = get_userid(false);
			$rows = $db->GetAssoc($sql,array($uid));
		}

		if ($rows)
		{
			foreach ($rows as $cid=>$row)
			{
				$one = new stdClass();
				$one->group_id = $cid;
				$one->name = $row['name'];
				if ($full)
				{
					$one->order = $row['likeorder'];
					$one->group_link = self::GetLink($mod,$id,$returnid,$one->name,$one->name);
				}
				$grparray[$cid] = $one;
			}
		}
		return $grparray;
	}
*/

	/**
	GetItemFamily:
	Get array of 'parents' and 'siblings' of @item_id e.g. for populating a
	dropdown-object. Unlike GetItemGroups() and GetGroupItems(), no recursion
	upwards or downwards.
	@mod: reference to Booker module object
	@db: reference to current database-connection-object
	@item_id: identifier of item whose alternates are wanted
	Returns: array, keys = item_id, values = corresponding name (no empty-check)
		partitioned between groups (if any) and non-groups (if any), either or both
		such partition(s) sorted (if the system supports the locale) by name
	*/
	public function GetItemFamily(&$mod,&$db,$item_id)
	{
		$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child=?';
		$grps = $db->GetCol($sql,array($item_id));
		if($item_id >= Booker::MINGRPID)
			$grps[] = $item_id;
		if(!$grps)
		{
			//nothing extra to report
			$sql = 'SELECT name FROM '.$mod->ItemTable.' WHERE item_id=?';
			$name = $db->GetOne($sql,array($item_id));
			return array($item_id=>$name);
		}

		$col = FALSE;
		$getters = implode(',',$grps);
		//name-n-sort em
		$sql = 'SELECT item_id,name FROM '.$mod->ItemTable.' WHERE item_id IN ('.$getters.')';
		$grps = $db->GetAssoc($sql);
		if(count($grps) > 1)
		{
			if(class_exists('Collator'))
			{
				try
				{
					$col = new Collator(self::GetLocale());
					uasort($grps,array(new bkritem_namecmp($col),'namecmp'));
				} catch (Exception $e) {
					asort($grps,SORT_LOCALE_STRING);
				}
			}
			else
				asort($grps,SORT_LOCALE_STRING);
		}
		$sql = 'SELECT DISTINCT child FROM '.$mod->GroupTable.' WHERE parent IN ('.
			$getters.') AND child<'.Booker::MINGRPID;
		$mems = $db->GetCol($sql);
		if($mems)
		{
			$getters = implode(',',$mems);
			//name-n-sort em
			$sql = 'SELECT item_id,name FROM '.$mod->ItemTable.' WHERE item_id IN ('.$getters.')';
			$mems = $db->GetAssoc($sql);
			if(count($mems) > 1)
			{
				if(!$col)
				{
					if(class_exists('Collator'))
					{
						try
						{
							$col = new Collator(self::GetLocale());
							uasort($mems,array(new bkritem_namecmp($col),'namecmp'));
						} catch (Exception $e) {
							asort($mems,SORT_LOCALE_STRING);
						}
					}
					else
						asort($mems,SORT_LOCALE_STRING);
				}
				else
					uasort($grps,array(new bkritem_namecmp($collator),'namecmp'));
			}
			return $grps + $mems;
		}
		return $grps;
	}

	/**
	GetItemGroups:
	@mod: reference to Booker module object
	@db: reference to current database-connection-object
	@item_id: identifier of item whose ancestors are wanted
	Returns: array of unique item_id's, sorted on closest-ancestor-first basis,
	 or empty array
	*/
	public function GetItemGroups(&$mod,&$db,$item_id)
	{
		$ret = array();
		$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child=? ORDER BY parent,proximity,likeorder';
		$all = $db->GetCol($sql,array($item_id));
		while($all)
		{
			$ret = array_merge($ret,$all);
			$fillers = str_repeat('?,',count($all)-1).'?';
			$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child IN ('.$fillers.') ORDER BY parent,child,proximity,likeorder';
			$all = $db->GetCol($sql,$all);
			if($all)
				$all = array_diff($all,$ret);
		}
		return $ret;
	}

	/**
	GetGroupItems:
	@mod: reference to current module-object
	@gid: identifier of group to be interrogated (non-groups are ignored)
	@withgrps: optional boolean, whether to include group-ids in the result, default FALSE
	Returns: array of item-ids from depth-first scan, proximity-ordered
	*/
	public function GetGroupItems(&$mod,$gid,$withgrps=FALSE,$down=0)
	{
		$ids = array();
		if($gid >= Booker::MINGRPID)
		{
			$db = $mod->dbHandle;
			$members = $db->GetCol('SELECT DISTINCT child FROM '.$mod->GroupTable.
				' WHERE parent=? ORDER BY proximity DESC',array($gid));
			if($members)
			{
				foreach($members as $mid)
				{
					if($mid >= Booker::MINGRPID)
					{
						$downers = self::GetGroupItems($mod,$mid,$withgrps,$down+1); //recurse
						if($downers)
							$ids = array_merge($ids,$downers);
						if($withgrps && !in_array($mid,$ids))
							array_unshift($ids,(int)$mid);
					}
					else
						$ids[] = (int)$mid;
				}
			}
			if($withgrps && !in_array($mid,$ids))
				array_unshift($ids,(int)$gid);
		}
		else
			$ids[] = (int)$gid;

		if($down == 0)
		{
			if($withgrps && !in_array($gid,$ids))
					array_unshift($ids,(int)$gid);
			if(count($ids) > 1)
				$ids = array_unique($ids);
		}
		return $ids;
	}

	/**
	OrderGroups:
	Re-sequence likeorder and proximity fields in GroupTable

	@mod reference to current module-object
	@db reference to current database-connection-object
	*/
	public function OrderGroups(&$mod,&$db)
	{
		$rows = $db->GetAssoc("SELECT gid,parent FROM $mod->GroupTable ORDER BY parent,likeorder,proximity");
		if($rows)
		{
			//for each distinct parent, renumber likeorder ascending from 1
			$nt = 10;
			while($nt > 0)
			{
				$m = '-999'; //unmatchable
				$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); //mysql or postgres
				$db->StartTrans();
				foreach($rows as $gid=>$id)
				{
					if($id != $m)
					{
						$m = $id;
						$o = 1;
					}
					$db->Execute("UPDATE $mod->GroupTable SET likeorder=$o WHERE gid=$gid");
					$o++;
				}
				if($db->CompleteTrans())
					break;
				else
					$nt--;
			}

			$rows = $db->GetAssoc('SELECT gid,child FROM '.$mod->GroupTable.' ORDER BY child,proximity,likeorder');
			//for each distinct child, renumber proximity ascending from 1
			$nt = 10;
			while($nt > 0)
			{
				$m = '-999'; //unmatchable
				$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
				$db->StartTrans();
				foreach($rows as $gid=>$id)
				{
					if($id != $m)
					{
						$m = $id;
						$o = 1;
					}
					$db->Execute("UPDATE $mod->GroupTable SET proximity=$o WHERE gid=$gid");
					$o++;
				}
				if($db->CompleteTrans())
					break;
				else
					$nt--;
			}
		}
	}

	/*
	collapse_links:
	@rows: reference to array of dbase groups-table query results
	@rels: reference to array of 'processed' relations for $id
	@id: item enumerator
	*/
	private function collapse_links(&$rows,&$rels,$id)
	{
		if(!array_key_exists($id,$rels) || $rels[$id] == FALSE)
		{
			$links = array();
			foreach($rows as &$row)
			{
				if($row['child'] == $id)
				{
					$links[] = (int)$row['parent'];
					$row['child'] = -1; //prevent duplication
				}
			}
			unset($row);

			if($links)
			{
				$rels[$id] = $links;
				foreach($links as $r)
				{
					if(!array_key_exists($r,$rels))
						$rels[$r] = array(); //placeholder for breadth-first scan
				}
				foreach($links as $r)
					self::collapse_links($rows,$rels,$r); //recurse
			}
			else
				$rels[$id] = array();
		}
	}

	/*
	breadth_first:
	@relations: reference to array of item relations
	 Array keys are item enumetators, to be processed in order, by decreasing 'proximity' (aka increasing distance)
	 Array values are arrays, each with members for each group in which the key is a member
	@start: start-identifier, should be a key in @relations
	Returns: array with ordered sequence of item identifiers, or empty array
	*/
	private function breadth_first(&$relations,$start)
	{
		//init
		$lookup = array_keys($relations);
		if(!in_array($start,$lookup))
			return array();

		$visited = array();
		foreach($lookup as $key)
			$visited[$key] = 0;
		$visited[$start] = 1;
		//enqueue starting vertex
		$q = array($start);
		$path = array($start);

		while (count($q))
		{
			$t = array_shift($q);
			foreach($relations[$t] as $vertex)
			{
				if(!$visited[$vertex])
				{
					$visited[$vertex] = 1;
					$q[] = $vertex;
					$path[] = $vertex;
				}
			}
		}
		return $path;
	}

	/**
	GetItemProperty:
	@mod: reference to current Booker module object
	@item_id: idenfier of item (resource or group) for which property is/are sought
	@propname: dbase table column-name for property sought (no checks here!)
		or '*' or array of such names
	@same: optional boolean, whether all requested properties must come from the same database record, default FALSE
	@search: optional boolean, whether to check for missing values in ancestor groups (if any), default TRUE
	Returns: array with key(s) = $propnames, value(s) = corresponding property value(s) if available,
		or empty array upon error
	*/
	public function GetItemProperty(&$mod,$item_id,$propname,$same=FALSE,$search=TRUE)
	{
		$multi = is_array($propname);
		if($multi)
			$getcols = implode(',',$propname);
		else
			$getcols = $propname;

		$ret = array();
		$db = $mod->dbHandle;
		//first try for the requested data specific to the item
		$sql = "SELECT $getcols FROM $mod->ItemTable WHERE item_id=? AND active>0";
		$rs = $db->Execute($sql,array($item_id));
		if($rs)
		{
			while ($row = $rs->FetchRow())
			{
				if($multi)
				{
					foreach($propname as $one)
						$ret[$one] = NULL;
					foreach($propname as $one)
					{
						$t = $row[$one];
						if($same && is_null($t)) //this one not supplied
							break 2; //abort
						elseif(!is_null($t))
							$ret[$one] = $t;  //record it
					}
					$rs->Close();
					if(!in_array(NULL,$ret,TRUE))
						return $ret;
				}
				elseif($getcols == '*')
				{
					foreach($row as $one=>$t)
						$ret[$one] = NULL;
					foreach($row as $one=>$t)
					{
						if($same && is_null($t)) //this one not supplied
							break 2; //abort
						elseif(!is_null($t))
							$ret[$one] = $t;  //record it
					}
					if(!in_array(NULL,$ret,TRUE))
					{
						$rs->Close();
						return $ret;
					}
				}
				else //single property
				{
					$t = $row[$propname];
					if(!is_null($t))
					{
						$ret[$propname] = $t;
						$rs->Close();
						return $ret;
					}
					$ret[$propname] = NULL;
				}
			}
			$rs->Close();
		}
		else
		{
			//TODO error
			return $ret; //empty
		}

		if(!$search)
			return $ret; //empty or part-filled with values

		//revert to data for closest group in which $item_id is an [in]direct member
		$family = array();
		$finds = array($item_id);
		$sql = 'SELECT child,parent FROM '.$mod->GroupTable.' WHERE child=? ORDER BY proximity ASC';
		//sluggish one-by-one queries to preserve relations-order
		while($finds)
		{
			$parents = array();
			foreach($finds as $one)
			{
				$rows = $db->GetAll($sql,array($one));
				if($rows)
				{
					$family = array_merge($family,$rows);
					$t = array_map(function($item)
					{
						return $item['parent'];
					}, $rows);
					$parents = array_merge($parents,$t);
				}
			}
			$finds = $parents;
		}
		if($family)
		{
			$relations = array();
			self::collapse_links($family,$relations,$item_id);
			if($relations)
			{
				$path = self::breadth_first($relations,$item_id);
				array_shift($path); //current item has already been checked
				$what = ($getcols == '*') ? '*' : 'item_id,'.$getcols;
				$sql = "SELECT $what FROM $mod->ItemTable WHERE item_id IN(".implode(',',$path).') AND active>0';
				$rows = $db->GetAll($sql);
				if($rows)
				{
					foreach($path as $id)
					{
						if(!empty($rows[$id]))
						{
							$nc = 0;
							$row = $rows[$id];
							if($same)
							{
								foreach($row as $one=>$t)
								{
									if(is_null($ret[$one]) && is_null($t))
										$nc++; //force this row to be skipped
								}
							}
							if($nc == 0)
							{
								foreach($row as $one=>$t)
								{
									if(is_null($ret[$one]) && !is_null($t))
										$ret[$one] = $t;
								}
								if(!in_array(NULL,$ret,TRUE))
									return $ret;
							}
						}
					}
				}
			}
		}
		//revert to global preference value(s)
		foreach($ret as $one=>$t)
		{
			if(is_null($t))
				$ret[$one] = $mod->GetPreference('pref_'.$one,NULL);
		}
		return $ret;
	}

	/**
	Get name for an item, with fallback
	*/
	public function GetItemName(&$mod,&$idata)
	{
		if(!empty($idata['name']))
			return $idata['name'];
		else
		{
			$type = ($idata['item_id'] >= Booker::MINGRPID) ? $mod->Lang('group'):$mod->Lang('item');
			return $mod->Lang('title_noname',$type,$idata['item_id']);
		}
	}

	/**
	Get name for an item_id, with fallback
	*/
	public function GetItemNameForID(&$mod,$item_id)
	{
		$idata = self::GetItemProperty($mod,$item_id,'name',FALSE,FALSE);
		if(!empty($idata['name']))
			return $idata['name'];
		$idata = array('name'=>FALSE,'item_id'=>$item_id);
		return self::GetItemName($mod,$idata);
	}

/*	public function GetBookingItemName(&$mod,$bkg_id)
	{
		$sql =<<<EOS
SELECT I.item_id,I.name FROM {$mod->ItemTable} I
JOIN {$mod->DataTable} B ON I.item_id = B.item_id
WHERE I.active>0 AND B.bkg_id=?
EOS;
		$idata = self::SafeGet($sql,array($bkg_id),'row');
		if($idata)
			return self::GetItemName($mod,$idata);
		return FALSE;
	}
*/
	/**
	GetInterval:
	Determine an interval (in seconds) to use. (Calculated per server-time)
	@mod: reference to Booker module object
	@item_id: resource or group identifier
	@prefix: first part of paired property/column names i.e. 'slot','lead,'keep'
	@default: optional value to return if all else fails, default=3600
	Returns: interval in seconds
	*/
	public function GetInterval(&$mod,$item_id,$prefix,$default=3600)
	{
		$idata = self::GetItemProperty($mod,$item_id,array($prefix.'type',$prefix.'count'),TRUE);
		if($idata && isset($idata[$prefix.'type']) && !is_null($idata[$prefix.'type'])
		 && isset($idata[$prefix.'count']) && !is_null($idata[$prefix.'count']))
		{
			$c = (int)$idata[$prefix.'count'];
			if($c < 1)
				$c = 1;
			$t = (int)$idata[$prefix.'type']; //enum 0..5 consistent with TimeIntervals()
			switch($t)
			{
				case 0:
					if($c > 60)
						$c = ceil($c/15) * 15; //round largish minutes up to qtr-hrs
					$c *= 60;
					break;
				case 1:
					$c *= 3600;
					break;
				case 2:
					$v = 'day';
				case 3:
					if($t == 3) $v = 'week';
				case 4:
					if($t == 4) $v = 'month';
				case 5:
					if($t == 5) $v = 'year';
					if($c > 1) $v .= 's';
					$s = time();
					$e = strtotime('+'.$c.' '.$v,$s); //not localised, but near enough in this context
					$c = $e - $s;
					break;
			}
			return $c;
		}
		return $default;
	}

	/**
	GetDefaultRange:
	Determine the default timespan for which to display bookings
	@mod: reference to Booker module object
	@item_id: resource or group identifier
	Returns: display-interval enum 0..3 consistent with DisplayIntervals()
	*/
	public function GetDefaultRange(&$mod,$item_id)
	{
		$idata = self::GetItemProperty($mod,$item_id,array('leadtype','leadcount'),TRUE);
		if($idata && !is_null($idata['leadtype']) && !is_null($idata['leadcount']))
		{
			$c = (int)$idata['leadcount'];
			switch($idata['leadtype']) //enum 0..5 consistent with TimeIntervals()
			{
				case 0: //minutes
					$c = (int)$c/15; //to qtr-hrs
				case 1: //hours
					if($c > 672) //28*24
						return 3;	//year-range
					elseif($c > 168) //7*24
						return 2;	//month-range
					elseif($c > 24) //1*24
						return 1; //week-range
					else
						return 0; //day-range
				case 2: //days
					if($c > 28)
						return 3;	//year-range
					elseif($c > 7)
						return 2;	//month-range
					elseif($c > 1)
						return 1; //week-range
					else
						return 0; //day-range
				case 3: //weeks
					if($c > 4)
						return 3;	//year-range
					elseif($c > 1)
						return 2;	//month-range
					else
						return 1; //week-range
				case 4: //months
					if($c > 1)
						return 3;
					return 2;
				case 5: //years
					return 3;
			}
		}
		//default
		return (int)$mod->GetPreference('pref_showrange');
	}

	/**
	TrimRange:

	Rationalise slot start and end times in objects @dtstart and @dtend
	@dtstart if 'near' either extreme of a slot will be rounded to that extreme.
	@dtend if 'near' the end of a slot will be rounded up. The minimum difference
	between the pair will be @slen.

	@dtstart: populated DateTime object
	@dtend: ditto, may be <= @ststart
	@slen: slot length, in seconds
	@part: optional boolean, whether to accept intra-slot times for @slen >= 3600, default FALSE
	*/
	public function TrimRange($dtstart,$dtend,$slen,$part=FALSE)
	{
		if($slen >= 3600 && $part)
		{
			if($slen <= 86400)
			{
				$slop = $slen * 0.25;
				$rounder = 3600;
			}
			else
			{
				$slop = 84600;
				$rounder = 84600;
			}
		}
		else
		{
			$slop = (int)($slen/2);
			$rounder = 1; //unused
		}

		$st = $dtstart->getTimestamp();
		$nd = $dtend->getTimestamp();
		if($st > $nd)
		{
			$t = $nd;
			$nd = $st;
			$st = $t;
		}
		elseif($st == $nd)
			$nd = $st + 60; //this will change

		$t = $st % $slen;
		if($t < $slop)
			$st -= $t;
		elseif($t > $slen - $slop)
			$st = $st + $slen - $t;
		else
			$st = ceil($nd/$rounder) * $rounder;
		$dtstart->setTimestamp($st);

		$t = ($nd-$st) % $slen;
		if($t < $slop)
			$nd = $nd - $t - 1;
		elseif($t > $slen - $slop)
			$nd = $nd + $slen - $t - 1;
		else
			$nd = floor($nd/$rounder) * $rounder;

		if($nd-$st < $slen-1)
			$nd = $st + $slen - 1;

		$dtend->setTimestamp($nd);
	}

	/**
	GetUploadsPath:
	Get file-system-directory used for this module's uploads. No permission-checks.
	@mod: reference to current module-object
	Returns: path string or FALSE
	*/
	public function GetUploadsPath(&$mod)
	{
		$config = cmsms()->GetConfig();
		$fp = $config['uploads_path'];
		if($fp && is_dir($fp))
		{
			$ud = $mod->GetPreference('pref_uploadsdir','');
			if($ud)
			{
				$fp = $fp.DIRECTORY_SEPARATOR.$ud;
				if(!is_dir($fp))
					return FALSE;
			}
			return $fp;
		}
		return FALSE;
	}

	/**
	GetUploadedFiles:
	Get sorted array of filenames whose extension is @ext in the uploads dir or
	in any subdir of that
	@mod: reference to current module-object
	@ext: optional file-extension string or or ','-separated series of them or
		array of them, default '*'
	Returns: array or FALSE
	*/
	public function GetUploadedFiles(&$mod,$ext='*')
	{
		$fp = self::GetUploadsPath($mod);
		if($fp)
		{
			$flags = GLOB_NOSORT;
			if(is_array($ext))
			{
				$ext = '{'.implode(',',$ext).'}';
				$flags |= GLOB_BRACE;
			}
			elseif(strpos($ext,',') !== FALSE)
			{
				$ext = '{'.$ext.'}';
				$flags |= GLOB_BRACE;
			}
			$pattern = $fp.DIRECTORY_SEPARATOR.'*.'.$ext;
			$names = glob($pattern,$flags);
			$pattern = $fp.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*.'.$ext;
			$names2 = glob($pattern,$flags);
			$names = array_merge($names,$names2);
			if($names)
			{
				$len = strlen($fp)+1; //omit leading path+sep
				foreach($names as &$one)
				{
					$one = substr($one,$len);
				}
				unset($one);
				sort($names);
			}
			return $names;
		}
		return FALSE;
	}

	/*
	@file: name, or relative path, of uploaded or to-be-uploaded file
	*/
	private function PathURL(&$mod,$file)
	{
		$config = cmsms()->GetConfig();
		$rooturl = (empty($_SERVER['HTTPS'])) ? $config['uploads_url']:$config['ssl_uploads_url'];
		$ud = $mod->GetPreference('pref_uploadsdir','');
		$lp = ($ud) ? '/'.str_replace('\\','/',$ud) : '';
		$url = $rooturl.$lp.'/'.str_replace('\\','/',$file);
		return $url;
	}

	/**
	GetUploadURL:
	Get URL of possibly-already-uploaded file @file

	@mod: reference to current Booker module-object
	@file: name, or relative path, of uploaded file
	@exists: optional boolean, whether to check existence of @file, default TRUE
	Returns: string or FALSE
	*/
	public function GetUploadURL(&$mod,$file,$exists=TRUE)
	{
		$fp = self::GetUploadsPath($mod);
		if($fp)
		{
			if($exists)
			{
				$fp = $fp.DIRECTORY_SEPARATOR.$file;
				if(!file_exists($fp))
					return FALSE;
			}
			return self::PathURL($mod,$file);
		}
		return FALSE;
	}

	/*
	GetImageURLs:
	Get array of URLs of image files represented by @imageparam

	@mod: reference to current module-object
	@image: string with one (or more, and if so, ','-separated) name(s),
	 or uploads-directory-relative path, of image file. May be empty.
	@name: optional string to construct image 'alt', default FALSE
	Returns: array or FALSE
	*/
	public function GetImageURLs(&$mod,$image,$name=FALSE)
	{
		if(!$image)
			return FALSE;
		if(!$name)
			$name = '<'.$mod->Lang('noname').'>';
		$name = htmlentities($name,ENT_QUOTES | ENT_XHTML,FALSE);
		$title = $mod->Lang('imagetitle',$name);
		$all = array();
		$parts = explode(',',$image);
		foreach($parts as &$one)
		{
			$url = self::GetUploadURL($mod,$one);
			if($url)
			{
				$oneset = new stdClass();
				$oneset->url = $url;
				$oneset->title = $title;
				$all[] = $oneset;
			}
		}
		unset($one);
		return $all;
	}

	/**
	GetStylesURL:
	Get URL of css-styles file for @item_id

	@mod reference to current module-object
	@item_id identifier of resource being processed
	@search optional flag for inherited search, default TRUE
	Returns: string or FALSE
	*/
	public function GetStylesURL(&$mod,$item_id,$search=TRUE)
	{
		$fp = self::GetUploadsPath($mod);
		if($fp)
		{
			$idata = self::GetItemProperty($mod,$item_id,'stylesfile',TRUE,$search);
			if($idata && !empty($idata['stylesfile']))
			{
				$fp = $fp.DIRECTORY_SEPARATOR.$idata['stylesfile'];
				if(file_exists($fp))
					return self::PathURL($mod,$idata['stylesfile']);
			}
		}
		return FALSE;
	}

	/**
	AllHours(&$mod)

	Get array of hour-strings midnight..11pm, suitable for admin selector
	*/
/*	function AllHours(&$mod)
	{
		$hours = array($mod->Lang('anyhour')=>24,$mod->Lang('midnight')=>0);
		//don't need times relative to localised DateTime object
		$tStart = strtotime('01:00');
		$tEnd = strtotime('23:00');
		$h = 1;
		for ($tNow = $tStart; $tNow <= $tEnd; $tNow += 3600)
		{
			if ($h != 12)
				$key = gmdate('g a',$tNow);
			else
				$key = $mod->Lang('midday');
			$hours[$key] = $h++;
		}
		return $hours;
	}
*/

	/**
	GetTimeOffset:
	Requires: PHP >= 5.2
	Get the offset from UTC to the local timezone, in seconds. 0 upon error.
	@local_zone: a timezone identifier like 'Europe/London' (or 'UTC','GMT','')
	@local_time: optional date/time string parsable by PHP strtotime(), default 'now'
	*/
	public function GetTimeOffset($local_zone,$local_time='now')
	{
		switch($local_zone)
		{
			case FALSE:
			case 'UTC':
			case 'GMT':
				return 0;
		}
		try {
			$tz = new DateTimeZone($local_zone);
		} catch (Exception $e) {
			return 0;
		}
		try {
			$dt = new DateTime($local_time,$tz);
		} catch (Exception $e) {
			return 0;
		}
//		$dt2 = new DateTime($local_time,new DateTimeZone('UTC'));
//		$dbg = $dt->getTimestamp() - $dt2->getTimestamp();
//		$dbg2 = $dt->format('Z');
		return $dt->format('Z');
	}

	/**
	GetZoneTime:
	@zonename: string describing timezone e.g. 'Europe/Paris'
	@when: optional, absolute or relative date/time descriptor, or a timestamp, or FALSE (='now'), default 'now'
	Returns: timestamp
	*/
	public function GetZoneTime($zonename,$when='now')
	{
		$tz = new DateTimeZone('UTC');
		if($when === FALSE)
			$when = 'now';
		if(is_numeric($when))
		{
			$stamp = $when;
			$dt = new DateTime('1900-1-1',$tz);
			$dt->setTimestamp($stamp);
		}
		else
		{
			try {
				$dt = new DateTime($when,$tz);
			} catch (Exception $e) {
				return 0;
			}
			$stamp = $dt->getTimestamp();
		}

		try {
			$tz = new DateTimeZone($zonename);
			$offt = $tz->getOffset($dt);
		} catch (Exception $e) {
			$offt = 0;
		}
		return $offt + $stamp;
	}

	/*
	GetTimeZones(&$mod)
	Requires: PHP >= 5.2
 	Generate an array looking like:
	 [Pacific/Midway] => (UTC-11:00) Pacific/Midway
	 [Pacific/Pago_Pago] => (UTC-11:00) Pacific/Pago_Pago
	 [Pacific/Niue] => (UTC-11:00) Pacific/Niue
	 [Pacific/Honolulu] => (UTC-10:00) Pacific/Honolulu
	 [Pacific/Fakaofo] => (UTC-10:00) Pacific/Fakaofo
	*/
	public function GetTimeZones(&$mod,$withtime=false)
	{
		static $regions = array(
			DateTimeZone::AFRICA,
			DateTimeZone::AMERICA,
			DateTimeZone::ANTARCTICA,
			DateTimeZone::ASIA,
			DateTimeZone::ATLANTIC,
			DateTimeZone::AUSTRALIA,
			DateTimeZone::EUROPE,
			DateTimeZone::INDIAN,
			DateTimeZone::PACIFIC,
		);

		$timezones = array();
		foreach($regions as $region)
		{
			$timezones = array_merge($timezones, DateTimeZone::listIdentifiers($region));
		}
		ksort($timezones);

		$current = new DateTime();
		$timezone_offsets = array();
		foreach($timezones as $timezone)
		{
			$tz = new DateTimeZone($timezone);
			$timezone_offsets[$timezone] = $tz->getOffset($current);
		}

		$timezone_list = array();
		foreach($timezone_offsets as $timezone => $offset)
		{
			if($withtime)
			{
				$offset_prefix = $offset < 0 ? '-' : '+';
				$offset_formatted = gmdate('H:i', abs($offset));
				$pretty_offset = "GMT${offset_prefix}${offset_formatted}";
				$timezone_list[$timezone] = "(${pretty_offset}) $timezone";
			}
			else
				$timezone_list[$timezone] = $timezone;
		}

		return $timezone_list;
	}

	/**
	GetLocale:
	Returns: string
	*/
	public function GetLocale()
	{
		$cfg = cmsms()->GetConfig();
		$loc = $cfg['locale'];
		if(!$loc)
			$loc = 'en_US';
		return $loc;
	}

	/**
	RangeStamps:
	@start: UTC timestamp for start of range
	@range: enum 0..3 indicating span of range
	Returns: pair of UTC DateTime objects, first represents start of
	  day including @start, second is for start of day one-past end of range
	*/
	public function RangeStamps($start,$range)
	{
		$sdt = new DateTime('1900-1-1 0:0:0',new DateTimeZone('UTC'));
		//start of day including start
		$sdt->setTimestamp($start);
		$sdt->setTime(0,0,0); //in case
		//start of day after end
		$ndt = clone $sdt;
		switch($range)
		{
			case Booker::RANGEDAY:
				$ndt->modify('+1 day');
				break;
			case Booker::RANGEWEEK:
				$ndt->modify('+7 days');
				break;
			case Booker::RANGEMTH:
				$ndt->modify('+1 month');
				break;
			case Booker::RANGEYR:
				$ndt->modify('+1 year');
				break;
		}
		return array($sdt,$ndt);
	}

	/**
	TimeIntervals()
	Get array of characteristic time-intervals, ordinal keys and untranslated
	'single-name' values. Relevant [mainly] to slot length
	*/
	public function TimeIntervals()
	{
		return array('minute','hour','day','week','month','year');
	}

	/**
	DisplayIntervals()
	Get zoom-order array of characteristic time-intervals, ordinal keys and
	untranslated 'single-name' values. Relevant [mainly] to bookings-tables display format
	*/
	public function DisplayIntervals()
	{
		return array('day','week','month','year');
	}

	/**
	IntervalFormat:
	Construct formatted string representing @dt, after replacing any D,l,F,M
		in @format to translated names
	@mod reference to current module-object
	@dt: UTC DateTime object to be interpreted
	@format: date-format string recognised by PHP date(), or empty
	Returns: string
	*/
	public function IntervalFormat(&$mod,$dt,$format)
	{
		if(!$format)
			$format = 'j M Y';
		$finds = array();
		$placers = array();
		$repls = array();
		if(strpos($format,'D') !== FALSE) //short dayname
		{
			$finds[] = '/(?<!\\\)D/';
			$indx = $dt->format('w'); //0..6
			$placers[] = '1Q1';
			$repls[] = self::DayNames($mod,$indx,TRUE);
		}
		if(strpos($format,'l') !== FALSE) //long dayname
		{
			$finds[] = '/(?<!\\\)l/';
			$indx = $dt->format('w');
			$placers[] = '2Q2';
			$repls[] = self::DayNames($mod,$indx);
		}
		if(strpos($format,'M') !== FALSE) //short monthname
		{
			$finds[] = '/(?<!\\\)M/';
			$indx = $dt->format('n'); //1..12
			$placers[] = '3Q3';
			$repls[] = self::MonthNames($mod,$indx,TRUE);
		}
		if(strpos($format,'F') !== FALSE) //long monthname
		{
			$finds[] = '/(?<!\\\)F/';
			$indx = $dt->format('n');
			$placers[] = '4Q4';
			$repls[] = self::MonthNames($mod,$indx);
		}
		if($finds)
		{
			$format = preg_replace($finds,$placers,$format);
			$interval = $dt->format($format);
			return str_replace($placers,$repls,$interval);
		}
		else
			return $dt->format($format);
	}

	/**
	IntervalNames:

	Get one, or array of, translated time-interval-name(s).
	If @cap is TRUE, this uses ucfirst() which expects PHP locale value to be correspond to the translation of names

	@mod reference to current module-object
	@which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array of
		such indices consistent with TimeIntervals() + 1
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	public function IntervalNames(&$mod,$which,$plural=FALSE,$cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$mod->Lang($k));
		array_unshift($all,$mod->Lang('none'));
		$c = count($all);

		if(!is_array($which))
		{
			if($which >= 0 && $which < $c)
			{
				if($cap)
					return ucfirst($all[$which]);
				else
					return $all[$which];
			}
			return '';
		}
		$ret = array();
		foreach($which as $period)
		{
			if($period >= 0 && $period < $c)
			{
				$ret[$period] = ($cap) ? ucfirst($all[$period]): //for current locale
					$all[$period];
			}
		}
		return $ret;
	}

	/**
	MonthNames:

	Get one, or array of, translated month-name(s).
	If array, returned keys are values from @which i.e. 1-based

	@mod reference to current module-object
	@which: 1 (for January) .. 12 (for December), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	public function MonthNames(&$mod,$which,$short=FALSE)
	{
		$k = ($short) ? 'shortmonths' : 'longmonths';
		$all = explode(',',$mod->Lang($k));

		if (!is_array($which))
		{
			if ($which > 0 && $which < 13)
				return $all[$which-1];
			return '';
		}
		$ret = array();
		foreach ($which as $month)
		{
			if ($month > 0 && $month < 13)
				$ret[$month] = $all[$month-1];
		}
		return $ret;
	}

	/**
	DayNames:

	Get one, or array of, translated day-name(s)

	@mod reference to current module-object
	@which: 0 (for Sunday) .. 6 (for Saturday), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	public function DayNames(&$mod,$which,$short=FALSE)
	{
		$k = ($short) ? 'shortdays' : 'longdays';
		$all = explode(',',$mod->Lang($k));
		$c = count($all);

		if (!is_array($which))
		{
			if ($which >= 0 && $which < $c)
				return $all[$which];
			return '';
		}
		$ret = array();
		foreach ($which as $day)
		{
			if ($day >= 0 && $day < $c)
				$ret[$day] = $all[$day];
		}
		return $ret;
	}

	/**
	RangeNames:

	Get one, or array of, translated time-range-name(s)
	If @cap is TRUE, this uses ucfirst() which expects PHP locale value to be correspond to the translation of names

	@mod reference to current module-object
	@which: index 0 (for 'day'), 1 (for 'week') .. 3 (for 'year'), or
		array of such indices consistent with bkrshared::DisplayIntervals()
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	public function RangeNames(&$mod,$which,$plural=FALSE,$cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$mod->Lang($k));
		array_shift($all); //no minute or hour in this context
		array_shift($all);
		$c = count($all);

		if (!is_array($which))
		{
			if ($which >= 0 && $which < $c)
			{
				if($cap)
					return ucfirst($all[$which]);
				else
					return $all[$which];
			}
			return '';
		}
		$ret = array();
		foreach($which as $period)
		{
			if ($period >= 0 && $period < $c)
			{
				$ret[$period] = ($cap) ? ucfirst($all[$period]): //for current locale TODO mbstring func
					$all[$period];
			}
		}
		return $ret;
	}

	/**
	StripTags:
	Remove and/or modify substring(s) of @str which are surrounded by <> and in @tags[]
	This is a simpler variant of PHP's strip_tags(), with custom treatment of
	expressions like '</tag>', which become '<br />'
	@str: the string to clean
	@tags: array of html tag(s), each without surrounding <>
	Returns: cleaned string
	*/
	public function StripTags($str,&$tags)
	{
		foreach($tags as $tag)
		{
			$str = preg_replace('#<'.$tag.'(>|\s[^>]*>)#is','',$str);
			$str = preg_replace('#</'.$tag.'(>|\s[^>]*>)#is','<br />',$str);
		}
		return $str;
	}
}

?>
