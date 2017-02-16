<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: itembookings
# Admin display bookings for resource or group, view or edit mode
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

$prettytype = FALSE;
if (isset($params['delete1'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	$funcs = new Booker\Bookingops();
	if (empty($params['repeat'])) { //onetime
		list($res,$msg) = $funcs->DeleteBkg($this,$params['bkg_id'],$params['custmsg']);
		if ($res) {
			$msg = $this->Lang('bookings_deleted',1);
			$prettytype = TRUE;
	//TODO payment reconciliation, if enough notice is given
		}
	} else { //repeat-booking
		list($res,$msg) = $funcs->DeleteRepeat($this,$params['bkg_id']);
		if ($res) {
	//DO RELATED STUFF ?
		}
	}
	$params['task'] = 'edit';
} elseif (isset($params['delete'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	if (isset($params['sel'])) {
		$funcs = new Booker\Bookingops();
		if (!empty($params['repeat'])) { //is repeat-booking
			list($res,$msg) = $funcs->DeleteRepeat($this,$params['sel']);
			if ($res) {
		//DO STUFF ?
				$msg = $this->Lang('bookings_deleted',count($params['sel']));
				$prettytype = TRUE;
			}
		} else { //onetime
			list($res,$msg) = $funcs->DeleteBkg($this,$params['sel'],$params['custmsg']);
			if ($res) {
		//TODO payment reconciliation, if enough notice is given
				$msg = $this->Lang('bookings_deleted',count($params['sel']));
				$prettytype = TRUE;
			}
		}
	} else { //nothing selected
		$msg = $this->Lang('notypesel',$this->Lang('booking_multi'));
	}
} elseif (isset($params['notify'])) {
//	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	if (isset($params['sel'])) {
		$funcs = new Booker\Messager();
		list($res,$msg) = $funcs->NotifyBooker($this,$params['sel'],$params['custmsg']);
	} else {
		$msg = $this->Lang('notypesel',$this->Lang('booking_multi'));
	}
} elseif (isset($params['export'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('view'))) exit;
	if (isset($params['sel'])) {
		$funcs = new Booker\Bookingops();
		list($res,$msg) = $funcs->ExportBkg($this,$params['sel']);
		if ($res)
			exit;
	} else {
		$msg = $this->Lang('notypesel',$this->Lang('booking_multi'));
	}
}

if ($params['task'] == 'see') {
	if ($this->_CheckAccess('view')) {
		$pmod = FALSE;
	} else
		exit;
} elseif ($params['task'] == 'edit' || $params['task'] == 'add') {
	if ($this->_CheckAccess('admin') || $this->_CheckAccess('book')) {
		$pmod = TRUE;
	} else
		exit;
} else
	exit;

$tplvars = array();
$tplvars['pmod'] = (($pmod)?1:0);

if (!empty($msg)) {
	$tplvars['message'] = $this->_PrettyMessage($msg,$prettytype,FALSE);
}

if ($this->havenotifier) {
	$tell = $pmod; //messages here are about changing a booking
} else
	$tell = FALSE;
$tplvars['tell'] = $tell;

$item_id = (int)$params['item_id'];
$is_group = ($item_id >= Booker::MINGRPID);

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
} else {
	$params['resume'] = array('defaultadmin'); //got here via link
}

$utils = new Booker\Utils();
$params['active_tab'] = ($is_group) ? 'groups':'items';
$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);

$tplvars['startform'] = $this->CreateFormStart($id,'itembookings',$returnid,'POST','','','',
	array('item_id'=>$item_id,'resume'=>$resume,'task'=>$params['task'],'custmsg'=>''));
$tplvars['startform2'] = $this->CreateFormStart($id,'itembookings',$returnid,'POST','','','',
	array('item_id'=>$item_id,'resume'=>$resume,'task'=>$params['task'],'repeat'=>1));
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$idata = $utils->GetItemProperty($this,$item_id,array('name','description'),FALSE);

$typename = ($is_group) ? $this->Lang('group'):$this->Lang('item');
if (!empty($idata['name'])) {
	if ($is_group)
		$tplvars['item_title'] = $this->Lang('title_booksfor',$typename,$idata['name']);
	else
		$tplvars['item_title'] = $this->Lang('title_booksfor',$idata['name'],'');
} else {
	$t = $this->Lang('title_noname',$typename,$item_id);
	$tplvars['item_title'] = $this->Lang('title_booksfor',$t,'');
}
if (!empty($idata['description']))
	$tplvars['desc'] = Booker\Utils::ProcessTemplateFromData($this,$idata['description'],$tplvars);
//in this context, ignore $idata['image']

$yes = $this->Lang('yes');
$no = $this->Lang('no');
$from_group = FALSE;

$baseurl = $this->GetModuleURLPath();
$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
	cms_utils::get_theme_object();

if ($pmod) {
	$t = $this->Lang('edit');
	$icon_open = '<img src="'.$baseurl.'/images/booking-edit.png" alt="'.$t.'" title="'.$t.'" border="0" />';
	$icon_delete = $theme->DisplayImage('icons/system/delete.gif',$this->Lang('delete'),'','','systemicon');
} else {
	$t = $this->Lang('view');
	$icon_open = '<img src="'.$baseurl.'/images/booking.png" alt="'.$t.'" title="'.$t.'" border="0" />';
}
$icon_export = $theme->DisplayImage('icons/system/export.gif',$this->Lang('export'),'','','systemicon');
$t = $this->Lang('tip_notifyuser');
$icon_tell = '<img src="'.$baseurl.'/images/notice.png" alt="'.$t.'" title="'.$t.'" border="0" />';

$funcs = new Booker\Payment();

$jsfuncs = array(); //script accumulators
$jsloads = array();
$jsincs = array();

//========== NON-REPEAT BOOKINGS ===========
//TODO support limit to date-range, changing such date-range
$sql = <<<EOS
SELECT D.bkg_id,D.item_id,D.booker_id,D.slotstart,D.slotlen,D.paid,COALESCE(A.name,B.name,'') AS name,B.publicid
FROM $this->DataTable D
JOIN $this->BookerTable B ON D.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.publicid=A.publicid
WHERE D.item_id=? ORDER BY D.slotstart
EOS;
$data = $utils->SafeGet($sql,array($item_id));
if ($data) {
	$utils->UserProperties($this,$data);
}

$groups = $utils->GetItemGroups($this,$item_id);
if ($groups) {
	$fillers = str_repeat('?,',count($groups)-1).'?';
	$sql = <<<EOS
SELECT D.bkg_id,D.item_id,D.booker_id,D.slotstart,D.slotlen,D.paid,COALESCE(A.name,B.name,'') AS name,B.publicid
FROM $this->DataTable D
JOIN $this->BookerTable B ON D.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.publicid=A.publicid
WHERE D.item_id IN ({$fillers})
ORDER BY D.slotstart
EOS;
	$data2 = $utils->SafeGet($sql,$groups);
	if ($data2) {
		$utils->UserProperties($this,$data2);
		$data = array_merge($data,$data2);
		usort($data, function ($a, $b)
		{
			return $a['slotstart'] - $b['slotstart'];
		});
	}
}

if ($tell) {
	$what = '{'.$this->Lang('item').'}';
	$on = '{'.$this->Lang('date').'}';
	$detail = $this->Lang('whatovrday',$what,$on);
	$notify = $this->Lang('email_change',$detail); //ETC
	$delete = $this->Lang('email_cancel',$detail);
	$jsfuncs[] = <<<EOS
function modalsetup(\$tg,btn) {
 var action,msg,clue;
 if (btn) {
  var id = \$tg.attr('id');
  action = id.replace('{$id}','');
 } else {
  action = \$tg.attr('href').replace(/^.+,{$id},(\w+),.+/,'$1');
 }
 switch (action) {
  case 'notifybooker':
  case 'notify':
   msg = '$notify';
   break;
  case 'itembookings':
  case 'delete':
   msg = '$delete';
   break;
  default:
   msg = '?';
   break;
 }
 clue = msg.substring(msg.lastIndexOf('['),msg.lastIndexOf(']')+1);
 return [msg,clue];
}
function deferbutton(tg,title) {
 var mstr = modalsetup($(tg),true),
  opts = {
   prompt: '<input id="alertable-input" type="text" value="' + mstr[1] + '" />'
  };
 if (title !== undefined) {
  opts.modal = '<form id="alertable"><h4 id="alertable-title">' + title + '</h4>' +
   '<p id="alertable-message"></p><div id="alertable-prompt"></div>' +
   '<div id="alertable-buttons"></div></form>';
 }
 $.alertable.prompt(mstr[0],opts).then(function() {
  var cust = $('#alertable-input').val();
  $('input[name="{$id}custmsg"]').val(cust);
  $(tg).trigger('click.deferred');
 });
}
function deferlink(tg,title) {
 var \$a = $(tg).closest('a'),
  mstr = modalsetup(\$a,false),
  opts = {
   prompt: '<input id="alertable-input" type="text" value="' + mstr[1] + '" />'
  };
 if (title !== undefined) {
  opts.modal = '<form id="alertable"><h4 id="alertable-title">' + title + '</h4>' +
   '<p id="alertable-message"></p><div id="alertable-prompt"></div>' +
   '<div id="alertable-buttons"></div></form>';
 }
 $.alertable.prompt(mstr[0],opts).then(function() {
  var cust = $('#alertable-input').val(),
   url = \$a.attr('href'),
   curl = url+'&{$id}custmsg='+encodeURIComponent(cust);
  \$a.attr('href',curl).trigger('click.deferred');
 });
}
EOS;
}

//some of these values will be tailored as needed
$linkparms = array(
	'item_id'=>$item_id,
	'bkg_id'=>0,
	'resume'=>$resume,
	'task'=>$params['task']
);
//if ($pmod) {
//	$linkparms['bookedit'] = 1;
//}

$rows = array();
if ($data) {
	$titles = array(
		$this->Lang('title_when'),
		$this->Lang('title_user'),
		$this->Lang('title_paid')
	);
	$tplvars['colnames'] = $titles;
	$tplvars['colsorts'] = $titles;

	$dfmt = 'Y-m-d';
	$tfmt = 'G:i';
	$bfmt = $dfmt.' '.$tfmt;
	$rfmt = $this->Lang('showrange');

	$dtw = new DateTime('@0',NULL);

	foreach ($data as &$one) {
		$bkgid = (int)$one['bkg_id'];
		$oneset = new stdClass();

		$dtw->setTimestamp($one['slotstart']);
		$st = $dtw->format($dfmt);
		$stt = $dtw->format($tfmt);
		$dtw->setTimestamp($one['slotstart'] + $one['slotlen']);
		$nd = $dtw->format($dfmt);
		if ($st == $nd)
			$nd = $dtw->format($tfmt);
		else
			$nd .= ' '.$dtw->format($tfmt);
		$st .= ' '.$stt;
		$period = sprintf($rfmt,$st,$nd);

		$linkparms['bkg_id'] = $bkgid;
		if ($pmod) //edit mode
			$oneset->time = $this->CreateLink($id,'openbooking','',$period,$linkparms);
		else
			$oneset->time = $period;
		if ($one['item_id'] != $item_id) { //this one from a group?
			$from_group = TRUE;
			$oneset->time .= ' &Dagger;';
		}
		$oneset->name = $one['name'];
		if ($funcs->MaybePayable($this,$utils,$item_id))
			$oneset->paid = ($one['paid']) ? $yes:$no;
		else
			$oneset->paid = '';
		$oneset->open = $this->CreateLink($id,'openbooking','',$icon_open,$linkparms);
		$oneset->export = $this->CreateLink($id,'exportbooking','',$icon_export,
			array('item_id'=>$item_id,'bkg_id'=>$bkgid,'task'=>$params['task']));
		if ($tell)
		 $oneset->tell = $this->CreateLink($id,'notifybooker','',$icon_tell,
				array('item_id'=>$item_id,'bkg_id'=>$bkgid,'task'=>$params['task']));
		if ($pmod)
		 $oneset->delete = $this->CreateLink($id,'itembookings','',$icon_delete,
			array('item_id'=>$item_id,'bkg_id'=>$bkgid,'delete1'=>1));
		$oneset->selected = $this->CreateInputCheckbox($id,'sel[]',$bkgid,-1);
		$rows[] = $oneset;
	}
	unset($one);
	$tplvars['oncerows'] = $rows;
}

$rc = count($rows);
$tplvars['ocount'] = $rc;
if ($rc) {
	//TODO make page-rows count window-size-responsive
	$pagerows = $this->GetPreference('pagerows',10);
	if ($pagerows && $rc > $pagerows) {
		$tplvars['hasnav'] = 1;
		//setup for SSsort
		$choices = array(strval($pagerows) => $pagerows);
		$f = ($pagerows < 4) ? 5 : 2;
		$n = $pagerows * $f;
		if ($n < $rc)
			$choices[strval($n)] = $n;
		$n *= 2;
		if ($n < $rc)
			$choices[strval($n)] = $n;
		$choices[$this->Lang('all')] = 0;
		$tplvars['rowchanger'] =
			$this->CreateInputDropdown($id,'pagerows',$choices,-1,$pagerows,
			'onchange="pagerows(this);"').'&nbsp;&nbsp;'.$this->Lang('pagerows');
		$curpg='<span id="cpage">1</span>';
		$totpg='<span id="tpage">'.ceil($rc/$pagerows).'</span>';
		$tplvars += array(
			'pageof' => $this->Lang('pageof',$curpg,$totpg),
			'first' => '<a href="javascript:pagefirst()">'.$this->Lang('first').'</a>',
			'prev' => '<a href="javascript:pageback()">'.$this->Lang('previous').'</a>',
			'next' => '<a href="javascript:pageforw()">'.$this->Lang('next').'</a>',
			'last' => '<a href="javascript:pagelast()">'.$this->Lang('last').'</a>'
		);
		$jsfuncs[] = <<<EOS
function pagefirst() {
 $.SSsort.movePage($('#bookings')[0],false,true);
}
function pagelast() {
 $.SSsort.movePage($('#bookings')[0],true,true);
}
function pageforw() {
 $.SSsort.movePage($('#bookings')[0],true,false);
}
function pageback() {
 $.SSsort.movePage($('#bookings')[0],false,false);
}
function pagerows(cb) {
 $.SSsort.setCurrent($('#bookings')[0],'pagesize',parseInt(cb.value));
}
EOS;
	} else {
		$tplvars['hasnav'] = 0;
	}

	if ($rc > 1) {
		$jsloads[] = <<<EOS
 $('#bookings').addClass('table_sort').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s',
  paginate: true,
  pagesize: {$pagerows},
  currentid: 'cpage',
  countid: 'tpage'
 });
EOS;
		$jsfuncs[] = <<<EOS
function select_all(cb) {
 $('#bookings > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
		$tplvars['header_checkbox'] =
			$this->CreateInputCheckbox($id,'selectall',TRUE,FALSE,'onclick="select_all(this);"');
	}

	$jsfuncs[] = <<<EOS
function any_selected() {
 var cb = $('#bookings input[name="{$id}sel[]"]:checked');
 return (cb.length > 0);
}
EOS;
	$tplvars['export'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('tip_export_selected_records').'"');

	if ($pmod) {

		$tplvars['delete'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
		'title="'.$this->Lang('tip_delsel_items').'"');

		if ($tell) {
			$tplvars['notify'] = $this->CreateInputSubmit($id,'notify',$this->Lang('notify'),
			'title="'.$this->Lang('tip_notify_selected_records').'"');

			$t = $this->Lang('title_feedback3');
			$jsloads[] = <<<EOS
 $('#{$id}moduleform_1 #{$id}delete').click(function() {
  if (any_selected()) {
    deferbutton(this,'$t');
  }
  return false;
 });
 $('#bookings .bkrdel > a').click(function(ev) {
  var tg = ev.target || ev.srcElement;
  deferlink(tg,'$t');
  return false;
 });
 $('#{$id}moduleform_1 #{$id}notify').click(function() {
  if (any_selected()) {
   deferbutton(this,'$t');
  }
  return false;
 });
 $('#bookings .bkrtell > a').click(function(ev) {
  var tg = ev.target || ev.srcElement;
  deferlink(tg,'$t');
  return false;
 });
EOS;
		} else { //no Notifier module
			$t = $this->Lang('confirm_delete_type',$this->Lang('booking'),'%s');
			$jsloads[] = <<<EOS
 $('#{$id}moduleform_1 #{$id}delete').click(function() {
  if (any_selected()) {
   var msg = '{$this->Lang('delsel_confirm',$this->Lang('booking_multi'))}';
   confirmclick(this,msg);
  }
  return false;
 });
 $('#bookings .bkrdel > a').click(function(ev) {
  var tg = ev.target || ev.srcElement;
  var n = $(this.parentNode).siblings(':first').children(':first').text(),
   msg = '$t'.replace('%s',n);
  confirmclick(tg,msg);
  return false;
 });
EOS;
		}
	} //$pmod

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
} else {
	$tplvars['norecords'] = $this->Lang('nodata');
}

if ($pmod) {
	$t = $this->Lang('addbooking');
	$icon_add = $theme->DisplayImage('icons/system/newobject.gif',$t,'','','systemicon');
	$linkparms['bkg_id'] = -1; //signal new item
	$tplvars['iconlinkadd'] = $this->CreateLink($id,'openbooking','',$icon_add,$linkparms);
	$tplvars['textlinkadd'] = $this->CreateLink($id,'openbooking','',$t,$linkparms);
	$tplvars['importbbtn'] = $this->CreateInputSubmit($id,'importbkg',$this->Lang('import'),
		'title="'.$this->Lang('tip_importbkg').'"');
}

//========== REPEAT BOOKINGS ===========
/*if (!empty($idata['name'])) {
	$tplvars['item_title2'] = $this->Lang('title_repeatsfor',$typename,$idata['name']);
} else {
	$t = $this->Lang('title_noname',$typename,$idata['item_id']);
	$tplvars['item_title2'] = $this->Lang('title_repeatsfor',$t,'');
}
*/
$tplvars['item_title2'] = $this->Lang('title_repeats');

$sql = <<<EOS
SELECT R.bkg_id,R.item_id,R.booker_id,R.formula,R.subgrpcount,R.paid,COALESCE(A.name,B.name,'') AS name,B.publicid
FROM $this->RepeatTable R
JOIN $this->BookerTable B ON R.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.publicid=A.publicid
WHERE R.item_id=? AND R.active=1
EOS;
$data = $db->GetArray($sql,array($item_id));
if ($data) {
	$utils->UserProperties($this,$data);
}

if ($groups) {
	$sql = <<<EOS
SELECT R.bkg_id,R.item_id,R.booker_id,R.formula,R.subgrpcount,R.paid,COALESCE(A.name,B.name,'') AS name,B.publicid
FROM $this->RepeatTable R
JOIN $this->BookerTable B ON R.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.publicid=A.publicid
WHERE R.item_id IN ({$fillers}) AND R.active=1
EOS;
	$data2 = $db->GetArray($sql,$groups);
	if ($data2) {
		$utils->UserProperties($this,$data2);
		$data = array_merge($data,$data2);
	}
}

$linkparms['repeat'] = 1; //rest of links are for repeat bookings
$rows = array();
if ($data) {
	//titles array same order as displayed columns
	$titles = array( $this->Lang('description'));
	$titles[] = $this->Lang('title_user');
	if ($is_group) {
		$titles[] = $this->Lang('title_gcount');
	}
	$titles[] = $this->Lang('title_paid');
	$tplvars['colnames2'] = $titles;
	$tplvars['colsorts2'] = $titles;

	foreach ($data as &$one) {
		$bkgid = (int)$one['bkg_id'];
		$oneset = new stdClass();

		$linkparms['bkg_id'] = $bkgid;
		if ($pmod)
			$oneset->desc = $this->CreateLink($id,'openbooking','',$one['formula'],$linkparms);
		else
			$oneset->desc = $one['formula'];
		if ($one['item_id'] != $item_id) { //this one from a group?
			$from_group = TRUE;
			$oneset->desc .= ' &Dagger;';
		}
		$oneset->name = $one['name'];
		if ($is_group) {
			$oneset->count = $one['subgrpcount'];
		}
		if ($funcs->MaybePayable($this,$utils,$item_id))
			$oneset->paid = ($one['paid']) ? $yes:$no;
		else
			$oneset->paid = '';
		$oneset->open = $this->CreateLink($id,'openbooking','',$icon_open,$linkparms);
		$oneset->export = $this->CreateLink($id,'exportbooking','',$icon_export,
			array('item_id'=>$item_id,'bkg_id'=>$bkgid,'task'=>$params['task']));
		if ($tell)
			$oneset->tell = $this->CreateLink($id,'notifybooker','',$icon_tell,
				array('item_id'=>$item_id,'bkg_id'=>$bkgid,'task'=>$params['task']));
		if ($pmod)
			$oneset->delete = $this->CreateLink($id,'itembookings','',$icon_delete,
				array('item_id'=>$item_id,'bkg_id'=>$bkgid,'delete1'=>1,'repeat'=>1));
		$oneset->selected = $this->CreateInputCheckbox($id,'sel[]',$bkgid,-1);
		$rows[] = $oneset;
	}
	unset($one);

	$tplvars['reptrows'] = $rows;
} //data

$rc = count($rows);
$tplvars['rcount'] = $rc;
if ($rc) {
	$jsfuncs[] = <<<EOS
function any_selected2() {
 var cb = $('#repeats input[name="{$id}sel[]"]:checked');
 return (cb.length > 0);
}
EOS;
	if ($pmod) {
		if ($rc > 1) {
				//assume small no. of bookings, so no pagination
			$jsloads[] = <<<EOS
 $('#repeats').addClass('table_sort').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s'
 });
EOS;
			$jsfuncs[] = <<<EOS
function select_all2(cb) {
 $('#repeats > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
			$tplvars['header_checkbox2'] =
				$this->CreateInputCheckbox($id,'selectall',TRUE,FALSE,'onclick="select_all2(this);"');
		}

		if ($tell) {
			$tplvars['notify2'] = $this->CreateInputSubmit($id,'notify',$this->Lang('notify'),
			 'title="'.$this->Lang('tip_notify_selected_records').'"');

			$t = $this->Lang('title_feedback3');
			$jsloads[] = <<<EOS
 $('#{$id}moduleform_2 #{$id}notify').click(function() {
  if (any_selected2()) {
   deferbutton(this,'$t');
  }
  return false;
 });
 $('#repeats .bkrtell > a').click(function(ev) {
  var tg = ev.target || ev.srcElement;
  deferlink(tg,'$t');
  return false;
 });
EOS;
		}

		$tplvars['delete2'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
		 'title="'.$this->Lang('tip_delsel_items').'"');

		$t = $this->Lang('confirm_delete_type',$this->Lang('booking'),'%s');
		$jsloads[] = <<<EOS
 $('#{$id}moduleform_2 #{$id}delete').click(function() {
  if (any_selected2()) {
   var msg = '{$this->Lang('delsel_confirm',$this->Lang('booking_multi'))}';
   confirmclick(this,msg);
  }
  return false;
 });
 $('#repeats .bkrdel > a').click(function(ev) {
  var n = $(this.parentNode).siblings(':first').children(':first').text(),
   msg = '$t'.replace('%s',n);
  var tg = ev.target || ev.srcElement;
  confirmclick(tg,msg);
  return false;
 });
EOS;
	} //pmod
} else { //rc i.e. data found
	$tplvars['norecords'] = $this->Lang('nodata'); //maybe epeat assigment, don't care
}

if ($pmod) {
	$t = $this->Lang('addbooking2');
	$icon_add = $theme->DisplayImage('icons/system/newobject.gif',$t,'','','systemicon');
	$linkparms['bkg_id'] = -1;
	$tplvars['iconlinkadd2'] = $this->CreateLink($id,'openbooking','',$icon_add,$linkparms);
	$tplvars['textlinkadd2'] = $this->CreateLink($id,'openbooking','',$t,$linkparms);
}

if ($from_group)
	$tplvars['help_group'] = $this->Lang('help_groupbooking');

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.SSsort.min.js"></script>
EOS;
if ($pmod) //TODO or can send messages
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.alertable.min.js"></script>
EOS;

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'bookings.tpl',$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
