<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Shared
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class bkr_itemname_cmp
{
	private $coll;

	public function __construct(&$collator)
	{
		$this->coll = $collator;
	}
	public function namecmp($a, $b)
	{
		return $this->coll->compare($a,$b);
	}
}

//comparer to sort fee-condition array rows, each with members including item_id,condorder
class bkrfee_cmp
{
	private $ids;

	public function __construct(&$allids)
	{
		$this->ids = $allids;
	}
	public function feecmp($a, $b)
	{
		$ta = $a['item_id'];
		$tb = $b['item_id'];
		if ($ta != $tb) {
			$ka = array_search($ta,$this->ids);
			$kb = array_search($tb,$this->ids);
			if ($ka != $kb) {
				return ($ka-$kb); //should always happen!
			}
		}
		return ($a['condorder'] - $b['condorder']);
	}
}

class Utils
{
	/**
	ProcessTemplate:
	@mod: reference to current Booker module object
	@tplname: template identifier
	@tplvars: associative array of template variables
	@cache: optional boolean, default TRUE
	Returns: string, processed template
	*/
	public static function ProcessTemplate(&$mod, $tplname, $tplvars, $cache=TRUE)
	{
		global $smarty;
		if ($mod->before20) {
			$smarty->assign($tplvars);
			return $mod->ProcessTemplate($tplname);
		} else {
			if ($cache) {
				$cache_id = md5('bkr'.$tplname.serialize(array_keys($tplvars)));
				$lang = \CmsNlsOperations::get_current_language();
				$compile_id = md5('bkr'.$tplname.$lang);
				$tpl = $smarty->CreateTemplate($mod->GetFileResource($tplname),$cache_id,$compile_id,$smarty);
				if (!$tpl->isCached())
					$tpl->assign($tplvars);
			} else {
				$tpl = $smarty->CreateTemplate($mod->GetFileResource($tplname),NULL,NULL,$smarty,$tplvars);
			}
			return $tpl->fetch();
		}
	}

	/**
	ProcessTemplateFromData:
	@mod: reference to current Booker module object
	@data: string
	@tplvars: associative array of template variables
	No cacheing.
	Returns: string, processed template
	*/
	public static function ProcessTemplateFromData(&$mod, $data, $tplvars)
	{
		global $smarty;
		if ($mod->before20) {
			$smarty->assign($tplvars);
			return $mod->ProcessTemplateFromData($data);
		} else {
			$tpl = $smarty->CreateTemplate('eval:'.$data,NULL,NULL,$smarty,$tplvars);
			return $tpl->fetch();
		}
	}

	/**
	SafeGet:
	Execute SQL command(s) with minimal chance of data-race
	@sql: SQL command
	@args: array of arguments for @sql
	@mode: optional type of get - 'one','row','col','assoc' or 'all', default 'all'
	Returns: boolean indicating successful completion
	*/
	public function SafeGet($sql, $args, $mode='all')
	{
		$db = cmsms()->GetDb();
		$nt = 10;
		while ($nt > 0) {
			$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
			$db->StartTrans();
			switch ($mode) {
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
			if ($db->CompleteTrans())
				return $ret;
			else {
				$nt--;
				usleep(50000);
			}
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
	public function SafeExec($sql, $args)
	{
		$db = cmsms()->GetDb();
		$nt = 10;
		while ($nt > 0) {
			$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); //this isn't perfect!
			$db->StartTrans();
			if (is_array($sql)) {
				foreach ($sql as $i=>$cmd) {
					$db->Execute($cmd,$args[$i]);
				}
			} else
				$db->Execute($sql,$args);
			if ($db->CompleteTrans())
				return TRUE;
			else {
				$nt--;
				usleep(50000);
			}
		}
		return FALSE;
	}

	/**
	GetItemID:
	Get resource/group id corresponding to @clue
	@mod reference to current module-object
	@clue: identifier of some sort (item id number|alias|name)
	*/
	public function GetItemID(&$mod, $clue)
	{
		$db = $mod->dbHandle;
		if (is_numeric($clue)) {
			if ($db->GetOne('SELECT 1 FROM '.$mod->ItemTable.' WHERE item_id=?',array($clue)))
				return (int)$clue;
		}
		$t = $db->GetOne('SELECT item_id FROM '.$mod->ItemTable.' WHERE alias=?',array($clue));
		if ($t)
			return (int)$t;
		$t = $db->GetOne('SELECT item_id FROM '.$mod->ItemTable.' WHERE name=?',array($clue));
		if ($t)
			return (int)$t;
		return FALSE;
	}

	/**
	GetBookingItemID:
	Get resource/group id to which @bkg_id applies
	@mod: reference to Booker module object
	@bkg_id: identfier of booking
	*/
	public function GetBookingItemID(&$mod, $bkg_id)
	{
		$sql = 'SELECT item_id FROM '.$mod->DataTable.' WHERE bkg_id=?';
		$t = self::SafeGet($sql,array($bkg_id),'one');
		if ($t)
			return (int)$t;
		return FALSE;
	}

	/**
	GetItemFamily:
	Get array of 'parents' and 'siblings' of @item_id e.g. for populating a
	dropdown-object. Unlike GetItemGroups() and GetGroupItems(), no further
	recursion upwards or downwards.
	@mod: reference to Booker module object
	@item_id: identifier of item whose alternates are wanted
	Returns: array, keys = item_id, values = corresponding name (no empty-check)
		partitioned between groups (if any) and non-groups (if any), either or both
		such partition(s) sorted (if the system supports the locale) by name
	*/
	public function GetItemFamily(&$mod, $item_id)
	{
		$db = $mod->dbHandle;
		$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child=?';
		$grps = $db->GetCol($sql,array($item_id));
		if ($item_id >= \Booker::MINGRPID)
			$grps[] = $item_id;
		if (!$grps) {
			//nothing extra to report
			$sql = 'SELECT name FROM '.$mod->ItemTable.' WHERE item_id=? AND active>0';
			$name = $db->GetOne($sql,array($item_id));
			return array($item_id=>$name);
		}

		$col = FALSE;
		$getters = implode(',',$grps);
		//name-n-sort em
		$sql = 'SELECT item_id,name FROM '.$mod->ItemTable.' WHERE item_id IN ('.$getters.') AND active>0';
		$grps = $db->GetAssoc($sql);
		if (count($grps) > 1) {
			if (class_exists('Collator')) {
				try {
					$col = new \Collator(self::GetLocale());
					uasort($grps,array(new bkr_itemname_cmp($col),'namecmp'));
				} catch (Exception $e) {
					asort($grps,SORT_LOCALE_STRING);
				}
			} else {
				asort($grps,SORT_LOCALE_STRING);
			}
		}
		$sql = 'SELECT DISTINCT child FROM '.$mod->GroupTable.' WHERE parent IN ('.
			$getters.') AND child<'.\Booker::MINGRPID;
		$mems = $db->GetCol($sql);
		if ($mems) {
			$getters = implode(',',$mems);
			//name-n-sort em
			$sql = 'SELECT item_id,name FROM '.$mod->ItemTable.' WHERE item_id IN ('.$getters.')';
			$mems = $db->GetAssoc($sql);
			if (count($mems) > 1) {
				if ($col) {
					uasort($mems,array(new bkr_itemname_cmp($col),'namecmp'));
				} elseif (class_exists('Collator')) {
					try {
						$col = new \Collator(self::GetLocale());
						uasort($mems,array(new bkr_itemname_cmp($col),'namecmp'));
					} catch (Exception $e) {
						asort($mems,SORT_LOCALE_STRING);
					}
				} else {
					asort($mems,SORT_LOCALE_STRING);
				}
			}
			return $grps + $mems;
		}
		return $grps;
	}

	/**
	GetItemGroups:
	Get proximity-sorted array of 'ancestors' of @item_id i.e. closest-ancestor-first
	@mod: reference to Booker module object
	@item_id: identifier of item whose ancestors are wanted
	Returns: array of unique item_id's, or empty array
	*/
	public function GetItemGroups(&$mod, $item_id)
	{
		$ret = array();
		$db = $mod->dbHandle;
		$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child=? ORDER BY proximity,likeorder';
		$all = $db->GetCol($sql,array($item_id));
		while ($all) {
			$ret = array_merge($ret,$all);
			$fillers = str_repeat('?,',count($all)-1);
			$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child IN ('.$fillers.'?) ORDER BY proximity,likeorder';
			$all = $db->GetCol($sql,$all);
			if ($all)
				$all = array_diff($all,$ret);
		}
		return $ret;
	}

	/**
	GetGroupItems:
	Get reverse-proximity-ordered array of 'descendants' of @gid
	@mod: reference to current module-object
	@gid: identifier of group to be interrogated (non-groups are ignored)
	@withgrps: optional boolean, whether to include groups in the result, default FALSE
	Returns: array of item-ids from depth-first scan
	*/
	public function GetGroupItems(&$mod, $gid, $withgrps=FALSE, $down=0)
	{
		$ids = array();
		if ($gid >= \Booker::MINGRPID) {
			$db = $mod->dbHandle;
			$members = $db->GetCol('SELECT DISTINCT child FROM '.$mod->GroupTable.
				' WHERE parent=? ORDER BY proximity DESC',array($gid));
			if ($members) {
				foreach ($members as $mid) {
					if ($mid >= \Booker::MINGRPID) {
						$downers = self::GetGroupItems($mod,$mid,$withgrps,$down+1); //recurse
						if ($downers)
							$ids = array_merge($ids,$downers);
						if ($withgrps && !in_array($mid,$ids))
							array_unshift($ids,(int)$mid);
					} else
						$ids[] = (int)$mid;
				}
			}
			if ($withgrps && !in_array($mid,$ids))
				array_unshift($ids,(int)$gid);
		} else
			$ids[] = (int)$gid;

		if ($down == 0) {
			if ($withgrps && !in_array($gid,$ids))
				array_unshift($ids,(int)$gid);
			if (count($ids) > 1)
				$ids = array_unique($ids);
		}
		return $ids;
	}

	/* *
	GetGroups(&$mod, $id=0, $returnid=0, $full=FALSE, $anyowner=TRUE)

	Create associative array of group-data, sorted by field 'likeorder',
	each array member's key is the group id, value is an object

	@id used in link, when $full is TRUE
	@returnid ditto
	@full FALSE	return group_id and name only
	@full TRUE return all table 'raw' data for the group, plus a link TODO describe
	@anyowner TRUE return all groups
	@anyowner FALSE return groups whose owner is 0 or matches the current user
	*/
/*	public function GetGroups(&$mod,$id=0, $returnid=0, $full=FALSE, $anyowner=TRUE)
	{
		$grparray = array();

		$db = $mod->dbHandle;
		if ($anyowner) {
			$sql = "SELECT group_id,name,likeorder FROM $mod->GroupTable ORDER BY likeorder ASC";
			$rows = $db->GetAssoc($sql);
		} else {
			$sql = "SELECT group_id,name,likeorder FROM $mod->GroupTable WHERE owner IN (0,?) ORDER BY likeorder ASC";
			$uid = get_userid(FALSE);
			$rows = $db->GetAssoc($sql,array($uid));
		}

		if ($rows) {
			foreach ($rows as $cid=>$row) {
				$one = new \stdClass();
				$one->group_id = $cid;
				$one->name = $row['name'];
				if ($full) {
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
	OrderGroups:
	Re-sequence likeorder and proximity fields in GroupTable

	@mod reference to current module-object
	*/
	public function OrderGroups(&$mod)
	{
		$db = $mod->dbHandle;
		$rows = $db->GetAssoc('SELECT gid,parent FROM '.$mod->GroupTable.' ORDER BY parent,likeorder,proximity');
		if ($rows) {
			//for each distinct parent, renumber likeorder ascending from 1
			$nt = 10;
			while ($nt > 0) {
				$m = '-999'; //unmatchable
				$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); //mysql or postgres
				$db->StartTrans();
				foreach ($rows as $gid=>$id) {
					if ($id != $m) {
						$m = $id;
						$o = 1;
					}
					$db->Execute("UPDATE $mod->GroupTable SET likeorder=$o WHERE gid=$gid");
					$o++;
				}
				if ($db->CompleteTrans())
					break;
				else
					$nt--;
			}

			$rows = $db->GetAssoc('SELECT gid,child FROM '.$mod->GroupTable.' ORDER BY child,proximity,likeorder');
			//for each distinct child, renumber proximity ascending from 1
			$nt = 10;
			while ($nt > 0) {
				$m = '-999'; //unmatchable
				$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
				$db->StartTrans();
				foreach ($rows as $gid=>$id) {
					if ($id != $m) {
						$m = $id;
						$o = 1;
					}
					$db->Execute("UPDATE $mod->GroupTable SET proximity=$o WHERE gid=$gid");
					$o++;
				}
				if ($db->CompleteTrans())
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
	private function collapse_links(&$rows, &$rels, $id)
	{
		if (!array_key_exists($id,$rels) || $rels[$id] == FALSE) {
			$links = array();
			foreach ($rows as &$row) {
				if ($row['child'] == $id) {
					$links[] = (int)$row['parent'];
					$row['child'] = -1; //prevent duplication
				}
			}
			unset($row);

			if ($links) {
				$rels[$id] = $links;
				foreach ($links as $r) {
					if (!array_key_exists($r,$rels))
						$rels[$r] = array(); //placeholder for breadth-first scan
				}
				foreach ($links as $r) {
					self::collapse_links($rows,$rels,$r); //recurse
				}
			} else
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
	private function breadth_first(&$relations, $start)
	{
		//init
		$lookup = array_keys($relations);
		if (!in_array($start,$lookup))
			return array();

		$visited = array();
		foreach ($lookup as $key) {
			$visited[$key] = 0;
		}
		$visited[$start] = 1;
		//enqueue starting vertex
		$q = array($start);
		$path = array($start);

		while (count($q)) {
			$t = array_shift($q);
			foreach ($relations[$t] as $vertex) {
				if (!$visited[$vertex]) {
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
	@item_id: identifier of resource or group for which property/ies is/are sought
	@propname: ItemTable field-name for property sought or '*' or ','-separated
		series of such names or array of such names (no checks here!)
	@same: optional boolean, whether all requested properties must come from the same database record, default FALSE
	@search: optional boolean, whether to check for missing values in ancestor groups (if any), default TRUE
	Returns: array with key(s) = $propnames, value(s) = corresponding property value(s) if available or NULL,
		or empty array upon error
	*/
	public function GetItemProperty(&$mod, $item_id, $propname, $same=FALSE, $search=TRUE)
	{
		if ($search) {
			$found = $this->GetHeritableProperty($mod,$item_id,$propname);
			if (!$found)
				return array();
			if (!is_array($propname)) {
				$propname = explode(',',$propname);
			}

			if ($propname[0] == '*') {
				$gets = array_fill_keys(array_keys(reset($found)),NULL);
			} else {
				$gets = array_fill_keys(array_filter($propname),NULL);//no name-validation, only presence-checks
			}
			if ($same)
				$rc = count($gets);
			$ret = array();
			foreach ($found as $row) {
				foreach ($gets as $k=>$val) {
					if (!isset($ret[$k]) && $row[$k]) {
						$ret[$k] = $row[$k];
					}
				}
				if ($same) {
					if (count($ret) < $rc)
						$ret = array(); //keep looking
					else
						break;
				}
			}
			return array_merge($gets,$ret);
		} else { //no search
			if (!is_array($propname)) {
				$propname = explode(',',$propname);
			}
			$getcols = implode(',',array_filter($propname)); //no name-validation, only presence-checks

			$db = $mod->dbHandle;
			$sql = 'SELECT '.$getcols.' FROM '.$mod->ItemTable.' WHERE item_id=?';
			$found = $db->GetRow($sql,array($item_id));
			if ($found)
				return $found;
			return array();
		}
	}

	/**
	GetHeritableProperty:
	@mod: reference to current Booker module object
	@item_id: identifier of resource or group for which property/ies is/are sought
	@propname: ItemTable field-name for property sought or '*' or ','-separated
		series of such names or array of such names (no checks here!)
	Returns: proximity-ordered array with members = property-values for $item_id
		and all its ancestors and corresponding module-preference values
	*/
	public function GetHeritableProperty(&$mod, $item_id, $propname)
	{
		if (!is_array($propname)) {
			$propname = explode(',',$propname);
		}
		$getcols = implode(',',array_filter($propname)); //no name-validation, only presence-checks

		$getids = array($item_id);
		$db = $mod->dbHandle;
		$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child=? ORDER BY proximity,likeorder';
		$all = $db->GetCol($sql,array($item_id));
		while ($all) {
			$getids = array_merge($getids,$all);
			$fillers = str_repeat('?,',count($all)-1);
			$sql = 'SELECT DISTINCT parent FROM '.$mod->GroupTable.' WHERE child IN ('.$fillers.'?) ORDER BY proximity,likeorder';
			$all = $db->GetCol($sql,$all);
			if ($all)
				$all = array_diff($all,$getids);
		}

		if ($getcols != '*')
			$getcols = 'item_id,'.$getcols;
		$sql = 'SELECT '.$getcols.' FROM '.$mod->ItemTable.' WHERE item_id IN('.implode(',',$getids).')';
		$found = $db->GetAssoc($sql);
		if ($found) {
			if ($getcols != '*')
				$getcols = substr($getcols,8); //strip the prepended 'item_id,'
			if (!is_array(reset($found))) {
				foreach ($found as &$val) {
					$val = array($getcols=>$val);
				}
				unset($val);
			}
			if ($getcols == '*') {
				//put id's back into returned values
				foreach ($found as $k=>&$val) {
					$val['item_id'] = $k;
				}
				unset($val);
			}

			$all = array_flip($getids);
			uksort($found,function($a,$b) use($all) {
				return ($all[$a] - $all[$b]);
			});
			$prefs = reset($found);
		} else {
			$found = array();
			$prefs = array_flip($propname);
		}

		foreach ($prefs as $k=>&$val) {
			$val = $mod->GetPreference('pref_'.$k);
			if (!$val)
				$val = NULL;
		}
		unset($val);
		$found[-1] = $prefs; //append preferences
		return array_values($found);
	}

	/**
	GetOneHeritableProperty:
	Wrapper for GetHeritableProperty() which collapses results for @propname
	into a non-associative array
	@mod: reference to current Booker module object
	@item_id: identifier of resource or group for which property/ies is/are sought
	@propname: ItemTable field-name for property sought or '*' or ','-separated
		series of such names or array of such names (no checks here!)
	*/
	public function GetOneHeritableProperty(&$mod, $item_id, $propname)
	{
		$propdata = $this->GetHeritableProperty($mod, $item_id, $propname);
		$ret = array();
		if ($propdata) {
			foreach ($propdata as $one) {
				$ret[] = $one[$propname];
			}
		}
		return $ret;
	}

	/**
	Get name for an item, with fallback
	*/
	public function GetItemName(&$mod, &$idata)
	{
		if (!empty($idata['name']))
			return $idata['name'];
		else {
			$type = ($idata['item_id'] >= \Booker::MINGRPID) ? $mod->Lang('group'):$mod->Lang('item');
			return $mod->Lang('title_noname',$type,$idata['item_id']);
		}
	}

	/**
	Get name for an item_id, with fallback
	*/
	public function GetItemNameForID(&$mod, $item_id)
	{
		$idata = self::GetItemProperty($mod,$item_id,'name',FALSE,FALSE);
		if (!empty($idata['name']))
			return $idata['name'];
		$idata = array('name'=>FALSE,'item_id'=>$item_id);
		return self::GetItemName($mod,$idata);
	}

	/**
	GetFeeSignature:
	Get identifier usable for cross-resource fee-comparisons
	@row: array of fee-data including members: slottype,slotcount,fee,feecondition
	Returns: 32-bit integer
	*/
	public function GetFeeSignature($row)
	{
		$sig = '';
		foreach (array('slottype','slotcount','fee','feecondition') as $k) {
			$sig .= (isset($row[$k]) && $row[$k] !== NULL) ? $row[$k] : 'NULL';
		}
		return crc32($sig);
	}

	/**
	GetItemFee:
	@mod: reference to current Booker module object
	@item_id: identifier of item (resource or group) for which fee(s) is/are sought
	@search: optional boolean, whether to check for missing fee in ancestor groups (if any), default FALSE
	@conditional: optional string, condition to check payability e.g. time or user-class,
	 may be '' to match explicit non-condition, may be 'ID<:>NUM' to match the
	 stored condition for @item_id and condition_id==NUM, default FALSE means
	 unconditional/no check needed
	Returns: the first-found non-NULL fee if @conditional === FALSE, or
	 the first-found non-NULL fee whose condition matches @conditional !== FALSE, or
	 boolean FALSE if there are no relevant fee-data (conditional or otherwise)
	*/
	public function GetItemFee(&$mod, $item_id, $search=FALSE, $conditional=FALSE)
	{
		$db = $mod->dbHandle;
		if ($search) {
			$args = self::GetItemGroups($mod,$item_id);
			array_unshift($args, $item_id); //priority-ordered for checking
			$fillers = str_repeat('?,',count($args)-1);
			$sql = 'SELECT item_id,fee,feecondition,condorder FROM '.$mod->FeeTable.
			' WHERE item_id IN ('.$fillers.'?) AND active=1 ORDER BY item_id,condorder'; //a bit of downstream sorting might help ...
			$fees = $db->GetAll($sql,$args); //NB ordered by item_id prob not what we want: $args has it
			if ($fees) {
				usort($fees,array(new bkrfee_cmp($args),'feecmp'));
			}
		} else {
			$sql = 'SELECT fee,feecondition FROM '.$mod->FeeTable.
			' WHERE item_id=? AND active=1 ORDER BY condorder';
			$fees = $db->GetAll($sql,array($item_id));
		}

		if ($fees) {
			if (strpos($conditional,'ID<:>') === 0) {
				$cid = substr($conditional,5);
				$conditional = $db->GetOne('SELECT feecondition FROM '.
					$mod->FeeTable.' WHERE item_id=? AND condition_id=?',array($item_id,$cid));
				if ($conditional === FALSE)
					return FALSE; //error
				elseif (!$conditional)
					$conditional = '';
			}

			foreach ($fees as $one) {
				if ($one['fee'] != NULL) {
					if ($conditional === FALSE) {
						return $one['fee']; //CHECKME
					} elseif ($one['feecondition']) {
	//TODO $this->FeeTable stuff
						if (0) {//TODO check for conforming condition
							return $one['fee'];
						}
					} elseif ($conditional === '') {
						return $one['fee'];
					}
				}
			}
		}
		return FALSE;
	}

	/**
	GetItemPayable:
	@mod: reference to current Booker module object
	@item_id: identifier of item (resource or group) for which property is/are sought
	@search: optional boolean, whether to check for missing fee in ancestor groups (if any), default FALSE
	@conditional: optional string, condition to check payability e.g. time or user-class,
	 may be '' to match explicit non-condition, may be 'ID<:>NUM' to match the
	 stored condition for @item_id and condition_id==NUM, default FALSE means
	 unconditional/no check needed
	Returns: boolean, FALSE if there are no relevant fee-data, or
	 TRUE if @conditional === FALSE there's any fee > 0, or
	 TRUE if @conditional !== FALSE and there's a fee > 0 for a condition that matches it
	 or FALSE
	*/
	public function GetItemPayable(&$mod, $item_id, $search=FALSE, $conditional=FALSE)
	{
		$fee = self::GetItemFee($mod,$item_id,$search,$conditional);
		return ($fee !== FALSE && $fee > 0);
	}

	/**
	GetDefaultRange:
	Determine the default timespan for which to display bookings
	@mod: reference to Booker module object
	@item_id: resource or group identifier
	Returns: display-interval enum 0..3 consistent with DisplayIntervals()
	*/
	public function GetDefaultRange(&$mod, $item_id)
	{
		$idata = self::GetItemProperty($mod,$item_id,array('leadtype','leadcount'),TRUE);
		if ($idata && !is_null($idata['leadtype']) && !is_null($idata['leadcount'])) {
			$c = (int)$idata['leadcount'];
			switch ($idata['leadtype']) { //enum 0..5 consistent with TimeIntervals()
			 case 0: //minutes
				$c = (int)$c/15; //to qtr-hrs
			 case 1: //hours
				if ($c > 672) //28*24
					return 3;	//year-range
				elseif ($c > 168) //7*24
					return 2;	//month-range
				elseif ($c > 24) //1*24
					return 1; //week-range
				else
					return 0; //day-range
			 case 2: //days
				if ($c > 28)
					return 3;	//year-range
				elseif ($c > 7)
					return 2;	//month-range
				elseif ($c > 1)
					return 1; //week-range
				else
					return 0; //day-range
			 case 3: //weeks
				if ($c > 4)
					return 3;	//year-range
				elseif ($c > 1)
					return 2;	//month-range
				else
					return 1; //week-range
			 case 4: //months
				if ($c > 1)
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

	Rationalise slot start and end times in objects @dts and @dte
	@dts if 'near' either extreme of a slot will be rounded to that extreme.
	@dte if 'near' the end of a slot will be rounded up. The minimum difference
	between the pair will be @slen.

	@dts: populated DateTime object
	@dte: ditto, may be <= @ststart
	@slen: slot length, in seconds
	@part: optional boolean, whether to accept intra-slot times for @slen >= 3600, default FALSE
	*/
	public function TrimRange($dts, $dte, $slen, $part=FALSE)
	{
		if ($slen >= 3600 && $part) {
			if ($slen <= 86400) {
				$slop = $slen * 0.25;
				$rounder = 3600;
			} else {
				$slop = 84600;
				$rounder = 84600;
			}
		} else {
			$slop = (int)($slen/2);
			$rounder = 1; //unused
		}

		$st = $dts->getTimestamp();
		$nd = $dte->getTimestamp();
		if ($st > $nd) {
			$t = $nd;
			$nd = $st;
			$st = $t;
		} elseif ($st == $nd)
			$nd = $st + 60; //this will change

		$t = $st % $slen;
		if ($t < $slop)
			$st -= $t;
		elseif ($t > $slen - $slop)
			$st = $st + $slen - $t;
		else
			$st = ceil($nd/$rounder) * $rounder;
		$dts->setTimestamp($st);

		$t = ($nd-$st) % $slen;
		if ($t < $slop)
			$nd = $nd - $t - 1;
		elseif ($t > $slen - $slop)
			$nd = $nd + $slen - $t - 1;
		else
			$nd = floor($nd/$rounder) * $rounder;

		if ($nd-$st < $slen-1)
			$nd = $st + $slen - 1;

		$dte->setTimestamp($nd);
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
		if ($fp && is_dir($fp)) {
			$ud = $mod->GetPreference('pref_uploadsdir','');
			if ($ud) {
				$fp = $fp.DIRECTORY_SEPARATOR.$ud;
				if (!is_dir($fp))
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
	public function GetUploadedFiles(&$mod, $ext='*')
	{
		$fp = self::GetUploadsPath($mod);
		if ($fp) {
			$flags = GLOB_NOSORT;
			if (is_array($ext)) {
				$ext = '{'.implode(',',$ext).'}';
				$flags |= GLOB_BRACE;
			} elseif (strpos($ext,',') !== FALSE) {
				$ext = '{'.$ext.'}';
				$flags |= GLOB_BRACE;
			}
			$pattern = $fp.DIRECTORY_SEPARATOR.'*.'.$ext;
			$names = glob($pattern,$flags);
			$pattern = $fp.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*.'.$ext;
			$names2 = glob($pattern,$flags);
			$names = array_merge($names,$names2);
			if ($names) {
				$len = strlen($fp)+1; //omit leading path+sep
				foreach ($names as &$one) {
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
	private function PathURL(&$mod, $file)
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
	public function GetUploadURL(&$mod, $file, $exists=TRUE)
	{
		$fp = self::GetUploadsPath($mod);
		if ($fp) {
			if ($exists) {
				$fp = $fp.DIRECTORY_SEPARATOR.$file;
				if (!file_exists($fp))
					return FALSE;
			}
			return self::PathURL($mod,$file);
		}
		return FALSE;
	}

	/**
	GetImageURLs:
	Get array of URLs of image files represented by @imageparam

	@mod: reference to current module-object
	@image: string with one (or more, and if so, ','-separated) name(s),
	 or uploads-directory-relative path, of image file. May be empty.
	@name: optional string to construct image 'alt', default FALSE
	Returns: array or FALSE
	*/
	public function GetImageURLs(&$mod, $image, $name=FALSE)
	{
		if (!$image)
			return FALSE;
		if (!$name)
			$name = '<'.$mod->Lang('noname').'>';
		$name = htmlentities($name,ENT_QUOTES | ENT_XHTML,FALSE);
		$title = $mod->Lang('picture_type',$name);
		$all = array();
		$parts = explode(',',$image);
		foreach ($parts as &$one) {
			$url = self::GetUploadURL($mod,$one);
			if ($url) {
				$oneset = new \stdClass();
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
	public function GetStylesURL(&$mod, $item_id, $search=TRUE)
	{
		$fp = self::GetUploadsPath($mod);
		if ($fp) {
			$idata = self::GetItemProperty($mod,$item_id,'stylesfile',TRUE,$search);
			if ($idata && !empty($idata['stylesfile'])) {
				$fp = $fp.DIRECTORY_SEPARATOR.$idata['stylesfile'];
				if (file_exists($fp))
					return self::PathURL($mod,$idata['stylesfile']);
			}
		}
		return FALSE;
	}

	/**
	AllHours(&$mod)

	Get array of hour-strings midnight..11pm, suitable for admin selector
	*/
/*	public function AllHours(&$mod)
	{
		$hours = array($mod->Lang('title_anytime')=>24,$mod->Lang('midnight')=>0);
		//don't need times relative to localised DateTime object
		$tStart = strtotime('01:00');
		$tEnd = strtotime('23:00');
		$h = 1;
		for ($tNow = $tStart; $tNow <= $tEnd; $tNow += 3600) {
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
	Requires: PHP 5.2+
	Get the offset from UTC to the local timezone, in seconds. 0 upon error.
	@local_zone: a timezone identifier like 'Europe/London' (or 'UTC','GMT','')
	@local_time: optional date/time string parsable by PHP strtotime(), default 'now'
	*/
	public function GetTimeOffset($local_zone, $local_time='now')
	{
		switch ($local_zone) {
		 case FALSE:
		 case 'UTC':
		 case 'GMT':
			return 0;
		}
		try {
			$tz = new \DateTimeZone($local_zone);
		} catch (Exception $e) {
			return 0;
		}
		try {
			$dt = new \DateTime($local_time,$tz);
		} catch (Exception $e) {
			return 0;
		}
//		$dt2 = new \DateTime($local_time,new \DateTimeZone('UTC'));
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
	public function GetZoneTime($zonename, $when='now')
	{
		$tz = new \DateTimeZone('UTC');
		if ($when === FALSE)
			$when = 'now';
		if (is_numeric($when)) {
			$stamp = $when;
			$dt = new \DateTime('1900-1-1',$tz);
			$dt->setTimestamp($stamp);
		} else {
			try {
				$dt = new \DateTime($when,$tz);
			} catch (Exception $e) {
				return 0;
			}
			$stamp = $dt->getTimestamp();
		}
//$this->Crash();
		try {
			$tz = new \DateTimeZone($zonename);
			$offt = $tz->getOffset($dt);
		} catch (Exception $e) {
			$offt = 0;
		}
		return $offt + $stamp;
	}

	/**
	GetTimeZones(&$mod)
	Requires: PHP >= 5.2
	Generate an array looking like:
	 [Pacific/Midway] => (UTC-11:00) Pacific/Midway
	 [Pacific/Pago_Pago] => (UTC-11:00) Pacific/Pago_Pago
	 [Pacific/Niue] => (UTC-11:00) Pacific/Niue
	 [Pacific/Honolulu] => (UTC-10:00) Pacific/Honolulu
	 [Pacific/Fakaofo] => (UTC-10:00) Pacific/Fakaofo
	*/
	public function GetTimeZones(&$mod, $withtime=FALSE)
	{
		static $regions = array(
			\DateTimeZone::AFRICA,
			\DateTimeZone::AMERICA,
			\DateTimeZone::ANTARCTICA,
			\DateTimeZone::ASIA,
			\DateTimeZone::ATLANTIC,
			\DateTimeZone::AUSTRALIA,
			\DateTimeZone::EUROPE,
			\DateTimeZone::INDIAN,
			\DateTimeZone::PACIFIC,
		);

		$timezones = array();
		foreach ($regions as $region) {
			$timezones = array_merge($timezones, \DateTimeZone::listIdentifiers($region));
		}
		ksort($timezones);

		$current = new \DateTime();
		$timezone_offsets = array();
		foreach ($timezones as $timezone) {
			$tz = new \DateTimeZone($timezone);
			$timezone_offsets[$timezone] = $tz->getOffset($current);
		}

		$timezone_list = array();
		foreach ($timezone_offsets as $timezone => $offset) {
			if ($withtime) {
				$offset_prefix = $offset < 0 ? '-' : '+';
				$offset_formatted = gmdate('H:i', abs($offset));
				$pretty_offset = "GMT${offset_prefix}${offset_formatted}";
				$timezone_list[$timezone] = "(${pretty_offset}) $timezone";
			} else
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
		if (!$loc)
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
	public function RangeStamps($start, $range)
	{
		$dts = new \DateTime('1900-1-1',new \DateTimeZone('UTC'));
		//start of day including start
		$dts->setTimestamp($start);
		$dts->setTime(0,0,0);
		//start of day after end
		$dte = clone $dts;
		switch ($range) {
		 case \Booker::RANGEDAY:
			$dte->modify('+1 day');
			break;
		 case \Booker::RANGEWEEK:
			$dte->modify('+7 days');
			break;
		 case \Booker::RANGEMTH:
			$dte->modify('+1 month');
			break;
		 case \Booker::RANGEYR:
			$dte->modify('+1 year');
			break;
		}
		return array($dts,$dte);
	}

/*	public function GetBookingItemName(&$mod, $bkg_id)
	{
		$sql =<<<EOS
	SELECT I.item_id,I.name FROM {$mod->ItemTable} I
	JOIN {$mod->DataTable} D ON I.item_id=D.item_id
	WHERE I.active>0 AND D.bkg_id=?
	EOS;
		$idata = self::SafeGet($sql,array($bkg_id),'row');
		if ($idata)
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
	public function GetInterval(&$mod, $item_id ,$prefix, $default=3600)
	{
		$idata = self::GetItemProperty($mod,$item_id,array($prefix.'type',$prefix.'count'),TRUE);
		if ($idata && isset($idata[$prefix.'type']) && !is_null($idata[$prefix.'type'])
		 && isset($idata[$prefix.'count']) && !is_null($idata[$prefix.'count']))
		{
			$c = (int)$idata[$prefix.'count'];
			if ($c < 1)
				$c = 1;
			$t = (int)$idata[$prefix.'type']; //enum 0..5 consistent with TimeIntervals()
			switch ($t) {
			 case 0:
				if ($c > 60)
					$c = ceil($c/15) * 15; //round largish minutes up to qtr-hrs
				$c *= 60;
				break;
			 case 1:
				$c *= 3600;
				break;
			 case 2:
				$v = 'day';
			 case 3:
				if ($t == 3) $v = 'week';
			 case 4:
				if ($t == 4) $v = 'month';
			 case 5:
				if ($t == 5) $v = 'year';
				if ($c > 1) $v .= 's';
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
	public function IntervalFormat(&$mod, $dt, $format)
	{
		if (!$format)
			$format = 'j M Y';
		$finds = array();
		$placers = array();
		$repls = array();
		if (strpos($format,'D') !== FALSE) { //short dayname
			$finds[] = '/(?<!\\\)D/';
			$indx = $dt->format('w'); //0..6
			$placers[] = '1Q1';
			$repls[] = self::DayNames($mod,$indx,TRUE);
		}
		if (strpos($format,'l') !== FALSE) { //long dayname
			$finds[] = '/(?<!\\\)l/';
			$indx = $dt->format('w');
			$placers[] = '2Q2';
			$repls[] = self::DayNames($mod,$indx);
		}
		if (strpos($format,'M') !== FALSE) { //short monthname
			$finds[] = '/(?<!\\\)M/';
			$indx = $dt->format('n'); //1..12
			$placers[] = '3Q3';
			$repls[] = self::MonthNames($mod,$indx,TRUE);
		}
		if (strpos($format,'F') !== FALSE) { //long monthname
			$finds[] = '/(?<!\\\)F/';
			$indx = $dt->format('n');
			$placers[] = '4Q4';
			$repls[] = self::MonthNames($mod,$indx);
		}
		if ($finds) {
			$format = preg_replace($finds,$placers,$format);
			$interval = $dt->format($format);
			return str_replace($placers,$repls,$interval);
		} else
			return $dt->format($format);
	}

	/**
	IntervalNames:

	Get one, or array of, translated time-interval-name(s).
	If @cap is TRUE, this uses ucfirst() which expects PHP locale value to be correspond to the translation of names

	@mod: reference to current module-object
	@which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array of
		such indices consistent with TimeIntervals() + 1
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	public function IntervalNames(&$mod, $which, $plural=FALSE, $cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$mod->Lang($k));
		array_unshift($all,$mod->Lang('none'));
		$c = count($all);

		if (!is_array($which)) {
			if ($which >= 0 && $which < $c) {
				if ($cap)
					return mb_convert_case($all[$which],MB_CASE_TITLE); //TODO CHECK encoding string
				else
					return $all[$which];
			}
			return '';
		}
		$ret = array();
		foreach ($which as $period) {
			if ($period >= 0 && $period < $c) {
				$ret[$period] = ($cap) ?
					mb_convert_case($all[$period],MB_CASE_TITLE):
					$all[$period];
			}
		}
		return $ret;
	}

	/**
	MonthNames:

	Get one, or array of, translated month-name(s).
	If array, returned keys are values from @which i.e. 1-based

	@mod: reference to current module-object
	@which: 1 (for January) .. 12 (for December), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	public function MonthNames(&$mod, $which, $short=FALSE)
	{
		$k = ($short) ? 'shortmonths' : 'longmonths';
		$all = explode(',',$mod->Lang($k));
		if (!is_array($which)) {
			if ($which > 0 && $which < 13)
				return $all[$which-1];
			return '';
		}
		$ret = array();
		foreach ($which as $month) {
			if ($month > 0 && $month < 13)
				$ret[$month] = $all[$month-1];
		}
		return $ret;
	}

	/**
	DayNames:

	Get one, or array of, translated day-name(s)

	@mod: reference to current module-object
	@which: 0 (for Sunday) .. 6 (for Saturday), or array of such indices
	@short: optional, whether to get short-form name, default FALSE
	*/
	public function DayNames(&$mod, $which, $short=FALSE)
	{
		$k = ($short) ? 'shortdays' : 'longdays';
		$all = explode(',',$mod->Lang($k));
		$c = count($all);

		if (!is_array($which)) {
			if ($which >= 0 && $which < $c)
				return $all[$which];
			return '';
		}
		$ret = array();
		foreach ($which as $day) {
			if ($day >= 0 && $day < $c)
				$ret[$day] = $all[$day];
		}
		return $ret;
	}

	/**
	RangeNames:

	Get one, or array of, translated time-range-name(s)
	If @cap is TRUE, this uses ucfirst() which expects PHP locale value to be correspond to the translation of names

	@mod: reference to current module-object
	@which: index 0 (for 'day'), 1 (for 'week') .. 3 (for 'year'), or
		array of such indices consistent with Utils::DisplayIntervals()
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
	public function RangeNames(&$mod, $which, $plural=FALSE, $cap=FALSE)
	{
		$k = ($plural) ? 'multiperiods' : 'periods';
		$all = explode(',',$mod->Lang($k));
		array_shift($all); //no minute or hour in this context
		array_shift($all);
		$c = count($all);

		if (!is_array($which)) {
			if ($which >= 0 && $which < $c) {
				if ($cap)
					return mb_convert_case($all[$which],MB_CASE_TITLE); //TODO CHECK encoding string
				else
					return $all[$which];
			}
			return '';
		}
		$ret = array();
		foreach ($which as $period) {
			if ($period >= 0 && $period < $c) {
				$ret[$period] = ($cap) ?
					mb_convert_case($all[$period],MB_CASE_TITLE):
					$all[$period];
			}
		}
		return $ret;
	}

	/**
	RangeDescriptor:

	Get string representation of the interval from @st to @nd inclusive,
	formatted suitably for public display

	@mod: reference to current module-object
	@st: UTC timestamp for start of interval
	@nd: UTC timestamp for end of interval
	@daynames: optional array of weekday names, keyed 0..6 and valued 'Sun'..'Sat' or similar
	  default NULL means get the names to be used from self->DayNames();
	*/
	public function RangeDescriptor(&$mod, $st, $nd, &$daynames=NULL)
	{
		$tz = new \DateTimeZone('UTC');
		$dts = new \DateTime('1900-1-1',$tz);
		$dte = clone $dts;
		$dts->setTimestamp($st);
		$dte->setTimestamp($nd);
		if ($daynames == NULL) {
			$daynames = $this->DayNames($mod,range(0,6),TRUE);
		}
		$t1 = $dts->format('Y-n-j');
		$t2 = $dte->format('Y-n-j');
		$t = $t1;
		if ($t1 == $t2) {
			$t1 = $dts->format('w G:i');
			$t .= ' ('.$daynames[$t1[0]].') ';
			$t1 = substr($t1,2);
			$t2 = $dte->format('G:i');
			$t .= $mod->Lang('showrange',$t1,$t2);
		} else {
			$t1 = $dts->format('w G:i');
			$t1 = $t.' ('.$daynames[$t1[0]].') '.substr($t1,2);
			$t = $t2;
			$t2 = $dte->format('w G:i');
			$t2 = $t.' ('.$daynames[$t2[0]].') '.substr($t2,2);
			$t = $mod->Lang('showrange',$t1,$t2);
		}
		return $t;
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
	public function StripTags($str, &$tags)
	{
		foreach ($tags as $tag) {
			$str = preg_replace('#<'.$tag.'(>|\s[^>]*>)#is','',$str);
			$str = preg_replace('#</'.$tag.'(>|\s[^>]*>)#is','<br />',$str);
		}
		return $str;
	}

	/**
	SaveParameters:

	Store all or some of @params array and @cart object in cache, for 12-hours.
	Adds @params['storedparams'] before saving, if that's not present already.
	That key is session-specfic. @params is not changed.

	@cache: reference to Cache oject
	@params: reference to request-parameters associative array to be (updated &) cached
	@except: parameter key, or array of them, to be omitted from cached @params, or FALSE
	@cart: optional cart-object to also be cached
	Returns: nothing
	*/
	public function SaveParameters (&$cache, &$params, $except=FALSE, $cart=FALSE)
	{
		if ($except) {
			if (is_array($except)) {
				$store = array_diff_key($params,array_flip($except));
			} else {
				$store = $params;
				unset($store[$except]);
			}
		} else {
			$store = $params;
		}
		if (empty($params['storedparams'])) {
			$params['storedparams'] = Cache::GetKey(\Booker::PARMKEY); //Cache::GetKey(session_id());
		}
		$cache->set($params['storedparams'],$store,43200);
		if ($cart && $params['cartkey'])
			$cache->set($params['cartkey'],$cart,43200);
	}

	/**
	RetrieveParameters:
	@cache: reference to Cache oject
	@params: reference to reqest-parameters array
	@except: parameter key, or array of them, to be omitted from cached @params, default FALSE
	Update @params from @cache, if possible
	Returns: nothing
	*/
	public function RetrieveParameters (&$cache, &$params, $except=FALSE)
	{
		if (!empty($params['storedparams'])) {
			$saved = $cache->get($params['storedparams']);
			if (!empty($saved)) {
				if ($except) {
					if (is_array($except)) {
						$saved = array_diff_key($saved,array_flip($except));
					} else {
						unset($saved[$except]);
					}
				}
				$params = array_merge($params,$saved); //prefer cached values
				return;
			} else {
				$cache->delete($params['storedparams']);
				unset($params['storedparams']);
			}
		}
	}

	/**
	RetrieveCart:
	If $params['cartkey'] is present it's used, or otherwise it's created and used,
	as the cache-key for the new cart-object.
	@cache: reference to Cache oject
	@params: reference to request-parameters array
	@context: mixed data about the cart, used (among other things) for setting prices
	@pricesWithTax: boolean, whether cart uses 'gross' prices, default TRUE
	Returns: existing BookingCart object, or a new one cached with 'unique'
	(NOT session-specific) key and 12-hour lifetime.
	*/
	public function RetrieveCart(&$cache, &$params, $context='', $pricesWithTax=TRUE)
	{
		if (!empty($params['cartkey'])) {
			$key = $params['cartkey'];
			$cart = $cache->get($key);
			if ($cart) {
				return $cart;
			}
		} else {
			$key = Cache::GetKey(\Booker::CARTKEY);
			$params['cartkey'] = $key;
		}
		$cart = new Cart\BookingCart($context,$pricesWithTax);
		$cache->set($key,$cart,43200);
//DEBUG
		$dt = new \DateTime('midnight',new \DateTimeZone('UTC'));
		$base = $dt->getTimestamp();
		$data = array(
			array(2,    0.0, Cart\BookingCartItem::NORMAL, $base+36000 ,3600,1),
			array(2,    12.0,Cart\BookingCartItem::PAID,   $base+45000 ,7200,1),
			array(3,    28.0,Cart\BookingCartItem::PAYABLE,$base+72000 ,84600+3600,1),
			array(10004,10.0,Cart\BookingCartItem::PAID,   $base+120600,3600,1),
			array(10004,10.0,Cart\BookingCartItem::PAYABLE,$base+205200,1800,2)
		);
		foreach ($data as $one) {
			$item = new Cart\BookingCartItem('',$one[0],$one[1]);
			$item->setStatus($one[2]);
			$item->data->start = $one[3];
			$item->data->slen = $one[4]-1;
			$cart->addItem($item,$one[5]);
		}

		return $cart;
	}

	/**
	OpenPaymentForm:
	Arrange for and display payment-gateway form
	@mod: reference to current module-object
	@id: module identifier
	@returnid:
	@params: array of parameters for the action
	@idata: array of data for the resource being booked
	@cart: cart-object containing the item(s) to be paid for
	Returns: only if something goes wrong - normally redirects
	*/
	public function OpenPaymentForm(&$mod, $id, $returnid, $params, $idata, $cart)
	{
		$t = $idata['paymentiface'];
		if ($t) {
			$ob = \cms_utils::get_module($t);
			$handlerclass = $ob->GetPayer();
			$ifuncs = new $handlerclass($mod,$ob);
			if ($ifuncs->Furnish(
				array(
				 'amount'=>TRUE,
				 'cancel'=>TRUE,
				 'payer'=>'who',
				 'payfor'=>'what',
//				 'surcharge'=>TRUE,
				 'cachekey'=>'storedparams',
				 'errmsg'=>'msg',
				 'success'=>'result',
				 'transactid'=>'identifier'
				),
				array($mod->GetName(),'requestfinish')
			)) {
				$num = $cart->countItems(); //TODO count only the payable items
				if ($num < 2) {
					$t = $this->GetItemName($mod,$idata);
					$desc = trim($mod->Lang('title_bookfor',$t,''));
				} else {
					$desc = $mod->Lang('title_booksfor',$num,$idata['membersname']);
				}
				$args = array_merge($params,array(
				 'amount'=>$cart->getTotal(),
				 'cancel'=>TRUE,
//				 'surcharge'=>'3%' TODO UI & API for setting this
				 'who'=>$params['name'],
				 'what'=>$desc
				));
				$ifuncs->ShowForm($id,$returnid,$args); //redirects
				exit;
			}
		}
	}
}
