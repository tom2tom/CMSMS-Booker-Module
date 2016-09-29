<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Utils
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
		$d = $this->coll->compare($a,$b);
		if ($d != 0) {
			$na = preg_match('/\d+/', $a,$ma,PREG_OFFSET_CAPTURE);
			if ($na == 1) {
				$nb = preg_match('/\d+/', $b,$mb,PREG_OFFSET_CAPTURE);
				if ($nb == 1) {
					if ($ma[0][1] == $mb[0][1]) { //same offsets
						$d = $ma[0][0] - $mb[0][0]; //order based on the numbers
					}
				}
			}
		}
		return $d;
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
				$ret = $db->GetArray($sql,$args);
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
				$res = TRUE;
				foreach ($sql as $i=>$cmd) {
					$res = $res && $db->Execute($cmd,$args[$i]);
				}
			} else {
				$res = ($db->Execute($sql,$args) != FALSE);
			}
			if ($db->CompleteTrans())
				return $res;
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
	Get resource/group id to which @bkgid applies
	@mod: reference to Booker module object
	@bkgid: identifier of booking
	*/
	public function GetBookingItemID(&$mod, $bkgid)
	{
		$sql = 'SELECT item_id FROM '.$mod->DataTable.' WHERE bkg_id=?';
		$t = self::SafeGet($sql,array($bkgid),'one');
		if ($t)
			return (int)$t;
		return FALSE;
	}

	/**
	GetItemPicker:
	@mod: reference to current Booker module object
	@id: session identifier
	@name: created-object name
	@alwayspick: item_id of choice to always include
	@currentpick: item_id of 'current' choice
	Returns: string, XHTML dropdown or empty
	 */
	public function GetItemPicker(&$mod, $id, $name, $alwayspick, $currentpick)
	{
		$choices = $this->GetItemGroups($mod,$currentpick);
		if ($choices && $choices[0] != $alwayspick) {
			$choices = array_merge($choices,$this->GetGroupItems($mod,$choices[0],TRUE));
		} elseif ($currentpick >= \Booker::MINGRPID) {
			$choices = $this->GetGroupItems($mod,$currentpick,TRUE);
		} elseif ($alwayspick >= \Booker::MINGRPID) {
			$choices = $this->GetGroupItems($mod,$alwayspick,TRUE);
			array_unshift($choices,$currentpick);
		} else {
			$choices = array($currentpick);
		}
		if ($alwayspick) {
			array_unshift($choices,$alwayspick);
		}
		$choices = array_unique($choices,SORT_NUMERIC);
		$picknames = $this->GetNamedItems($mod,$choices);

		if (count($choices) > 1) {
			if (class_exists('Collator')) {
				try {
					$col = new \Collator(self::GetLocale());
					uasort($picknames,array(new bkr_itemname_cmp($col),'namecmp'));
//					$col->sort($picknames,SORT_STRING);
					//TODO preserve keys like asort
				} catch (Exception $e) {
					asort($picknames,SORT_LOCALE_STRING);
				}
			} else {
				asort($picknames,SORT_LOCALE_STRING);
			}
		}
		return $mod->CreateInputDropdown($id,$name,array_flip($picknames),
			-1,$currentpick,'id="'.$id.$name.'"');
	}

	/* *
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
/*	public function GetItemFamily(&$mod, $item_id)
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
*/
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
						$downers = self::GetGroupItems($mod,$mid,TRUE,$down+1); //recurse
						if ($downers)
							$ids = array_merge($ids,$downers);
						if (!in_array($mid,$ids))
							array_unshift($ids,(int)$mid);
					} else
						$ids[] = (int)$mid;
				}
			}
			if (!in_array($mid,$ids))
				array_unshift($ids,(int)$gid);
		} else
			$ids[] = (int)$gid;

		if ($down == 0) {
			if (!in_array($gid,$ids)) {
				array_unshift($ids,(int)$gid);
			}
			if (!$withgrps) {
				$ids = array_filter($ids,function($mid){ return ($mid < \Booker::MINGRPID); });
			}
			if (count($ids) > 1) {
				$ids = array_unique($ids);
			}
			return array_values($ids);
		}
		return $ids;
	}

	/* *
	GetGroups(&$mod, $id=0, $returnid=0, $full=FALSE, $anyowner=TRUE)

	Create associative array of group-data, sorted by field 'likeorder',
	each array member's key is the group id, value is an object

	@id: session identifier used in link, when $full is TRUE
	@returnid: ditto
	@full: FALSE	return group_id and name only
	@full: TRUE return all table 'raw' data for the group, plus a link TODO describe
	@anyowner: TRUE to return all groups or FALSE to return groups whose owner
	  is 0 or matches the current user
	*/
/*	public function GetGroups(&$mod, $id=0, $returnid=0, $full=FALSE, $anyowner=TRUE)
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
			//TODO use self::SafeExec()
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
			//TODO use self::SafeExec()
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
	@wantedprops: ItemTable field-name for property sought or ','-separated series
	 	of such names or array of such names (no checks here!) or '*'
	@same: optional boolean, whether all requested properties must come from the
		same database record, default FALSE
	@search: optional boolean, whether to check for missing values in ancestor
		groups (if any), default TRUE
	Returns: array with key(s) = field name(s), value(s) = corresponding value(s)
		if available or NULL if not, or empty array upon error
	Flavours of FALSE other than NULL,'' are assumed to be actual parameter values.
	*/
	public function GetItemProperty(&$mod, $item_id, $wantedprops, $same=FALSE, $search=TRUE)
	{
		if ($search) {
			$found = $this->GetHeritableProperty($mod,$item_id,$wantedprops);
			if (!$found)
				return array();
			if (!is_array($wantedprops)) {
				$wantedprops = explode(',',$wantedprops);
			}

			if ($wantedprops[0] == '*') {
				$gets = array_fill_keys(array_keys(reset($found)),NULL);
			} else {
				$gets = array_fill_keys(array_filter($wantedprops),NULL);//no name-validation, only presence-checks
			}
			if ($same)
				$rc = count($gets);
			$got = array();
			$k = 'item_id';
			if (array_key_exists($k,$gets)) {
				$got[$k] = (int)$item_id;
				unset($gets[$k]);
			}
			foreach ($found as $row) {
				foreach ($gets as $k=>$val) {
					if (!isset($got[$k]) && !($row[$k] === NULL || $row[$k] === '')) {
						$got[$k] = $row[$k];
					}
				}
				if ($same) {
					if (count($got) < $rc) {
						$got = array(); //keep looking
					} else {
						return $got;
					}
				}
			}
			return $got+$gets; //infill NULL's
		} else { //no search
			if (!is_array($wantedprops)) {
				$wantedprops = explode(',',$wantedprops);
			}
			$getcols = implode(',',array_filter($wantedprops)); //no name-validation, only presence-checks

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
	@wantedprops: ItemTable field-name for property sought or ','-separated series
		of such names or array of such names (no checks here!) or '*'
	Returns: proximity-ordered array with member(s) = property-value(s) for @item_id
		and all its ancestors and corresponding module-preference values
	*/
	public function GetHeritableProperty(&$mod, $item_id, $wantedprops)
	{
		if (!is_array($wantedprops)) {
			$adbg = $wantedprops;
			$wantedprops = explode(',',$wantedprops);
			$adbg2 = $wantedprops;
		}
		$getcols = implode(',',array_filter($wantedprops)); //no name-validation, only presence-checks
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
		} elseif ($getcols == '*') {
			return array(); //sould never happen!
		} else {
			$found = array();
			$prefs = array_flip($wantedprops);
		}

		foreach ($prefs as $k=>&$val) {
			$val = $mod->GetPreference('pref_'.$k,NULL);
		}
		unset($val);
		$found[-1] = $prefs; //append preferences
		return array_values($found);
	}

	/**
	GetOneHeritableProperty:
	Wrapper for GetHeritableProperty() which collapses results for @propname
	(including FALSE/NULL results) into a non-associative array
	@mod: reference to current Booker module object
	@item_id: identifier of resource or group for which property/ies is/are sought
	@propname: ItemTable field-name for property sought
	*/
	public function GetOneHeritableProperty(&$mod, $item_id, $propname)
	{
		$propdata = $this->GetHeritableProperty($mod, $item_id, $propname);
		$ret = array();
		if ($propdata) {
			foreach ($propdata as $one) {
				$ret[] = $one[$propname]; //includes any flavour of FALSE
			}
		}
		return $ret;
	}

	/**
	GetItemName:
	Get name for an item, with fallback
	@idata: reference to array of item-parameters
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

	/*
	@mod: reference to current Booker module object
	@items: array of item_id's for resource(s) and/or group(s)
	Returns: associative array, or maybe empty
	*/
	private function GetNamedItems(&$mod, $items)
	{
		$sql = 'SELECT item_id,name FROM '.$mod->ItemTable.' WHERE item_id IN('.implode(',',$items).')';
		$rows = $mod->dbHandle->GetAssoc($sql);
		$ret = array();
		if ($rows) {
			$iname = FALSE;
			foreach ($rows as $id=>$name) {
				if ($name) {
					$ret[$id] = $name;
				} else {
					if (!$iname) {
						$iname = $mod->Lang('item');
						$gname = $mod->Lang('group');
					}
					$type = ($id >= \Booker::MINGRPID) ? $gname:$iname;
					$ret[$id] = $mod->Lang('title_noname',$type,$id);
				}
			}
		}
		return $ret;
	}

	/**
	GetItemNameForID:
	Get name for @item_id, with fallback
	@mod: reference to current Booker module object
	@item_id: identifier of resource or group whose name is wanted
	*/
	public function GetItemNameForID(&$mod, $item_id)
	{
		$name = $this->GetNamedItems($mod,array($item_id));
		if ($name) {
			return reset($name);
		}
		return '';
	}

	/**
	BuildResume:
	Create (as array) or update @params['resume']
	@resume: name of action
	$params: reference to array of request-parameters
	Returns: nothing
	*/
	public function BuildResume($resume, &$params)
	{
		if (empty($params['resume'])) {
			$params['resume'] = array($resume);
		} elseif (!is_array($params['resume'])) {
			if ($params['resume'] != $resume) {
				$params['resume'] = array($params['resume'],$resume);
			} else {
				$params['resume'] = array($resume);
			}
		} elseif (reset($params['resume']) != $resume) {
				$params['resume'][] = $resume;
		}
	}

	/**
	BuildNav:
	Generate XHTML page-change links for admin action
	@mod: reference to current module-object
	@id: session identifier
	@returnid:
	@resume: action name
	@params: reference to array of request-parameters including link-related data
	Returns: string, maybe ''
	*/
	public function BuildNav(&$mod, $id, $returnid, $resume, &$params)
	{
		$this->BuildResume($resume,$params);
		$navstr = '';
		foreach ($params['resume'] as $action) {
			if ($action == $resume)
				continue; //no link to self
			switch($action) {
			 case 'defaultadmin':
				$key = 'back_module';
				if (empty($params['active_tab'])) {
					$xtra = array();
				} else {
					$xtra = array('active_tab'=>$params['active_tab']);
				}
				break;
			 case 'itembookings':
				$key = 'title_bookings';
				$xtra = array('item_id'=>$params['item_id'],'task'=>$params['task']);
				break;
			 case 'bookerbookings':
				$key = 'title_bookings';
				$xtra = array('booker_id'=>$params['booker_id'],'task'=>$params['task']);
				break;
			 default:
				break 2;
			}
			$navstr .= $mod->CreateLink($id,$action,$returnid,'&#171; '.$mod->Lang($key),$xtra);
		}
		return $navstr;
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
			$dt = new \DateTime('@'.$when,$tz);
			$stamp = $when;
		} else {
			try {
				$dt = new \DateTime($when,$tz);
			} catch (Exception $e) {
				return 0;
			}
			$stamp = $dt->getTimestamp();
		}
		try {
			$tz = new \DateTimeZone($zonename);
			$offt = $tz->getOffset($dt);
		} catch (Exception $e) {
$this->Crash();
			$offt = 0;
		}
		return $offt + $stamp;
	}

	/**
	GetTimeZones(&$mod)
	Requires: PHP 5.2+
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
	Rationalise slot start and end times @bs, @be
	@bs if 'near' either extreme of a slot will be rounded to that extreme.
	@be if 'near' the end of a slot will be rounded up. The minimum difference
	between the pair will be the slotlen derived from @slottype and @slotcount.
	@slottype: enum 0..5 per Utils::TimeIntervals() i.e. for minute,hour,day,week,month,year
	@slotcount: no. of @slottype's comprising a slot
	@bs: timestamp for start of range
	@be: timestamp for end of range ditto, may be <= @start
	@part: optional boolean, whether to accept intra-slot times for @slen >= 3600, default FALSE
	Returns: 2-member array, replacements for @bs, @be
	*/
	public function TrimRange($slottype, $slotcount, $bs, $be, $part=FALSE)
	{
		$slen = $this->GetCurrentSlotlen($bs, $slottype, $slotcount);
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

		if ($bs > $be) {
			$t = $be;
			$be = $bs;
			$bs = $t;
		} elseif ($bs == $be) {
			$be = $bs + 60; //this will change
		}

		$t = $bs % $slen;
		if ($t < $slop) {
			$st = $bs - $t;
		} elseif ($t > $slen - $slop) {
			$st = $bs + $slen - $t;
		} else {
			$st = floor($bs/$slen) * $slen + $slen;
		}
		$be += $st - $bs;
		$t = ($be-$st) % $slen;
		if ($t < $slop) {
			$be = $be - $t - 1;
		} elseif ($t > $slen - $slop) {
			$be = $be + $slen - $t - 1;
		} else {
			$be = floor($be/$rounder) * $rounder;
		}
		if ($be-$st < $slen-1) {
			$be = $st + $slen - 1;
		}
		return array($st,$be);
	}

	/**
	RangeStamps:
	@st: UTC timestamp for start of range
	@range: enum 0..3 indicating span of range
	Returns: pair of UTC DateTime objects, first represents start of
	  day including @start, second is for start of day one-past end of range
	*/
	public function RangeStamps($st, $range)
	{
		//start of day including $st
		$dts = new \DateTime('@'.$st,NULL);
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

/*	public function GetBookingItemName(&$mod, $bkgid)
	{
		$sql =<<<EOS
	SELECT I.item_id,I.name FROM $mod->ItemTable I
	JOIN $mod->DataTable D ON I.item_id=D.item_id
	WHERE I.active>0 AND D.bkg_id=?
	EOS;
		$idata = self::SafeGet($sql,array($bkgid),'row');
		if ($idata)
			return self::GetItemName($mod,$idata);
		return FALSE;
	}
*/

	/**
	GetCurrentSlotlen:
	@bs: timestamp for start of slot
	@slottype: enum 0..5 per Utils::TimeIntervals() i.e. for minute,hour,day,week,month,year
	@slotcount: no. of @slottype's comprising the slot
	Returns: length in seconds
	*/
	public function GetCurrentSlotlen($bs, $slottype, $slotcount)
	{
		if ($slotcount < 1)
			$slotcount = 1;
		switch ($slottype) {
		 case 0:
			$offs = '+'.($slotcount*60).' seconds';
			break;
		 case 2:
			$offs = '+'.$slotcount.' days';
			break;
		 case 3:
			$offs = '+'.($slotcount*7).' days';
			break;
		 case 4:
			$offs = '+'.$slotcount.' months';
			break;
		 case 5:
			$offs = '+'.$slotcount.' years';
			break;
		 default:
			$offs = '+'.($slotcount*3600).' seconds';
			break;
		}
		$dtw = new \DateTime('@'.$bs,NULL);
		$dtw->modify($offs);
		return $dtw->getTimestamp() - $bs;
	}

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
				return 0;
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
	Construct formatted string representing @dt, after replacing any D,l,F,M in
	@format to translated names, and if $withyear is TRUE, ensuring that a Y is
	present	(by appending if need be)
	@mod reference to current module-object
	@dt: UTC DateTime object to be interpreted
	@format: date-format string recognised by PHP date(), or empty
	@withyear: whether to add a year-value, if not already present
	Returns: string
	*/
	public function IntervalFormat(&$mod, $dt, $format, $withyear=FALSE)
	{
		if ($format) {
			if ($withyear && stripos($format,'Y') === FALSE) { //no year of any sort
				if (strpos($format,'/') !== FALSE)
					$format .= '/Y';
				elseif (strpos($format,'-') !== FALSE)
					$format = 'Y-'.$format;
				else
					$format .= ' Y';
			}
		} else {
			$format = 'j M Y';
		}
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
	DateTimeFormat:
	@iso: whether to generate ISO format
	@admin: whether to generate admin-suitable format, if @iso is FALSE
	@withyear: whether to add a year-value, if not already present
	@withtime: whether to include time-component in the format
	@dayfmt: optional date()-compatible day/month/year formatter to use when relevant
	@timefmt: optional date()-compatible time formatter to use when relevant
	Returns: date()-compatible format string
	*/
	public function DateTimeFormat($iso, $admin, $withyear, $withtime, $dayfmt='', $timefmt='')
	{
		if ($iso) {
			$fmt = 'Y-m-d';
			if ($withtime) {
				$fmt .= ' G:i';
			}
		} elseif ($admin) {
			$fmt = 'Y-m-j';
			if ($withtime) {
				$fmt .= ' G:i';
			}
		} else { //frontend
			if ($dayfmt) {
				$fmt = $dayfmt;
				if ($withyear && stripos($fmt,'Y') === FALSE) { //no year of any sort
					if (strpos($fmt,'/') !== FALSE)
						$fmt .= '/Y';
					elseif (strpos($fmt,'-') !== FALSE)
						$fmt = 'Y-'.$fmt;
					else
						$fmt .= ' Y';
				}
			} else {
				$fmt = 'j M Y';
			}
			if ($withtime) {
				if ($timefmt) {
					$fmt .= ' '.$timefmt;
				} else {
					$fmt .= ' G:i';
				}
			}
		}
		return $fmt;
	}

	/*
	isodate_from_format:
	Convert @dvalue to ISO format i.e. like Y-M-d H:i:s
	For testing, at least
	@dformat: string which includes one or more of many (not all) format-characters
	 understood by PHP date(). If it includes 'z', the corresponding element of
	 @dvalue must be 1-based
	@dvalue: date-time string consistent with @dformat
	*/
/*	private function isodate_from_format($dformat, $dvalue)
	{
		$sformat = str_replace(
			array('Y' ,'M' ,'m' ,'d' ,'H' ,'h' ,'i' ,'s' ,'a' ,'A' ,'z'),
			array('%Y','%b','%m','%d','%H','%I','%M','%S','%P','%p','%j'),$dformat);
		$parts = strptime($dvalue,$sformat); //PHP 5.1+
		return sprintf('%04d-%02d-%02d %02d:%02d:%02d',
			$parts['tm_year'] + 1900,  //tm_year = relative to 1900
			$parts['tm_mon'] + 1,      //tm_mon = 0-based
			$parts['tm_mday'],
			$parts['tm_hour'],
			$parts['tm_min'],
			$parts['tm_sec']);
	}
*/

	/* *
	IntervalNames:

	Get one, or array of, translated time-interval-name(s).
	If @cap is TRUE, this uses ucfirst() which expects PHP locale value to be correspond to the translation of names

	@mod: reference to current module-object
	@which: index 0 (for 'none'), 1 (for 'minute') .. 6 (for 'year'), or array of
		such indices consistent with TimeIntervals() + 1
	@plural: optional, whether to get plural form of the interval name(s), default FALSE
	@cap: optional, whether to capitalise the first character of the name(s), default FALSE
	*/
/*	public function IntervalNames(&$mod, $which, $plural=FALSE, $cap=FALSE)
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
*/
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
		$dts = new \DateTime('@'.$st,NULL);
		$dte = clone $dts;
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

	public function mb_asort(&$array)
	{
		if (extension_loaded('intl') === TRUE) {
			collator_asort(collator_create('root'),$array);
		} else {
			array_multisort(array_map(function($str)
			{
				return preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i',
				'$1'.chr(255).'$2',htmlentities($str,ENT_QUOTES,'UTF-8'));
			},$array),$array);
		}
	}

	public function mb_ksort(&$array)
	{
		$swap = array_flip($array);
		$this->mb_asort($swap);
		$array = array_flip($swap);
	}

	/**
	Create array with members as in @params but without member(s) named in @except.
	Keys in the array will have 'bkr_' prefix. Any value which is an array will
	be json'd.
	@params: reference to request-parameters associative array to be cached
	@except: optional parameter key, or array of them, to be omitted from
		returned array, or FALSE
	Returns: array
	*/
	public function FilterParameters(&$params, $except=FALSE)
	{
		if (!$except) {
			$filter = array();
		} elseif (!is_array($except)) {
			$filter = array($except);
		} else {
			$filter = $except;
		}
		$filter = array_diff_key($params,array_flip($filter));
		$keep = array();
		foreach ($filter as $k=>$v) {
			if (is_array($v)) {
				$v = json_encode($v);
			}
			$keep['bkr_'.$k] = $v;
		}
		return $keep;
	}

	/**
	UnFilterParameters:
	Remove key-prefix and otherwise filter @params
	@params: reference to request-parameters array
	@except: optional parameter key, or array of them, to be ignored if present
	in @params, or FALSE
	Returns: nothing
	*/
	public function UnFilterParameters(&$params, $except=FALSE)
	{
		$keep = array();
		foreach ($params as $k=>$v) {
			if (strpos($k,'bkr_') === 0) {
				$kn = substr($k,4);
			} else {
				$kn = $k;
			}
			if (!((is_array($except) && in_array($kn,$except)) || $except==$kn)) {
				if (is_array($v) || $v == '' || $v[0] != '[') {
					if ($kn != $k) {
						unset($params[$k]);
						$params[$kn] = $v;
					}
				} else {
					$t = json_decode(html_entity_decode($v)); //CHECK flags e.g. ENT_QUOTES|ENT_HTML401
					if (json_last_error() == JSON_ERROR_NONE) {
						if ($kn != $k)
							unset($params[$k]);
						$params[$kn] = $t;
					} elseif ($kn != $k) {
						unset($params[$k]);
						$params[$kn] = $v;
					}
				}
			} elseif ($kn != $k) {
				unset($params[$k]);
			}
		}
		return $keep;
	}

	/**
	DecodeParameters:
	Cleanup @params: numerics to numbers, htmlentities to chars, injection disabled
	@params: reference to request-parameters array
	@include: optional parameter key, or array of them, to be processed, or '*', default '*'
	Returns: nothing
	*/
	public function DecodeParameters(&$params, $include='*')
	{
		if ($include) {
			if (!is_array($include)) {
				if ($include == '*') {
					$include = array_keys($params);
				} else {
					$include = array($include);
				}
			}
			$patn = '/[\'"] ?[Oo][Rr] ?["\']/';
			foreach ($include as $k) {
				if (isset($params[$k])) {
					$v = $params[$k];
					if (!is_array($v)) {
						if (is_string($v) && $v) {
							if (is_numeric($v)) {
								$params[$k] = $v + 0;
							} else {
								if (strpos($v,'&') !== FALSE) {
									$v = html_entity_decode($v,ENT_QUOTES|ENT_HTML401);
								}
								$v = str_replace('`','',$v);
								$v = preg_replace($patn,'_',$v);
								$params[$k] = $v;
							}
						}
					} else {
						foreach ($v as $i=>&$one) {
							if (is_string($one) && $one) {
								if (is_numeric($one)) {
									$params[$k][$i] = $one + 0;
								} else {
									if (strpos($one,'&') !== FALSE) {
										$one = html_entity_decode($one,ENT_QUOTES|ENT_HTML401);
									}
									$one = str_replace('`','',$one);
									$one = preg_replace($patn,'_',$one);
									$params[$k][$i] = $one;
								}
							}
						}
						unset ($one);
					}
				}
			}
		}
	}

	public function SaveCart($cart, &$cache, &$params)
	{
		if (empty($params['cartkey'])) {
			$params['cartkey'] = Cache::GetKey(\Booker::CARTKEY);
		}
		$cache->set($params['cartkey'],$cart,43200);
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
/* DEBUG
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
*/
		return $cart;
	}

	/**
	OpenPaymentForm:
	Arrange for and display payment-gateway form
	@mod: reference to current module-object
	@id: session identifier
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
				 'errmsg'=>'msg',
				 'success'=>'result',
				 'transactid'=>'identifier',
				 'passthru'=>'paramskey'
				),
				array($mod->GetName(),'method.requestfinish'),
				array($id,'default',$returnid)
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
