<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: bookerbookings
# Admin display a user's bookings, view or edit mode
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
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
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

$ob = cms_utils::get_module('Notifier');
if ($ob) {
	unset($ob);
	$tell = $pmod; //messages here are about cancellation
} else
	$tell = FALSE;
$tplvars['tell'] = $tell;

$bookerid = (int)$params['booker_id'];

$params['active_tab'] = 'people';
$tplvars['pagenav'] = $this->_BuildNav($id,$returnid,'defaultadmin',$params);
$tplvars['startform'] = $this->CreateFormStart($id,'bookerbookings',$returnid,'POST','','','',
	array('booker_id'=>$bookerid,'resume'=>$params['action'],'task'=>$params['task'],'custmsg'=>''));
$tplvars['startform2'] = $this->CreateFormStart($id,'bookerbookings',$returnid,'POST','','','',
	array('booker_id'=>$bookerid,'resume'=>$params['action'],'task'=>$params['task'],'repeat'=>1));
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$utils = new Booker\Utils();

$payable = FALSE; //TODO per item  $utils->GetItemPayable($this,$item_id); //any payment condition
$yes = $this->Lang('yes');
$no = $this->Lang('no');
$from_group = FALSE;
//modal overlay
$tplvars += array(
	'modaltitle' => $this->Lang('title_feedback3'),
	'customentry' => $this->CreateInputText($id,'customentry','',20,30),
	'prompttitle' => $this->Lang('title_prompt'),
	'proceed' => $this->Lang('proceed'),
	'abort' => $this->Lang('cancel'),
	'yes' => $yes,
	'no' => $no
);

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

$jsfuncs = array(); //script accumulators
$jsloads = array();
$jsincs = array();

//========== NON-REPEAT BOOKINGS ===========
//TODO support limit to date-range, changing such date-range
$sql = <<<EOS
SELECT D.item_id,D.booker_id,D.bkg_id,D.slotstart,D.slotlen,D.paid,I.name,B.name AS user FROM {$this->DataTable} D
JOIN {$this->ItemTable} I ON D.item_id=I.item_id
JOIN {$this->BookerTable} B ON D.booker_id=B.booker_id
WHERE D.booker_id=? ORDER BY D.slotstart
EOS;
$data = $utils->SafeGet($sql,array($bookerid));

/* TODO
$groups = $utils->GetItemGroups($this,$item_id);
if ($groups) {
	$fillers = str_repeat('?,',count($groups)-1).'?';
	$sql = <<<EOS
SELECT D.bkg_id,D.item_id,D.slotstart,D.slotlen,D.paid,B.name FROM {$this->DataTable} D
JOIN {$this->BookerTable} B ON D.booker_id=B.booker_id
WHERE D.item_id IN({$fillers})
ORDER BY D.slotstart
EOS;
	$data2 = $utils->SafeGet($sql,$groups);
	if ($data2) {
		$data = array_merge($data,$data2);
		usort($data, function ($a, $b)
		{
			return $a['slotstart'] - $b['slotstart'];
		});
	}
}
*/

//some of these values will be tailored as needed
$linkparms = array(
	'item_id'=>0,
	'booker_id'=>0,
	'bkg_id'=>0,
	'resume'=>$params['action'],
	'task'=>$params['task']
);
//if ($pmod) {
//	$linkparms['bookedit'] = 1;
//}

$rows = array();
if ($data) {
	$linkparms['booker_id'] = $data[0]['booker_id'];
	$tplvars['item_title'] = $this->Lang('title_booksfor',$this->Lang('user'),$data[0]['user']);
	$titles = array(
		$this->Lang('title_when'),
		$this->Lang('title_item'),
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
		$item_id = (int)$one['item_id'];
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

		$linkparms['item_id'] = $item_id;
		$linkparms['bkg_id'] = $bkgid;
		if ($pmod) //edit mode
			$oneset->time = $this->CreateLink($id,'openbooking','',$period,$linkparms);
		else
			$oneset->time = $period;
/*TODO		if ($one['item_id'] != $item_id) { //this one from a group?
			$from_group = TRUE;
			$oneset->time .= ' &Dagger;';
		}
*/
		$oneset->name = $one['name'];
		if ($payable)
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
	$pagerows = $this->GetPreference('pref_pagerows',10);
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

			$jsloads[] = <<<EOS
 $('#{$id}moduleform_1 #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  doCheck: any_selected,
  preShow: modalsetup,
  onConfirm: savecustom
 });
 $('#bookings .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  preShow: modalsetup,
  onConfirm: savecustom2
 });
 $('#{$id}moduleform_1 #{$id}notify').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  doCheck: any_selected,
  preShow: modalsetup,
  onConfirm: savecustom
 });
 $('#bookings .bkrtell > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  preShow: modalsetup,
  onConfirm: savecustom2
 });

EOS;
		} else { //no Notifier module, no message popup
			$t = $this->Lang('confirm_delete_type',$this->Lang('booking'),'%s');
			$jsloads[] = <<<EOS
 $('#{$id}moduleform_1 #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: any_selected,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('booking_multi'))}';
  }
 });
 $('#bookings .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d) {
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
	$tplvars['item_title2'] = $this->Lang('title_repeatsfor',$type,$idata['name']);
} else {
	$t = $this->Lang('title_noname',$type,$idata['item_id']);
	$tplvars['item_title2'] = $this->Lang('title_repeatsfor',$t,'');
}
*/
$tplvars['item_title2'] = $this->Lang('title_repeats');

$sql = <<<EOS
SELECT R.bkg_id,R.item_id,R.formula,R.subgrpcount,R.paid,I.name,B.name AS user FROM {$this->RepeatTable} R
JOIN {$this->ItemTable} I ON R.item_id=I.item_id
JOIN {$this->BookerTable} B ON R.booker_id=B.booker_id
WHERE R.booker_id=? AND R.active=1
EOS;

$data = $db->GetArray($sql,array($bookerid));
/* TODO
if ($groups) {
	$sql = <<<EOS
SELECT R.bkg_id,R.item_id,R.formula,R.subgrpcount,R.paid,B.name FROM {$this->RepeatTable} R
JOIN {$this->BookerTable} B ON R.booker_id=B.booker_id
WHERE R.item_id IN({$fillers}) AND R.active=1
EOS;
	$data2 = $db->GetArray($sql,$groups);
	if ($data2)
		$data = array_merge($data,$data2);
}
*/
$linkparms['repeat'] = 1; //rest of links are for repeat bookings
$rows = array();
if ($data) {
	if (!isset($tplvars['item_title']))
		$tplvars['item_title'] = $this->Lang('title_booksfor',$this->Lang('user'),$data[0]['user']);
	//titles array same order as displayed columns
	$titles = array(
	$this->Lang('description'),
	$this->Lang('title_item'),
    $this->Lang('title_gcount'),
	$this->Lang('title_paid')
	);
	$tplvars['colnames2'] = $titles;
	$tplvars['colsorts2'] = $titles;

	foreach ($data as &$one) {
		$bkgid = (int)$one['bkg_id'];
		$item_id = (int)$one['item_id'];
		$oneset = new stdClass();

		$linkparms['item_id'] = $item_id;
		$linkparms['bkg_id'] = $bkgid;
		if ($pmod)
			$oneset->desc = $this->CreateLink($id,'openbooking','',$one['formula'],$linkparms);
		else
			$oneset->desc = $one['formula'];
/* TODO
 		if ($one['item_id'] != $item_id) { //this one from a group?
			$from_group = TRUE;
			$oneset->desc .= ' &Dagger;';
		}
*/
		$oneset->name = $one['name'];
		if ($item_id >= Booker::MINGRPID) {
			$oneset->count = $one['subgrpcount'];
		}
		if ($payable)
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
			$jsloads[] = <<<EOS
 $('#{$id}moduleform_2 #{$id}notify').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  doCheck: any_selected2,
  preShow: modalsetup,
  onConfirm: savecustom
 });
 $('#repeats .bkrtell > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  preShow: modalsetup,
  onConfirm: savecustom2
 });

EOS;
		}

		$tplvars['delete2'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
		 'title="'.$this->Lang('tip_delsel_items').'"');

		$t = $this->Lang('confirm_delete_type',$this->Lang('booking'),'%s');
		$jsloads[] = <<<EOS
 $('#{$id}moduleform_2 #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: any_selected2,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('booking_multi'))}';
  }
 });
 $('#repeats .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d) {
    var n = $(this.parentNode).siblings(':first').children(':first').text(),
    t = "{$t}",
    m = t.replace('%s',n),
    para = \$d.children('p:first')[0];
   para.innerHTML = m;
  }
 });

EOS;
	} //pmod
} else { //rc i.e. data found
	$tplvars['norecords'] = $this->Lang('nodata'); //maybe epeat assigment, don't care
}

if ($tell && ($rc || $tplvars['ocount'])) {
	$what = '{'.$this->Lang('item').'}';
	$on = '{'.$this->Lang('date').'}';
	$detail = $this->Lang('whatovrday',$what,$on);
	$notify = $this->Lang('email_change',$detail); //ETC
	$delete = $this->Lang('email_cancel',$detail);
	$jsfuncs[] = <<<EOS
function modalsetup(tg,\$d) {
 var msg,action,id = $(this).attr('id');
 if (id) {
  action = id.replace('{$id}','');
 } else {
  action = $(this).attr('href').replace(/^.+,{$id},(\w+),.+/,'$1');
 }
 switch (action) {
  case 'notifybooker':
  case 'notify':
   msg = "$notify";
   break;
  case 'itembookings':
  case 'delete':
   msg = "$delete";
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
 $('input[name="{$id}custmsg"]').val(custom);
 return true;
}
function savecustom2(tg,\$d) {
 var custom = \$d.find('#{$id}customentry').val(),
   url = $(tg).attr('href'),
   curl = url+'&{$id}custmsg='+encodeURIComponent(custom);
 $(tg).attr('href',curl);
 return true;
}

EOS;
}

if (!isset($tplvars['item_title'])) {
	$funcs = new Booker\Userops();
	$name = $funcs->GetName($this,$bookerid);
	$tplvars['item_title'] = $this->Lang('title_booksfor',$this->Lang('user'),$name);
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

if ($jsloads) {
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.SSsort.min.js"></script>
EOS;
if ($pmod)
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.modalconfirm.min.js"></script>

EOS;
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,'bookings.tpl',$tplvars);
