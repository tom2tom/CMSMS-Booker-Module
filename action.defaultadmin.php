<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: defaultadmin
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright,licence,etc.
#----------------------------------------------------------------------

$pdev = $this->CheckPermission('Modify Any Page');
$pset = $this->_CheckAccess('module');
$padm = $pset || $this->_CheckAccess('admin');
if ($padm)
{
//	$psee = true;
	$padd = true;
	$pdel = true;
	$pmod = true;
	$pbkg = true;
}
else
{
//	$psee = $this->_CheckAccess('view');
	$padd = $this->_CheckAccess('add');
	$pdel = $this->_CheckAccess('delete');
	$pmod = $this->_CheckAccess('modify');
	$pbkg = $this->_CheckAccess('book');
}

$mod = $padm || $pmod;
$bmod = $padm || $pbkg;

//$smarty->assign('see',$psee);
$smarty->assign('add',$padd);
$smarty->assign('adm',$padm);
$smarty->assign('bmod',$bmod);
$smarty->assign('del',$pdel);
$smarty->assign('dev',$pdev);
$smarty->assign('mod',$mod); //not $pmod
$smarty->assign('set',$pset);

$ob = cms_utils::get_module('Notifier');
if($ob)
{
	unset($ob);
	$tell = TRUE;
}
else
	$tell = FALSE;
$smarty->assign('tell',$tell);

$si = $this->Lang('item');
$sg = $this->Lang('group');
$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
	cms_utils::get_theme_object();
$iconyes = $theme->DisplayImage('icons/system/true.gif',$this->Lang('true'),'','','systemicon');
$iconno = $theme->DisplayImage('icons/system/false.gif',$this->Lang('false'),'','','systemicon');

$baseurl = $this->GetModuleURLPath();
$bseetip = $this->Lang('tip_seetype','%s');
$iconbsee = '<img src="'.$baseurl.'/images/calendar.png" alt="%s" title="%s" border="0" />';
if($bmod)
{
	$bedittip = $this->Lang('tip_admintype','%s');
	$iconbedit = '<img src="'.$baseurl.'/images/calendar-edit.png" alt="%s" title="%s" border="0" />';
	$iconbdel = $theme->DisplayImage('icons/system/delete.gif','%s','','','systemicon');
}
$exporttip = $this->Lang('exporttype','%s');
$iconexport = $theme->DisplayImage('icons/system/export.gif','%s','','','systemicon');
$seetip = $this->Lang('tip_viewtype','%s');
$iconsee = $theme->DisplayImage('icons/system/view.gif','%s','','','systemicon');
if($mod)
{
	$edittip= $this->Lang('tip_edittype','%s');
	$iconedit = $theme->DisplayImage('icons/system/edit.gif','%s','','','systemicon');
}
if($padd)
{
	$copytip = $this->Lang('tip_copytype','%s');
	$iconcopy = $theme->DisplayImage('icons/system/copy.gif','%s','','','systemicon');
}
if($pdel)
{
	$deltip = $this->Lang('tip_deletetype','%s');
	$icondel = $theme->DisplayImage('icons/system/delete.gif','%s','','','systemicon');
}
$seltip = $this->Lang('tip_selecttype','%s');

if(isset($params['message']))
	$smarty->assign('message',$params['message']);

if(isset($params['active_tab']))
	$showtab = $params['active_tab'];
else
	$showtab = 'data'; //default
$seetab1 = ($showtab=='items');
$seetab2 = ($showtab=='groups');
$seetab3 = ($showtab=='settings');

$smarty->assign('hidden',$this->CreateInputHidden($id,'active_tab',$showtab));

$smarty->assign('tab_headers',$this->StartTabHeaders().
	$this->SetTabHeader('data',$this->Lang('title_bookings')).
	$this->SetTabHeader('items',$this->Lang('title_items'),$seetab1).
	$this->SetTabHeader('groups',$this->Lang('title_groups'),$seetab2).
	$this->SetTabHeader('settings',$this->Lang('settings'),$seetab3).
	$this->EndTabHeaders().
	$this->StartTabContent());
$smarty->assign('tab_footers',$this->EndTabContent());
$smarty->assign('end_tab',$this->EndTab());

$funcs = new bkrshared();
$jsfuncs = array(); //script accumulators
$jsloads = array();
$jsincs = array();
//modal overlay
$smarty->assign('yes',$this->Lang('yes'));
$smarty->assign('no',$this->Lang('no'));
$smarty->assign('proceed',$this->Lang('proceed'));
$smarty->assign('abort',$this->Lang('cancel'));

$jsincs[] =<<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.modalconfirm.min.js"></script>

EOS;

//BOOKINGS TAB
$smarty->assign('startform1',$this->CreateFormStart($id,'adminbooking',$returnid,
	'POST','','','',array('custmsg'=>'')));
$smarty->assign('start_data_tab',$this->StartTab('data'));

$sql = 'SELECT * FROM '.$this->RequestTable.' ORDER BY lodged';
$data = $db->GetAll($sql);
if($data)
{
	$t = $this->Lang('request');
	$rtip = $this->Lang('tip_seereq');
	$iconrsee = '<img src="'.$baseurl.'/images/request.png" alt="'.$rtip.'" title="'.$rtip.'" border="0" />';
	$rtip = $this->Lang('tip_notifylodger');
	$icontell = '<img src="'.$baseurl.'/images/notice.png" alt="'.$rtip.'" title="'.$rtip.'" border="0" />';
	if($bmod)
	{
		$rtip = $this->Lang('tip_edittype',$t);
		$iconredit = '<img src="'.$baseurl.'/images/request-edit.png" alt="'.$rtip.'" title="'.$rtip.'" border="0" />';
		$rtip = $this->Lang('tip_deletetype',$t);
		$iconrdel = $theme->DisplayImage('icons/system/delete.gif',$rtip,'','','systemicon');
		$iconryes = $theme->DisplayImage('icons/system/true.gif',$this->Lang('tip_approve'),'','','systemicon');
		$iconrno = $theme->DisplayImage('icons/system/false.gif',$this->Lang('tip_reject'),'','','systemicon');
	}
	$rtip = $this->Lang('tip_selecttype',$t);
	$yes = $this->Lang('yes');
	$no = $this->Lang('no');
	$fmt = $this->GetPreference('pref_dateformat','Y-m-j').' '.
		$this->GetPreference('pref_timeformat','G:i');
	$tz = new DateTimeZone('UTC');
	$statNONE = $this->Lang('stat_none');
	$statTEMP = $this->Lang('stat_temp');
	$statNEW = $this->Lang('stat_new');
	$statCHG = $this->Lang('stat_chg');
	$statDEL = $this->Lang('stat_del');
	$statTELL = $this->Lang('stat_tell');
	$statASK = $this->Lang('stat_ask');
	$statNOPAY = $this->Lang('stat_nopay');
	$statOK = $this->Lang('stat_ok');
	$statCANCEL = $this->Lang('stat_cancel');

	$pending = array();
	foreach($data as &$row)
	{
		$one = new stdClass();
		$one->sender = $row['sender'];
		$one->contact = $row['contact'];
		$one->paid = ($row['paid']) ? $yes:$no;
		switch($row['status'])
		{
		 case Booker::STATNONE:
			$t = $statNONE;
			break;
		 case Booker::STATTEMP:
			$t = $statTEMP;
			break;
		 case Booker::STATNEW:
			$t = $statNEW;
			break;
		 case Booker::STATCHG:
			$t = $statCHG;
			break;
		 case Booker::STATDEL:
			$t = $statDEL;
			break;
		 case Booker::STATTELL:
			$t = $statTELL;
			break;
		 case Booker::STATASK:
			$t = $statASK;
			break;
		 case Booker::STATNOPAY:
			$t = $statNOPAY;
			break;
		 case Booker::STATOK:
			$t = $statOK;
			break;
		 case Booker::STATCANCEL:
			$t = $statCANCEL;
			break;
		 default:
			$t = $row['status'];
		}
		$one->status = $t;
		$dt = new DateTime('1900-1-1',$tz);
		$dt->setTimestamp($row['lodged']);
		$one->lodged = $dt->format($fmt);
		$t = $funcs->GetItemNameForID($this,$row['item_id']);
		if($row['item_id'] >= Booker::MINGRPID)
		{
			if($row['subgrpcount'])
				$t .= ','.$row['subgrpcount'];
		}
		$one->name = $t;
		$dt = new DateTime('1900-1-1',$tz);
		$dt->setTimestamp($row['slotstart']);
		$one->start = $dt->format($fmt);
		$t = $row['comment'];
		if($t && strlen($t) > 5)
			$t = substr($t,0,5).'...';
		$one->comment = $t;
		$thisid = (int)$row['req_id'];
		$one->see = $this->CreateLink($id,'rsee',$returnid,$iconrsee,array('req_id'=>$thisid));
		if($bmod)
		{
			$one->edit = $this->CreateLink($id,'redit',$returnid,$iconredit,array('req_id'=>$thisid));
			if(1)	//TODO if e.g. not an info-request
			{
				if(empty($row['approved']))
				{
					$one->approve = $this->CreateLink($id,'rapprove',$returnid,$iconryes,array('req_id'=>$thisid));
					$one->reject = $this->CreateLink($id,'rreject',$returnid,$iconrno,array('req_id'=>$thisid));
				}
				else
				{
					$one->approve = ''; //$yes;
					$one->reject = $this->CreateLink($id,'rreject',$returnid,$iconrno,array('req_id'=>$thisid)); //TODO 'tip_reject2'
				}
			}
			else
			{
				$one->approve = '';
				$one->reject = '';
			}
		}
		if($tell)
			$one->notice = $this->CreateLink($id,'rnotify',$returnid,$icontell,array('req_id'=>$thisid));
		if($pdel)
			$one->delete = $this->CreateLink($id,'rdelete',$returnid,$iconrdel,array('req_id'=>$thisid));
		$one->selected = $this->CreateInputCheckbox($id,'selreq[]',$thisid,-1,'title="'.$rtip.'"');
		$pending[] = $one;
	}
	unset($row);

	$smarty->assign('title_pending',$this->Lang('pending'));
	$dcount = count($data);
	$smarty->assign('dcount',$dcount);
	if($dcount > 1)
	{
		$smarty->assign('selectall_req',$this->CreateInputCheckbox($id,'req',true,false,'title="'.$this->Lang('selectall').'" onclick="select_all_req(this)"'));
		$jsfuncs[] = <<<EOS
function select_all_req(b)
{
 var st = $(b).attr('checked');
 if(! st) st = false;
 $('input[name="{$id}selreq[]"][type="checkbox"]').attr('checked',st);
}

EOS;
	} //endif $dcount > 1

	$jsfuncs[] =<<<EOS
function confirm_reqcount() {
 var cb = $('input[name="{$id}selreq[]"]:checked');
 return (cb.length > 0);
}

EOS;

	if($tell)
	{
		$smarty->assign('modaltitle',$this->Lang('title_feedback2'));
		$smarty->assign('customentry',$this->CreateInputText($id,'customentry','',20,30));
		$smarty->assign('prompttitle',$this->Lang('title_prompt'));

		$what = '{'.$this->Lang('item').'}';
		$on = '{'.$this->Lang('date').'}';
		$approve = $this->Lang('email_approve',$what,$on);
		$reject = $this->Lang('email_reject',$what,$on);
		$notify = $this->Lang('email_changed',$what,$on); //ETC

		$jsfuncs[] =<<<EOS
function modalsetup(tg,\$d) {
 var msg,action,id = $(this).attr('id');
 if(id) {
  action = id.replace('{$id}','');
 } else {
  action = $(this).attr('href').replace(/^.+,{$id},(\w+),.+/,'$1');
 }
 switch(action) {
  case 'rapprove':
  case 'approve':
   msg = "$approve";
   break;
  case 'rreject':
  case 'reject':
   msg = "$reject";
   break;
  case 'rnotify':
  case 'notify':
   msg = "$notify";
   break;
  default:
   msg = '?';
   break;
 }
 \$d.find('#common').html(msg);
 var clue = msg.substring(msg.lastIndexOf('['),msg.lastIndexOf(']')+1);
 \$d.find('#{$id}customentry').val(clue);
}
function savecustom(tg,\$d) {
 var custom = \$d.find('#{$id}customentry').val();
 $('input[name={$id}custmsg]').val(custom);
}
function savecustom2(tg,\$d) {
 var custom = \$d.find('#{$id}customentry').val(),
   url = $(tg).attr('href'),
   curl = url+'&{$id}custmsg='+encodeURIComponent(custom);
 $(tg).attr('href',curl);
}

EOS;
		$jsloads[] =<<<EOS
 $('#data .bkrtell > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  preShow: modalsetup,
  onConfirm: savecustom2
 });
 $('#{$id}notify').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  doCheck: confirm_reqcount,
  preShow: modalsetup,
  onConfirm: savecustom
 });

EOS;
		if($bmod)
		{
			$jsloads[] =<<<EOS
 $('#data .bkrapp > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  preShow: modalsetup,
  onConfirm: savecustom2
 });
 $('#{$id}approve').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  doCheck: confirm_reqcount,
  preShow: modalsetup,
  onConfirm: savecustom
 });

EOS;
			$jsloads[] =<<<EOS
 $('#data .bkrrej > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  preShow: modalsetup,
  onConfirm: savecustom2
 });
 $('#{$id}reject').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  doCheck: confirm_reqcount,
  preShow: modalsetup,
  onConfirm: savecustom
 });

EOS;
		} //endif $bmod
	}
	else //Notifier module N/A
	{
		if($bmod)
		{
			$jsloads[] =<<<EOS
 $('#data .bkrapp > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d){
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });
 $('#{$id}approve').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_reqcount,
  preShow: function(tg,\$d){
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });

EOS;
			$jsloads[] =<<<EOS
 $('#data .bkrrej > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d){
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });
 $('#{$id}reject').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_reqcount,
  preShow: function(tg,\$d){
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });

EOS;
		} //endif $bmod
	} //endif Notifier module N/A

	if($tell)
	{
		$smarty->assign('notifybtn',$this->CreateInputSubmit($id,'notify',
			$this->Lang('notify'),'title="'.$this->Lang('tip_notify_selected_requests').'"'));
	}
	if($bmod)
	{
		$smarty->assign('approvbtn',$this->CreateInputSubmit($id,'approve',
			$this->Lang('approve'),'title="'.$this->Lang('tip_approve_sel').'"'));
		$smarty->assign('rejectbtn',$this->CreateInputSubmit($id,'reject',
			$this->Lang('reject'),'title="'.$this->Lang('tip_reject_sel').'"'));
		$smarty->assign('deletebtn0',$this->CreateInputSubmit($id,'delete',
			$this->Lang('delete'),'title="'.$this->Lang('tip_delseltype',$this->Lang('request_multi')).'"'));

		$t = $this->Lang('confirm_delete_type',$this->Lang('request'),'%s');
		$jsloads[] =<<<EOS
 $('#{$id}moduleform_1 #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_reqcount,
  preShow: function(tg,\$d){
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('request_multi'))}';
  }
 });
 $('#data .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d){
    var n = $(this.parentNode).siblings(':first').text(),
    t = "{$t}",
    m = t.replace('%s',n),
    para = \$d.children('p:first')[0];
   para.innerHTML = m;
  }
 });

EOS;
	}
	//titles
	$smarty->assign('pending',$pending);
	$smarty->assign('title_lodger',$this->Lang('title_lodger'));
	$smarty->assign('title_contact',$this->Lang('title_contact'));
	$smarty->assign('title_lodged',$this->Lang('lodged'));
	$smarty->assign('title_status',$this->Lang('status'));
	$smarty->assign('title_paid',$this->Lang('title_paid'));
	$smarty->assign('title_name',$this->Lang('title_name'));
	$smarty->assign('title_start',$this->Lang('start'));
	$smarty->assign('title_comment',$this->Lang('title_comment'));
}
else
{
	$smarty->assign('dcount',0);
	$smarty->assign('nodata',$this->Lang('nonew'));
}
if($bmod)
	$smarty->assign('importbbtn',$this->CreateInputSubmit($id,'importbkg',$this->Lang('import'),
		'title="'.$this->Lang('tip_importbkg').'"'));
$smarty->assign('findbtn',$this->CreateInputSubmit($id,'find',$this->Lang('find'),
		'title="'.$this->Lang('tip_findbkg').'"'));

$items = array();
$icount = 0;
$groups = array();
$gcount	= 0;

$relations = $db->GetAssoc('SELECT * FROM '.$this->GroupTable.' ORDER BY child,proximity');
if($relations)
	$relkeys = array_keys($relations);
$memcounts = $db->GetAssoc('SELECT parent,COUNT(gid) AS num FROM '.$this->GroupTable.' GROUP BY parent');
$grpnames = $db->GetAssoc('SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id>='.Booker::MINGRPID.' ORDER BY item_id');
$owned = $db->GetOne('SELECT FIRST(item_id) AS own FROM '.$this->ItemTable.' WHERE owner > 0'); //something has an owner

$sql =<<<EOS
SELECT I.item_id,I.alias,I.name,I.owner,I.active,U.first_name,U.last_name
FROM {$this->ItemTable} I
LEFT JOIN {$this->UserTable} U ON I.owner = U.user_id
ORDER BY I.name
EOS;
$rs = $db->Execute($sql);
if ($rs)
{
	$uid = ($owned) ? get_userid(false) : 0; //current user
	while ($row = $rs->FetchRow())
	{
		//omit some choices when editing,but current user hasn't admin permission and doesn't own the item
		$skip = $owned && $mod && !$padm && $row['owner'] > 0 && $row['owner'] != $uid;
		$thisid	= (int)$row['item_id'];
		$isitem = ($thisid < Booker::MINGRPID && $thisid != -Booker::MINGRPID);

		$one = new stdClass();

		if ($mod)
			$one->name = $this->CreateLink($id,'edit',$returnid,
				strip_tags($row['name']),
				array('item_id'=>$thisid));
		else
			$one->name	= strip_tags($row['name']);
		//for group-name lookups
		$gotnames[$thisid] = $one->name;

		if ($pdev)
		{
			if ($row['alias'])
				$one->tag = '\''.$row['alias'].'\'';
			else
				$one->tag = $thisid;
		}

		$one->group = '';
		if($relations)
		{
			foreach($relations as $k=>$gdata)
			{
				if($gdata['child'] == $thisid)
				{
					$p = (int)$gdata['parent'];
					if(isset($grpnames[$p]))
						$one->group = $grpnames[$p];
					else
						$one->group = '<'.$this->Lang('noname').'>';
					$p = array_search($k,$relkeys) + 1;
					if(isset($relkeys[$p]) && $relations[$relkeys[$p]]['child'] == $thisid)
						$one->group .= ' +';
					break;
				}
			}
		}

		if($owned)
		{
			if($row['owner'])
			{
				$name = trim($row['first_name'].' '.$row['last_name']);
				if ($name == '') $name = '<'.$this->Lang('noowner').'>';
				$one->ownername = $name;
			}
			else
				$one->ownername = '';
		}

		$t = sprintf($bseetip,($isitem)?$si:$sg);
		$t = sprintf($iconbsee,$t,$t);
		$one->bsee = $this->CreateLink($id,'inspect','',$t,array('item_id'=>$thisid));

//	if($isitem && $bmod && !$skip)
		if($mod && !$skip)
		{
			$t = sprintf($bedittip,($isitem)?$si:$sg);
			$t = sprintf($iconbedit,$t,$t);
			$one->bedit = $this->CreateLink($id,'administer','',$t,array('item_id'=>$thisid));
		}
		else
			$one->bedit = '';

		$t = sprintf($exporttip,($isitem)?$si:$sg);
		$t = sprintf($iconexport,$t,$t);
		$one->export = $this->CreateLink($id,'exportbooking','',$t,array('item_id'=>$thisid));

		$t = sprintf($seetip,($isitem)?$si:$sg);
		$t = sprintf($iconsee,$t,$t);
		$one->see = $this->CreateLink($id,'see','',$t,array('item_id'=>$thisid));

		if($mod && !$skip)
		{
			if($row['active'] > 0) //it's active so create a deactivate-link
				$one->active = $this->CreateLink($id,'toggle',$returnid,$iconyes,
					array('item_id'=>$thisid,'active'=>true));
			elseif($row['active'] == 0) //it's inactive so create an activate-link
				$one->active = $this->CreateLink($id,'toggle',$returnid,$iconno,
					array('item_id'=>$thisid,'active'=>false));
			else
				$one->active = ''; //fake-deleted

			$t = sprintf($edittip,($isitem)?$si:$sg);
			$t = sprintf($iconedit,$t,$t);
			$one->edit = $this->CreateLink($id,'edit',$returnid,$t,array('item_id'=>$thisid));
		}
		else
		{
			if($row['active'] > 0)
				$one->active = $yes;
			elseif($row['active'] == 0)
				$one->active = $no;
			else
				$one->active = '';
			$one->edit = '';
		}

		if ($padd)
		{
			$t = sprintf($copytip,($isitem)?$si:$sg);
			$t = sprintf($iconcopy,$t,$t);
			$one->copy = $this->CreateLink($id,'copy','',$t,array('item_id'=>$thisid));
		}
		else
		{
			$one->copy = '';
		}

		if ($pdel  && !$skip)
		{
			$s = ($isitem)?$si:$sg;
			$t = sprintf($deltip,$s);
			$t = sprintf($icondel,$t,$t);
			$one->delete = $this->CreateLink($id,'delete',$returnid,$t,array('item_id'=>$thisid));
		}
		else
		{
			$one->delete = '';
		}

		$t = sprintf($seltip,($isitem)?$si:$sg);
		if ($isitem)
		{
			$one->selected = $this->CreateInputCheckbox($id,'selitems[]',$thisid,-1,'title="'.$t.'"');
			$items[] = $one;
			$icount++;
		}
		else
		{
			if(!empty($memcounts[$thisid]))
				$one->count = (int)$memcounts[$thisid];
			else
				$one->count = 0;
			$one->selected = $this->CreateInputCheckbox($id,'selgroups[]',$thisid,-1,'title="'.$t.'"');
			$groups[] = $one;
			$gcount++;
		}
	}
	$rs->Close();
}

if ($icount > 0 || $gcount > 0)
{
	$smarty->assign('own',$owned);
	if ($pdev)
		$smarty->assign('title_tag',$this->Lang('title_pagetag'));
	$smarty->assign('title_grp',$this->Lang('title_groups'));
	$smarty->assign('title_owner',$this->Lang('title_owner'));
	$smarty->assign('title_active',$this->Lang('title_active'));
}

//RESOURCES TAB
$smarty->assign('startform2',$this->CreateFormStart($id,'process',$returnid));
$smarty->assign('endform',$this->CreateFormEnd()); //used for all forms
$smarty->assign('start_items_tab',$this->StartTab('items'));

$smarty->assign('icount',$icount);
if ($icount > 0)
{
	$smarty->assign('items',$items);
	$smarty->assign('inametext',$this->Lang('title_name'));
	if ($icount > 1)
		$smarty->assign('selectall_items',
			$this->CreateInputCheckbox($id,'item',true,false,'title="'.$this->Lang('selectall').'" onclick="select_all_items(this)"'));
	$smarty->assign('exportbtn1',
		$this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('exportsel',$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"'));
	$t = ($mod) ? 'update':'inspect';
	$t = strtolower($this->Lang($t));
	$smarty->assign('pricebtn1',
		$this->CreateInputSubmit($id,'price',$this->Lang('price'),
		'title="'.$this->Lang('pricesel',$t,$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"'));
	if ($mod)
	{
		if ($icount > 1)
			$smarty->assign('sortbtn1',
				$this->CreateInputSubmit($id,'sort',$this->Lang('sort'),
				'title="'.$this->Lang('tip_sorttype',$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"'));
		$smarty->assign('ablebtn1',
			$this->CreateInputSubmit($id,'activate',$this->Lang('activate'),
			'title="'.$this->Lang('activatesel',$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"'));
	}
	if ($pdel)
		$smarty->assign('deletebtn1',
			$this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$this->Lang('tip_delseltype',$this->Lang('item_multi')).'"'));
	//related js
	$jsfuncs[] = <<<EOS
function select_all_items(b)
{
 var st = $(b).attr('checked');
 if(! st) st = false;
 $('input[name="{$id}selitems[]"][type="checkbox"]').attr('checked',st);
}
function confirm_itmcount()
{
 var cb = $('input[name="{$id}selitems[]"]:checked');
 return (cb.length > 0);
}

EOS;
	if($pdel)
	{
		$t = $this->Lang('confirm_delete_type',$this->Lang('item'),'%s');
		$jsloads[] = <<<EOS
 $('#{$id}moduleform_2 #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_itmcount,
  preShow: function(tg,\$d){
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('item_multi'))}';
  }
 });
 $('#items .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d){
    var n = $(this.parentNode).siblings(':first').children(':first').text(),
    t = "{$t}",
    m = t.replace('%s',n),
    para = \$d.children('p:first')[0];
   para.innerHTML = m;
  }
 });

EOS;
	}
}
else
	$smarty->assign('noitems',$this->Lang('noitems'));

if ($padd)
{
	$smarty->assign('additem',
	 $this->CreateLink($id,'add',$returnid,
		 $theme->DisplayImage('icons/system/newobject.gif',$this->Lang('additem'),'','','systemicon'),
		 array('item_id'=>-1),'',false,false,'')
	 .' '.
	 $this->CreateLink($id,'add',$returnid,
		 $this->Lang('additem'),
		 array('item_id'=>-1),'',false,false,'class="pageoptions"'));

	$smarty->assign('importibtn',$this->CreateInputSubmit($id,'importitm',$this->Lang('fetch'),
		'title="'.$this->Lang('tip_importitm').'"'));
}

//GROUPS TAB
$smarty->assign('start_grps_tab',$this->StartTab('groups'));
$smarty->assign('startform3',$this->CreateFormStart($id,'process',$returnid));

$smarty->assign('gcount',$gcount);
if($gcount > 0)
{
	$smarty->assign('groups',$groups);
	$smarty->assign('title_gname',$this->Lang('title_name'));
	$smarty->assign('title_gcount',$this->Lang('title_gcount'));
	if ($gcount > 1)
		$smarty->assign('selectall_grps',
			$this->CreateInputCheckbox($id,'group',true,false,'title="'.$this->Lang('selectall').'" onclick="select_all_groups(this)"'));
	$smarty->assign('exportbtn2',
		$this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('exportsel',$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"'));
	$t = ($mod) ? 'update':'inspect';
	$t = strtolower($this->Lang($t));
	$smarty->assign('pricebtn2',
		$this->CreateInputSubmit($id,'price',$this->Lang('price'),
		'title="'.$this->Lang('pricesel',$t,$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"'));
	if ($mod)
	{
		if ($gcount > 1)
			$smarty->assign('sortbtn2',
				$this->CreateInputSubmit($id,'sort',$this->Lang('sort'),
				'title="'.$this->Lang('tip_sorttype',$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"'));
		$smarty->assign('ablebtn2',
			$this->CreateInputSubmit($id,'activate',$this->Lang('activate'),
			'title="'.$this->Lang('activatesel',$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"'));
	}
	if ($pdel)
		$smarty->assign('deletebtn2',
			$this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$this->Lang('tip_delseltype',$this->Lang('group_multi')).'"'));
	//related js
	$t = $this->Lang('confirm_delete_type',$this->Lang('group'),'%s');
	$jsfuncs[] = <<<EOS
function select_all_groups(b)
{
 var st = $(b).attr('checked');
 if(! st) st = false;
 $('input[name="{$id}selgroups[]"][type="checkbox"]').attr('checked',st);
}
function confirm_grpcount()
{
 var cb = $('input[name="{$id}selgroups[]"]:checked');
 return (cb.length > 0);
}

EOS;
	if ($pdel)
	{
		$jsloads[] = <<<EOS
 $('#{$id}moduleform_3 #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_grpcount,
  preShow: function(tg,\$d){
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('group_multi'))}';
  }
 });
 $('#groups .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d){
    var n = $(this.parentNode).siblings(':first').children(':first').text(),
    t = "{$t}",
    m = t.replace('%s',n),
    para = \$d.children('p:first')[0];
   para.innerHTML = m;
  }
 });

EOS;
	}
}
else
	$smarty->assign('nogroups',$this->Lang('nogroups'));

if ($mod)
{
	$smarty->assign('addgrp',
	 $this->CreateLink($id,'add',$returnid,
		 $theme->DisplayImage('icons/system/newobject.gif',$this->Lang('addgroup'),'','','systemicon'),
		 array('item_id'=>-Booker::MINGRPID),'',false,false,'')
	 .' '.
	 $this->CreateLink($id,'add',$returnid,
		 $this->Lang('addgroup'),
		 array('item_id'=>-Booker::MINGRPID),'',false,false,'class="pageoptions"'));
}

//SETTINGS TAB
$smarty->assign('startform4',$this->CreateFormStart($id,'setprefs',$returnid));
$smarty->assign('start_settings_tab',$this->StartTab('settings'));
if($pset)
{
	$settings = array();

	$one = new stdClass();
	$one->title = $this->Lang('title_cleargroup');
	$one->input = $this->CreateInputCheckbox($id,'pref_cleargroup',true,
		$this->GetPreference('pref_cleargroup',false),'');
	$one->must = 1;
	$settings[] = $one;

	$sql = 'SELECT user_id,first_name,last_name FROM '.$this->UserTable.' WHERE active=TRUE ORDER BY last_name,first_name';
	$allusers = $db->GetAssoc($sql);
	if($allusers)
	{
		$one = new stdClass();
		$one->title = $this->Lang('title_owner');
		foreach($allusers as $k=>&$t)
			$t = trim($t['first_name'].' '.$t['last_name']);
		unset($t);
		$allusers = array_flip($allusers);
		$allusers = array($this->Lang('none')=>0) + $allusers; //prepend 'none'
		$one->input = $this->CreateInputDropdown($id,'pref_owner',$allusers,-1,$this->GetPreference('pref_owner'));
		$settings[] = $one;
	}

	$one = new stdClass();
	$one->title = $this->Lang('approver');
	$one->input = $this->CreateInputText($id,'pref_approver',$this->GetPreference('pref_approver'),30,64);
//	$one->help = $this->Lang('help_approver');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('approvercontact');
	$one->input = $this->CreateInputText($id,'pref_approvercontact',$this->GetPreference('pref_approvercontact'),40,128);
//	$one->help = $this->Lang('help_approvercontact');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_formiface');
	$one->input = $this->CreateInputText($id,'pref_formiface',$this->GetPreference('pref_formiface'),30,48);
	$one->help = $this->Lang('help_formiface');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_smsprefix');
	$one->input = $this->CreateInputText($id,'pref_smsprefix',$this->GetPreference('pref_smsprefix'),4,8);
	$one->help = $this->Lang('help_smsprefix');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_smspattern');
	$one->input = $this->CreateInputText($id,'pref_smspattern',$this->GetPreference('pref_smspattern'),20,32);
	$one->help = $this->Lang('help_smspattern');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_email_domains');
	$one->input = $this->CreateInputText($id,'email_domains',$this->GetPreference('pref_domains'),40,80);
	$one->help = $this->Lang('help_email_domains');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_email_subdomains');
	$one->input = $this->CreateInputText($id,'email_subdomains',$this->GetPreference('pref_subdomains'),40,80);
	$one->help = $this->Lang('help_email_subdomains');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_email_topdomains');
	$one->input = $this->CreateInputText($id,'email_topdomains',$this->GetPreference('pref_topdomains'),40,80);
	$one->help = $this->Lang('help_email_topdomains');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_range');
	$choices = explode(',',$this->Lang('periods')); //'minute,hour,day,week,month,year'
	//conform indices to range-usage
	unset($choices[0]);
	unset($choices[1]);
	$choices = array_values($choices);
	$one->input = $this->CreateInputDropdown($id,'pref_showrange',array_flip($choices),-1,$this->GetPreference('pref_showrange'));
	$one->must = 1;
	$one->help = ''; //$this->Lang('help_range');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('listformat');
	$choices = array(
		$this->Lang('start+user')=>Booker::LISTSU,
		$this->Lang('start+resource')=>Booker::LISTSR,
		$this->Lang('resource+start')=>Booker::LISTRS,
		$this->Lang('user+start')=>Booker::LISTUS
	);
	$one->input = $this->CreateInputDropdown($id,'pref_listformat',$choices,-1,$this->GetPreference('pref_listformat'));
	$one->must = 1;
//	$one->help = $this->Lang('help_listformat');
	$settings[] = $one;

	$alltypes = explode(',',$this->Lang('multiperiods')); //'minutes,hours,days,weeks,months,years'
	$alltypes = array_flip($alltypes);

	$one = new stdClass();
	$one->title = $this->Lang('title_slotlength');
	$one->input = $this->CreateInputText($id,'pref_slotcount',$this->GetPreference('pref_slotcount'),3,3).'&nbsp;'.
			$this->CreateInputDropdown($id,'pref_slottype',$alltypes,-1,$this->GetPreference('pref_slottype'));
	$one->must = 1;
	$one->help = ''; //$this->Lang('help_slotlength');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_available');
	$one->input = $this->CreateTextArea(FALSE,$id,$this->GetPreference('pref_available'),
		'available','','','','',40,3,'','','style="height:3em;"');
	$one->help = $this->Lang('help_intervals');
	$settings[] = $one;

	$ob = ModuleOperations::get_instance()->get_module_instance('FrontEndUsers');
	if(is_object($ob))
	{
		$allusers = $ob->GetGroupList(); //associative array with group names as keys,id's as values
		unset($ob);
		$rc = count($allusers);
		if($rc)
			ksort($allusers,SORT_NATURAL | SORT_FLAG_CASE);
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
P.permission_name IN('{$this->PermAddName}','{$this->PermAdminName}','{$this->PermModName}')
ORDER BY U.last_name,U.first_name
EOS;
*/
		$allusers = array($this->Lang('any')=>-1,$this->Lang('none')=>0) + $allusers; //prepend 'none'

		$one = new stdClass();
		$one->title = $this->Lang('title_feugroup');
		$one->input = $this->CreateInputDropdown($id,'pref_feugroup',$allusers,-1,$this->GetPreference('pref_feugroup',0));
		$one->help = $this->Lang('help_feugroup');
		$settings[] = $one;
	}

	$one = new stdClass();
	$one->title = $this->Lang('title_subgrpalloc');
	$choices = array(
		$this->Lang('assignnone')=>Booker::ALLOCNONE,
		$this->Lang('assignfirst')=>Booker::ALLOCFIRST,
		$this->Lang('assignrandom')=>Booker::ALLOCRAND,
		$this->Lang('assignrotate')=>Booker::ALLOCROTE
	);
	$one->input = $this->CreateInputDropdown($id,'pref_subgrpalloc',$choices,-1,$this->GetPreference('pref_subgrpalloc'));
	$one->must = 1;
	$one->help = $this->Lang('help_subgrpalloc');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('fee1');
	$one->input = $this->CreateInputText($id,'pref_fee1',$this->GetPreference('pref_fee1'),6,8);
	$one->help = $this->Lang('help_fee1');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_fee1condition');
	$one->input = $this->CreateTextArea(FALSE,$id,$this->GetPreference('pref_fee1condition'),
		'pref_fee1condition','','','','',40,3,'','','style="height:3em;"');
	$one->help = $this->Lang('help_fee1condition');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('fee2');
	$one->input = $this->CreateInputText($id,'pref_fee2',$this->GetPreference('pref_fee2'),6,8);
	$one->help = $this->Lang('help_fee2');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_fee2condition');
	$one->input = $this->CreateTextArea(FALSE,$id,$this->GetPreference('pref_fee2condition'),
		'pref_fee2condition','','','','',40,3,'','','style="height:3em;"');
	$one->help = $this->Lang('help_fee2condition');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_paymentiface');
	$one->input = $this->CreateInputText($id,'pref_paymentiface',$this->GetPreference('pref_paymentiface'),30,48);
	$one->help = $this->Lang('help_paymentiface');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_ration');
	$one->input = $this->CreateInputText($id,'pref_rationcount',$this->GetPreference('pref_rationcount'),3,3);
	$one->help = $this->Lang('help_ration');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_bookcount');
	$one->input = $this->CreateInputText($id,'pref_bookcount',$this->GetPreference('pref_bookcount'),3,3);
	$one->help = $this->Lang('help_bookcount');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_lead');
	$one->input = $this->CreateInputText($id,'pref_leadcount',$this->GetPreference('pref_leadcount'),3,3).'&nbsp;'.
			$this->CreateInputDropdown($id,'pref_leadtype',$alltypes,-1,$this->GetPreference('pref_leadtype'));
	$one->help = $this->Lang('help_lead');
	$settings[] = $one;

	//days+ for this one
	$t = array_flip($alltypes);
	unset($t[0]);
	unset($t[1]);
	$one = new stdClass();
	$one->title = $this->Lang('title_keep');
	$one->input = $this->CreateInputText($id,'pref_keepcount',$this->GetPreference('pref_keepcount'),3,3).'&nbsp;'.
			$this->CreateInputDropdown($id,'pref_keeptype',array_flip($t),-1,$this->GetPreference('pref_keeptype'));
	$one->help = $this->Lang('help_keep');
	$settings[] = $one;

	$one = new stdClass();
	$choices = $funcs->GetTimeZones($this);
	$one->title = $this->Lang('title_timezone');
	$one->input = $this->CreateInputDropdown($id,'pref_timezone',$choices,-1,$this->GetPreference('pref_timezone'));
	$one->help = $this->Lang('help_zone');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('latitude');
	$one->input = $this->CreateInputText($id,'pref_latitude',$this->GetPreference('pref_latitude'),6,8);
	$one->help = $this->Lang('help_latitude');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('longitude');
	$one->input = $this->CreateInputText($id,'pref_longitude',$this->GetPreference('pref_longitude'),6,8);
	$one->help = $this->Lang('help_longitude');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_dateformat');
	$one->input = $this->CreateInputText($id,'pref_dateformat',
		$this->GetPreference('pref_dateformat','j F Y'),10,12);
	$one->must = 1;
	$one->help = $this->Lang('help_date');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_timeformat');
	$one->input = $this->CreateInputText($id,'pref_timeformat',
		$this->GetPreference('pref_timeformat','G:i'),10,12);
	$one->must = 1;
	$one->help = $this->Lang('help_time');
	$settings[] = $one;

	if(ini_get('mbstring.internal_encoding') !== FALSE) //PHP's encoding-conversion capability is installed
	{
		$one = new stdClass();
		$one->title = $this->Lang('title_exportencoding');
		$encodings = array('utf-8'=>'UTF-8','windows-1252'=>'Windows-1252','iso-8859-1'=>'ISO-8859-1');
		$expchars = $this->GetPreference('pref_exportencoding','UTF-8');
		$t = $this->CreateInputRadioGroup($id,'pref_exportencoding',$encodings,$expchars,'','&nbsp;&nbsp;');
		//override crappy default label-layout
		$t = preg_replace('~label class="(.*)"~U','label class="\\1 radiolabel"',$t);
		$one->input = $t;
		$settings[] = $one;
	}

	$one = new stdClass();
	$one->title = $this->Lang('title_stripexport');
	$one->input = $this->CreateInputCheckbox($id,'pref_stripexport',1,
		   $this->GetPreference('pref_stripexport'));
	$one->help = $this->Lang('help_stripexport');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_exportfile');
	$one->input = $this->CreateInputCheckbox($id,'pref_exportfile',1,
		   $this->GetPreference('pref_exportfile'));
	$one->help = $this->Lang('help_exportfile');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_uploadsdir');
	$one->input = $this->CreateInputText($id,'pref_uploadsdir',$this->GetPreference('pref_uploadsdir',''),30);
	$one->help = $this->Lang('help_uploadsdir');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_cssfile');
	$one->input = $this->CreateInputFile($id,'pref_stylesfile','text/css',36,'id="'.$id.'pref_stylesfile" title="'.
		$this->Lang('tip_upload').'" onchange="file_selected()"');
	$t = $this->GetPreference('pref_stylesfile');
	if($t)
		$one->input .= ' '.$this->CreateInputCheckbox($id,'stylesdelete',1,-1).'&nbsp;'.$this->Lang('delete_upload',$t);
	$one->help = $this->Lang('help_cssfile');
	$settings[] = $one;

	$smarty->assign('compulsory',$this->Lang('help_compulsory'));
	$smarty->assign('settings',$settings);
	//buttons
	$smarty->assign('submitbtn4',$this->CreateInputSubmit($id,'submit',$this->Lang('apply')));
	$smarty->assign('cancel',$this->CreateInputSubmit($id,'cancel',$this->Lang('cancel')));
}
else
{
	$smarty->assign('nopermission',$this->Lang('accessdenied3'));
}

//js
if ($icount > 0 || $gcount > 0)
{
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.SSsort.min.js"></script>

EOS;
	$jsloads[] = <<<EOS
 $('table.table_sort').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s'
 });

EOS;
}
//hacky js here to work around tab-specific forms, i.e. no single page-tab object
$jsloads[] = <<<EOS
 $('input[type="submit"]').click(function() {
  var active = $('#page_tabs > .active');
  $(this).closest('form').append("<input type='hidden' name='{$id}active_tab' value='"+
   active.attr('id')+"' />");
  return true;
 });

EOS;

$jsfuncs[] = <<<EOS
$(document).ready(function() {

EOS;
$jsfuncs = array_merge($jsfuncs,$jsloads);
$jsfuncs[] = <<<EOS
});

EOS;

$smarty->assign('jsincs',$jsincs);
$smarty->assign('jsfuncs',$jsfuncs);

echo $this->ProcessTemplate('adminpanel.tpl');

?>
