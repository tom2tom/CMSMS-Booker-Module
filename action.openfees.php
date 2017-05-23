<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openfees
# view or edit usage-fees for the specified item or group ($params['item_id']) or
# edit for all selected item(s) or group(s)
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!function_exists('getfeedata')) {
 function getfeedata(&$params, $item_id, &$mod)
 {
	if (!isset($params['condition_id'])) {
		global $db;
		$sql = 'SELECT * FROM '.$mod->FeeTable.' WHERE item_id=? ORDER BY condorder';
		return $db->GetArray($sql,array($item_id));
	}
	return mergefeedata($params,$item_id);
 }

 function mergefeedata(&$params, $item_id)
 {
	$fields = array('description','slottype','slotcount','fee','feecondition');
	if (!isset($params['active']))
		$params['active'] = array();
	$o = 1;
 	$ret = array();
	foreach ($params['condition_id'] as $one) {
		$cid = (int)$one;
		$row = array('condition_id'=>$cid, 'item_id' => $item_id);
		foreach ($fields as $key) {
			$row[$key] = $params[$key][$cid]; }
		$row['condorder'] = $o++;
		$key = 'active';
		if (!empty($params[$key][$cid]))
			$row[$key] = 1;
		else {
			$params[$key][$cid] = 0;
			$row[$key] = 0;
		}
		$ret[] = $row;
	}
 	return $ret;
 }

 function addfeedata(&$params, $item_id, &$pdata)
 {
	if (1) { //$pdata || $params['action'] == 'update') //came direct from action.openitem button-click
		$blank = array(
		 'condition_id' => -1,	//signal for attention before saving
		 'item_id' => $item_id,
		 'description' => '',
		 'slottype' => 0, //minutes
		 'slotcount' => '',
		 'fee' => '',
		 'feecondition' => '',
		 'usercondition' => '',
//		 'condorder I(1) DEFAULT -1,
		 'active' => 1
		);
		$pdata[] = $blank; //append new one
	}
 }

 function delfeedata(&$params, $ids)
 {
	$fields = array('condition_id','item_id','description','slottype','slotcount','fee','feecondition');
	if (!isset($params['active']))
		$params['active'] = array();
	if (!is_array($ids))
		$ids = array($ids);
	foreach ($ids as $one) {
		$i = array_search($one,$params['condition_id']);
		if ($i !== FALSE) {
			foreach ($fields as $key) {
				if (is_array($params[$key])) {
					unset($params[$key][$i]); }
			}
			$key = 'active';
			if (isset($params[$key][$i]))
				unset($params[$key][$i]);
		}
	}
 }

 //swap member of $pdata, which has member 'condition_id' == $cid, with next member, if possible
 function swapfeedata(&$pdata, $cid)
 {
	$key = 'condition_id';
	foreach ($pdata as $i=>$sub) {
		if (isset($sub[$key]) && $sub[$key] == $cid) {
			$tmp = current($pdata);
			if ($tmp !== FALSE) {
				$t = key($pdata);
				$pdata[$t] = $pdata[$i];
				$pdata[$i] = $tmp;
			}
			break;
		}
	}
 }

 function FeeRedirParms($resume, &$params, $msg = FALSE)
 {
	$pnew = array_intersect_key($params,array(
	'item_id'=>1,
	'booker_id'=>1,
	'task'=>1,
	'active_tab'=>1));
	if (!empty($params['resume']))
		$pnew['resume'] = json_encode($params['resume']);
	switch ($resume) {
	 case 'defaultadmin':
		$t = ($params['item_id'] < Booker::MINGRPID) ? 'items':'groups';
		$pnew['active_tab'] = $t;
		break;
	 case 'openitem':
		$pnew['active_tab'] = 'basic';
		break;
	 default:
	}
	if ($msg)
		$pnew['message'] = $msg;
	return $pnew;
 }
}

$utils = new Booker\Utils();
$utils->DecodeParameters($params,array(
	'condition_id',
	'description',
	'fee',
	'slotcount'
));

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
}

if (isset($params['cancel'])) {
	$resume = array_pop($params['resume']);
	$newparms = FeeRedirParms($resume,$params);
	$this->Redirect($id,$resume,'',$newparms);
}

if (isset($params['selitm'])) {
/*came from defaultadmin action 'Fees' button-click, set fees for all
$params = array
'selitm' OR 'selgrp' => array
	0 => string '1'
'fees' => string 'Fees'
'active_tab' => string 'items' OR 'groups'
'action' => string 'processitem'
*/
	$item_id = array_shift($params['selitm']); //use 1st-selected for editing
	$sel = $params['selitm']; //maybe empty now
} elseif (isset($params['selgrp'])) {
	$item_id = array_shift($params['selgrp']);
	$sel = $params['selgrp'];
} elseif (!empty($params['sel'])) {
	//TODO came back
$this->Crash();
	$item_id = $params['item_id'];
	$sel = json_decode($params['sel']); //CHECK html_entity_decode($params['sel'],ENT_QUOTES|ENT_HTML401));
} else {
/*came from openitem add/edit fees button-click
$params = array of all item/group properties, including 'active_tab'
*/
	$item_id = $params['item_id'];
	$sel = FALSE;
}

$funcs = new Booker\Payment();

if (isset($params['submit'])) {
/*$params = array
 'item_id' => string '2'
 'active_tab' => string 'basic' OR 'groups' etc
 'condition_id' => array (string '1' ... ) with keys = respective condition_id's
 'description' => array (string 'Fixed test' ... ) ditto
 'slotcount' => array (string '' ... ) ditto
 'slottype' => array (string '-1' ... ) ditto
 'fee' => array (string '28.00' ... ) ditto
 'feecondition' => array(string 'sunrise..sunset' ... ) ditto
 'usercondition' => array (string '1,2,3...');
 'active' => array(string '1' ....) ditto BUT
   missing member(s) whose condition is inactive AND missing whole $param if
   none is active
*/
	$sql0 = 'DELETE FROM '.$this->FeeTable.' WHERE item_id IN(';

	$pdata = mergefeedata($params,$item_id);
	if ($pdata) {
		$sql1 = 'INSERT INTO '.$this->FeeTable.' (
condition_id,
item_id,
signature,
description,
slottype,
slotcount,
fee,
feecondition,
usercondition,
condorder,
active
) VALUES (?,?,?,?,?,?,?,?,?,?,?)';
		$sql2 = 'UPDATE '.$this->FeeTable.' SET
signature=?,
description=?,
slottype=?,
slotcount=?,
fee=?,
feecondition=?,
usercondition=?,
condorder=?,
active=?
WHERE condition_id=?';
		$allsql = array();
		$allargs = array();
		//accumulate all remaining and tabled condition-id's in array data
		$allids = array();
		foreach ($pdata as $one) {
			if ($one['condition_id'] >= 0)
				$allids[] = (int)$one;
		}
		//remove non-continuing conditions for $item_id
		$t = 'DELETE FROM '.$this->FeeTable.' WHERE item_id=?';
		$args = array($item_id);
		if ($allids) {
			$fillers = str_repeat('?,',count($allids)-1);
			$t .= ' AND condition_id NOT IN('.$fillers.'?)';
			$args = $args + $allids;
		}
		$allsql[] = $t;
		$allargs[] = $args;
		//next, cleanup and accumulate new+retained data
		$params['feesnow'] = json_decode(html_entity_decode($params['feesnow'],ENT_QUOTES|ENT_HTML401));
		foreach ($pdata as &$one) {
			if ($one['slottype'] == -1)
				$one['slotcount'] = NULL; //no count for fixed fee
			$relfee = preg_match('/^ *[+-]/', $one['fee']);
			if ($relfee)
				$lp = $this->Lang('percent');
			if ($one['usercondition']) {
				$parts = preg_split('/[^\d]/',$one['usercondition'],-1,PREG_SPLIT_NO_EMPTY);
				foreach($parts as $i=>$t) {
					if ($t < 0 || $t > 9)
						unset($parts[$i]);
				}
				unset($one);
				$one['usercondition'] = implode(',',$parts);
			}
			$one['active'] = (bool)$one['active'];
			if ($one['condition_id'] < 0) { //new fee-item
				$allsql[] = $sql1;
				$cid = $db->GenID($this->FeeTable.'_seq');
				if ($relfee)
					$one['fee'] = NULL; //no basis for relative fee in new conditions
				$sig = $funcs->GetFeeSignature($one);
				$allargs[] = array(
				 $cid,
				 $item_id,
				 $sig,
				 $one['description'],
				 $one['slottype'],
				 $one['slotcount'],
				 $one['fee'],
				 $one['feecondition'],
				 $one['usercondition'],
				 $one['condorder'],
				 $one['active'],
				);
			} else { //existing fee-item
				$allsql[] = $sql2;
				$cid = $one['condition_id'];
				if ($relfee) {
					$now = $params['feesnow'][$cid];
					if ($now && is_numeric($now)) {
						$t = trim($one['fee']);
						$r = preg_replace('/(%|'.$lp.')$/','',$t);
						if ($t != $r) {
							$one['fee'] = $now + ($now*(float)$r)/100; //maybe 0
						} else {
							$one['fee'] = $now + (float)$t; //ditto
						}
					} else
						$one['fee'] = $now;
				}
				$sig = $funcs->GetFeeSignature($one);
				$allargs[] = array(
				 $sig,
				 $one['description'],
				 $one['slottype'],
				 $one['slotcount'],
				 $one['fee'],
				 $one['feecondition'],
				 $one['usercondition'],
				 $one['condorder'],
				 $one['active'],
				 $cid
				);
			}
		}
		unset($one);

		if (!empty($params['sel'])) {
			//no basis for incremental update of other resources, so we start them afresh
			//TODO scan for matching signature-field values, update those, otherwise add/delete rows
			$sel = json_decode($params['sel']); //array CHECK html_entity_decode($params['sel'],ENT_QUOTES|ENT_HTML401));
			$fillers = str_repeat('?,',count($sel)-1);
			$allsql[] = $sql0.$fillers.'?)';
			$allargs[] = $sel;
			foreach ($sel as $thisid) {
				foreach ($pdata as $one) {
					$allsql[] = $sql1;
					$cid = $db->GenID($this->FeeTable.'_seq');
					$allargs[] = array(
					 $cid,
					 $thisid,
					 $one['description'],
					 $one['slottype'],
					 $one['slotcount'],
					 $one['fee'],
					 $one['feecondition'],
					 $one['condorder'],
					 $one['active'],
					);
				}
			}
		}
		$utils->SafeExec($allsql,$allargs);
	} else { //no fee-data now, clear from table
		if (isset($params['sel'])) {
			$sel = json_decode($params['sel']); //array CHECK html_entity_decode($params['sel'],ENT_QUOTES|ENT_HTML401));
			$fillers = str_repeat('?,',count($sel)-1);
			$sql = $sql0.$fillers.'?)';
			$utils->SafeExec($sql,$sel);
		} else {
			$sql = $sql0.'?)';
			$utils->SafeExec($sql,array($item_id));
		}
	}

	$resume = array_pop($params['resume']);
	$newparms = FeeRedirParms($resume,$params);
	$this->Redirect($id,$resume,'',$newparms);
} elseif (isset($params['delete'])) { //delete selected fees(s)
	if (isset($params['selfees'])) {
		$ids = array_keys($params['selfees']);
		//basic sanity check
		foreach ($ids as &$one) {
			if (!is_numeric($one))
				unset($one);
		}
		unset($one);
		if ($ids) {
			delfeedata($params,$ids);
		}
		//TODO if $params['sel'] == multi-resources upon submit
	}
} elseif (isset($params['delfee'])) { //delete single fee
	$cid = key($params['delfee']);
	delfeedata($params,$cid);
	//TODO if $params['sel'] == multi-resources upon submit
}

$is_group = ($item_id >= Booker::MINGRPID);
$typename = ($is_group) ? $this->Lang('group') : $this->Lang('item');
$pmod = $this->_CheckAccess('admin');
$pdata = getfeedata($params,$item_id,$this);

if ($pmod) {
	if (isset($params['addfee']) || (!$pdata && $params['action'] == 'update')) {
		addfeedata($params,$item_id,$pdata); } elseif (isset($params['move'])) {
		swapfeedata($pdata,key($params['move'])); }
	//TODO if $params['sel'] == multi-resources upon submit
}

$resume = json_encode($params['resume']);

$hidden = array('item_id'=>$item_id,'resume'=>$resume);
if ($sel) {
	$hidden['sel'] = json_encode($sel);
}

$tplvars = array(
	'mod' => $pmod,
	'startform' => $this->CreateFormStart($id,'openfees',$returnid,'POST','','','',$hidden),
	'endform' => $this->CreateFormEnd(),
);

$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);

if (isset($params['message']))
	$tplvars['message'] = $params['message'];

if ($pmod) {
	if ($sel)
		$key = ($is_group) ? 'title_feemod3' : 'title_feemod1';
	else
		$key = ($is_group) ? 'title_feemod2' : 'title_feemod';
} else {
	$key = ($is_group) ? 'title_feesee2' : 'title_feesee';
}

$t = $utils->GetItemNameForID($this,$item_id);
$tplvars['title'] = $this->Lang($key,$t);

$jsloads = array();
$jsfuncs = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();
$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
	cms_utils::get_theme_object();

if ($pdata) {
	$count = count($pdata);
	$choices = array($this->Lang('anytime')=>-1) + array_flip(explode(',',$this->Lang('periods'))); //'minute,hour,day,week,month,year'
	if ($pmod) {
		$tip_del = $this->Lang('tip_delfeetype',$typename);
		$icondel = 'delete.gif';
		if ($count > 1) {
			$tip_up = $this->Lang('tip_up');
			$iconup = 'arrow-u.gif';
			$tip_dn = $this->Lang('tip_down');
			$icondn = 'arrow-d.gif';
		}
	} else {
		$yes = $this->Lang('yes');
		$no = $this->Lang('no');
	}
	$r = 0;
	$rc = $count - 1;

	$feesnow = array();
	$items = array();
	foreach ($pdata as $one) {
		$oneset = new stdClass();
		if ($pmod) {
			$cid = $one['condition_id'];
			$feesnow[$cid] = $one['fee'];
			$oneset->hidden = $this->CreateInputHidden($id,'condition_id[]',$cid);
			$oneset->desc = $this->CreateInputText($id,'description['.$cid.']',$one['description'],20,64);
			$oneset->count = $this->CreateInputText($id,'slotcount['.$cid.']',$one['slotcount'],3,3);
			$oneset->type = $this->CreateInputDropdown($id,'slottype['.$cid.']',$choices,-1,$one['slottype']);
			$oneset->fee = $this->CreateInputText($id,'fee['.$cid.']',$one['fee'],6,8);
			$oneset->cond = $this->CreateTextArea(FALSE,$id,$one['feecondition'],
				'feecondition['.$cid.']','','','','',20,2,'','','style="height:2em;width:20em;"');
			$oneset->user = $this->CreateInputText($id,'usercondition['.$cid.']',$one['usercondition'],20,32);
			$oneset->active = $this->CreateInputCheckbox($id,'active['.$cid.']',1,$one['active']);
			$cid = (int)$cid;
			if ($r == 0) {
				$oneset->uplink = '';
				if ($count > 1) {
					$oneset->dnlink = $this->_CreateInputLinks($id,'move['.$cid.']',$icondn,FALSE,$tip_dn);
					$previd = $cid;
				} else
					$oneset->dnlink = '';
			} else {
				$oneset->uplink = $this->_CreateInputLinks($id,'move['.$previd.']',$iconup,FALSE,$tip_up);
				if ($r < $rc) {
					$oneset->dnlink = $this->_CreateInputLinks($id,'move['.$cid.']',$icondn,FALSE,$tip_dn);
					$previd = $cid;
				} else
					$oneset->dnlink = '';
			}
			$r++;

			$oneset->deletelink = $this->_CreateInputLinks($id,'delfee['.$cid.']',
				$icondel,FALSE,$tip_del);
			//NOT selitm or selgrp - those may be supplied from elsewhere
			$oneset->selected = $this->CreateInputCheckbox($id,'selfees['.$cid.']',1,-1);
		} else {
			$oneset->desc = $one['description'];
			$oneset->count = ($one['slottype'] != -1) ? $one['slotcount']:'';
			$oneset->type = $choices[$one['slottype']];
			$oneset->fee = $one['fee'];
			$oneset->cond = $one['feecondition'];
			$oneset->user = $one['usercondition'];
			$oneset->active = $one['active'] ? $yes:$no;
		}
		$items[] = $oneset;
	}

	$tplvars['hidden'] = $this->CreateInputHidden($id,'feesnow',json_encode($feesnow));

	if ($pmod) {
		$jsincs[] =
'<script type="text/javascript" src="'.$baseurl.'/lib/js/jquery.alertable.min.js"></script>';
/*	$t = ($one['description']) ? $one['description'] : $one['fee'].
		(($one['feecondition']) ? '::'.$one['feecondition']:'');
	if ($t)
		$t = '\\\''.$t.'\\\''; //surrounding escaped quotes go inside js string inside double-quoted html
	else
		$t = $this->Lang('fee_multi');
	$t = $this->Lang('del_confirm',$t);
*/
		$jsfuncs[] = <<<EOS
function confirmclick(tg,msg) {
 $.alertable.confirm(msg,{
  okName: '{$this->Lang('yes')}',
  cancelName: '{$this->Lang('no')}'
 }).then(function() {
  $(tg).trigger('click.deferred');
 });
}
EOS;
		$t = $this->Lang('del_confirm','%s');
		//this table uses fakelinks for deletes
		$jsloads[] = <<<EOS
 $('#fees .feedel > input').click(function(ev) {
  var tg = ev.target || ev.srcElement;
  var n = $(this.parentNode).siblings(':first').children('[name^="{$id}description"]').val(),
   msg = '$t'.replace('%s','\''+n+'\'');
  confirmclick(tg,msg);
  return false;
 });
EOS;
	}

	$tplvars += array(
//	'intro' => $this->Lang('feeintro'),
	'intro' => $this->Lang('help_fees').'<br />'.$this->Lang('help_feeconditions'),
	'items' => $items,
	'desctext' => $this->Lang('description'),
	'periodtext' => $this->Lang('interval'),
	'feetext' => $this->Lang('title_feeinterval'),
	'condtext' => $this->Lang('title_feecondition'),
	'usertext' => $this->Lang('title_usercondition'),
	'activetext' => $this->Lang('title_active'),
	'movetext' => $this->Lang('move')
	);
} else {
	$count = 0;
	$tplvars['hidden'] = NULL;
	$tplvars['nofees'] = $this->Lang('nofees');
}

$tplvars['count'] = $count;

if ($pmod) {
	$t = $this->Lang('addfee');
	$tplvars['addfee'] = $this->_CreateInputLinks($id,'addfee','newobject.gif',TRUE,$t);
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));

	if ($count > 0) {
		$t = $this->Lang('tip_delseltype',$this->Lang('fee_multi'));
		$tplvars['delete'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$t.'" onclick="confirm_delete_item(this);return false;"');

		$jsloads[] = <<<EOS
 $('.updown').hide();
EOS;
		$jsfuncs[] = <<<EOS
function selitm_count() {
 var cb = $('input[name^="{$id}selfees"]:checked');
 return cb.length;
}
function confirm_selitm_count() {
 return (selitm_count() > 0);
}
function confirm_delete_item(btn) {
 if (selitm_count() > 0) {
  var msg = '{$this->Lang('delsel_confirm',$this->Lang('fee_multi'))}';
  $.alertable.confirm(msg,{
   okName: '{$this->Lang('proceed')}',
   cancelName: '{$this->Lang('cancel')}'
  }).then(function() {
   $(btn).trigger('click.deferred');
  });
 }
}
EOS;
		if ($count > 1) {
			$tplvars['selectall'] = $this->CreateInputCheckbox($id,'item',1,-1,
			'title="'.$this->Lang('selectall').'" onclick="select_all_itm(this)"');
			$jsfuncs[] = <<<EOS
function select_all_itm(b) {
 var st = $(b).attr('checked');
 if (!st) st = false;
 $('input[name^="{$id}selfees"][type="checkbox"]').attr('checked', st);
}
EOS;
			$tplvars['dndhelp'] = $this->Lang('help_dnd');
			$jsincs[] =
'<script type="text/javascript" src="'.$baseurl.'/lib/js/jquery.tablednd.min.js"></script>';

			$jsloads[] = <<<EOS
 $('.dndhelp').css('display','block');
 var elem = $('p.pageinput:first'),
  color = $(elem).css('color'),
  size = $(elem).css('font-size');
 $('.fakelink').css({'color':color,'font-size':size});
 $('.table_drag').tableDnD({
  onDragClass: 'row1hover',
  onDrop: function(table, droprows) {
   var odd = false,
    oddclass = 'row1',
    evenclass = 'row2',
    droprow = $(droprows)[0],
    name;
   $(table).find('tbody tr').each(function() {
    name = (odd) ? oddclass : evenclass;
    if (this === droprow){
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
EOS;
		}//$count > 1
	}//$count > 0
} else { //!$pmod
	$tplvars['submit'] = NULL;
	$tplvars['cancel'] =  $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
}

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'fullfees.tpl',$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
