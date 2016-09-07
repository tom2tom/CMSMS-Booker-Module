<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: administer
# Admin display bookings for resource or group, view or edit mode
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if ($params['task'] == 'see') {
	if ($this->_CheckAccess('view')) {
		$pmod = FALSE;
	} else
		exit;
} elseif ($params['task'] == 'edit') {
	if ($this->_CheckAccess('admin') || $this->_CheckAccess('book')) {
		$pmod = TRUE;
	} else
		exit;
} else
	exit;

$tplvars = array();
$tplvars['pmod'] = (($pmod)?1:0);

$ob = cms_utils::get_module('Notifier');
if ($ob) {
	unset($ob);
	$tell = TRUE;
} else
	$tell = FALSE;
$tplvars['tell'] = $tell;

$item_id = (int)$params['item_id'];

//some of these values will be tailored as needed
$linkparms = array(
	'item_id'=>$item_id,
	'bkg_id'=>0,
	'resume'=>$params['action'],
	'task'=>$params['task'],
	'repeat'=>0
);
if ($pmod) {
	$linkparms['bookedit'] = 1;
}

$tplvars['startform'] = $this->CreateFormStart($id,'multibooking',$returnid,'POST','','','',
	array('item_id'=>$item_id,'resume'=>$params['action'],'task'=>$params['task'],'repeat'=>0,'custmsg'=>''));
$tplvars['startform2'] = $this->CreateFormStart($id,'multibooking',$returnid,'POST','','','',
	array('item_id'=>$item_id,'resume'=>$params['action'],'task'=>$params['task'],'repeat'=>1));
$tplvars['endform'] = $this->CreateFormEnd();

$this->_BuildNav($id,$params,$returnid,$tplvars);

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$utils = new Booker\Utils();
$idata = $utils->GetItemProperty($this,$item_id,'*',FALSE);

$is_group = ($item_id >= Booker::MINGRPID);
$type = ($is_group) ? $this->Lang('group'):$this->Lang('item');
if (!empty($idata['name'])) {
	if ($is_group)
		$tplvars['item_title'] = $this->Lang('title_booksfor',$type,$idata['name']);
	else
		$tplvars['item_title'] = $this->Lang('title_booksfor',$idata['name'],'');
} else {
	$t = $this->Lang('title_noname',$type,$idata['item_id']);
	$tplvars['item_title'] = $this->Lang('title_booksfor',$t,'');
}
if (!empty($idata['description']))
	$tplvars['desc'] = Booker\Utils::ProcessTemplateFromData($this,$idata['description'],$tplvars);
//in this context, ignore $idata['image']

$payable = $utils->GetItemPayable($this,$item_id); //any payment condition
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
SELECT D.item_id,D.bkg_id,D.slotstart,D.slotlen,D.paid,B.name FROM {$this->DataTable} D
JOIN {$this->BookerTable} B ON D.booker_id=B.booker_id
WHERE D.item_id=? ORDER BY D.slotstart
EOS;
$data = $utils->SafeGet($sql,array($item_id));

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
		usort($data, function ($a, $b) {
			return $a['slotstart'] - $b['slotstart'];
		});
	}
}

if ($tell) {
	$what = '{'.$this->Lang('item').'}';
	$on = '{'.$this->Lang('date').'}';
	$detail = $this->Lang('whatovrday',$what,$on);
	$notify = $this->Lang('email_changed',$detail); //ETC
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
  case 'delbooking':
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
 $('input[name={$id}custmsg]').val(custom);
}
function savecustom2(tg,\$d) {
 var custom = \$d.find('#{$id}customentry').val(),
   url = $(tg).attr('href'),
   curl = url+'&{$id}custmsg='+encodeURIComponent(custom);
 $(tg).attr('href',curl);
}

EOS;
}

$rows = array();
if ($data) {
	$titles = array(
		$this->Lang('title_when'),
		$this->Lang('title_user'),
		$this->Lang('title_paid')
	);
	$tplvars['colnames'] = $titles;
	$tplvars['colsorts'] = $titles;

	$dfmt = $idata['dateformat']; //translation via Booker\Utils->IntervalFormat() not relevant here
	$tfmt = $idata['timeformat'];
	$bfmt = $dfmt.' '.$tfmt;
	$rfmt = $this->Lang('showrange');

	$dtw = new DateTime('@0',new DateTimeZone('UTC'));

	foreach ($data as &$one) {
		$bid = (int)$one['bkg_id'];
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

		$linkparms['bkg_id'] = $bid;
		if ($pmod) //edit mode
			$oneset->time = $this->CreateLink($id,'openbooking','',$period,$linkparms);
		else
			$oneset->time = $period;
		if ($one['item_id'] != $item_id) { //this one from a group?
			$from_group = TRUE;
			$oneset->time .= ' &Dagger;';
		}
		$oneset->user = $one['name'];
		if ($payable)
			$oneset->paid = ($one['paid']) ? $yes:$no;
		else
			$oneset->paid = '';
		$oneset->open = $this->CreateLink($id,'openbooking','',$icon_open,$linkparms);
		$oneset->export = $this->CreateLink($id,'exportbooking','',$icon_export,
			array('item_id'=>$item_id,'bkg_id'=>$bid));
		if ($tell)
		 $oneset->tell = $this->CreateLink($id,'notifybooker','',$icon_tell,
				array('item_id'=>$item_id,'bkg_id'=>$bid));
		if ($pmod)
		 $oneset->delete = $this->CreateLink($id,'delbooking','',$icon_delete,
			array('item_id'=>$item_id,'bkg_id'=>$bid,'repeat'=>0));
		$oneset->selected = $this->CreateInputCheckbox($id,'sel[]',$bid,-1);
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

	if ($this->_CheckAccess('view') || $this->_CheckAccess('admin')) {
		if ($tell) {
			$tplvars['notify'] = $this->CreateInputSubmit($id,'notify',$this->Lang('notify'),
			'title="'.$this->Lang('tip_notify_selected_records').'"');
			$jsloads[] = <<<EOS
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
		}
		$tplvars['export'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('tip_export_selected_records').'"');
	}
	if ($pmod) {
		$tplvars['delete'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
		'title="'.$this->Lang('tip_delsel_items').'"');
		$t = $this->Lang('confirm_delete_type',$this->Lang('booking'),'%s');
		if ($tell) {
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

EOS;
		} else { //no Notifier module
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
SELECT R.bkg_id,R.item_id,R.formula,R.subgrpcount,R.paid,B.name FROM {$this->RepeatTable} R
JOIN {$this->BookerTable} B ON R.booker_id=B.booker_id
WHERE R.item_id=? AND R.active=1
EOS;
$data = $db->GetArray($sql,array($item_id));
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

$rows = array();
if ($data) {
	//titles array same order as displayed columns
	$titles = array( $this->Lang('description'));
	if ($is_group) {
		$titles[] = $this->Lang('title_count');
	}
	$titles[] = $this->Lang('title_user');
	$titles[] = $this->Lang('title_paid');
	$tplvars['colnames2'] = $titles;
	$tplvars['colsorts2'] = $titles;

	$linkparms['repeat'] = 1; //rest of links are for repeat bookings 

	foreach ($data as &$one) {
		$bid = (int)$one['bkg_id'];
		$oneset = new stdClass();

		$linkparms['bkg_id'] = $bid;
		if ($pmod)
			$oneset->desc = $this->CreateLink($id,'openbooking','',$one['formula'],$linkparms);
		else
			$oneset->desc = $one['formula'];
		if ($one['item_id'] != $item_id) { //this one from a group?
			$from_group = TRUE;
			$oneset->desc .= ' &Dagger;';
		}
		$oneset->user = $one['name'];
		if ($is_group) {
			$oneset->count = $one['subgrpcount'];
		}
		if ($payable)
			$oneset->paid = ($one['paid']) ? $yes:$no;
		else
			$oneset->paid = '';
		$oneset->open = $this->CreateLink($id,'openbooking','',$icon_open,$linkparms);
		$oneset->export = $this->CreateLink($id,'exportbooking','',$icon_export,
			array('item_id'=>$item_id,'bkg_id'=>$bid));
		if ($tell)
			$oneset->tell = $this->CreateLink($id,'notifybooker','',$icon_tell,
				array('item_id'=>$item_id,'bkg_id'=>$bid));
		if ($pmod)
			$oneset->delete = $this->CreateLink($id,'delbooking','',$icon_delete,
				array('item_id'=>$item_id,'bkg_id'=>$bid,'repeat'=>1));
		$oneset->selected = $this->CreateInputCheckbox($id,'sel[]',$bid,-1);
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
	if ($this->_CheckAccess('view') || $this->_CheckAccess('admin')) {
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
	}
	if ($pmod) {
		$tplvars['delete2'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
		 'title="'.$this->Lang('tip_delsel_items').'"');

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
			$jsloads[] = <<<EOS
 $('#{$id}moduleform_2 #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  doCheck: any_selected2,
  preShow: modalsetup,
  onConfirm: savecustom
 });
 $('#repeats .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confmessage',
  confirmBtnID: 'mc_conf2',
  denyBtnID: 'mc_deny2',
  preShow: modalsetup,
  onConfirm: savecustom2
 });

EOS;
		} else { //no Notifier module
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
		} //no Notifier
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

echo Booker\Utils::ProcessTemplate($this,'administer.tpl',$tplvars);
