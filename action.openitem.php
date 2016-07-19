<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openitem - open the specified resource or group in a edit/view page
# Also used for adding an item ($params['id'] -1 or -Booker::MINGRPID)
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!function_exists('groupstable')) {
 //construct table of group-data (members, parents) for display on advanced-tab
 function groupstable(&$mod, &$tplvars, $id, $obname, $returnid,  $icondn,$iconup,
	$item_id, &$groups, $relations=FALSE, $dodrag=FALSE, $dosort=FALSE, $tableid=FALSE)
 {
	$r = 0;
	$rc = count($groups) - 1; //last index
	$sel = is_array($relations);
	$target = ($obname == 'members') ? 'child':'parent';
	$rows = array();
	//NOTE action.sortlike table-data creation must conform with this
	foreach ($groups as $k=>&$name) {
		$one = new stdClass();
		$one->name = $name;
		if ($r == 0) {
			$one->uplink = '';
			$one->dnlink = '';
			$prevk = $k;
		} else {
			//TODO action.swapgroups would work better with a $params['active_tab'] for returning!
			//TODO mechanism to incrementally [de]select items and re-arrange the current selected bundle
			$one->uplink = $mod->CreateLink($id,'swapgroups',$returnid,$iconup,
				array('item_id'=>$k, 'prev_item_id'=>$prevk,'ref_id'=>$item_id,'change'=>$target));
			$rows[$r-1]->dnlink = $mod->CreateLink($id,'swapgroups',$returnid,$icondn,
				array('item_id'=>$prevk,'next_item_id'=>$k,'ref_id'=>$item_id,'change'=>$target));
			$prevk = $k;
			if ($r == $rc)
				$one->dnlink = '';
		}
		if ($sel)
			$m = (array_search($k,$relations) !== FALSE) ? $k:-1;
		else
			$m = -1;
		$one->check = $mod->CreateInputCheckbox($id,$obname.'[]',$k,$m);
		$rows[] = $one;
		$r++;
	}
	unset($name);
	$c = count($rows);
	$tplvars['rc'] = $c;
	if ($c > 1)
		$tplvars['selectall'] = $mod->CreateInputCheckbox($id,'selectall',1,-1);
	else
		$tplvars['selectall'] = '';
	$tplvars['identifier'] = $tableid;
	$tplvars['drag'] = $dodrag && ($c > 1);
	$tplvars['sort'] = $dosort && ($c > 1);
	$tplvars['entries'] = $rows;

	return Booker\Utils::ProcessTemplate($mod,'groupsinput.tpl',$tplvars);
 }
}

if (!function_exists('groupsupdate')) {
/*
 Update relevant data in GroupTable

 $mod reference to current module object
 $db reference to current database-connection-object
 $item_id resource/group identifier as per ItemTable
 $parent TRUE if $item_id is for a parent group (for which we're processing member(s))
  or FALSE if is child (for which we're processing group(s))
 $sel array of id's of group members (if $parent TRUE) or else groups in which $item_is placed
*/
 function groupsupdate(&$mod, &$db, $item_id, $parent, $sel)
 {
	if (!$sel)
		return;
	$o = 1;	//reordered-row ordinator
	$os = 1; //appended-row offsetter
	$getby = ($parent) ? 'parent':'child'; //name of field to filter records
	$sortby = ($parent) ? 'likeorder':'proximity';
	$current = $db->GetAssoc("SELECT * FROM $mod->GroupTable WHERE $getby=? ORDER BY $sortby",array($item_id));
	$new = array();
	if ($parent) { //we're processing members of group $item_id
		foreach ($sel as $oneid) {
			if ($current) {
				$p = rowof($current,$oneid,$item_id);
				if ($p > 0) {
					$p = $current[$p]['proximity'];
				} else {
//				$p = maxfield($current,'proximity','child',$oneid) + $os++;
					$p = Booker::MINGRPID + $os++;
				}
			} else {
//			$p = maxfield($current,'likeorder','child',$oneid) + $os++;
				$p = Booker::MINGRPID + $os++;
			}
			//add new member in lowest likeorder
			$new[] = array('child'=>$oneid,'parent'=>$item_id,'likeorder'=>$o++,'proximity'=>$p);
		}
	} else { //we're processing groups of which $item_id is a member
		foreach ($sel as $oneid) {
			if ($current) {
				$p = rowof($current,$item_id,$oneid);
				if ($p > 0) {
					$p = $current[$p]['likeorder'];
				} else {
					//add new group with highest/closest proximity
					//$p = maxfield($current,'likeorder','parent',$oneid) + $os++;
					$p = Booker::MINGRPID + $os++;
				}
			} else {
				//add new group with highest/closest proximity
				//$p = maxfield($current,'proximity','parent',$oneid) + $os++;
				$p = Booker::MINGRPID + $os++;
			}
			$new[] = array('child'=>$item_id,'parent'=>$oneid,'likeorder'=>$p,'proximity'=>$o++);
		}
	}
	$db = $mod->dbHandle;
	$nt = 10;
	while ($nt > 0) {
		$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); //this isn't perfect!
		$db->StartTrans();
		if ($current) {
			$db->Execute("DELETE FROM $mod->GroupTable WHERE $getby=?",array($item_id)); //start afresh
		}
		if ($new) {
			$sql = 'INSERT INTO '.$mod->GroupTable.' (child,parent,likeorder,proximity) VALUES (?,?,?,?)';
			foreach ($new as &$row) {
				$db->Execute($sql,array($row['child'],$row['parent'],$row['likeorder'],$row['proximity']));
			}
			unset($row);
		}
		if ($db->CompleteTrans())
			break;
		else {
			$nt--;
			usleep(50000);
		}
	}

	if ($current || $new) {
		$funcs = new Booker\Utils();
		$funcs->OrderGroups($mod,$db);
	}
 }

 function rowof(&$current, $c, $p)
 {
	foreach ($current as $k=>&$row) {
		if ($row['child'] == $c && $row['parent'] == $p) {
			unset($row);
			return $k;
		}
	}
	unset($row);
	return 0;
 }
 //find max value of field $check where field $type = $oneid
/* function maxfield(&$current, $check, $type, $oneid)
 {
 	$max = -1;
	foreach ($current as &$row) {
		if ($row[$type] == $oneid) {
			$m = $row[$check];
			if ($m > $max)
				$max = $m;
		}
	}
	unset($row);
	return $max;
 }
*/
}

$item_id = (int)$params['item_id'];
$is_group = ($item_id >= Booker::MINGRPID || $item_id == -Booker::MINGRPID);
$act = $params['action'];
if (isset($params['active_tab'])) {
	$seetab = $params['active_tab'];
	unset($params['active_tab']);
} else
	$seetab = 'basic'; //default to show this tab

//feedback-message-accumulator
if (isset($params['message']))
	$msg = $params['message'];
else
	$msg = '';

if (isset($params['apply']) || isset($params['submit'])) {
	//===== FIELD CLEANUPS TO SUIT PRE-SAVE CONVERSION =====
	unset($params['action']);
	if (isset($params['apply'])) {
		unset($params['apply']);
		$act = 'edit';
	} else {
		unset($params['submit']);
		$act = 'submit';
	}
	//============ DATA CLEANUPS =============
	if ($params['name'] == FALSE)
		$params['name'] = '<'.$this->Lang('noname').$item_id.'>';
	elseif ($params['alias'] == FALSE)
		$params['alias'] = strtolower(soundex($params['name']));
	if (isset($params['owner']) && $params['owner'] == FALSE) //list-selector value 0 is returned as ''
			$params['owner'] = 0;
	if (!isset($params['active']))
		$params['active'] = 0;
	//group members
	//if present, is ordered array of strings, each value being a member's item_id
	//like $params['members'] = array(0 => string '21', 1 => string '1')
	//if not, no member selected
	if (isset($params['members'])) {
		$members = $params['members'];
		unset($params['members']);
	} else
		$members = FALSE; //option not in use, or no member selected
	if ($item_id >= Booker::MINGRPID) {
		if ($members) {
			groupsupdate($this,$db,$item_id,TRUE,$members);
		} else {
			$sql = 'DELETE FROM '.$this->GroupTable.' WHERE parent=?';
			$db->Execute($sql,array($item_id));
		}
	}
	//(parent) groups
	if (isset($params['ingroups'])) {
		$groups = $params['ingroups'];
		unset($params['ingroups']);
	} else
		$groups = FALSE; //option not in use, or no group selected
	if ($groups) {
		if ($members)
			$groups = array_diff($groups,$members);//filter out any common values
		if ($groups)
			groupsupdate($this,$db,$item_id,FALSE,$groups);
	} elseif ($item_id > 0 && $item_id < Booker::MINGRPID) {
		$sql = 'DELETE FROM '.$this->GroupTable.' WHERE child=?';
		$db->Execute($sql,array($item_id));
	}
	//stylesfile
	if (isset($params['stylesdelete'])) {
		$fp = $config['uploads_path'];
		if ($fp && is_dir($fp)) {
			$ud = $this->GetPreference('pref_uploadsdir','');
			if ($ud)
				$fp = cms_join_path($fp,$ud,$params['oldstyles']);
			else
				$fp = cms_join_path($fp,$params['oldstyles']);
			if (is_file($fp))
				unlink($fp);
		}
		unset($params['stylesdelete']);
	}
	$t = $id.'stylesfile';
	if (isset($_FILES) && isset($_FILES[$t])) {
		$file_data = $_FILES[$t];
		if ($file_data['name']) {
		 	if ($file_data['error'] != 0)
				$umsg = $this->Lang('err_upload',$this->Lang('err_system'));
			else {
				$parts = explode('.',$file_data['name']);
				$ext = end($parts);
				if ($file_data['type'] != 'text/css'
				 || !($ext == 'css' || $ext == 'CSS')
				 || $file_data['size'] <= 0 || $file_data['size'] > 2048) { //plenty big enough in this context
					$umsg = $this->Lang('err_upload',$this->Lang('err_file'));
				} else {
					$h = fopen($file_data['tmp_name'],'r');
					if ($h) {
						//basic validation of file-content
						$content = fread($h,512);
						fclose($h);
						if ($content == FALSE)
							$umsg = $this->Lang('err_upload',$this->Lang('err_perm'));
						elseif (!preg_match('/\.bkgitem/',$content))
							$umsg = $this->Lang('err_upload',$this->Lang('err_file'));
						unset($content);
					} else
						$umsg = $this->Lang('err_upload',$this->Lang('err_perm'));
				}
				if (empty($umsg)) {
					$fp = $config['uploads_path'];
					if ($fp && is_dir($fp)) {
						$ud = $this->GetPreference('pref_uploadsdir','');
						if ($ud)
							$fp = cms_join_path($fp,$ud,$file_data['name']);
						else
							$fp = cms_join_path($fp,$file_data['name']);
						if (// !chmod($file_data['tmp_name'],0644) ||
							!cms_move_uploaded_file($file_data['tmp_name'],$fp)) {
							$umsg = $this->Lang('err_upload',$this->Lang('err_perm'));
						}
					} else
						$umsg = $this->Lang('err_upload',$this->Lang('err_system'));
				}
			}
			if (empty($umsg))
				$params['stylesfile'] = $file_data['name'];
			else {
				$msg .= '<br />'.$umsg;
				$params['stylesfile'] = $params['oldstyles'];
			}

		} else {
//TODO adding file	$params['stylesfile'] = $params['oldstyles'];
		}
	}
	unset($params['stylessel']);
	unset($params['oldstyles']);
	$t = $id.'imgfile';
	if (isset($_FILES) && isset($_FILES[$t])) {
		$file_data = $_FILES[$t];
		if ($file_data['name']) {
		 	if ($file_data['error'] != 0)
				$umsg = $this->Lang('err_upload',$this->Lang('err_system'));
			else {
				$fp = $config['uploads_path'];
				if ($fp && is_dir($fp)) {
					$ud = $this->GetPreference('pref_uploadsdir','');
					if ($ud)
						$fp = cms_join_path($fp,$ud,$file_data['name']);
					else
						$fp = cms_join_path($fp,$file_data['name']);
					if (// !chmod($file_data['tmp_name'],0644) ||
						!cms_move_uploaded_file($file_data['tmp_name'],$fp)) {
						$umsg = $this->Lang('err_upload',$this->Lang('err_perm'));
					}
				} else
					$umsg = $this->Lang('err_upload',$this->Lang('err_system'));
			}
			if (!empty($umsg)) {
				$msg .= '<br />'.$umsg;
			}
		}
	}
	unset($params['imgsel']);

	if ($item_id == -1) {
		$params['item_id'] = $db->GenID($this->ItemTable.'_seq');
		$data = array_keys($params);
		$fields = implode(',',$data);
		$data = array_values($params);
		$placer = str_repeat('?,',count($data)-1).'?';
		$sql = 'INSERT INTO '.$this->ItemTable." ($fields) VALUES ($placer)";
	} elseif ($item_id == -Booker::MINGRPID) {
		$params['item_id'] = $db->GenID($this->ItemTable.'_gseq');
		$data = array_keys($params);
		$fields = implode(',',$data);
		$data = array_values($params);
		$placer = str_repeat('?,',count($data)-1).'?';
		$sql = 'INSERT INTO '.$this->ItemTable." ($fields) VALUES ($placer)";
	} else {
		unset($params['item_id']);
		$data = array_keys($params);
		$fields = implode('=?,',$data).'=?';
		$data = array_values($params);
		$data[] = $item_id;
		$sql = 'UPDATE '.$this->ItemTable." SET $fields WHERE item_id=?";
	}
	//cleanup
	foreach ($data as &$val) {
		if (is_numeric($val))
			$val = $val + 0; //cast to number
		elseif ($val == '')
			$val = NULL;
	}
	unset($val);
	$db->Execute($sql,$data);
}
if (isset($params['cancel']) || $act == 'submit') {
	$t = ($is_group) ? 'groups':'items';
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$t));
}

// get data for the item with the passed-in id, or an empty one if that id not found
$funcs = new Booker\Utils();
//$item = $funcs->GetItem($this,$item_id,FALSE);
$sql = 'SELECT * FROM '.$this->ItemTable.' WHERE item_id=?';
$row = $db->GetRow($sql,array($item_id));
if ($row) {
	$item = (object) $row;
	//cleanups
	$item->item_id = (int)$item_id;
	if ($item->stylesfile && !$funcs->GetStylesURL($this,$item->item_id,FALSE)) //styles file must be there
		$item->stylesfile = '';
	if ($act == 'copy') {
		$item_id = ($is_group) ? -Booker::MINGRPID : -1;
		$item->item_id = $item_id;
		$item->name = $this->Lang('copied',$item->name);
		$item->alias = '';
		$item->owner = 0; //OR 'me' ?
	}
} else {
	//postgres supported pre-1.11
	$stype = (preg_match('/mysql/i',$config['dbms'])) ? '=DATABASE()':' IN (SELECT current_database())';
	$sql =<<<EOS
SELECT column_name,is_nullable FROM information_schema.columns
WHERE table_schema{$stype} AND table_name='{$this->ItemTable}'
ORDER BY ordinal_position
EOS;
	$rows = $db->GetAll($sql);
	$item = new stdClass();
	//object-members which represent inheritable values are NULL'd, if allowed
	foreach ($rows as $one) {
		$item->{$one['column_name']} = (stripos($one['is_nullable'],'Y') !== FALSE) ? NULL:'';
	}
	$item->item_id = $item_id; //-1 or -MINGRPID
	$item->active = 1;
}

$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
	cms_utils::get_theme_object();
$iconup = $theme->DisplayImage('icons/system/arrow-u.gif',$this->Lang('tip_up'),'','','systemicon');
$icondn = $theme->DisplayImage('icons/system/arrow-d.gif',$this->Lang('tip_down'),'','','systemicon');
$iconinfo = $theme->DisplayImage('icons/system/info.gif',$this->Lang('showhelp'),'','','systemicon tipper');

$cascade = '&#8225; '; //label prefix
$notyet = ' '.$this->Lang('notyet'); //development pending
$cleartypes = array('p'); //for StripTags()

//NB all objects' (except ingroups) name-field is used directly for dbase field names!

$pdev = $this->CheckPermission('Modify Any Page');
$padm = $this->_CheckAccess('admin');
$pmod = ($act == 'see') ? FALSE :
		$padm
|| ($act == 'edit' && $this->_CheckAccess('modify'))
|| (($act == 'add' || $act == 'copy') && $this->_CheckAccess('add'));

if (!$pmod) {
	//avoid empty strings in displayed text
	$none = $this->Lang('none');
	$nolimit = $this->Lang('nolimit');
}

// setup variables for the view-template
$tplvars = array('mod' =>  $pmod);

$t = array();
$this->_BuildNav($id,$t,$returnid,$tplvars);

//multipart form needed for file uploads
$tplvars['startform'] = $this->CreateFormStart($id,'update',$returnid,'POST',
	'multipart/form-data','','',array('item_id'=>$item_id));
$tplvars['endform'] = $this->CreateFormEnd();
$tplvars['tab_headers'] =  $this->StartTabHeaders().
	$this->SetTabHeader('basic',$this->Lang('basic'),($seetab=='basic')).
	$this->SetTabHeader('advanced',$this->Lang('advanced'),($seetab=='advanced')).
	$this->SetTabHeader('formats',$this->Lang('formats'),($seetab=='formats')).
	$this->EndTabHeaders().
	$this->StartTabContent();

$tplvars += array(
	'tab_footers' => $this->EndTabContent(),
	'end_tab' => $this->EndTab(),
	'start_basic_tab' => $this->StartTab('basic'),
	'start_adv_tab' => $this->StartTab('advanced'),
	'start_fmt_tab' => $this->StartTab('formats')
);

$hidden = $this->CreateInputHidden($id,'active_tab','');

$tplvars['title'] = ($is_group) ? $this->Lang('group_page_title'):$this->Lang('item_page_title');
$s = ($is_group) ? $this->Lang('group'):$this->Lang('item');

$baseurl = $this->GetModuleURLPath();

$intro = ($pmod) ? $this->Lang('help_compulsory').'<br />' : '';
$intro .= $this->Lang('help_cascade');
$tplvars['intro'] = $intro;

$inherit = $this->Lang('inherit');
$yes = $this->Lang('yes');
$no = $this->Lang('no');
//script accumulators
$jsfuncs = array();
$jsloads = array();
$jsincs = array();
//construct arrays of UI-items, in display-order
$basic = array();
$advanced = array();
$formats = array();
//------- active
if ($pmod)
	$i = $this->CreateInputCheckbox($id,'active','1',$item->active);
elseif ($item->active)
	$i = $yes;
else
	$i = $no;
$basic[] = array('ttl'=>$this->Lang('title_active'),
'mst'=>1,
'inp'=>$i,
'hlp'=>NULL
);
//------- name
$t = $this->Lang('title_name');
if ($pmod)
	$t .= ' ('.$this->Lang('short_length').')';
if ($pmod)
	$i = $this->CreateInputText($id, 'name', $item->name, 40, 64);
elseif ($item->name)
	$i = $item->name;
else
	$i = $none;
$h = ($pmod) ?
	$this->Lang('help_use_smarty')/*.', '.$this->Lang('label_usage')*/:
	NULL;
$basic[] = array('ttl'=>$t,
'mst'=>1,
'inp'=>$i,
'hlp'=>$h
);
//------- description
if ($pmod)
	$i = $this->CreateTextArea(TRUE,$id,$item->description,'description','','','','',80,5,'','','style="height:12em;"');
elseif ($item->description)
	$i = $funcs->StripTags($item->description,$cleartypes);
else
	$i = $none;
$h = ($pmod) ? $this->Lang('help_use_smarty'):NULL;
$basic[] = array('ttl'=>$this->Lang('title_long_desc'),
'inp'=>$i,
'hlp'=>$h
);
//------- image
if ($pmod) {
	$i = $this->CreateInputText($id,'image',$item->image,60,128);
	$files = $funcs->GetUploadedFiles($this,'jpg,jpeg,gif,png,svg');
	if ($files) {
		$files = array_combine($files,$files); //keys match values
		$files = array_merge(array($this->Lang('select')=>''),$files);
		$i .= '<br />'.$this->CreateInputDropdown($id,'imgsel',$files,-1,-1,'onchange="imgfile_selected(this)"'); //TODO onchange set textinput if empty
	}
	$i .= '<br />'.$this->CreateInputFile($id,'imgfile','image/*',30,'id="'.$id.'imgfile" title="'.
		$this->Lang('tip_upload').'" onchange="imgfile_selected(this)"');
	if ($item->image && $files)
		$i .= ' '.$this->CreateInputCheckbox($id,'imgdelete',1,-1).'&nbsp;'.$this->Lang('delete_upload',$item->image);
} elseif ($item->image) {
	$t = $funcs->GetImageURLs($this,$item->image,$item->name);
	if ($t) {
		$i = '';
		foreach ($t as &$one) {
			$i .= '  <img src="'.$one->url.'" style="width:50px;height:50px;" />';
		}
		unset($one);
	} else
		$i = $this->Lang('missing_type',$this->Lang('file'));
} else
	$i = $none;
$basic[] = array('ttl'=>$cascade.$this->Lang('title_image'),
'inp'=>$i,
'hlp'=>$this->Lang('help_image')
);
//------- membersname
if ($is_group) {
	if ($pmod)
		$i = $this->CreateInputText($id,'membersname',$item->membersname,25,32);
	elseif ($item->membersname)
		$i = $item->membersname;
	else
		$i = $none;
	$basic[] = array('ttl'=>$cascade.$this->Lang('title_membersname'),
	'inp'=>$i,
	'hlp'=>$this->Lang('help_membersname')
	);
}
//------- slotcount, slottype (c.f. TimeIntervals())
$alltypes = explode(',',$this->Lang('periods')); //'minute,hour,day,week,month,year'
if ($pmod) {
	$alltypes = array_flip($alltypes);
	$i = $this->CreateInputText($id,'slotcount',$item->slotcount,6,6).'&nbsp;'.
		$this->CreateInputDropdown($id,'slottype',$alltypes,-1,$item->slottype);
} else {
	$i = $item->slotcount.' '.$alltypes[$item->slottype];
}
$basic[] = array('ttl'=>$cascade.$this->Lang('title_slotlength'),
'mst'=>1,
'inp'=>$i,
'hlp'=>NULL //$this->Lang('help_slotlength')
);
//------- max slots per booking
if ($pmod)
	$i = $this->CreateInputText($id,'bookcount',$item->bookcount,3,3);
elseif ($item->bookcount)
	$i = $item->bookcount;
else
	$i = $nolimit;
$basic[] = array('ttl'=>$cascade.$this->Lang('title_bookcount'),
'inp'=>$i,
'hlp'=>$this->Lang('help_bookcount')
);
//------- fees
$sql = 'SELECT description,fee,feecondition FROM '.$this->FeeTable.' WHERE item_id=? AND active=1 ORDER BY condorder';
$sel = $db->GetAll($sql,array($item_id));
if ($sel) {
	$fees = array();
	foreach ($sel as &$one) {
		$oneset = new stdClass();
		$oneset->desc = $one['description'];
		$oneset->fee = $one['fee'];
		$t = $one['feecondition'];
		if (!$t)
			$t = $this->Lang('always');
		$oneset->cond = $t;
		$fees[] = $oneset;
	}
	unset($one);
	$tplvars['entries'] = $fees;
	$i = Booker\Utils::ProcessTemplate($this,'brieffees.tpl',$tplvars);
	$t = $this->Lang('edit');
	$h = $this->Lang('help_fee');
} else {
	$i = $this->Lang('nofees').'<br />';
	$t = $this->Lang('addfee');
	$h = NULL;
}
if ($pmod)
	$i .= '<br />'.$this->CreateInputSubmit($id,'modfee',$t,
		'onclick="current_tab();return confirm(\''.$this->Lang('allsaved').'\');"');
$basic[] = array('ttl'=>$cascade.$this->Lang('title_feeusage'),
'inp'=>$i,
'hlp'=>$h
);
//============ ADVANCED TAB
//------- alias
if ($pdev && $pmod) {
	$i = $this->CreateInputText($id,'alias',$item->alias,20,24);
	$h = $this->Lang('help_alias',$s);
} else {
	if ($item->alias)
		$i = $item->alias;
	else
		$i = $none;
	$h = NULL;
}
$advanced[] = array('ttl'=>$this->Lang('title_alias',$s),
'inp'=>$i,
'hlp'=>$h
);
//------- keywords
if ($pdev && $pmod) {
	$i = $this->CreateTextArea(FALSE,$id,$item->keywords,'keywords','','','','',50,4,'','','style="height:4em;"');
	$t = ($is_group) ? $this->Lang('title_groups'):$this->Lang('title_items');
	$t = mb_convert_case($t,MB_CASE_LOWER);
	$h = $this->Lang('help_keywords',$t);
} else {
	if ($item->keywords)
		$i = $item->keywords;
	else
		$i = $none;
	$h = NULL;
}
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_keywords'),
'inp'=>$i,
'hlp'=>$h
);
//------- members
if ($is_group) {
	//to keep things manageable, we allow non-member groups to be added to this one,
	//but not so for non-groups. any current member (group or resouce) can be de-selected
	$sql = 'SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id>='.Booker::MINGRPID.' AND item_id<>? ORDER BY name';
	$allgrps = $db->GetAssoc($sql,array($item_id));
	if ($item_id > 0) { //i.e. not new
		$sql = 'SELECT child FROM '.$this->GroupTable.' WHERE parent=? ORDER BY likeorder';
		$relations = $db->GetCol($sql,array($item_id));
		$rc = count($relations);
		if ($rc > 0) {
			$t = implode(',',$relations);
			$sql = 'SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id IN('.$t.')';
			$sel = $db->GetAssoc($sql);
			if ($pmod && $padm) {
				foreach ($sel as $k=>$name) {
					unset($allgrps[$k]);
				}
				//rest are still alphabetic by name
				$allgrps = $sel + $allgrps;
				//TODO send 'active_tab' as a $param to action.swapgroups
				$i = groupstable($this,$tplvars,$id,'members',$returnid,$icondn,$iconup,
					$item_id,$allgrps,$relations,TRUE,TRUE,'members');
				if ($rc > 1) //TODO send 'active_tab' as a $param to action.swapgroups
					$i .= '  '.$this->CreateInputSubmit($id,'sortlike',$this->Lang('sort'),
						'title="'.$this->Lang('tip_sortchilds').'" style="display:none;"'); //button shown by runtime js
			} elseif ($sel)
				$i = implode(', ',$sel);
			else
				$i = $none;
		} elseif ($pmod && $padm) //editing old item
			//create table, alphabetic by groupname, each row has name, unchecked checkbox
			$i = groupstable($this,$tplvars,$id,'members',$returnid,$icondn,$iconup,
				$item_id,$allgrps,FALSE,TRUE,TRUE,'members');
		else //viewing old item
			$i = $none;
	} elseif ($pmod && $padm) //editing new item
		//create table, alphabetic by groupname, each row has name, unchecked checkbox
		$i = groupstable($this,$tplvars,$id,'members',$returnid,$icondn,$iconup,
			$item_id,$allgrps,FALSE,TRUE,TRUE,'members');
	else //viewing new item - should never happen
		$i = $none;
	if (count($allgrps) > 1)
		$h = $this->Lang('help_members2').
		'<span class="dndhelp">'.$this->Lang('help_dnd').'</span>'.
		$this->Lang('help_members');
	else
		$h = $this->Lang('help_members');

	$advanced[] = array('ttl'=>$this->Lang('title_members'),
	'inp'=>$i,
	'hlp'=>$h
	);

	$t = $this->Lang('err_server');
	$jsfuncs[] = <<<EOS
function sortresponse(data,status)
{
 if (status=='success') {
  if (data != '') {
   $('#members > tbody').html(data);
   $('#members .updown').hide();
  }
 } else {
  $('#page_tabs').prepend('<p style="font-weight:bold;color:red;">{$t}!</p><br />');
 }
}

EOS;
	$u = $this->create_url($id,'sortlike','',array('item_id'=>$item_id,'first_id'=>''));
	$offs = strpos($u,'?mact=');
	$u = str_replace('&amp;','&',substr($u,$offs+1));

	$jsloads[] = <<<EOS
 $('#{$id}sortlike').css('display','block').click(function(ev) {
  var first = $('#members').find('input:checkbox').eq(1).val();
  $.ajax({
   type: 'POST',
   url: 'moduleinterface.php',
   data: '{$u}'+first,
   success: sortresponse,
   dataType: 'html'
  });
  ev.stopImmediatePropagation();
  ev.preventDefault();
	return false;
 });

EOS;
//}
//------- cleargroup
//if ($is_group) {
	if ($pmod && $padm) {
		$choices = array($inherit=>-1,$no=>0,$yes=>1);
		$sel = is_null($item->cleargroup) ? -1:(int)$item->cleargroup;
		$i = $this->CreateInputRadioGroup($id,'cleargroup',$choices,$sel,'','&nbsp;&nbsp;');
		//override crappy default label-layout
		$i = preg_replace('~label class="(.*)"~U','label class="\\1 radiolabel"',$i);
	} else {
		if ($item->cleargroup)
			$i = $yes;
		elseif (is_null($item->cleargroup))
			$i = $inherit;
		else
			$i = $no;
	}
	$advanced[] = array('ttl'=>$cascade.$this->Lang('title_cleargroup2'),
	'inp'=>$i,
	'hlp'=>NULL
	);
}
//------- groups
$sql = 'SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id>='.Booker::MINGRPID.' AND item_id<>? ORDER BY name';
$allgrps = $db->GetAssoc($sql,array($item_id));
if ($allgrps) {
	if ($item_id > 0) { //i.e. not new
		$sql = 'SELECT parent FROM '.$this->GroupTable.' WHERE child=? ORDER BY proximity';
		$relations = $db->GetCol($sql,array($item_id));
		$rc = count($relations);
		if ($rc > 0) {
			//get selected items 1st, in order
			$sel = array();
			foreach ($relations as $k) {
				$offset = array_search($k,array_keys($allgrps));
				$sel += array_slice($allgrps,$offset,1,TRUE);
				unset($allgrps[$k]);
			}
			//rest are still alphabetic by name
			if ($pmod && $padm) {
				$allgrps = $sel + $allgrps;
				 //TODO send 'active_tab' as a $param to action.swapgroups
 				$i = groupstable($this,$tplvars,$id,'ingroups',$returnid,$icondn,$iconup,
					$item_id,$allgrps,$relations,TRUE,FALSE,'groups');
				if ($rc > 1)
					$i .= '  '.$this->CreateInputSubmit($id,'sortlike',$this->Lang('sort'),
						'title="'.$this->Lang('tip_sortparents').'" style="display:none;"'); //button shown by runtime js
			} elseif ($sel)
				$i = implode(', ',$sel);
			else
				$i = $none;
		} elseif ($pmod && $padm) //editing old item
			//create table, alphabetic by groupname, each row has name, unchecked checkbox
			$i = groupstable($this,$tplvars,$id,'ingroups',$returnid,$icondn,$iconup,
				$item_id,$allgrps,FALSE,TRUE,FALSE,'groups');
		else //viewing old item
			$i = $none;
	} elseif ($pmod && $padm) //editing new item
		//create table, alphabetic by groupname, each row has name, unchecked checkbox
		$i = groupstable($this,$tplvars,$id,'ingroups',$returnid,$icondn,$iconup,
			$item_id,$allgrps,FALSE,TRUE,FALSE,'groups');
	else //viewing new item - should never happen
		$i = $none;
	$h = $this->Lang('help_groups',$s,$s,$s);
	if ($pmod && count($allgrps) > 1)
		$h .= '<span class="dndhelp">'.$this->Lang('help_dnd').'</span>';
	$advanced[] = array('ttl'=>$this->Lang('title_groups2',$s),
	'inp'=>$i,
	'hlp'=>$h
	);

	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.tablednd.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.SSsort.min.js"></script>
EOS;

	$t = $this->Lang('err_server');
	$u = $this->create_url($id,'sortlike','',array('item_id'=>$item_id,'sort'=>''));
	$offs = strpos($u,'?mact=');
	$u = str_replace('&amp;','&',substr($u,$offs+1)); //'groups' or 'members' will be appended at runtime

	$jsloads[] =<<<EOS
 $('p.help').hide();
 $('.dndhelp').css('display','block');
 $('.updown').hide();
 $('input[name^="{$id}sortlike"]').css('display','inline').click(function(ev) {
  ev.stopImmediatePropagation();
  ev.preventDefault();
  var \$tbl = $(this).prev('table'),
   what = \$tbl.attr('id');
  $.ajax({
   type: 'POST',
   url: 'moduleinterface.php',
   data: '{$u}'+what,
   dataType: 'html',
   success: function(data,status) {
    if (status=='success'){
     if (data != ''){
      \$tbl.find('tbody').html(data);
      \$tbl.find('.updown').hide();
     }
    } else {
     $('#page_tabs').prepend('<p style="font-weight:bold;color:red;">{$t}!</p><br />');
    }
   }
  });
  return false;
 });
 $('img.tipper').css({'display':'inline','padding-left':'10px'}).click(function() {
   $(this).parent().next().next().slideToggle();
 });
 $('table.table_sort').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s'
 });
 $('table.table_drag').tableDnD({
  onDragClass: 'row1hover',
  onDrop: function(table, droprows) {
   var odd = false,
    oddclass = 'row1',
    evenclass = 'row2',
    droprow = $(droprows)[0],
    name, hname, fullname;
   $(table).find('tbody tr').each(function() {
    name = (odd) ? oddclass : evenclass;
    if (this == droprow){
     name = name+'hover';
    }
    $(this).removeClass().addClass(name);
    odd = !odd;
   });
  }
 }).find('tbody tr').removeAttr('onmouseover').removeAttr('onmouseout')
 .mouseover(function() {
     var now = $(this).attr('class');
     $(this).attr('class', now+'hover');
 }).mouseout(function() {
    var now = $(this).attr('class');
    var to = now.indexOf('hover');
    $(this).attr('class', now.substring(0,to));
 });
 $('#members th > input').click(function() {
   var chk = $(this).is(':checked');
   $('#members > tbody').find('input[type="checkbox"]').attr('checked',chk);
 });
 $('#members td > input').change(function() {
  if ($(this).is(':checked')){
   var v = $(this).val();
   $('#groups td > input[value='+v+']').removeAttr('checked');
  }
 });
 $('#groups td > input').change(function() {
  if ($(this).is(':checked')){
   var v = $(this).val();
   $('#members td > input[value='+v+']').removeAttr('checked');
  }
 });

EOS;
}
//------- available
if ($pmod)
	$i = $this->CreateTextArea(FALSE,$id,$item->available,'available','','','','',40,3,'','','style="height:3em;"');
elseif ($item->available)
	$i = $item->available;
else
	$i = $this->Lang('always');
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_available'),
'inp'=>$i,
'hlp'=>$this->Lang('help_intervals')
);
if ($is_group) {
//-------- sub-group allocation mode
	$choices = array(
		$this->Lang('assignnone')=>Booker::ALLOCNONE,
		$this->Lang('assignfirst')=>Booker::ALLOCFIRST,
		$this->Lang('assignrandom')=>Booker::ALLOCRAND,
		$this->Lang('assignrotate')=>Booker::ALLOCROTE,
		$this->Lang('assignchoose')=>Booker::ALLOCCHOOSE
	);
	if ($pmod) {
		$t = (int)$item->subgrpalloc;
		$i = $this->CreateInputDropdown($id,'subgrpalloc',$choices,-1,$t);
	} else {
		$i = array_search($item->subgrpalloc,$choices);
	}
	$advanced[] = array('ttl'=>$cascade.$this->Lang('title_subgrpalloc'),
	'inp'=>$i,
	'hlp'=>$this->Lang('help_subgrpalloc'));
}
//------- leadcount, leadtype
$alltypes = explode(',',$this->Lang('periods')); //'minute,hour,day,week,month,year'
$alltypes[8] = $alltypes[5];
$alltypes[7] = $alltypes[4];
$alltypes[6] = $alltypes[3];
$alltypes[5] = $alltypes[2];
unset($alltypes[4]);
$alltypes[3] = $alltypes[1];
unset($alltypes[2]);
unset($alltypes[1]);
if ($pmod) {
	$alltypes = array_flip($alltypes);
	$i = $this->CreateInputText($id, 'leadcount', $item->leadcount, 6, 6).'&nbsp;'.
		$this->CreateInputDropdown($id,'leadtype',$alltypes,-1,$item->leadtype);
} else {
	$i = $item->leadcount.' '.$alltypes[$item->leadtype];
}
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_lead'),
'inp'=>$i,
'hlp'=>$this->Lang('help_lead')
);
//------- keepcount, keeptype
$alltypes = explode(',',$this->Lang('periods')); //'minute,hour,day,week,month,year'
unset($alltypes[0]);
unset($alltypes[1]);
if ($pmod && $padm) {
	$alltypes = array_flip($alltypes);
	$i = $this->CreateInputText($id,'keepcount',$item->keepcount,6,6).'&nbsp;'.
		$this->CreateInputDropdown($id,'keeptype',$alltypes,-1,$item->keeptype);
} else {
	$i = $item->keepcount.' '.$alltypes[$item->keeptype];
}
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_keep'),
'inp'=>$i,
'hlp'=>$this->Lang('help_keep')
);
//------- rationcount
$i = ($pmod) ?
	$this->CreateInputText($id, 'rationcount', $item->rationcount,3,3):
	$item->rationcount;
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_ration'),
'inp'=>$i,
'hlp'=>$this->Lang('help_ration')
);
//------- approver
if ($pmod)
	$i = $this->CreateInputText($id,'approver',$item->approver,30,64);
elseif ($item->approver)
	$i = $item->approver;
else
	$i = $none;
$advanced[] = array('ttl'=>$cascade.$this->Lang('approver'),
'inp'=>$i,
'hlp'=>NULL
);
//------- contact
if ($pmod)
	$i = $this->CreateInputText($id,'approvercontact',$item->approvercontact,40,128);
elseif ($item->approvercontact)
	$i = $item->approvercontact;
else
	$i = $none;
$advanced[] = array('ttl'=>$cascade.$this->Lang('approvercontact'),
'inp'=>$i,
'hlp'=>NULL
);
//------- owner
$sql = 'SELECT user_id,first_name,last_name FROM '.$this->UserTable.' WHERE active=1 ORDER BY last_name,first_name';
$allusers = $db->GetAssoc($sql);
if ($pmod) {
	if ($allusers) {
		foreach ($allusers as $k=>&$t) {
			$t = trim($t['first_name'].' '.$t['last_name']);
		}
		unset($t);
	}
	$allusers = array_flip($allusers);
	//prepend other choices NB something changes index 0 to '', even if done postflip()
	$allusers = array(
		$inherit=>-1,
		$this->Lang('none')=>0
	) + $allusers;
	$i = $this->CreateInputDropdown($id,'owner',$allusers,-1,$item->owner);
} elseif ($item->owner)
	$i = $allusers[$item->owner];
else
	$i = $none;
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_owner2'),
'inp'=>$i,
'hlp'=>NULL
);
//------- feugroup (savers)
$ob = ModuleOperations::get_instance()->get_module_instance('FrontEndUsers');
if (is_object($ob)) {
	$allusers = $ob->GetGroupList(); //associative array with group names as keys, id's as values
	unset($ob);
	$rc = count($allusers);
	if ($pmod) {
		if ($rc)
			ksort($allusers, SORT_NATURAL | SORT_FLAG_CASE);
/* TODO filter by permissions c.f. for admin users
		$pref = cms_db_prefix();
		$sql =<<<EOS
SELECT DISTINCT U.user_id,U.username,U.first_name,U.last_name FROM {$this->UserTable} U
JOIN {$pref}user_groups UG ON U.user_id = UG.user_id
JOIN {$pref}group_perms GP ON GP.group_id = UG.group_id
JOIN {$pref}permissions P ON P.permission_id = GP.permission_id
JOIN {$pref}groups GR ON GR.group_id = UG.group_id
WHERE
EOS;
		if (!$allowners)
			$sql .= "U.user_id=$uid AND "; //no injection risk
		$sql .=<<<EOS U.admin_access=1 AND U.active=1 AND GR.active=1 AND
P.permission_name IN ('{$this->PermAddName}','{$this->PermAdminName}','{$this->PermModName}')
ORDER BY U.last_name,U.first_name
EOS;
*/
		$allusers = array($this->Lang('none')=>0) + $allusers; //prepend 'none'
		$i = $this->CreateInputDropdown($id,'feugroup',$allusers,-1,$item->feugroup);
	} elseif ($rc && $item->feugroup)
		$i = array_search($item->feugroup,$allusers);
	else
		$i = $none;
	$advanced[] = array('ttl'=>$cascade.$this->Lang('title_feugroup'),
	'inp'=>$i,
	'hlp'=>$this->Lang('help_feugroup')
	);
}
//------- payments interface
if ($pmod)
	$i = $this->CreateInputText($id,'paymentiface',$item->paymentiface,30,48);
elseif ($item->paymentiface)
	$i = $item->paymentiface;
else
	$i = $none;
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_paymentiface').$notyet,
'inp'=>$i,
'hlp'=>$this->Lang('help_paymentiface')
);
//------- custom-forms interface
if ($pmod)
	$i = $this->CreateInputText($id,'formiface',$item->formiface,30,48);
elseif ($item->formiface)
	$i = $item->formiface;
else
	$i = $none;
$advanced[] = array('ttl'=>$cascade.$this->Lang('title_formiface').$notyet,
'inp'=>$i,
'hlp'=>$this->Lang('help_formiface')
);
//============ FORMATS TAB
//------- timezone
if ($pmod)
	$i = $this->CreateInputText($id, 'timezone', $item->timezone,40,48);
elseif ($item->timezone)
	$i = $item->timezone;
else
	$i = $none;
$formats[] = array('ttl'=>$cascade.$this->Lang('title_zone'),
'inp'=>$i,
'hlp'=>$this->Lang('help_zone')
);
//------- latitude
$i = ($pmod) ?
	$this->CreateInputText($id, 'latitude', $item->latitude,8,8):
	$item->latitude;
$formats[] = array('ttl'=>$cascade.$this->Lang('latitude'),
'inp'=>$i,
'hlp'=>$this->Lang('help_latitude')
);
//------- longitude
$i = ($pmod) ?
	$this->CreateInputText($id, 'longitude', $item->longitude,8,8):
	$item->longitude;
$formats[] = array('ttl'=>$cascade.$this->Lang('longitude'),
'inp'=>$i,
'hlp'=>$this->Lang('help_longitude')
);
//-------- SMS prefix, pattern
if ($pmod)
	$i = $this->CreateInputText($id,'smsprefix',$item->smsprefix,4,8);
elseif ($item->smsprefix)
	$i = $item->smsprefix;
else
	$i = $none;
$formats[] = array('ttl'=>$cascade.$this->Lang('title_smsprefix'),
'inp'=>$i,
'hlp'=>$this->Lang('help_smsprefix')
);
if ($pmod)
	$i = $this->CreateInputText($id,'smspattern',$item->smspattern,20,32);
elseif ($item->smspattern)
	$i = $item->smspattern;
else
	$i = $none;
$formats[] = array('ttl'=>$cascade.$this->Lang('title_smspattern'),
'inp'=>$i,
'hlp'=>$this->Lang('help_smspattern')
);
//------- dateformat
if ($pmod)
	$i = $this->CreateInputText($id,'dateformat',$item->dateformat,10,12);
elseif ($item->dateformat)
	$i = $item->dateformat;
else
	$i = $none;
$formats[] = array('ttl'=>$cascade.$this->Lang('title_dateformat'),
'inp'=>$i,
'hlp'=>$this->Lang('help_date')
);
//------- timeformat
if ($pmod)
	$i = $this->CreateInputText($id,'timeformat',$item->timeformat,8,12);
elseif ($item->timeformat)
	$i = $item->timeformat;
else
	$i = $none;
$formats[] = array('ttl'=>$cascade.$this->Lang('title_timeformat'),
'inp'=>$i,
'hlp'=>$this->Lang('help_time')
);
//------- listformat
if ($is_group) {
	$choices = array(
	$inherit=>-1,
	$this->Lang('start+user')=>Booker::LISTSU,
	$this->Lang('start+resource')=>Booker::LISTSR,
	$this->Lang('resource+start')=>Booker::LISTRS,
	$this->Lang('user+start')=>Booker::LISTUS
	);
} else {
	$choices = array(
	$inherit=>-1,
	$this->Lang('start+user')=>Booker::LISTSU,
	$this->Lang('user+start')=>Booker::LISTUS
	);
}

if ($pmod) {
	$i = $this->CreateInputDropdown($id,'listformat',$choices,-1,$item->listformat,
		'id="'.$id.'listformat" title="'.$this->Lang('tip_listtype').'"');
} else
	$i = array_search($item->listformat,$choices);
$formats[] = array('ttl'=>$cascade.$this->Lang('listformat'),
'inp'=>$i,
'hlp'=>NULL //$this->Lang('help_')
);
//------- stylesfile
//remember this, to support incremental change
$hidden .= $this->CreateInputHidden($id,'oldstyles',$item->stylesfile);
//set explicit id for file input, cuz CMSMS doesn't!
if ($pmod) {
	$i = $this->CreateInputText($id,'stylesfile',$item->stylesfile,30,36);
	$files = $funcs->GetUploadedFiles($this,'css');
	if ($files) {
		$files = array_combine($files,$files); //keys match values
		$files = array_merge(array($this->Lang('select')=>''),$files);
		$i .= '<br />'.$this->CreateInputDropdown($id,'stylessel',$files,-1,-1,'onchange="stylefile_selected(this)"'); //TODO onchange set textinput if empty
	}
	$i .= '<br />'.$this->CreateInputFile($id,'stylesfile','text/css',36,'id="'.$id.'stylesfile" title="'.
		$this->Lang('tip_upload').'" onchange="stylefile_selected(this)"');
	if ($item->stylesfile && $files)
		$i .= ' '.$this->CreateInputCheckbox($id,'stylesdelete',1,-1).'&nbsp;'.$this->Lang('delete_upload',$item->stylesfile);
} elseif ($item->stylesfile)
	$i = $item->stylesfile;
else
	$i = $none;

//$h = $this->Lang('help_upload',$this->Lang('apply'),$this->Lang('submit'));
$formats[] = array('ttl'=>$cascade.$this->Lang('title_styles',$s),
'inp'=>$i,
'hlp'=>NULL
);
//-------

$tplvars += array(
	'basic' => $basic,
	'advanced' => $advanced,
	'formats' => $formats,
	'showtip' => $iconinfo
);

if ($pmod) {
	$tplvars['apply'] = $this->CreateInputSubmit($id, 'apply', $this->Lang('apply'),'onclick="current_tab()"');
	$tplvars['submit'] = $this->CreateInputSubmit($id, 'submit', $this->Lang('submit'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id, 'cancel', $this->Lang('cancel'));
} else {
	$tplvars['apply'] = '';
	$tplvars['submit'] = '';
	$tplvars['cancel'] = $this->CreateInputSubmit($id, 'cancel', $this->Lang('close'));
}

$tplvars['hidden'] = $hidden;
$tplvars['message'] = $msg;

$jsfuncs[] = <<<EOS
function current_tab() {
 var active = $('#page_tabs > .active');
 $('#{$id}active_tab').val(active.attr('id'));
}
function imgfile_selected(el) {
 var sel = el.value;
 if (sel) {
 	var now = $('#{$id}image').val();
	if (now) {
	  now = now+','+sel;
	} else {
	  now = sel;
	}
	$('#{$id}image').val(now);
 }
}
function stylefile_selected(el) {
 var sel = el.value;
 if (sel) {
 	var now = $('#{$id}stylesfile').val();
	if (now) {
	  now = now+','+sel;
	} else {
	  now = sel;
	}
	$('#{$id}stylesfile').val(now);
 }
}

EOS;

if ($jsloads) {
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,'openitem.tpl',$tplvars);
