<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: fees
# view or edit usage-fees for the specified item or group ($params['id']) or
# edit for all selected item(s) or group(s)
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if(isset($params['selitems']))
{
/*came from defaultadmin action 'Fees' button-click, set fees for all
$params = array
'selitems' OR 'selgroups' => array
    0 => string '1'
'fees' => string 'Fees'
'active_tab' => string 'items' OR 'groups'
'action' => string 'process'
*/
	$sel = $params['selitems'];
	$item_id = reset($params['selitems']); //use 1st-selected for editing
}
elseif(isset($params['selgroups']))
{
	$sel = $params['selgroups'];
	$item_id = reset($params['selgroups']);
}
else
{
/*came from openitem add/edit fees button-click
$params = array
*/
$this->Crash();
	$sel = false;
	$params['active_tab'] = false;
	$item_id = $params['item_id'];
}

if(isset($params['cancel']))
{
$this->Crash();
	if(isset($params['sel']))
		$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
	else
		$this->Redirect($id,'openitem','',array('item_id'=>$item_id));
}
elseif(isset($params['submit']))
{
	//TODO save stuff
/*$params = array
 'item_id' => string '2'
 'condition_id' => array (string '1' ... )
 'description' => array (string 'Fixed test' ... )
 'slotcount' => array (string '' ... )
 'slottype' => array (string '-1' ... )
 'fee' => array (string '28.00' ... )
 'feecondition' => array(string 'sunrise..sunset' ... )
 'active' => array(string '1' ....) but missing if condition is inactive
*/
$this->Crash();
	$this->Redirect($id,'openitem','',array('item_id'=>$item_id));
}
elseif(isset($params['addfee']))
{
$this->Crash();
	//TODO add data-row
}
elseif(isset($params['delete'])) //delete selected fees(s)
{
	if(isset($params['selitems']))
	{
		//basic sanity check
		foreach($params['selitems'] as &$one)
		{
			$t = (int)$one;
			if(is_numeric($t) && $t >= 0)
				$one = $t;
			else
				unset($one);
		}
		unset($one);
		if($params['selitems'])
		{
			$t = implode(',',$params['selitems']);
			$sql = 'DELETE FROM '.$this->PayTable.' WHERE condition_id IN ('.$t.')';
			$db->Execute($sql);
		}
	}
}
elseif(isset($params['delfee'])) //delete single fee
{
	$sql = 'DELETE FROM '.$this->PayTable.' WHERE condition_id=?';
	$db->Execute($sql, array((int)$params['cond_id']));
}

$is_group = ($item_id >= Booker::MINGRPID);
$typestr = ($is_group) ? $this->Lang('group') : $this->Lang('item');

$hidden = $this->CreateInputHidden($id,'item_id',$item_id);
if($sel)
	$hidden .= $this->CreateInputHidden($id,'sel',json_encode($sel))
	.$this->CreateInputHidden($id,'active_tab',$params['active_tab']);

$pmod = $this->_CheckAccess('admin');
$tplvars = array(
	'mod' => $pmod,
	'startform' => $this->CreateFormStart($id,'fees',$returnid),
	'endform' => $this->CreateFormEnd(),
	'hidden' => $hidden
);

if(isset($params['message']))
	$tplvars['message'] = $params['message'];


if ($pmod)
{
	$key = ($is_group) ? 'feemodtitle2' : 'feemodtitle';
}
else
{
	$key = ($is_group) ? 'feeseetitle2' : 'feeseetitle';
}
$tplvars['title'] = $this->Lang($key);

$jsloads = array();
$jsfuncs = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();
$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
	cms_utils::get_theme_object();

$sql = 'SELECT * FROM '.$this->PayTable.' WHERE item_id=? ORDER BY condorder';
$pdata = $db->GetAll($sql,array($item_id));
if($pdata)
{
	$count = count($pdata);
	$choices = array($this->Lang('anytime')=>-1) + array_flip(explode(',',$this->Lang('periods'))); //'minute,hour,day,week,month,year'
	if($pmod)
	{
		$tip = $this->Lang('tip_delfeetype',$typestr);
		$icondel = $theme->DisplayImage('icons/system/delete.gif',$tip,'','','systemicon');
		if($count > 1)
		{
			$iconup = $theme->DisplayImage('icons/system/arrow-u.gif',$this->Lang('tip_up'),'','','systemicon');
			$icondn = $theme->DisplayImage('icons/system/arrow-d.gif',$this->Lang('tip_down'),'','','systemicon');
		}
	}
	else
	{
		$yes = $this->Lang('yes');
		$no = $this->Lang('no');
	}
	$r = 0;
	$rc = $count - 1;

	$items = array();
	foreach($pdata as $one)
	{
		$oneset = new stdClass();
		if($pmod)
		{
			$oneset->hidden = $this->CreateInputHidden($id,'condition_id[]',$one['condition_id']);
			$oneset->desc = $this->CreateInputText($id,'description[]',$one['description'],20,64);
			$oneset->count = $this->CreateInputText($id,'slotcount[]',$one['slotcount'],3,3);
			$oneset->type = $this->CreateInputDropdown($id,'slottype[]',$choices,-1,$one['slottype']);
			$oneset->fee = $this->CreateInputText($id,'fee',$one['fee[]'],6,8);
			$oneset->cond = $this->CreateTextArea(FALSE,$id,$one['feecondition'],
				'feecondition[]','','','','',15,2,'','','style="height:2em;"');
			$oneset->active = $this->CreateInputCheckbox($id,'active[]',1,$one['active']);
			if($r == 0)
			{
				$oneset->uplink = '';
				$oneset->dnlink = '';
			}
			else
			{
				$thisid = (int)$one['condition_id'];
				$oneset->uplink = $this->CreateLink($id,'swapfees',$returnid,$icnoup,
					array('condition_id'=>$thisid, 'prev_cond_id'=>$previd));
				$items[$r-1]->dnlink = $this->CreateLink($id,'swapfees',$returnid,$icondn,
					array('condition_id'=>$previd,'next_cond_id'=>$thisid));
				if($r == $rc)
					$oneset->dnlink = '';
				$previd = $thisid;
			}
			$r++;

			$t = ($one['description']) ? $one['description'] : $one['fee'].
				(($one['feecondition']) ? '::'.$one['feecondition']:'');
			$this->Lang('delselfee_confirm',$t);
			$oneset->deletelink = $this->CreateLink($id,'delfee',$returnid,$icondel,
				array('cond_id'=>$one['condition_id']),$t);
			$oneset->selected = ($count > 1) ? $this->CreateInputCheckbox($id,'selitems[]',1,-1):NULL;
		}
		else
		{
			$oneset->desc = $one['description'];
			$oneset->count = ($one['slottype'] != -1) ? $one['slotcount']:'';
			$oneset->type = $choices[$one['slottype']];
			$oneset->fee = $one['fee'];
			$oneset->cond = $one['feecondition'];
			$oneset->active = $one['active'] ? $yes:$no;
		}
		$items[] = $oneset;
	}
	
	$tplvars = $tplvars + array(
	//'intro' => $this->Lang('feeintro'),
	'intro' => $this->Lang('help_fees').'<br />'.$this->Lang('help_feeconditions'),
	'items' => $items,
	'desctext' => $this->Lang('description'),
	'periodtext' => $this->Lang('interval'),
	'feetext' => $this->Lang('title_fee1'),
	'condtext' => $this->Lang('title_fee1condition'),
	'activetext' => $this->Lang('title_active'),
	'movetext' => $this->Lang('move')
	);
}
else
{
	$count = 0;
	$tplvars['nofees'] = $this->Lang('nofees');
}

$tplvars['count'] = $count;

if($pmod)
{
	$t = $this->Lang('addfee');
	$tplvars['addlink'] = $this->CreateLink($id,'addfee',$returnid,
		$theme->DisplayImage('icons/system/newobject.gif',$t,'','','systemicon'),
			array(),'',false,false,'')
		.' '.
		$this->CreateLink($id,'addfee',$returnid,$t,
			array(),'',false,false,'class="pageoptions"');
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
	$t = $this->Lang('fee_multi');
	$tplvars['delete'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
		'title="'.$this->Lang('tip_delseltype',$t)
		.'" onclick="return confirm_delete_item();"');

	if($count > 0)
	{
		if($count > 1)
		{
			$tplvars['selectall'] = $this->CreateInputCheckbox($id,'item',1,-1,
			'title="'.$this->Lang('selectall').'" onclick="select_all_items(this)"');
			$jsfuncs[] = <<<EOS
function select_all_items(b)
{
 var st = $(b).attr('checked');
 if(! st) st = false;
 $('input[name="{$id}selitems[]"][type="checkbox"]').attr('checked', st);
}

EOS;
		$tplvars['dndhelp'] = $this->Lang('help_dnd');
		}
	
		$t = $this->Lang('delsel_confirm',$t);
		$jsfuncs[] = <<<EOS
function selitm_count()
{
 var cb = $('input[name="{$id}selitems[]"]:checked');
 return cb.length;
}
function confirm_selitm_count()
{
 return (selitm_count() > 0);
}
function confirm_delete_item()
{
 if (selitm_count() > 0)
  return confirm('{$t}');
 return false;
}

EOS;
		$jsincs[] =
'<script type="text/javascript" src="'.$baseurl.'/include/jquery.tablednd.min.js"></script>';
		$jsloads[] = <<<EOS
 $('.updown').hide();
 $('.dndhelp').css('display','block');
 $('.table_drag').tableDnD({
  onDragClass: 'row1hover',
  onDrop: function(table, droprows){
   var name;
   var odd = true;
   var oddclass = 'row1';
   var evenclass = 'row2';
   var droprow = $(droprows)[0];
   $(table).find('tbody tr').each(function(){
    name = odd ? oddclass : evenclass;
    if (this === droprow){
     name = name+'hover';
    }
    $(this).removeClass().addClass(name);
    odd = !odd;
   });
  }
 }).find('tbody tr').removeAttr('onmouseover').removeAttr('onmouseout')
   .mouseover(function(){
  var now = $(this).attr('class');
  $(this).attr('class', now+'hover');
 }).mouseout(function() {
  var now = $(this).attr('class');
  var to = now.indexOf('hover');
  $(this).attr('class', now.substring(0,to));
 });
 
EOS;
	}
}
else
{
	$tplvars['submit'] = NULL;
	$tplvars['cancel'] =  $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
}

$tplvars['jsincs'] = $jsincs;
if($jsloads)
{
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;

echo bkrshared::ProcessTemplate($this,'fullfees.tpl',$tplvars);
?>
