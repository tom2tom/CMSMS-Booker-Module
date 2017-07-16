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
 function groupstable(&$mod, &$tplvars, $id, $obname, $returnid,  $icondn, $iconup,
	$item_id, &$groups, $relations = FALSE, $dodrag = FALSE, $dosort = FALSE, $tableid = FALSE)
 {
	$r = 0;
	$rc = count($groups) - 1; //last index
	$sel = is_array($relations);
	$target = ($obname == 'members') ? 'child' : 'parent';
	$rows = [];
	//NOTE action.sortlike table-data creation must conform with this
	foreach ($groups as $k => &$name) {
		$one = new stdClass();
		$one->name = $name;
		if ($r == 0) {
			$one->uplink = '';
			$one->dnlink = '';
			$prevk = $k;
		} else {
			//TODO action.swapgroups would work better with a $params['active_tab'] for returning!
			//TODO mechanism to incrementally [de]select items and re-arrange the current selected bundle
			$one->uplink = $mod->CreateLink($id, 'swapgroups', $returnid, $iconup,
				['item_id' => $k, 'prev_item_id' => $prevk, 'ref_id' => $item_id, 'change' => $target]);
			$rows[$r - 1]->dnlink = $mod->CreateLink($id, 'swapgroups', $returnid, $icondn,
				['item_id' => $prevk, 'next_item_id' => $k, 'ref_id' => $item_id, 'change' => $target]);
			$prevk = $k;
			if ($r == $rc) {
				$one->dnlink = '';
			}
		}
		if ($sel) {
			$m = (in_array($k, $relations)) ? $k : -1;
		} else {
			$m = -1;
		}
		$one->check = $mod->CreateInputCheckbox($id, $obname.'[]', $k, $m);
		$rows[] = $one;
		$r++;
	}
	unset($name);
	$c = count($rows);
	$tplvars['rc'] = $c;
	if ($c > 1) {
		$tplvars['selectall'] = $mod->CreateInputCheckbox($id, 'selectall', 1, -1);
	} else {
		$tplvars['selectall'] = '';
	}
	$tplvars['identifier'] = $tableid;
	$tplvars['drag'] = $dodrag && ($c > 1);
	$tplvars['sort'] = $dosort && ($c > 1);
	$tplvars['entries'] = $rows;

	return Booker\Utils::ProcessTemplate($mod, 'groupsinput.tpl', $tplvars);
 }
}

if (!function_exists('groupsupdate')) {
/*
 Upsert relevant data in GroupTable

 $mod reference to current module object
 $db reference to current database-connection-object
 $item_id resource/group identifier as per ItemTable, maybe newly-allocated
 $parent TRUE if $item_id is for a parent group (for which we're processing member(s))
  or FALSE if is child (for which we're processing group(s))
 $sel array of id's of group members (if $parent TRUE) or else groups in which $item_is placed
*/
 function groupsupdate(&$mod, &$db, $item_id, $parent, $sel)
 {
	if (!$sel) {
		return;
	}
	$o = 1;	//reordered-row ordinator
	$os = 1; //appended-row offsetter
	$getby = ($parent) ? 'parent' : 'child'; //name of field to filter records
	$sortby = ($parent) ? 'likeorder' : 'proximity';
	$current = $db->GetAssoc("SELECT * FROM $mod->GroupTable WHERE $getby=? ORDER BY $sortby", [$item_id]);
	$new = [];
	if ($parent) { //we're processing members of group $item_id
		foreach ($sel as $oneid) {
			if ($current) {
				$p = rowof($current, $oneid, $item_id);
				if ($p > 0) {
					$p = $current[$p]['proximity'];
				} else {
//					$p = maxfield($current,'proximity','child',$oneid) + $os++;
					$p = Booker::MINGRPID + $os++;
				}
			} else {
//				$p = maxfield($current,'likeorder','child',$oneid) + $os++;
				$p = Booker::MINGRPID + $os++;
			}
			//add new member in lowest likeorder
			$new[] = ['child' => $oneid,'parent' => $item_id,'likeorder' => $o++,'proximity' => $p];
		}
	} else { //we're processing groups of which $item_id is a member
		foreach ($sel as $oneid) {
			if ($current) {
				$p = rowof($current, $item_id, $oneid);
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
			$new[] = ['child' => $item_id,'parent' => $oneid,'likeorder' => $p,'proximity' => $o++];
		}
	}
	$db = $mod->dbHandle;
	$nt = 10;
	//TODO $utils->SafeExec()
	while ($nt > 0) {
		$db->Execute('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE'); //this isn't perfect!
		$db->StartTrans();
		if ($current) {
			$db->Execute("DELETE FROM $mod->GroupTable WHERE $getby=?", [$item_id]); //start afresh
		}
		if ($new) {
			$sql = 'INSERT INTO '.$mod->GroupTable.' (child,parent,likeorder,proximity) VALUES (?,?,?,?)';
			foreach ($new as &$row) {
				$db->Execute($sql, [$row['child'], $row['parent'], $row['likeorder'], $row['proximity']]);
			}
			unset($row);
		}
		if ($db->CompleteTrans()) {
			break;
		} else {
			$nt--;
			usleep(50000);
		}
	}

	if ($current || $new) {
		$utils = new Booker\Utils();
		$utils->OrderGroups($mod);
	}
 }

 function rowof(&$current, $c, $p)
 {
	foreach ($current as $k => &$row) {
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

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'], ENT_QUOTES | ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
}

$item_id = (int)$params['item_id'];
$is_group = ($item_id >= Booker::MINGRPID || $item_id == -Booker::MINGRPID);

if (isset($params['cancel'])) {
	$resume = array_pop($params['resume']);
	$newparms = ['resume' => json_encode($params['resume'])]; //TODO etc
	switch ($resume) {
	 case 'defaultadmin':
		$t = ($is_group) ? 'groups' : 'items';
		$newparms['active_tab'] = $t;
		break;
	 default:
$this->Crash();
	}
	$this->Redirect($id, $resume, '', $newparms);
}

if ($item_id == -1) {
	$new_id = $db->GenID($this->ItemTable.'_seq');
}  elseif ($item_id == -Booker::MINGRPID) {
	$new_id = $db->GenID($this->ItemTable.'_gseq');
}

$task = $params['task'];
//feedback-message-accumulator
$msg = (isset($params['message'])) ? $params['message'] : '';

if (isset($params['submit']) || isset($params['apply'])) {
	//$params not wanted in sql
	$excludes = [
	'action' => 1,
	'active_tab' => 1,
	'apply' => 1,
	'resume' => 1,
	'submit' => 1,
	'task' => 1
	];
	$fields = array_diff_key($params, $excludes);
	if ($fields['name'] == FALSE) {
		$fields['name'] = '&lt;'.$this->Lang('noname').$item_id.'&gt;';
	} elseif ($fields['alias'] == FALSE) {
		$fields['alias'] = strtolower(soundex($fields['name']));
	}
	if (isset($fields['owner']) && $fields['owner'] == FALSE) { //list-selector value 0 is returned as ''
			$fields['owner'] = 0;
	}
	if (!isset($fields['active'])) {
		$fields['active'] = 0;
	}
	//radios, prevent NULL'd values
	foreach (['bookertell','grossfees','pickthis','pickmembers','cleargroup','approvertell']
		as $t) {
		if ($fields[$t] == '') {
			$fields[$t] = 0;
		}
	}
	//group members
	//if present, is ordered array of strings, each value being a member's item_id
	//like $fields['members'] = array(0 => string '21', 1 => string '1')
	//if not, no member selected
	if (isset($fields['members'])) {
		$members = $fields['members'];
		unset($fields['members']);
	} else {
		$members = FALSE; //option not in use, or no member selected
	}
	if ($is_group) {
		if ($members) {
			$c = ($item_id >= Booker::MINGRPID) ? $item_id : $new_id;
			groupsupdate($this, $db, $c, TRUE, $members);
		} elseif ($item_id >= Booker::MINGRPID) {
			$sql = 'DELETE FROM '.$this->GroupTable.' WHERE parent=?';
			//TODO $utils->SafeExec()
			$db->Execute($sql, [$item_id]);
		}
	}
	//(parent) groups
	if (isset($fields['ingroups'])) {
		$groups = $fields['ingroups'];
		unset($fields['ingroups']);
	} else {
		$groups = FALSE; //option not in use, or no group selected
	}
	if ($groups) {
		if ($members) {
			$groups = array_diff($groups, $members);	//filter out any common values
		}
		if ($groups) {
			$c = ($item_id > 0) ? $item_id : $new_id;
			groupsupdate($this, $db, $c, FALSE, $groups);
		}
	} elseif ($item_id > 0 && $item_id < Booker::MINGRPID) {
		$sql = 'DELETE FROM '.$this->GroupTable.' WHERE child=?';
		//TODO $utils->SafeExec()
		$db->Execute($sql, [$item_id]);
	}
	//stylesfile
	if (isset($fields['stylesdelete'])) {
		$fp = $config['uploads_path'];
		if ($fp && is_dir($fp)) {
			$ud = $this->GetPreference('uploadsdir', '');
			if ($ud) {
				$fp = cms_join_path($fp, $ud, $fields['oldstyles']);
			} else {
				$fp = cms_join_path($fp, $fields['oldstyles']);
			}
			if (is_file($fp)) {
				unlink($fp);
			}
		}
		unset($fields['stylesdelete']);
	}
	$t = $id.'stylesfile';
	if (isset($_FILES) && isset($_FILES[$t])) {
		$file_data = $_FILES[$t];
		if ($file_data['name']) {
			if ($file_data['error'] != 0) {
				$umsg = $this->Lang('err_upload', $this->Lang('err_system'));
			} else {
				$parts = explode('.', $file_data['name']);
				$ext = end($parts);
				if ($file_data['type'] != 'text/css'
				 || !($ext == 'css' || $ext == 'CSS')
				 || $file_data['size'] <= 0 || $file_data['size'] > 2048) { //plenty big enough in this context
					$umsg = $this->Lang('err_upload', $this->Lang('err_file'));
				} else {
					$h = fopen($file_data['tmp_name'], 'r');
					if ($h) {
						//basic validation of file-content
						$content = fread($h, 512);
						fclose($h);
						if ($content == FALSE) {
							$umsg = $this->Lang('err_upload', $this->Lang('err_perm'));
						} elseif (!preg_match('/\.bkgtitle/', $content)) { //TODO some more-general test
							$umsg = $this->Lang('err_upload', $this->Lang('err_file'));
						}
						unset($content);
					} else {
						$umsg = $this->Lang('err_upload', $this->Lang('err_perm'));
					}
				}
				if (empty($umsg)) {
					$fp = $config['uploads_path'];
					if ($fp && is_dir($fp)) {
						$ud = $this->GetPreference('uploadsdir', '');
						if ($ud) {
							$fp = cms_join_path($fp, $ud, $file_data['name']);
						} else {
							$fp = cms_join_path($fp, $file_data['name']);
						}
						if (// !chmod($file_data['tmp_name'],0644) ||
							!cms_move_uploaded_file($file_data['tmp_name'], $fp)) {
							$umsg = $this->Lang('err_upload', $this->Lang('err_perm'));
						}
					} else {
						$umsg = $this->Lang('err_upload', $this->Lang('err_system'));
					}
				}
			}
			if (empty($umsg)) {
				$fields['stylesfile'] = $file_data['name'];
			} else {
				$msg .= '<br />'.$umsg;
				$fields['stylesfile'] = $fields['oldstyles'];
			}
		} else {
			//TODO adding file	$fields['stylesfile'] = $fields['oldstyles'];
		}
	}
	unset($fields['stylessel']);
	unset($fields['oldstyles']);
	$t = $id.'imgfile';
	if (isset($_FILES) && isset($_FILES[$t])) {
		$file_data = $_FILES[$t];
		if ($file_data['name']) {
			if ($file_data['error'] != 0) {
				$umsg = $this->Lang('err_upload', $this->Lang('err_system'));
			} else {
				$fp = $config['uploads_path'];
				if ($fp && is_dir($fp)) {
					$ud = $this->GetPreference('uploadsdir', '');
					if ($ud) {
						$fp = cms_join_path($fp, $ud, $file_data['name']);
					} else {
						$fp = cms_join_path($fp, $file_data['name']);
					}
					if (// !chmod($file_data['tmp_name'],0644) ||
						!cms_move_uploaded_file($file_data['tmp_name'], $fp)) {
						$umsg = $this->Lang('err_upload', $this->Lang('err_perm'));
					}
				} else {
					$umsg = $this->Lang('err_upload', $this->Lang('err_system'));
				}
			}
			if (!empty($umsg)) {
				$msg .= '<br />'.$umsg;
			}
		}
	}
	unset($fields['imgsel']);

	if ($item_id == -1) {
		$params['item_id'] = $new_id;
		$fields['item_id'] = $new_id;
		$data = array_keys($fields);
		$namers = implode(',', $data);
		$data = array_values($fields);
		$fillers = str_repeat('?,', count($data) - 1);
		$sql = 'INSERT INTO '.$this->ItemTable." ($namers) VALUES ($fillers?)";
	} elseif ($item_id == -Booker::MINGRPID) {
		$params['item_id'] = $new_id;
		$fields['item_id'] = $new_id;
		$data = array_keys($fields);
		$namers = implode(',', $data);
		$data = array_values($fields);
		$fillers = str_repeat('?,', count($data) - 1);
		$sql = 'INSERT INTO '.$this->ItemTable." ($namers) VALUES ($fillers?)";
	} else {
		unset($fields['item_id']);
		$data = array_keys($fields);
		$namers = implode('=?,', $data).'=?';
		$data = array_values($fields);
		$data[] = $item_id;
		$sql = 'UPDATE '.$this->ItemTable." SET $namers WHERE item_id=?";
	}
	//cleanup
	foreach ($data as &$val) {
		if (is_numeric($val)) {
			$val = $val + 0; //cast to number
		} elseif ($val === '') {
			$val = NULL;
		}
	}
	unset($val);
	//TODO $utils->SafeExec()
	$db->Execute($sql, $data);

	if (isset($params['submit'])) {
		$t = ($is_group) ? 'groups' : 'items';
		$this->Redirect($id, 'defaultadmin', '', ['active_tab' => $t]);
	}

	$task = 'edit'; //in case was 'add'
}

// get data for the item with the passed-in id, or an empty one if that id not found
$utils = new Booker\Utils();
//$idata = $utils->GetItem($this,$item_id,FALSE);
$sql = 'SELECT * FROM '.$this->ItemTable.' WHERE item_id=?';
$row = $db->GetRow($sql, [$item_id]);
if ($row) {
	$idata = (object) $row; //TODO CHECKME need matched pairs like 'slottype'+'slotcount' ?
	//cleanups
	$idata->item_id = (int)$item_id;
	if ($idata->stylesfile && !$utils->GetStylesURL($this, $idata->item_id, FALSE)) { //styles file must be there
		$idata->stylesfile = '';
	}
	if ($task == 'copy') {
		$item_id = ($is_group) ? -Booker::MINGRPID : -1;
		$idata->item_id = $item_id;
		$idata->name = $this->Lang('copy_type', $idata->name);
		$idata->alias = '';
		$idata->owner = 0; //OR 'me' ?
	}
} else {
	//postgres supported pre-1.11
	$stype = (preg_match('/mysql/i', $config['dbms'])) ? '=DATABASE()' : ' IN (SELECT current_database())';
	$sql = <<<EOS
SELECT column_name,is_nullable FROM information_schema.columns
WHERE table_schema{$stype} AND table_name='{$this->ItemTable}'
ORDER BY ordinal_position
EOS;
	$rows = $db->GetArray($sql);
	$idata = new stdClass();
	//object-members which represent inheritable values are NULL'd, if allowed
	foreach ($rows as $one) {
		$idata->{$one['column_name']} = (stripos($one['is_nullable'], 'Y') !== FALSE) ? NULL : '';
	}
	$idata->item_id = $item_id; //-1 or -MINGRPID
	$idata->active = 1;
}

$theme = ($this->before20) ? cmsms()->get_variable('admintheme') :
	cms_utils::get_theme_object();
$iconup = $theme->DisplayImage('icons/system/arrow-u.gif', $this->Lang('tip_up'), '', '', 'systemicon');
$icondn = $theme->DisplayImage('icons/system/arrow-d.gif', $this->Lang('tip_down'), '', '', 'systemicon');
$iconinfo = $theme->DisplayImage('icons/system/info.gif', $this->Lang('showhelp'), '', '', 'systemicon tipper');

$cascade = '&#8225; '; //label prefix
$notyet = ' '.$this->Lang('notyet'); //development pending
$cleartypes = ['p']; //for StripTags()

//NB all objects' (except ingroups) name-field is used directly for dbase field names!

$pdev = $this->CheckPermission('Modify Any Page');
$padm = $this->_CheckAccess('admin');
if ($task == 'see') {
	$pmod = FALSE;
} else {
	$pmod = (($task == 'edit' || $task == 'update') && $this->_CheckAccess('modify'))
		|| (($task == 'add' || $task == 'copy') && $this->_CheckAccess('add'));
}

$none = $this->Lang('none');
if (!$pmod) {
	$nolimit = $this->Lang('nolimit');
}

// setup variables for the view-template
$tplvars = ['mod' => $pmod];

$seetab = (!empty($params['active_tab'])) ? $params['active_tab'] : 'basic'; //default shown tab

$params['active_tab'] = ($is_group) ? 'groups' : 'items';
$params['resume'] = ['defaultadmin']; //redirects can [eventually] get back to there

$tplvars['pagenav'] = $utils->BuildNav($this, $id, $returnid, $params['action'], $params);
$resume = json_encode($params['resume']);

//multipart form needed for file uploads
$tplvars['startform'] = $this->CreateFormStart($id, 'openitem', $returnid, 'POST', 'multipart/form-data', '', '',
	['item_id' => $item_id, 'task' => $task, 'resume' => $resume, 'active_tab' => '']);
$tplvars['endform'] = $this->CreateFormEnd();
$tplvars['tab_headers'] = $this->StartTabHeaders().
	$this->SetTabHeader('basic', $this->Lang('basic'), ($seetab == 'basic')).
	$this->SetTabHeader('advanced', $this->Lang('advanced'), ($seetab == 'advanced')).
	$this->SetTabHeader('formats', $this->Lang('formats'), ($seetab == 'formats')).
	$this->EndTabHeaders().
	$this->StartTabContent();
//workaround CMSMS2 crap 'auto-end', EndTab() & EndTabContent() before [1st] StartTab()
$tplvars += [
	'end_tab' => $this->EndTab(),
	'tab_footers' => $this->EndTabContent(),
	'start_basic_tab' => $this->StartTab('basic'),
	'start_adv_tab' => $this->StartTab('advanced'),
	'start_fmt_tab' => $this->StartTab('formats')
];

$tplvars['title'] = ($is_group) ? $this->Lang('title_group_page') : $this->Lang('title_item_page');
$s = ($is_group) ? $this->Lang('group') : $this->Lang('item');

$baseurl = $this->GetModuleURLPath();

$intro = ($pmod) ? $this->Lang('compulsory_items').'<br />' : '';
$intro .= $this->Lang('help_cascade');
$tplvars['intro'] = $intro;

$inherit = $this->Lang('inherit');
$yes = $this->Lang('yes');
$no = $this->Lang('no');
//script accumulators
$jsfuncs = [];
$jsloads = [];
$jsincs = [];

$jsloads[] = <<<EOS
 $('p.help').hide();
 $('img.tipper').css({'display':'inline','padding-left':'10px'}).click(function() {
  $(this).parent().next().next().slideToggle();
 });
EOS;

//construct arrays of UI-items, in display-order
$basic = [];
$advanced = [];
$formats = [];
//------- active
if ($pmod) {
	$i = $this->CreateInputCheckbox($id, 'active', 1, $idata->active);
} elseif ($idata->active) {
	$i = $yes;
} else {
	$i = $no;
}
$basic[] = ['ttl' => $this->Lang('title_active'),
'inp' => $i,
'hlp' => NULL
];
//------- name
$t = $this->Lang('title_name');
if ($pmod) {
	$t .= ' ('.$this->Lang('short_length').')';
}
if ($pmod) {
	$i = $this->CreateInputText($id, 'name', $idata->name, 40, 64);
} elseif ($idata->name) {
	$i = $idata->name;
} else {
	$i = $none;
}
$h = ($pmod) ?
	$this->Lang('help_use_smarty')/*.', '.$this->Lang('label_usage')*/ :
	NULL;
$basic[] = ['ttl' => $t,
'mst' => $pmod,
'inp' => $i,
'hlp' => $h
];
//------- description
if ($pmod) {
	$i = $this->CreateTextArea(TRUE, $id, $idata->description, 'description', '', '', '', '', 80, 5, '', '', 'style="height:10em;"');
} elseif ($idata->description) {
	$i = $utils->StripTags($idata->description, $cleartypes);
} else {
	$i = $none;
}
$h = ($pmod) ? $this->Lang('help_use_smarty') : NULL;
$basic[] = ['ttl' => $this->Lang('title_long_desc'),
'inp' => $i,
'hlp' => $h
];
//------- image
if ($pmod) {
	$i = $this->CreateInputText($id, 'image', $idata->image, 60, 128);
	$files = $utils->GetUploadedFiles($this, 'jpg,jpeg,gif,png,svg');
	if ($files) {
		$files = array_combine($files, $files); //keys match values
		$files = array_merge([$this->Lang('select') => ''], $files);
		$i .= '<br />'.$this->CreateInputDropdown($id, 'imgsel', $files, -1, -1, 'onchange="imgfile_selected(this)"'); //TODO onchange set textinput if empty
	}
	$i .= '<br />'.$this->CreateInputFile($id, 'imgfile', 'image/*', 30, 'id="'.$id.'imgfile" title="'.
		$this->Lang('tip_upload').'" onchange="imgfile_selected(this)"');
	if ($idata->image && $files) {
		$i .= ' '.$this->CreateInputCheckbox($id, 'imgdelete', 1, -1).'&nbsp;'.$this->Lang('delete_upload', $idata->image);
	}
} elseif ($idata->image) {
	$t = $utils->GetImageURLs($this, $idata->image, $idata->name);
	if ($t) {
		$i = '';
		foreach ($t as &$one) {
			$i .= '  <img src="'.$one->url.'" style="width:50px;height:50px;" />';
		}
		unset($one);
	} else {
		$i = $this->Lang('missing_type', $this->Lang('file'));
	}
} else {
	$i = $none;
}
$basic[] = ['ttl' => $cascade.$this->Lang('title_image'),
'inp' => $i,
'hlp' => $this->Lang('help_image')
];
//------- bulletins
$basic[] = ['ttl' => $cascade.$this->Lang('title_bulletin'),
'inp' => $this->CreateTextArea(TRUE, $id, $idata->bulletin, 'bulletin', '', '', '', '', 80, 3, '', '', 'style="height:5em;"'),
'hlp' => NULL //$this->Lang('help_bulletin')
];
$basic[] = ['ttl' => $cascade.$this->Lang('title_bulletin2'),
'inp' => $this->CreateTextArea(TRUE, $id, $idata->bulletin2, 'bulletin2', '', '', '', '', 80, 3, '', '', 'style="height:5em;"'),
'hlp' => NULL //$this->Lang('help_bulletin')
];
//------- membersname
if ($is_group) {
	if ($pmod) {
		$i = $this->CreateInputText($id, 'membersname', $idata->membersname, 25, 32);
	} elseif ($idata->membersname) {
		$i = $idata->membersname;
	} else {
		$i = $none;
	}
	$basic[] = ['ttl' => $cascade.$this->Lang('title_membersname'),
	'inp' => $i,
	'hlp' => $this->Lang('help_membersname')
	];
}
//------- pickname
if ($pmod) {
	$i = $this->CreateInputText($id, 'pickname', $idata->pickname, 25, 32);
} elseif ($idata->pickname) {
	$i = $idata->pickname;
} else {
	$i = $none;
}
$basic[] = ['ttl' => $cascade.$this->Lang('title_pickname'),
'inp' => $i,
'hlp' => $this->Lang('help_pickname')
];
//------- slotcount, slottype (c.f. TimeIntervals())
$alltypes = explode(',', $this->Lang('periods')); //'minute,hour,day,week,month,year'
if ($pmod) {
	$choices = array_flip($alltypes);
	$i = $this->CreateInputText($id, 'slotcount', $idata->slotcount, 6, 6).'&nbsp;'.
		$this->CreateInputDropdown($id, 'slottype', $choices, -1, $idata->slottype);
} else {
	$i = $idata->slotcount.' '.$alltypes[$idata->slottype];
}
$basic[] = ['ttl' => $cascade.$this->Lang('title_slotlength'),
'mst' => $pmod,
'inp' => $i,
'hlp' => NULL //$this->Lang('help_slotlength')
];
//------- max slots per booking
if ($pmod) {
	$i = $this->CreateInputText($id, 'bookcount', $idata->bookcount, 3, 3);
} elseif ($idata->bookcount) {
	$i = $idata->bookcount;
} else {
	$i = $nolimit;
}
$basic[] = ['ttl' => $cascade.$this->Lang('title_bookcount'),
'inp' => $i,
'hlp' => $this->Lang('help_bookcount')
];
//------- confirmation to booker
if ($pmod && $padm) {
	$choices = [$inherit => -1,$no => 0,$yes => 1];
	$sel = is_null($idata->bookertell) ? -1 : (int)$idata->bookertell;
	$i = $this->CreateInputRadioGroup($id, 'bookertell', $choices, $sel, '', '&nbsp;&nbsp;');
	//override crappy default label-layout
	$i = preg_replace('~label class="(.*)"~U', 'label class="\\1 radiolabel"', $i);
} else {
	if ($idata->bookertell) {
		$i = $yes;
	} elseif (is_null($idata->bookertell)) {
		$i = $inherit;
	} else {
		$i = $no;
	}
}
$basic[] = ['ttl' => $this->Lang('bookertell'),
'inp' => $i,
'hlp' => NULL
];
//------- fees
$sql = 'SELECT description,fee,feecondition FROM '.$this->FeeTable.' WHERE item_id=? AND active=1 ORDER BY condorder';
$sel = $db->GetArray($sql, [$item_id]);
if ($sel) {
	$fees = [];
	foreach ($sel as &$one) {
		$oneset = new stdClass();
		$oneset->desc = $one['description'];
		$oneset->fee = $one['fee'];
		$t = $one['feecondition'];
		if (!$t) {
			$t = $this->Lang('always');
		}
		$oneset->cond = $t;
		$fees[] = $oneset;
	}
	unset($one);
	$tplvars['entries'] = $fees;
	$i = Booker\Utils::ProcessTemplate($this, 'brieffees.tpl', $tplvars);
	if ($pmod) {
		$t = $this->Lang('edit');
		$i .= '<br /><br />'.$this->CreateInputSubmit($id, 'modfee', $t, 'onclick="confirm_saved(this);return false;"');
	}
	$h = $this->Lang('help_fee');
} else {
	$i = $this->Lang('nofees');
	if ($pmod) {
		$t = $this->Lang('addfee');
		$i .= '<br /><br />'.$this->CreateInputSubmit($id, 'modfee', $t, 'onclick="confirm_saved(this);return false;"');
	}
	$h = NULL;
}
if ($pmod) {
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;

	$jsfuncs[] = <<<EOS
function confirm_saved(btn) {
 $.alertable.confirm('{$this->Lang('allsaved')}',{
  okName: '{$this->Lang('yes')}',
  cancelName: '{$this->Lang('no')}'
 }).then(function() {
  current_tab();
  \$frm = $(btn).closest('form');
  \$frm.append($('<input type="hidden" name="{$id}modfee" value="1"/>'));
  \$frm.trigger('submit');
 })
}
EOS;
}
$basic[] = ['ttl' => $cascade.$this->Lang('title_feeusage'),
'inp' => $i,
'hlp' => $h
];
//------- gross or pretax fees
if ($pmod && $padm) {
	$choices = [$inherit => -1,$no => 0,$yes => 1];
	$sel = is_null($idata->grossfees) ? -1 : (int)$idata->grossfees;
	$i = $this->CreateInputRadioGroup($id, 'grossfees', $choices, $sel, '', '&nbsp;&nbsp;');
	//override crappy default label-layout
	$i = preg_replace('~label class="(.*)"~U', 'label class="\\1 radiolabel"', $i);
} else {
	if ($idata->grossfees) {
		$i = $yes;
	} elseif (is_null($idata->grossfees)) {
		$i = $inherit;
	} else {
		$i = $no;
	}
}
$basic[] = ['ttl' => $this->Lang('title_grossfees'),
'inp' => $i,
'hlp' => NULL
];
//--------- tax rate
//TODO
//============ ADVANCED TAB
//------- alias
if ($pdev && $pmod) {
	$i = $this->CreateInputText($id, 'alias', $idata->alias, 20, 24);
	$h = $this->Lang('help_alias', $s);
} else {
	if ($idata->alias) {
		$i = $idata->alias;
	} else {
		$i = $none;
	}
	$h = NULL;
}
$advanced[] = ['ttl' => $this->Lang('title_alias', $s),
'inp' => $i,
'hlp' => $h
];
//------- keywords
if ($pdev && $pmod) {
	$i = $this->CreateTextArea(FALSE, $id, $idata->keywords, 'keywords', '', '', '', '', 50, 4, '', '', 'style="height:4em;"');
	$t = ($is_group) ? $this->Lang('title_groups') : $this->Lang('title_items');
	$t = mb_convert_case($t, MB_CASE_LOWER);
	$h = $this->Lang('help_keywords', $t);
} else {
	if ($idata->keywords) {
		$i = $idata->keywords;
	} else {
		$i = $none;
	}
	$h = NULL;
}
$advanced[] = ['ttl' => $cascade.$this->Lang('title_keywords'),
'inp' => $i,
'hlp' => $h
];
//------- pickthis
if ($pmod) {
	$choices = [$inherit => -1,$no => 0,$yes => 1];
	$sel = is_null($idata->pickthis) ? -1 : (int)$idata->pickthis;
	$i = $this->CreateInputRadioGroup($id, 'pickthis', $choices, $sel, '', '&nbsp;&nbsp;');
	//override crappy default label-layout
	$i = preg_replace('~label class="(.*)"~U', 'label class="\\1 radiolabel"', $i);
} else {
	if ($idata->pickthis) {
		$i = $yes;
	} elseif (is_null($idata->pickthis)) {
		$i = $inherit;
	} else {
		$i = $no;
	}
}
$advanced[] = ['ttl' => $this->Lang('title_pickthis'),
'inp' => $i,
'hlp' => NULL
];
//------- members
if ($is_group) {
	//TODO get this into a scrollable div
	$sql = 'SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id!=? ORDER BY item_id DESC';
	$allitems = $db->GetArray($sql, [$item_id]);
	if ($allitems) {
		$itypename = FALSE;
		$gtypename = FALSE;
		foreach ($allitems as &$one) {
			if (!$one['name']) {
				if ($one['item_id'] < Booker::MINGRPID) {
					if (!$itypename) {
						$itypename = $this->Lang('title_noname', $this->Lang('item'), '%s');
					}
					$one['name'] = sprintf($itypename, $one['item_id']);
				} else {
					if (!$gtypename) {
						$gtypename = $this->Lang('title_noname', $this->Lang('group'), '%s');
					}
					$one['name'] = sprintf($gtypename, $one['item_id']);
				}
			}
		}
		unset($one);

		if (class_exists('Collator')) {
			$col = new Collator($utils->GetLocale());
		} else {
			$col = FALSE;
		}

		uasort($allitems, function ($a, $b) use ($col) {
			$ta = $a['item_id'];
			$tb = $b['item_id'];
			if ($ta >= Booker::MINGRPID) {
				if ($tb < Booker::MINGRPID) {
					return -1;
				}
			} elseif ($tb >= Booker::MINGRPID) {
				return 1;
			}
			if ($col) {
				if ($col->compare($a['name'], $b['name']) == 0) {
					return 0;
				}
			} else {
				if (strcmp($a['name'], $b['name']) == 0) { //TODO encoding
					return 0;
				}
			}
			$na = preg_match('/\d+/', $a['name'], $ma, PREG_OFFSET_CAPTURE);
			if ($na == 1) {
				$nb = preg_match('/\d+/', $b['name'], $mb, PREG_OFFSET_CAPTURE);
				if ($nb == 1) {
					if ($ma[0][1] == $mb[0][1]) { //same offsets
						return ($ma[0][0] - $mb[0][0]); //order based on the numbers
					}
				}
			}
			return 0;
		});
		$allitems = array_column($allitems, 'name', 'item_id');
	}

	$rc = 0;
	if ($item_id > 0) { //i.e. not new
		$sql = 'SELECT child FROM '.$this->GroupTable.' WHERE parent=? ORDER BY likeorder';
		$relations = $db->GetCol($sql, [$item_id]);
		$rc = count($relations);
		if ($rc > 0) {
			$t = implode(',', $relations);
			$sql = 'SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id IN('.$t.')';
			$sel = $db->GetAssoc($sql);
			if ($pmod && $padm) {
				foreach ($sel as $k => $name) {
					unset($allitems[$k]);
				}
				//rest are still alphabetic by name
				$allitems = $sel + $allitems;
				//TODO send 'active_tab' as a $param to action.swapgroups
				$i = groupstable($this, $tplvars, $id, 'members', $returnid, $icondn, $iconup,
					$item_id, $allitems, $relations, TRUE, TRUE, 'members');
				if ($rc > 1) { //TODO send 'active_tab' as a $param to action.swapgroups
					$i .= '<br /><br />'.$this->CreateInputSubmit($id, 'sortlike1', $this->Lang('sort'),
						'title="'.$this->Lang('tip_sortchilds').'" style="display:none;"');
				} //button shown by runtime js
			} elseif ($sel) {
				$i = implode(', ', $sel);
			} else {
				$i = $none;
			}
		} elseif ($pmod && $padm) { //editing old item
			//create table, alphabetic by groupname, each row has name, unchecked checkbox
			$i = groupstable($this, $tplvars, $id, 'members', $returnid, $icondn, $iconup,
				$item_id, $allitems, FALSE, TRUE, TRUE, 'members');
		} else { //viewing old item
			$i = $none;
		}
	} elseif ($pmod && $padm) { //editing new item
		//create table, alphabetic by groupname, each row has name, unchecked checkbox
		$i = groupstable($this, $tplvars, $id, 'members', $returnid, $icondn, $iconup,
			$item_id, $allitems, FALSE, TRUE, TRUE, 'members');
	} else { //viewing new item - should never happen
		$i = $none;
	}
	if (count($allitems) > 1) {
		$h = $this->Lang('help_members2').
		'<span class="dndhelp">'.$this->Lang('help_dnd').'</span>'.
		$this->Lang('help_members');
	} else {
		$h = $this->Lang('help_members');
	}

	$advanced[] = ['ttl' => $this->Lang('title_members'),
	'inp' => $i,
	'hlp' => $h
	];

	if ($rc > 1) {
		$t = $this->Lang('err_server');
		$u = $this->create_url($id, 'sortlike', '', ['item_id' => $item_id, 'first_id' => '']);
		$offs = strpos($u, '?mact=');
		$u = str_replace('&amp;', '&', substr($u, $offs + 1));
		$jsloads[] = <<<EOS
 $('#{$id}sortlike1').css('display','block').click(function(ev) {
  var first = $('#members').find('input:checkbox').eq(1).val();
  if (first) {
   $.ajax({
    type: 'POST',
    url: 'moduleinterface.php',
    data: '{$u}'+first,
    dataType: 'html',
    success: function (data,status) {
     if (status=='success') {
      if (data != '') {
       $('#members > tbody').html(data);
       $('#members .updown').hide();
      }
     } else {
      $('#page_tabs').prepend('<p style="font-weight:bold;color:red;">{$t}!</p><br />');
     }
    }
   });
  }
  ev.stopImmediatePropagation();
  ev.preventDefault();
  return false;
 });
EOS;
	} //$rc>1
//------- pickmembers
	if ($pmod) {
		$choices = [$inherit => -1,$no => 0,$yes => 1];
		$sel = is_null($idata->pickmembers) ? -1 : (int)$idata->pickmembers;
		$i = $this->CreateInputRadioGroup($id, 'pickmembers', $choices, $sel, '', '&nbsp;&nbsp;');
		//override crappy default label-layout
		$i = preg_replace('~label class="(.*)"~U', 'label class="\\1 radiolabel"', $i);
	} else {
		if ($idata->pickmembers) {
			$i = $yes;
		} elseif (is_null($idata->pickmembers)) {
			$i = $inherit;
		} else {
			$i = $no;
		}
	}
	$advanced[] = ['ttl' => $this->Lang('title_pickmembers'),
	'inp' => $i,
	'hlp' => NULL
	];
//------- cleargroup
//if ($is_group) {
	if ($pmod && $padm) {
		$choices = [$inherit => -1,$no => 0,$yes => 1];
		$sel = is_null($idata->cleargroup) ? -1 : (int)$idata->cleargroup;
		$i = $this->CreateInputRadioGroup($id, 'cleargroup', $choices, $sel, '', '&nbsp;&nbsp;');
		//override crappy default label-layout
		$i = preg_replace('~label class="(.*)"~U', 'label class="\\1 radiolabel"', $i);
	} else {
		if ($idata->cleargroup) {
			$i = $yes;
		} elseif (is_null($idata->cleargroup)) {
			$i = $inherit;
		} else {
			$i = $no;
		}
	}
	$advanced[] = ['ttl' => $this->Lang('title_cleargroup2'),
	'inp' => $i,
	'hlp' => NULL
	];
} // $is_group
//------- groups
$rc = 0;
$sql = 'SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id>='.Booker::MINGRPID.' AND item_id<>? ORDER BY name';
$allgrps = $db->GetAssoc($sql, [$item_id]);
if ($allgrps) {
	if ($item_id > 0) { //i.e. not new
		$sql = 'SELECT parent FROM '.$this->GroupTable.' WHERE child=? ORDER BY proximity DESC';
		$relations = $db->GetCol($sql, [$item_id]);
		$rc = count($relations);
		if ($rc > 0) {
			//get selected items 1st, in order
			$sel = [];
			foreach ($relations as $k) {
				$offset = array_search($k, array_keys($allgrps));
				$sel += array_slice($allgrps, $offset, 1, TRUE);
				unset($allgrps[$k]);
			}
			//rest are still alphabetic by name
			if ($pmod && $padm) {
				$allgrps = $sel + $allgrps;
				 //TODO send 'active_tab' as a $param to action.swapgroups
				$i = groupstable($this, $tplvars, $id, 'ingroups', $returnid, $icondn, $iconup,
					$item_id, $allgrps, $relations, TRUE, FALSE, 'groups');
				if ($rc > 1) {
					$i .= '  '.$this->CreateInputSubmit($id, 'sortlike2', $this->Lang('sort'),
						'title="'.$this->Lang('tip_sortparents').'" style="display:none;"');
				} //button shown by runtime js
			} elseif ($sel) {
				$i = implode(', ', $sel);
			} else {
				$i = $none;
			}
		} elseif ($pmod && $padm) { //editing old item
			//create table, alphabetic by groupname, each row has name, unchecked checkbox
			$i = groupstable($this, $tplvars, $id, 'ingroups', $returnid, $icondn, $iconup,
				$item_id, $allgrps, FALSE, TRUE, FALSE, 'groups');
		} else { //viewing old item
			$i = $none;
		}
	} elseif ($pmod && $padm) { //editing new item
		//create table, alphabetic by groupname, each row has name, unchecked checkbox
		$i = groupstable($this, $tplvars, $id, 'ingroups', $returnid, $icondn, $iconup,
			$item_id, $allgrps, FALSE, TRUE, FALSE, 'groups');
	} else { //viewing new item - should never happen
		$i = $none;
	}
	$h = $this->Lang('help_groups', $s, $s, $s);
	if ($pmod && count($allgrps) > 1) {
		$h .= '<span class="dndhelp">'.$this->Lang('help_dnd').'</span>';
	}
	$advanced[] = ['ttl' => $this->Lang('title_groups2', $s),
	'inp' => $i,
	'hlp' => $h
	];

	$t = $this->Lang('err_server');
	$u = $this->create_url($id, 'sortlike', '', ['item_id' => $item_id, 'sort' => '']);
	$offs = strpos($u, '?mact=');
	$u = str_replace('&amp;', '&', substr($u, $offs + 1)); //'groups' or 'members' will be appended at runtime

	if ($rc > 1) {
		$jsloads[] = <<<EOS
 $('#{$id}sortlike2').css('display','block').click(function(ev) {
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
    if (status=='success') {
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
EOS;
	}
} //$allgrps
//------- members and/or groups table js
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.tablednd.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.SSsort.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/tableHeadFixer.min.js"></script>
EOS;
$jsloads[] = <<<EOS
 $('.dndhelp').css('display','block');
 $('.updown').hide();
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
 $('table.scrollable').tableHeadFixer();
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
//------- available
if ($pmod) {
	$i = $this->CreateTextArea(FALSE, $id, $idata->available, 'available', '', '', '', '', 40, 3, '', '', 'style="height:3em;"');
} elseif ($idata->available) {
	$i = $idata->available;
} else {
	$i = $this->Lang('always');
}
$advanced[] = ['ttl' => $cascade.$this->Lang('title_available'),
'inp' => $i,
'hlp' => $this->Lang('help_intervals')
];
if ($is_group) {
	//-------- sub-group allocation mode
	$choices = [
		$this->Lang('assignnone') => Booker::ALLOCNONE,
		$this->Lang('assignfirst') => Booker::ALLOCFIRST,
		$this->Lang('assignrandom') => Booker::ALLOCRAND,
		$this->Lang('assignrotate') => Booker::ALLOCROTE,
		$this->Lang('assignchoose') => Booker::ALLOCCHOOSE
	];
	if ($pmod) {
		$t = (int)$idata->subgrpalloc;
		$i = $this->CreateInputDropdown($id, 'subgrpalloc', $choices, -1, $t);
	} else {
		$i = array_search($idata->subgrpalloc, $choices);
	}
	$advanced[] = ['ttl' => $cascade.$this->Lang('title_subgrpalloc'),
	'inp' => $i,
	'hlp' => $this->Lang('help_subgrpalloc')];
}
//------- leadcount, leadtype
if ($pmod) {
	$choices = array_flip($alltypes);
	array_shift($choices);
	$i = $this->CreateInputText($id, 'leadcount', $idata->leadcount, 6, 6).'&nbsp;'.
		$this->CreateInputDropdown($id, 'leadtype', $choices, -1, $idata->leadtype);
} else {
	$i = $idata->leadcount.' '.$alltypes[$idata->leadtype];
}
$advanced[] = ['ttl' => $cascade.$this->Lang('title_lead'),
'inp' => $i,
'hlp' => $this->Lang('help_lead')
];
//------- keepcount, keeptype
if ($pmod && $padm) {
	$choices = array_flip($alltypes);
	array_shift($choices);
	array_shift($choices);
	$i = $this->CreateInputText($id, 'keepcount', $idata->keepcount, 6, 6).'&nbsp;'.
		$this->CreateInputDropdown($id, 'keeptype', $choices, -1, $idata->keeptype);
} else {
	$i = $idata->keepcount.' '.$alltypes[$idata->keeptype];
}
$advanced[] = ['ttl' => $cascade.$this->Lang('title_keep'),
'inp' => $i,
'hlp' => $this->Lang('help_keep')
];
//------- rationcount
$i = ($pmod) ?
	$this->CreateInputText($id, 'rationcount', $idata->rationcount, 3, 3) :
	$idata->rationcount;
$advanced[] = ['ttl' => $cascade.$this->Lang('title_ration'),
'inp' => $i,
'hlp' => $this->Lang('help_ration')
];
//------- approver
if ($pmod) {
	$i = $this->CreateInputText($id, 'approver', $idata->approver, 30, 64);
} elseif ($idata->approver) {
	$i = $idata->approver;
} else {
	$i = $none;
}
$advanced[] = ['ttl' => $cascade.$this->Lang('approver'),
'inp' => $i,
'hlp' => NULL
];
//------- contact
if ($pmod) {
	$i = $this->CreateInputText($id, 'approvercontact', $idata->approvercontact, 40, 128);
} elseif ($idata->approvercontact) {
	$i = $idata->approvercontact;
} else {
	$i = $none;
}
$advanced[] = ['ttl' => $cascade.$this->Lang('approvercontact'),
'inp' => $i,
'hlp' => NULL
];
//------- messages to contact
if ($pmod && $padm) {
	$choices = [$inherit => -1,$no => 0,$yes => 1];
	$sel = is_null($idata->approvertell) ? -1 : (int)$idata->approvertell;
	$i = $this->CreateInputRadioGroup($id, 'approvertell', $choices, $sel, '', '&nbsp;&nbsp;');
	//override crappy default label-layout
	$i = preg_replace('~label class="(.*)"~U', 'label class="\\1 radiolabel"', $i);
} else {
	if ($idata->approvertell) {
		$i = $yes;
	} elseif (is_null($idata->approvertell)) {
		$i = $inherit;
	} else {
		$i = $no;
	}
}
$advanced[] = ['ttl' => $this->Lang('approvertell'),
'inp' => $i,
'hlp' => NULL
];
//------- owner
$sql = 'SELECT user_id,first_name,last_name FROM '.$this->UserTable.' WHERE active=1 ORDER BY last_name,first_name';
$allusers = $db->GetAssoc($sql);
if ($pmod) {
	if ($allusers) {
		foreach ($allusers as $k => &$t) {
			$t = trim($t['first_name'].' '.$t['last_name']);
		}
		unset($t);
	}
	$allusers = array_flip($allusers);
	//prepend other choices NB something changes index 0 to '', even if done postflip()
	$allusers = [
		$inherit => -1,
		$none => 0
	] + $allusers;
	$i = $this->CreateInputDropdown($id, 'owner', $allusers, -1, $idata->owner);
} elseif ($idata->owner) {
	$i = $allusers[$idata->owner];
} else {
	$i = $none;
}
$advanced[] = ['ttl' => $cascade.$this->Lang('title_owner'),
'inp' => $i,
'hlp' => NULL
];
//------- feugroup (savers)
$ob = cms_utils::get_module('FrontEndUsers');
if (is_object($ob)) {
	$allusers = $ob->GetGroupList(); //associative array with group names as keys, id's as values
	unset($ob);
	$rc = count($allusers);
	if ($pmod) {
		if ($rc) {
			ksort($allusers, SORT_NATURAL | SORT_FLAG_CASE);
		}
/* TODO filter by permissions c.f. for admin users
		$pref = cms_db_prefix();
		$sql = <<<EOS
SELECT DISTINCT U.user_id,U.username,U.first_name,U.last_name FROM $this->UserTable U
JOIN {$pref}user_groups UG ON U.user_id = UG.user_id
JOIN {$pref}group_perms GP ON GP.group_id = UG.group_id
JOIN {$pref}permissions P ON P.permission_id = GP.permission_id
JOIN {$pref}groups GR ON GR.group_id = UG.group_id
WHERE
EOS;
		if (!$allowners)
			$sql .= "U.user_id=$uid AND "; //no injection risk
		$sql .= <<<EOS U.admin_access=1 AND U.active=1 AND GR.active=1 AND
P.permission_name IN ('{$this->PermAddName}','{$this->PermAdminName}','{$this->PermModName}')
ORDER BY U.last_name,U.first_name
EOS;
*/
		$allusers = [$none => 0] + $allusers;
		$i = $this->CreateInputDropdown($id, 'feugroup', $allusers, -1, $idata->feugroup);
	} elseif ($rc && $idata->feugroup) {
		$i = array_search($idata->feugroup, $allusers);
	} else {
		$i = $none;
	}
	$advanced[] = ['ttl' => $cascade.$this->Lang('title_feugroup'),
	'inp' => $i,
	'hlp' => $this->Lang('help_feugroup')
	];
}
//------- payments interface
$choices = [];
$allmodules = $this->GetModulesWithCapability('GatePayer');
foreach ($allmodules as $name) {
	$ob = cms_utils::get_module($name);
	if ($ob) {
		$n = $ob->GetFriendlyName();
		$choices[$n] = $name;
		unset ($ob);
	}
}
asort($choices);
$choices = [$none => '',	$inherit => '-1'] + $choices;
if ($pmod) {
	$i = $this->CreateInputDropdown($id, 'paymentiface', $choices, -1, $idata->paymentiface);
} elseif ($idata->paymentiface) {
	$i = $idata->paymentiface;
} else {
	$i = $none;
}
$advanced[] = ['ttl' => $cascade.$this->Lang('title_paymentiface'),
'inp' => $i,
'hlp' => $this->Lang('help_paymentiface')
];
//------- custom-forms interface
if ($pmod) {
	$i = $this->CreateInputText($id, 'formiface', $idata->formiface, 30, 48);
} elseif ($idata->formiface) {
	$i = $idata->formiface;
} else {
	$i = $none;
}
$advanced[] = ['ttl' => $cascade.$this->Lang('title_formiface').$notyet,
'inp' => $i,
'hlp' => $this->Lang('help_formiface')
];
//============ FORMATS TAB
//------- timezone
if ($pmod) {
	$i = $this->CreateInputText($id, 'timezone', $idata->timezone, 40, 48);
} elseif ($idata->timezone) {
	$i = $idata->timezone;
} else {
	$i = $none;
}
$formats[] = ['ttl' => $cascade.$this->Lang('title_zone'),
'inp' => $i,
'hlp' => $this->Lang('help_zone')
];
//------- latitude
$i = ($pmod) ?
	$this->CreateInputText($id, 'latitude', $idata->latitude, 8, 8) :
	$idata->latitude;
$formats[] = ['ttl' => $cascade.$this->Lang('latitude'),
'inp' => $i,
'hlp' => $this->Lang('help_latitude')
];
//------- longitude
$i = ($pmod) ?
	$this->CreateInputText($id, 'longitude', $idata->longitude, 8, 8) :
	$idata->longitude;
$formats[] = ['ttl' => $cascade.$this->Lang('longitude'),
'inp' => $i,
'hlp' => $this->Lang('help_longitude')
];
//-------- SMS prefix, pattern
if ($pmod) {
	$i = $this->CreateInputText($id, 'smsprefix', $idata->smsprefix, 4, 8);
} elseif ($idata->smsprefix) {
	$i = $idata->smsprefix;
} else {
	$i = $none;
}
$formats[] = ['ttl' => $cascade.$this->Lang('title_smsprefix'),
'inp' => $i,
'hlp' => $this->Lang('help_smsprefix')
];
if ($pmod) {
	$i = $this->CreateInputText($id, 'smspattern', $idata->smspattern, 20, 32);
} elseif ($idata->smspattern) {
	$i = $idata->smspattern;
} else {
	$i = $none;
}
$formats[] = ['ttl' => $cascade.$this->Lang('title_smspattern'),
'inp' => $i,
'hlp' => $this->Lang('help_smspattern')
];
//------- dateformat
if ($pmod) {
	$i = $this->CreateInputText($id, 'dateformat', $idata->dateformat, 10, 12);
} elseif ($idata->dateformat) {
	$i = $idata->dateformat;
} else {
	$i = $none;
}
$formats[] = ['ttl' => $cascade.$this->Lang('title_dateformat'),
'inp' => $i,
'hlp' => $this->Lang('help_date')
];
//------- timeformat
if ($pmod) {
	$i = $this->CreateInputText($id, 'timeformat', $idata->timeformat, 8, 12);
} elseif ($idata->timeformat) {
	$i = $idata->timeformat;
} else {
	$i = $none;
}
$formats[] = ['ttl' => $cascade.$this->Lang('title_timeformat'),
'inp' => $i,
'hlp' => $this->Lang('help_time')
];
//------- listformat
if ($is_group) {
	$choices = [
	$inherit => -1,
	$this->Lang('start+user') => Booker::LISTSU,
	$this->Lang('resource+start') => Booker::LISTRS,
	$this->Lang('user+resource') => Booker::LISTUR,
	$this->Lang('user+start') => Booker::LISTUS
	];
} else {
	$choices = [
	$inherit => -1,
	$this->Lang('start+user') => Booker::LISTSU,
	$this->Lang('user+resource') => Booker::LISTUR,
	$this->Lang('user+start') => Booker::LISTUS
	];
}

if ($pmod) {
	$i = $this->CreateInputDropdown($id, 'listformat', $choices, -1, $idata->listformat,
		'id="'.$id.'listformat" title="'.$this->Lang('tip_listtype').'"');
} else {
	$i = array_search($idata->listformat, $choices);
}
$formats[] = ['ttl' => $cascade.$this->Lang('listformat'),
'inp' => $i,
'hlp' => NULL //$this->Lang('help_')
];
//------- stylesfile
//remember this, to support incremental change
$hidden = $this->CreateInputHidden($id, 'oldstyles', $idata->stylesfile);
//set explicit id for file input, cuz CMSMS doesn't!
if ($pmod) {
	$i = $this->CreateInputText($id, 'stylesfile', $idata->stylesfile, 30, 36);
	$files = $utils->GetUploadedFiles($this, 'css');
	if ($files) {
		$files = array_combine($files, $files); //keys match values
		$files = array_merge([$this->Lang('select') => ''], $files);
		$i .= '<br />'.$this->CreateInputDropdown($id, 'stylessel', $files, -1, -1, 'onchange="stylefile_selected(this)"'); //TODO onchange set textinput if empty
	}
	$i .= '<br />'.$this->CreateInputFile($id, 'stylesfile', 'text/css', 36, 'id="'.$id.'stylesfile" title="'.
		$this->Lang('tip_upload').'" onchange="stylefile_selected(this)"');
	if ($idata->stylesfile && $files) {
		$i .= ' '.$this->CreateInputCheckbox($id, 'stylesdelete', 1, -1).'&nbsp;'.$this->Lang('delete_upload', $idata->stylesfile);
	}
} elseif ($idata->stylesfile) {
	$i = $idata->stylesfile;
} else {
	$i = $none;
}

//$h = $this->Lang('help_upload',$this->Lang('apply'),$this->Lang('submit'));
$formats[] = ['ttl' => $cascade.$this->Lang('title_styles', $s),
'inp' => $i,
'hlp' => NULL
];
//-------

$tplvars += [
	'basic' => $basic,
	'advanced' => $advanced,
	'formats' => $formats,
	'showtip' => $iconinfo
];

if ($pmod) {
	$tplvars['apply'] = $this->CreateInputSubmit($id, 'apply', $this->Lang('apply'), 'onclick="current_tab()"');
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
 $('input[name="{$id}active_tab"]').val(active.attr('id'));
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

$jsall = $utils->MergeJS($jsincs, $jsfuncs, $jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this, 'openitem.tpl', $tplvars);
if ($jsall) {
	echo $jsall;//inject constructed js after other content
}
