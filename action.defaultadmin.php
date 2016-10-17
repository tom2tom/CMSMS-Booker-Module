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
if ($padm) {
//	$psee = TRUE;
	$padd = TRUE;
	$pdel = TRUE;
	$pmod = TRUE;
	$pbkg = TRUE;
	$pper = TRUE;
} else {
//	$psee = $this->_CheckAccess('view');
	$padd = $this->_CheckAccess('add');
	$pdel = $this->_CheckAccess('delete');
	$pmod = $this->_CheckAccess('modify');
	$pbkg = $this->_CheckAccess('book');
	$pper = $this->_CheckAccess('booker');
}

$mod = $padm || $pmod;
$bmod = $padm || $pbkg;
$tell = $this->havenotifier;

$tplvars = array(
//	'see' => $psee,
	'add' => $padd,
	'adm' => $padm,
	'bmod' => $bmod,
	'del' => $pdel,
	'dev' => $pdev,
	'mod' => $mod, //not $pmod
	'per' => $pper,
	'set' => $pset,
	'tell' => $tell
);

$si = $this->Lang('item');
$sg = $this->Lang('group');
$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
	cms_utils::get_theme_object();
$iconyes = $theme->DisplayImage('icons/system/true.gif',$this->Lang('true'),'','','systemicon');
$iconno = $theme->DisplayImage('icons/system/false.gif',$this->Lang('false'),'','','systemicon');
$yes = $this->Lang('yes');
$no = $this->Lang('no');

$baseurl = $this->GetModuleURLPath();
$bseetip = $this->Lang('tip_seetype','%s');
$iconbsee = '<img src="'.$baseurl.'/images/booking.png" alt="%s" title="%s" border="0" />';
if ($bmod) {
	$baddtip = $this->Lang('tip_addbooktype','%s');
	$iconbadd = '<img src="'.$baseurl.'/images/booking-add.png" alt="%s" title="%s" border="0" />';
	$bedittip = $this->Lang('tip_admintype','%s');
	$iconbedit = '<img src="'.$baseurl.'/images/booking-edit.png" alt="%s" title="%s" border="0" />';
}
$exporttip = $this->Lang('tip_exportbooktype','%s');
$iconexport = $theme->DisplayImage('icons/system/export.gif','%s','','','systemicon');
$seetip = $this->Lang('tip_viewtype','%s');
$iconsee = $theme->DisplayImage('icons/system/view.gif','%s','','','systemicon');
if ($mod || $pper) {
	$edittip= $this->Lang('tip_edittype','%s');
	$iconedit = $theme->DisplayImage('icons/system/edit.gif','%s','','','systemicon');
}
if ($padd) {
	$copytip = $this->Lang('tip_copytype','%s');
	$iconcopy = $theme->DisplayImage('icons/system/copy.gif','%s','','','systemicon');
}
if ($bmod || $pdel || $pper) {
	$deltip = $this->Lang('tip_deletetype','%s');
	$icondel = $theme->DisplayImage('icons/system/delete.gif','%s','','','systemicon');
}
$seltip = $this->Lang('tip_selecttype','%s');

if (isset($params['message']))
	$tplvars['message'] = $params['message'];

if (isset($params['active_tab']))
	$showtab = $params['active_tab'];
else
	$showtab = 'data'; //default
$seetab1 = ($showtab=='people');
$seetab2 = ($showtab=='items');
$seetab3 = ($showtab=='groups');
$seetab4 = ($showtab=='reports');
$seetab5 = ($showtab=='settings');

$tplvars['hidden'] = NULL; //$this->CreateInputHidden($id,'active_tab',$showtab);

$tplvars['tab_headers'] = $this->StartTabHeaders().
	$this->SetTabHeader('data',$this->Lang('title_bookings')).
	$this->SetTabHeader('people',$this->Lang('title_bookers'),$seetab1).
	$this->SetTabHeader('items',$this->Lang('title_items'),$seetab2).
	$this->SetTabHeader('groups',$this->Lang('title_groups'),$seetab3).
	$this->SetTabHeader('reports',$this->Lang('reports'),$seetab4).
	$this->SetTabHeader('settings',$this->Lang('settings'),$seetab5).
	$this->EndTabHeaders().
	$this->StartTabContent();
$tplvars['tab_footers'] = $this->EndTabContent();
$tplvars['end_tab'] = $this->EndTab();
$tplvars['endform'] = $this->CreateFormEnd();

$utils = new Booker\Utils();
$resume = json_encode(array($params['action'])); //head of resumption Q
$jsfuncs = array(); //script accumulators
$jsloads = array();
$jsincs = array();
//modal overlay
$tplvars['yes'] = $yes;
$tplvars['no'] = $no;
$tplvars['proceed'] = $this->Lang('proceed');
$tplvars['abort'] = $this->Lang('cancel');

$jsincs[] =<<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.modalconfirm.min.js"></script>
EOS;
$tablerows = array();

//BOOKINGS TAB (& FORM)
$tplvars['startform1'] = $this->CreateFormStart($id,'processrequest',$returnid,
	'POST','','','',array('active_tab'=>'data','resume'=>$resume,'custmsg'=>''));
$tplvars['start_data_tab'] = $this->StartTab('data');

$tablerows[1] = 0;
$pending = array();
$sql = 'SELECT H.*,B.name,B.publicid,B.address,B.phone FROM '.$this->HistoryTable.
' H LEFT JOIN '.$this->BookerTable.' B ON H.booker_id = B.booker_id WHERE status<='.
Booker::STATMAXREQ.' OR status>'.Booker::STATMAXOK.' ORDER BY lodged';
$data = $db->GetArray($sql);
if ($data) {
	$t = $this->Lang('request');
	$rtip = $this->Lang('tip_seereq');
	$iconrsee = '<img src="'.$baseurl.'/images/request.png" alt="'.$rtip.'" title="'.$rtip.'" border="0" />';
	$rtip = $this->Lang('tip_notifylodger');
	$icontell = '<img src="'.$baseurl.'/images/notice.png" alt="'.$rtip.'" title="'.$rtip.'" border="0" />';
	if ($bmod) {
		$rtip = $this->Lang('tip_edittype',$t);
		$iconredit = '<img src="'.$baseurl.'/images/request-edit.png" alt="'.$rtip.'" title="'.$rtip.'" border="0" />';
		$rtip = $this->Lang('tip_deletetype',$t);
		$iconrdel = $theme->DisplayImage('icons/system/delete.gif',$rtip,'','','systemicon');
		$iconryes = $theme->DisplayImage('icons/system/true.gif',$this->Lang('tip_approve'),'','','systemicon');
		$iconrno = $theme->DisplayImage('icons/system/false.gif',$this->Lang('tip_reject'),'','','systemicon');
	}
	$rtip = $this->Lang('tip_selecttype',$t);
	$fmt = 'j M Y G:i'; //specific format, not the 'public' (frontend) format, to restrict table width
	$tz = new DateTimeZone('UTC');
	$dt = new DateTime('@0',$tz);
	$funcs = new Booker\Status();
	$statnames = $funcs->GetStatusChoices($this,5); //1|4

	foreach ($data as &$row) {
		$one = new stdClass();
		$one->sender = $row['publicid'] ? $row['publicid']:$row['name'];
		$one->contact = $row['address'] ? $row['address']:$row['phone'];
		$dt->setTimestamp($row['lodged']);
		$one->lodged = $dt->format($fmt);
		$t = $utils->GetItemNameForID($this,$row['item_id']);
		if ($row['item_id'] >= Booker::MINGRPID) {
			if ($row['subgrpcount'])
				$t .= ','.$row['subgrpcount'];
		}
		$one->name = $t;
		$dt->setTimestamp($row['slotstart']);
		$one->start = $dt->format($fmt);
		$t = (int)$row['status'];
		$one->status = array_search($t,$statnames);
		if ($one->status === FALSE) {
			$one->status = $t;
		}
		switch ($row['payment']) {
		 case Booker::STATFREE:
 			$one->paid = NULL;
			break;
		 case Booker::STATPAID:
			$one->paid = $iconyes;
			break;
		 case Booker::STATCREDITED:
		 case Booker::STATPAYABLE:
		 case Booker::STATNOTPAID:
		 default:
			$one->paid = $iconno;
			break;
		}
		$t = $row['comment'];
		if ($t && strlen($t) > 8)
			$t = substr($t,0,8).'...';
		$one->comment = $t;
		$hid = (int)$row['history_id'];
		$one->see = $this->CreateLink($id,'processrequest',$returnid,$iconrsee,array('history_id'=>$hid,'task'=>'see'));
		if ($bmod) {
			$one->edit = $this->CreateLink($id,'processrequest',$returnid,$iconredit,array('history_id'=>$hid,'task'=>'edit'));
			if (1) { //TODO if e.g. not an info-request
				if (empty($row['approved'])) {
					$one->approve = $this->CreateLink($id,'processrequest',$returnid,$iconryes,array('history_id'=>$hid,'task'=>'approve'));
					$one->reject = $this->CreateLink($id,'processrequest',$returnid,$iconrno,array('history_id'=>$hid,'task'=>'reject'));
				} else {
					$one->approve = ''; //$yes;
					$one->reject = $this->CreateLink($id,'processrequest',$returnid,$iconrno,array('history_id'=>$hid,'task'=>'reject')); //TODO 'tip_reject2'
				}
			} else {
				$one->approve = '';
				$one->reject = '';
			}
		}
		if ($tell)
			$one->notice = $this->CreateLink($id,'processrequest',$returnid,$icontell,array('history_id'=>$hid,'task'=>'ask'));
		if ($pdel)
			$one->delete = $this->CreateLink($id,'processrequest',$returnid,$iconrdel,array('history_id'=>$hid,'task'=>'delete'));
		$one->sel = $this->CreateInputCheckbox($id,'selreq[]',$hid,-1,'title="'.$rtip.'"');
		$pending[] = $one;
	}
	unset($row);
}

if ($pending) {
	$tplvars['title_pending'] = $this->Lang('pending');
	$dcount = count($data);
	$tablerows[1] = $dcount;
	$tplvars['dcount'] = $dcount;
	if ($dcount > 1) {
		$tplvars['selectall_req'] = $this->CreateInputCheckbox($id,'req',TRUE,FALSE,'title="'.$this->Lang('selectall').'" onclick="select_all_req(this)"');
		$jsfuncs[] = <<<EOS
function select_all_req(b) {
 var st = $(b).attr('checked');
 if (!st) { st = false; }
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

	if ($tell) {
		$tplvars['modaltitle'] = $this->Lang('title_feedback2');
		$tplvars['customentry'] = $this->CreateInputText($id,'customentry','',20,30);
		$tplvars['prompttitle'] = $this->Lang('title_prompt');

		$what = '{'.$this->Lang('item').'}';
		$on = '{'.$this->Lang('date').'}';
		$detail = $this->Lang('whatovrday',$what,$on);
		$approve = $this->Lang('email_approve',$detail);
		$reject = $this->Lang('email_reject',$detail);
		$ask = $this->Lang('email_ask',$detail);

		$jsfuncs[] =<<<EOS
function modalsetup(tg,\$d) {
 var msg,action,id = $(this).attr('id');
 if (id) {
  action = id.replace('{$id}','');
 } else {
  var m = $(this).attr('href').match(/^.+{$id}task=(\w+).*$/);
  if (m) {
   action = m[1];
  } else {
   action = 'unknown';
  }
 }
 switch (action) {
  case 'approve':
   msg = "$approve";
   break;
  case 'reject':
   msg = "$reject";
   break;
  case 'ask':
   msg = "$ask";
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
		$jsloads[] =<<<EOS
 $('#datatable .bkrtell > a').modalconfirm({
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
		if ($bmod) {
			$jsloads[] =<<<EOS
 $('#datatable .bkrapp > a').modalconfirm({
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
 $('#datatable .bkrrej > a').modalconfirm({
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
	} else { //Notifier module N/A
		if ($bmod) {
			$jsloads[] =<<<EOS
 $('#datatable .bkrapp > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });
 $('#{$id}approve').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_reqcount,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });
EOS;
			$jsloads[] =<<<EOS
 $('#datatable .bkrrej > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });
 $('#{$id}reject').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_reqcount,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('reminder')}';
  }
 });
EOS;
		} //endif $bmod
	} //endif Notifier module N/A

	if ($tell) {
		$tplvars['askbtn'] = $this->CreateInputSubmit($id,'ask',
			$this->Lang('ask'),'title="'.$this->Lang('tip_ask_selected_requests').'"');
	}
	if ($bmod) {
		$tplvars['approvbtn'] = $this->CreateInputSubmit($id,'approve',
			$this->Lang('approve'),'title="'.$this->Lang('tip_approve_sel').'"');
		$tplvars['rejectbtn'] = $this->CreateInputSubmit($id,'reject',
			$this->Lang('reject'),'title="'.$this->Lang('tip_reject_sel').'"');
		$tplvars['deletebtn1'] = $this->CreateInputSubmit($id,'delete',
			$this->Lang('delete'),'title="'.$this->Lang('tip_delseltype',$this->Lang('request_multi')).'"');

		$t = $this->Lang('confirm_delete_type',$this->Lang('request'),'%s');
		$jsloads[] =<<<EOS
 $('#datatable .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d) {
   var n = $(this.parentNode).siblings(':first').text(),
    t = "{$t}",
    m = t.replace('%s',n),
    para = \$d.children('p:first')[0];
   para.innerHTML = m;
  }
 });
 $('#dataacts #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_reqcount,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('request_multi'))}';
  }
 });
EOS;
	}
	//titles
	$tplvars += array(
		'pending' => $pending,
		'title_lodger' => $this->Lang('title_lodger'),
		'title_contact' => $this->Lang('title_contact'),
		'title_lodged' => $this->Lang('lodged'),
		'title_status' => $this->Lang('status'),
		'title_paid' => $this->Lang('title_paid'),
		'title_name' => $this->Lang('title_name'),
		'title_start' => $this->Lang('start'),
		'title_comment' => $this->Lang('title_comment')
	);
} else {
	$tplvars['dcount'] = 0;
	$tplvars['nodata'] = $this->Lang('nonew');
}
if ($bmod)
	$tplvars['importbtn1'] = $this->CreateInputSubmit($id,'importbkg',$this->Lang('import'),
		'title="'.$this->Lang('tip_importbkg').'"');
$tplvars['findbtn'] = $this->CreateInputSubmit($id,'find',$this->Lang('find'),
	'title="'.$this->Lang('tip_findbkg').'"');
$tplvars['bexportbtn1'] = $this->CreateInputSubmit($id,'exportbkg',$this->Lang('exportbook'),
	'title="'.$this->Lang('tip_exportbookseltype',$this->Lang('request_multi')).'" onclick="return confirm_reqcount();"');
$tplvars['title_total'] = $this->Lang('title_bookings');
$tplvars['title_first'] = $this->Lang('first');
$tplvars['title_last'] = $this->Lang('last');
$tplvars['title_future'] = $this->Lang('future');

//BOOKERS TAB (& FORM)
$tplvars['startform2'] = $this->CreateFormStart($id,'adminbooker',$returnid,
	'POST','','','',array('active_tab'=>'people','resume'=>$resume,'custmsg'=>''));
$tplvars['start_people_tab'] = $this->StartTab('people');
$tablerows[2] = 0;

$histdata = FALSE;
$bkrs = array();
$sql = 'SELECT booker_id,name,publicid,passhash,addwhen,active FROM '.$this->BookerTable.' ORDER BY name';
$data = $db->GetArray($sql);
if ($data) {
	$sb = $this->Lang('booker');
	$st = $utils->GetZoneTime('UTC'); //'now' timestamp with same zone as booking data
	$dt = new DateTime('@0',NULL);
	$sql = 'SELECT booker_id AS B,slotstart AS S,item_id AS I FROM '.$this->HistoryTable.' ORDER BY booker_id,slotstart';
	$histdata = $db->GetArray($sql);
	$t = sprintf($bseetip,$this->Lang('recorded'));
	$icon1 = sprintf($iconbsee,$t,$t);
	if ($bmod) {
		$t = sprintf($baddtip,$this->Lang('user'));
		$icon2 = sprintf($iconbadd,$t,$t);
		$t = sprintf($bedittip,$this->Lang('recorded'));
		$icon3 = sprintf($iconbedit,$t,$t);
	}
	$t = sprintf($exporttip,$sb);
	$icon4 = sprintf($iconexport,$t,$t);
	$t = sprintf($seetip,$sb);
	$icon5 = sprintf($iconsee,$t,$t);
	if ($pper) {
		$t = sprintf($edittip,$sb);
		$icon6 = sprintf($iconedit,$t,$t);
		$t = sprintf($deltip,$sb);
		$icon7 = sprintf($icondel,$t,$t);
	}
	foreach ($data as $row) {
		$bookerid = (int)$row['booker_id'];
		$one = new stdClass();
		$one->name = ($pper) ?
			$this->CreateLink($id,'adminbooker',$returnid,$row['name'],array('booker_id'=>$bookerid,'task'=>'edit')):
			$row['name'];
		$one->reg = ($row['publicid'] && $row['passhash']) ? $iconyes : $iconno;
		if ($pper) {
			$one->act = ($row['active']) ?
				$this->CreateLink($id,'adminbooker',$returnid,$iconyes,
					array('booker_id'=>$bookerid,'task'=>'toggle','active'=>TRUE)):
				$this->CreateLink($id,'adminbooker',$returnid,$iconno,
					array('booker_id'=>$bookerid,'task'=>'toggle','active'=>FALSE));
		} else {
			$one->act = ($row['active']) ? $iconyes : $iconno;
		}
		$dt->setTimestamp($row['addwhen']);
		$one->added = $dt->format('Y-m-d'); //sortable format
		if ($histdata) {
			$belongs = array_filter($histdata,function($v)use($bookerid){return $v['B'] == $bookerid;});
			if ($belongs) {
				$count = count($belongs);
				$v = reset($belongs);
				$dt->setTimestamp($v['S']);
				$first = $dt->format('Y-m-d');
				$i = 0;
				foreach ($belongs as $v) {
					if ($v['S'] <= $st)
						$i++;
					else
						break;
				}
				$future = $count - $i;
				$v = end($belongs);
				$dt->setTimestamp($v['S']);
				$last = $dt->format('Y-m-d');
			}
		} else {
			$belongs = FALSE;
		}
		if (!$belongs) {
			$count = 0;
			$first = '';
			$last = '';
			$future = '';
		}
		$one->total = $count;
		$one->first = $first;
		$one->last = $last;
		$one->future = $future;

		$one->bsee = ($count) ?
			$this->CreateLink($id,'bookerbookings',$returnid,$icon1,array('booker_id'=>$bookerid,'task'=>'see')):
			NULL;
		if ($bmod) {
			$t = ($count) ? $icon3 : $icon2;
			$one->bedit = $this->CreateLink($id,'bookerbookings',$returnid,$t,array('booker_id'=>$bookerid,'task'=>'edit'));
		} else {
			$one->bedit = NULL;
		}
		$one->export = ($count) ?
			$this->CreateLink($id,'adminbooker',$returnid,$icon4,array('booker_id'=>$bookerid,'task'=>'export')):
			NULL;
		$one->see = $this->CreateLink($id,'adminbooker',$returnid,$icon5,array('booker_id'=>$bookerid,'task'=>'see'));
		if ($pper) {
			$one->edit = $this->CreateLink($id,'adminbooker',$returnid,$icon6,array('booker_id'=>$bookerid,'task'=>'edit'));
			$one->delete = $this->CreateLink($id,'adminbooker',$returnid,$icon7,array('booker_id'=>$bookerid,'task'=>'delete'));
		} else {
			$one->bedit = NULL;
			$one->edit = NULL;
			$one->delete = NULL;
		}
		$t = sprintf($seltip,$sb);
		$one->sel = $this->CreateInputCheckbox($id,'selbkr[]',$bookerid,-1,'title="'.$t.'"');
		$bkrs[] = $one;
	}
} //$data
$pcount = count($bkrs);
$tablerows[2] = $pcount;
$tplvars['pcount'] = $pcount;

/*if($padd)
	$tplvars['addbooking'] = $this->CreateLink($id,'processrequest',$returnid,
		 $theme->DisplayImage('icons/system/newobject.gif',$this->Lang('addbooking'),'','','systemicon'),
		 array('history_id'=>-1,'task'=>'add'),'',FALSE,FALSE,'')
	 .' '.$this->CreateLink($id,'processrequest',$returnid,
		 $this->Lang('addbooking'),
		 array('history_id'=>-1,'task'=>'add'),'',FALSE,FALSE,'class="pageoptions"');
*/
if ($pcount > 0) {
	$tplvars += array(
	 'bookers' => $bkrs,
	 'title_person' => $this->Lang('title_name'),
	 'title_reg' => $this->Lang('registered'),
	 'title_active' => $this->Lang('title_active'),
	 'title_added' => $this->Lang('title_commenced'),
	);
	if ($pcount > 1) {
		$tplvars['selectall_bookers'] = $this->CreateInputCheckbox($id,'booker',TRUE,FALSE,'title="'.$this->Lang('selectall').'" onclick="select_all_bkr(this)"');
		$jsfuncs[] = <<<EOS
function select_all_bkr(b) {
 var st = $(b).attr('checked');
 if (!st) { st = false; }
 $('input[name="{$id}selbkr[]"][type="checkbox"]').attr('checked',st);
}
EOS;
	} // $pcount > 1

	$jsfuncs[] =<<<EOS
function confirm_bkrcount() {
 var cb = $('input[name="{$id}selbkr[]"]:checked');
 return (cb.length > 0);
}
EOS;
	if($padd) {
		$tplvars['ablebtn2'] =
			$this->CreateInputSubmit($id,'activate',$this->Lang('activate'),
			'title="'.$this->Lang('activatesel',$this->Lang('booker_multi')).'" onclick="return confirm_bkrcount();"');
		$tplvars['deletebtn2'] = $this->CreateInputSubmit($id,'delete',
			$this->Lang('delete'),'title="'.$this->Lang('tip_delseltype',$this->Lang('booker_multi')).'"');

		$t = $this->Lang('confirm_delete_type',$this->Lang('booker'),'%s');
		$jsloads[] =<<<EOS
 $('#peopletable .bkrdel > a').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  preShow: function(tg,\$d) {
   var n = $(this.parentNode).siblings(':first').text(),
    t = "{$t}",
    m = t.replace('%s',n),
    para = \$d.children('p:first')[0];
   para.innerHTML = m;
  }
 });
 $('#peopleacts #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_bkrcount,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('booker_multi'))}';
  }
 });
EOS;
	} //$pper
	$tplvars['exportbtn2'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('tip_exportseltype',$this->Lang('booker_multi')).'" onclick="return confirm_bkrcount();"');
	$tplvars['bexportbtn2'] = $this->CreateInputSubmit($id,'exportbkg',$this->Lang('exportbook'),
		'title="'.$this->Lang('tip_exportbookseltype',$this->Lang('booker_multi')).'" onclick="return confirm_bkrcount();"');
} else { //$pcount == 0
	$tplvars['nobookers'] = $this->Lang('nobooker');
}
if ($pper) {
	$tplvars['addbooker'] = $this->CreateLink($id,'adminbooker',$returnid,
		 $theme->DisplayImage('icons/system/newobject.gif',$this->Lang('addbooker'),'','','systemicon'),
		 array('booker_id'=>-1,'task'=>'add'),'',FALSE,FALSE,'')
	 .' '.$this->CreateLink($id,'adminbooker',$returnid,
		 $this->Lang('addbooker'),
		 array('booker_id'=>-1,'task'=>'add'),'',FALSE,FALSE,'class="pageoptions"');
	$tplvars['importbtn2'] = $this->CreateInputSubmit($id,'importbkr',$this->Lang('import'),
		'title="'.$this->Lang('tip_importbkr').'"');
}

$items = array();
$icount = 0;
$groups = array();
$gcount	= 0;

$relations = $db->GetAssoc('SELECT * FROM '.$this->GroupTable.' ORDER BY child,proximity');
if ($relations)
	$relkeys = array_keys($relations);
$memcounts = $db->GetAssoc('SELECT parent,COUNT(gid) AS num FROM '.$this->GroupTable.' GROUP BY parent');
$grpnames = $db->GetAssoc('SELECT item_id,name FROM '.$this->ItemTable.' WHERE item_id>='.Booker::MINGRPID.' ORDER BY item_id');
$owned = $db->GetOne('SELECT FIRST(item_id) AS own FROM '.$this->ItemTable.' WHERE owner > 0'); //something has an owner

$sql =<<<EOS
SELECT I.item_id,I.alias,I.name,I.owner,I.active,U.first_name,U.last_name
FROM $this->ItemTable I
LEFT JOIN $this->UserTable U ON I.owner = U.user_id
ORDER BY I.name
EOS;
//TODO $utils->SafeExec()
$data = $db->GetArray($sql);
if ($data) {
	$uid = ($owned) ? get_userid(FALSE) : 0; //current user
	foreach ($data as $row) {
/*$data = array
 0 => array
'item_id' => string '10001'
'alias' => string 'allcourts'
'name' => string 'All courts'
'owner' => null
'active' => string '1'
'first_name' => null
'last_name' => null
*/
		//omit some choices when editing, but current user hasn't admin permission and doesn't own the item
		$skip = $owned && $mod && !$padm && $row['owner'] > 0 && $row['owner'] != $uid;
		$item_id	= (int)$row['item_id'];
		$isitem = ($item_id < Booker::MINGRPID && $item_id != -Booker::MINGRPID);

		$one = new stdClass();
		//TODO make this sortable
		if ($mod)
			$one->name = $this->CreateLink($id,'processitem',$returnid,
				strip_tags($row['name']),
				array('item_id'=>$item_id,'task'=>'edit'));
		else
			$one->name	= strip_tags($row['name']);
		//for group-name lookups
		$gotnames[$item_id] = $one->name;

		if ($pdev) {
			if ($row['alias'])
				$one->tag = '\''.$row['alias'].'\'';
			else
				$one->tag = $item_id;
		}

		$one->group = '';
		if ($relations) {
			foreach ($relations as $k=>$gdata) {
				if ($gdata['child'] == $item_id) {
					$p = (int)$gdata['parent'];
					if (isset($grpnames[$p]))
						$one->group = $grpnames[$p];
					else
						$one->group = '<'.$this->Lang('noname').'>';
					$p = array_search($k,$relkeys) + 1;
					if (isset($relkeys[$p]) && $relations[$relkeys[$p]]['child'] == $item_id)
						$one->group .= ' +';
					break;
				}
			}
		}

		if ($owned) {
			if ($row['owner']) {
				$name = trim($row['first_name'].' '.$row['last_name']);
				if ($name == '') $name = '<'.$this->Lang('noowner').'>';
				$one->ownername = $name;
			} else
				$one->ownername = '';
		}
		if ($histdata) {
			//TODO CHECK ok to ignore group bookings? i.e. HistoryTable has group-derived entries ?
			$belongs = array_filter($histdata,function($v)use($item_id){return $v['I'] == $item_id;});
			if ($belongs) {
				$count = count($belongs);
//TODO array ordered by booker_id,slotstart, not item_id, not worth resorting?
				$future = 0;
				$min = PHP_INT_MAX;
				$max = ~PHP_INT_MAX;
				foreach ($belongs as $v) {
					$t = $v['S'];
					if ($t >= $st)
						$future++;
					if ($t > $max)
						$max = $t;
					if ($t < $min)
						$min = $t;
				}
				$dt->setTimestamp($min);
				$first = $dt->format('Y-m-d');
				$dt->setTimestamp($max);
				$last = $dt->format('Y-m-d');
			}
		} else {
			$belongs = FALSE;
		}

		if ($belongs) {
			$t = sprintf($bseetip,$si); //($isitem)?$si:$sg);
			$t = sprintf($iconbsee,$t,$t);
			$one->bsee = $this->CreateLink($id,'itembookings','',$t,array('item_id'=>$item_id,'task'=>'see'));

//			if ($isitem && $bmod && !$skip)
			if ($mod && !$skip) {
				$t = sprintf($bedittip,$si); //($isitem)?$si:$sg);
				$t = sprintf($iconbedit,$t,$t);
				$one->bedit = $this->CreateLink($id,'itembookings','',$t,array('item_id'=>$item_id,'task'=>'edit'));
			} else
				$one->bedit = '';

			$t = sprintf($exporttip,$si); //($isitem)?$si:$sg);
			$t = sprintf($iconexport,$t,$t);
			$one->export = $this->CreateLink($id,'processitem',$returnid,$t,array('item_id'=>$item_id,'task'=>'export'));
		} else {
			$count = 0;
			$first = '';
			$last = '';
			$future = '';

			$one->bsee = '';
			if ($mod && !$skip) {
				$t = sprintf($baddtip,($isitem)?$si:$sg);
				$t = sprintf($iconbadd,$t,$t);
				$one->bedit = $this->CreateLink($id,'itembookings','',$t,array('item_id'=>$item_id,'task'=>'edit'));
			} else
				$one->bedit = '';
			$one->export = '';
		}

		$one->total = $count;
		$one->first = $first;
		$one->last = $last;
		$one->future = $future;

		$t = sprintf($seetip,($isitem)?$si:$sg);
		$t = sprintf($iconsee,$t,$t);
		$one->see = $this->CreateLink($id,'processitem',$returnid,$t,array('item_id'=>$item_id,'task'=>'see'));

		if ($mod && !$skip) {
			if ($row['active'] > 0)
				$one->active = $this->CreateLink($id,'processitem',$returnid,$iconyes,
					array('item_id'=>$item_id,'task'=>'toggle','active'=>TRUE));
			elseif ($row['active'] == 0) //it's inactive so create an activate-link
				$one->active = $this->CreateLink($id,'processitem',$returnid,$iconno,
					array('item_id'=>$item_id,'task'=>'toggle','active'=>FALSE));
			else
				$one->active = ''; //fake-deleted

			$t = sprintf($edittip,($isitem)?$si:$sg);
			$t = sprintf($iconedit,$t,$t);
			$one->edit = $this->CreateLink($id,'processitem',$returnid,$t,array('item_id'=>$item_id,'task'=>'edit'));
		} else {
			if ($row['active'] > 0)
				$one->active = $yes;
			elseif ($row['active'] == 0)
				$one->active = $no;
			else
				$one->active = '';
			$one->edit = '';
		}

		if ($padd) {
			$t = sprintf($copytip,($isitem)?$si:$sg);
			$t = sprintf($iconcopy,$t,$t);
			$one->copy = $this->CreateLink($id,'processitem',$returnid,$t,array('item_id'=>$item_id,'task'=>'copy'));
		} else {
			$one->copy = '';
		}

		if ($pdel && !$skip) {
			$s = ($isitem)?$si:$sg;
			$t = sprintf($deltip,$s);
			$t = sprintf($icondel,$t,$t);
			$one->delete = $this->CreateLink($id,'processitem',$returnid,$t,array('item_id'=>$item_id,'task'=>'delete'));
		} else {
			$one->delete = '';
		}

		$t = sprintf($seltip,($isitem)?$si:$sg);
		if ($isitem) {
			$one->sel = $this->CreateInputCheckbox($id,'selitm[]',$item_id,-1,'title="'.$t.'"');
			$items[] = $one;
			$icount++;
		} else {
			if (!empty($memcounts[$item_id]))
				$one->count = (int)$memcounts[$item_id];
			else
				$one->count = 0;
			$one->sel = $this->CreateInputCheckbox($id,'selgrp[]',$item_id,-1,'title="'.$t.'"');
			$groups[] = $one;
			$gcount++;
		}
	}
} //data
if ($icount > 0 || $gcount > 0) {
	$tplvars['own'] = $owned;
	if ($pdev)
		$tplvars['title_tag'] = $this->Lang('title_pagetag');
	$tplvars['title_grp'] = $this->Lang('title_groups');
	$tplvars['title_owner'] = $this->Lang('title_owner');
	$tplvars['title_active'] = $this->Lang('title_active');
}

//RESOURCES TAB
$tplvars['startform3'] = $this->CreateFormStart($id,'processitem',$returnid,
	'POST','','','',array('active_tab'=>'items','resume'=>$resume));
$tplvars['start_items_tab'] = $this->StartTab('items');

$tablerows[3] = $icount;
$tplvars['icount'] = $icount;
if ($icount > 0) {
	$tplvars['items'] = $items;
	$tplvars['inametext'] = $this->Lang('title_name');
	if ($icount > 1)
		$tplvars['selectall_items'] =
			$this->CreateInputCheckbox($id,'item',TRUE,FALSE,'title="'.$this->Lang('selectall').'" onclick="select_all_itm(this)"');
	$tplvars['exportbtn3'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('tip_exportseltype',$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"');
	$tplvars['bexportbtn3'] = $this->CreateInputSubmit($id,'exportbkg',$this->Lang('exportbook'),
		'title="'.$this->Lang('tip_exportbookseltype',$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"');
	$t = ($mod) ? 'update':'inspect';
	$t = $this->Lang($t);
	$t = mb_convert_case($t,MB_CASE_LOWER);
	$tplvars['feebtn3'] = $this->CreateInputSubmit($id,'setfees',$this->Lang('title_fees'),
		'title="'.$this->Lang('feesel',$t,$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"');
	if ($mod) {
		if ($icount > 1)
			$tplvars['sortbtn3'] = $this->CreateInputSubmit($id,'sort',$this->Lang('sort'),
				'title="'.$this->Lang('tip_sorttype',$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"');
		$tplvars['ablebtn3'] = $this->CreateInputSubmit($id,'activate',$this->Lang('activate'),
			'title="'.$this->Lang('activatesel',$this->Lang('item_multi')).'" onclick="return confirm_itmcount();"');
	}
	if ($pdel)
		$tplvars['deletebtn3'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$this->Lang('tip_delseltype',$this->Lang('item_multi')).'"');
	//related js
	$jsfuncs[] = <<<EOS
function select_all_itm(b) {
 var st = $(b).attr('checked');
 if (!st) { st = false; }
 $('input[name="{$id}selitm[]"][type="checkbox"]').attr('checked',st);
}
function confirm_itmcount() {
 var cb = $('input[name="{$id}selitm[]"]:checked');
 return (cb.length > 0);
}
EOS;
	if ($pdel) {
		$t = $this->Lang('confirm_delete_type',$this->Lang('item'),'%s');
		$jsloads[] = <<<EOS
 $('#itemstable .bkrdel > a').modalconfirm({
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
 $('#itemacts #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_itmcount,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('item_multi'))}';
  }
 });
EOS;
	}
} else
	$tplvars['noitems'] = $this->Lang('noitems');

if ($padd) {
	$tplvars['additem'] = $this->CreateLink($id,'processitem',$returnid,
		 $theme->DisplayImage('icons/system/newobject.gif',$this->Lang('additem'),'','','systemicon'),
		 array('item_id'=>-1,'task'=>'add'),'',FALSE,FALSE,'')
	 .' '.$this->CreateLink($id,'processitem',$returnid,
		 $this->Lang('additem'),
		 array('item_id'=>-1,'task'=>'add'),'',FALSE,FALSE,'class="pageoptions"');

	$tplvars['importbtn3'] = $this->CreateInputSubmit($id,'importitm',$this->Lang('import'),
		'title="'.$this->Lang('tip_importitm').'"');
	$tplvars['fimportbtn3'] = $this->CreateInputSubmit($id,'importfee',$this->Lang('import_fees'),
		'title="'.$this->Lang('tip_importfee').'"');
}

//GROUPS TAB
$tplvars['start_grps_tab'] = $this->StartTab('groups');
$tplvars['startform4'] = $this->CreateFormStart($id,'processitem',$returnid,
	'POST','','','',array('active_tab'=>'groups','resume'=>$resume));

$tablerows[4] = $gcount;
$tplvars['gcount'] = $gcount;
if ($gcount > 0) {
	$tplvars['groups'] = $groups;
	$tplvars['title_gname'] = $this->Lang('title_name');
	$tplvars['title_gcount'] = $this->Lang('title_gcount');
	if ($gcount > 1)
		$tplvars['selectall_grps'] =
			$this->CreateInputCheckbox($id,'group',TRUE,FALSE,'title="'.$this->Lang('selectall').'" onclick="select_all_grp(this)"');
	$tplvars['exportbtn4'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'),
		'title="'.$this->Lang('tip_exportseltype',$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"');
	$tplvars['bexportbtn4'] = $this->CreateInputSubmit($id,'exportbkg',$this->Lang('exportbook'),
		'title="'.$this->Lang('tip_exportbookseltype',$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"');
	$t = ($mod) ? 'update':'inspect';
	$t = $this->Lang($t);
	$t = mb_convert_case($t,MB_CASE_LOWER);
	$tplvars['feebtn4'] = $this->CreateInputSubmit($id,'setfees',$this->Lang('title_fees'),
		'title="'.$this->Lang('feesel',$t,$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"');
	if ($mod) {
		if ($gcount > 1)
			$tplvars['sortbtn4'] = $this->CreateInputSubmit($id,'sort',$this->Lang('sort'),
				'title="'.$this->Lang('tip_sorttype',$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"');
		$tplvars['ablebtn4'] =
			$this->CreateInputSubmit($id,'activate',$this->Lang('activate'),
			'title="'.$this->Lang('activatesel',$this->Lang('group_multi')).'" onclick="return confirm_grpcount();"');
	}
	if ($pdel)
		$tplvars['deletebtn4'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$this->Lang('tip_delseltype',$this->Lang('group_multi')).'"');
	//related js
	$t = $this->Lang('confirm_delete_type',$this->Lang('group'),'%s');
	$jsfuncs[] = <<<EOS
function select_all_grp(b) {
 var st = $(b).attr('checked');
 if (!st) { st = false; }
 $('input[name="{$id}selgrp[]"][type="checkbox"]').attr('checked',st);
}
function confirm_grpcount() {
 var cb = $('input[name="{$id}selgrp[]"]:checked');
 return (cb.length > 0);
}
EOS;
	if ($pdel) {
		$jsloads[] = <<<EOS
 $('#groupstable .bkrdel > a').modalconfirm({
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
 $('#groupacts #{$id}delete').modalconfirm({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  doCheck: confirm_grpcount,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = '{$this->Lang('delsel_confirm',$this->Lang('group_multi'))}';
  }
 });
EOS;
	}
} else
	$tplvars['nogroups'] = $this->Lang('nogroups');

if ($mod) {
	$tplvars['addgrp'] = $this->CreateLink($id,'processitem',$returnid,
		 $theme->DisplayImage('icons/system/newobject.gif',$this->Lang('addgroup'),'','','systemicon'),
		 array('item_id'=>-Booker::MINGRPID,'task'=>'add'),'',FALSE,FALSE,'')
	 .' '.$this->CreateLink($id,'processitem',$returnid,
		 $this->Lang('addgroup'),
		 array('item_id'=>-Booker::MINGRPID,'task'=>'add'),'',FALSE,FALSE,'class="pageoptions"');
	$tplvars['importbtn4'] = $tplvars['importbtn3'];
	$tplvars['fimportbtn4'] = $tplvars['fimportbtn3'];
}

//REPORTS TAB (&FORM)
$tplvars['startform5'] = $this->CreateFormStart($id,'processreport',$returnid,
	'POST','','','',array('active_tab'=>'reports','resume'=>$resume));
$tplvars['start_reports_tab'] = $this->StartTab('reports');

$cbbase = $this->CreateInputCheckbox($id,'base',1,-1);
$types = array('item','bkr','when');
//TODO LANG
$cells = array();
//column 0
//'title_period'
$column = array(
'','By Item','By Booker','By Interval'
);
$cells[] = $column;
//column 1
$column = array('Overview');
for ($t=0; $t<3; $t++) {
	$cb = str_replace(array('class="','base'),array('class="reportcheck ',$types[$t].'view'),$cbbase);
	$column[] = $cb;
}
$cells[] = $column;
//column 2
$column = array('Payments');
for ($t=0; $t<3; $t++) {
	$cb = str_replace(array('class="','base'),array('class="reportcheck ',$types[$t].'payment'),$cbbase);
	$column[] = $cb;
}
$cells[] = $column;
//column 3
$column = array($this->Lang('status'));
for ($t=0; $t<3; $t++) {
	$cb = str_replace(array('class="','base'),array('class="reportcheck ',$types[$t].'status'),$cbbase);
	$column[] = $cb;
}
$cells[] = $column;

$tplvars['reportcells'] = $cells;
$tplvars['reportrows'] = 4;
$tplvars['displaybtn'] = '[BTNDISPLAY]';
$tplvars['exportbtn5'] = '[BTNEXPORT]';

$jsloads[] =<<<EOS
 $('#reportstable').find('input.reportcheck').click(function(ev) {
  var \$b = $(this),
   st = \$b.attr('checked');
  if (st) {
   $('#reportstable').find('input.reportcheck').attr('checked',false);
   \$b.attr('checked',true);
  }
 });
EOS;

//SETTINGS TAB
$tplvars['startform6'] = $this->CreateFormStart($id,'setprefs',$returnid,
	'POST','','','',array('active_tab'=>'settings','resume'=>$resume));
$tplvars['start_settings_tab'] = $this->StartTab('settings');
if ($pset) {
	$settings = array();

	$one = new stdClass();
	$one->title = $this->Lang('title_sitepage');
	$one->input = $this->CreateInputText($id,'pref_sitepage',
		$this->GetPreference('pref_sitepage',''),25,25);
	$one->must = 1;
	$one->help = $this->Lang('help_sitepage');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_cleargroup');
	$one->input = $this->CreateInputCheckbox($id,'pref_cleargroup',TRUE,
		$this->GetPreference('pref_cleargroup',FALSE),'');
	$settings[] = $one;

	$sql = 'SELECT user_id,first_name,last_name FROM '.$this->UserTable.' WHERE active=1 ORDER BY last_name,first_name';
	$allusers = $db->GetAssoc($sql);
	if ($allusers) {
		$one = new stdClass();
		$one->title = $this->Lang('title_owner');
		foreach ($allusers as $k=>&$t) {
			$t = trim($t['first_name'].' '.$t['last_name']);
		}
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
	$one->title = $this->Lang('approvertell');
	$one->input = $this->CreateInputCheckbox($id,'pref_approvertell',TRUE,
		$this->GetPreference('pref_approvertell',FALSE),'');
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
		$this->Lang('resource+start')=>Booker::LISTRS,
		$this->Lang('user+resource')=>Booker::LISTUR,
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

	$ob = cms_utils::get_module('FrontEndUsers');
	if (is_object($ob)) {
		$allusers = $ob->GetGroupList(); //associative array with group names as keys,id's as values
		unset($ob);
		$rc = count($allusers);
		if ($rc)
			ksort($allusers,SORT_NATURAL | SORT_FLAG_CASE);
/* TODO filter by permissions c.f. for admin users
			$pref = cms_db_prefix();
			$sql =<<<EOS
SELECT DISTINCT U.user_id,U.username,U.first_name,U.last_name FROM $this->UserTable U
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
	$one->title = $this->Lang('title_feeusage');
	$one->input = $this->CreateInputText($id,'pref_fee',$this->GetPreference('pref_fee'),6,8);
	$one->help = $this->Lang('help_fee');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_grossfees');
	$one->input = $this->CreateInputCheckbox($id,'pref_grossfees',TRUE,
		$this->GetPreference('pref_grossfees',FALSE),'');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_taxrate');
	$one->input = $this->CreateInputText($id,'pref_taxrate',
		$this->GetPreference('pref_taxrate',0.0),4,8);
//	$one->must = 1;
	$one->help = $this->Lang('help_taxrate');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_feecondition');
	$one->input = $this->CreateTextArea(FALSE,$id,$this->GetPreference('pref_feecondition'),
		'pref_feecondition','','','','',40,3,'','','style="height:3em;"');
	$one->help = $this->Lang('help_feecondition');
	$settings[] = $one;

	$one = new stdClass();
	$one->title = $this->Lang('title_paymentiface');
	$choices = array();
	$allmodules = $this->GetModulesWithCapability('GatePayer');
	if ($allmodules) {
		foreach ($allmodules as $name) {
			$ob = cms_utils::get_module($name);
			if ($ob) {
				$n = $ob->GetFriendlyName();
				$choices[$n] = $name;
				unset ($ob);
			}
		}
		asort($choices);
	}
	$choices = array($this->Lang('none')=>'') + $choices;
	$one->input = $this->CreateInputDropdown($id,'pref_paymentiface',$choices,-1,
	$this->GetPreference('pref_paymentiface'));
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

	$one = new stdClass();
	$one->title = $this->Lang('bookertell');
	$one->input = $this->CreateInputCheckbox($id,'pref_bookertell',TRUE,
		$this->GetPreference('pref_bookertell',FALSE),'');
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
	$choices = $utils->GetTimeZones($this);
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

	if (ini_get('mbstring.internal_encoding') !== FALSE) { //PHP's encoding-conversion capability is installed
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
	if ($t)
		$one->input .= ' '.$this->CreateInputCheckbox($id,'stylesdelete',1,-1).'&nbsp;'.$this->Lang('delete_upload',$t);
	$one->help = $this->Lang('help_cssfile');
	$settings[] = $one;

	$tplvars['compulsory'] = $this->Lang('help_compulsory');
	$tplvars['settings'] = $settings;
	//buttons
	$tplvars['submitbtn4'] = $this->CreateInputSubmit($id,'submit',$this->Lang('apply'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
} else {
	$tplvars['nopermission'] = $this->Lang('accessdenied3');
}

//js
// TODO make page-rows count window-size-responsive
$pagerows = $this->GetPreference('pref_pagerows',10);
$tablevars = array(); //accumulator for relevant table-identifiers
$pagerdata = array(); //accumulator for relevant js-object properties
$include = FALSE;
foreach ($tablerows as $i=>$rc) {
	if ($rc > $pagerows) {
		//setup extra table-specific parameters
		$curpg = '<span id="cpage'.$i.'">1</span>';
		$totpg = '<span id="tpage'.$i.'">'.ceil($rc/$pagerows).'</span>';

		$choices = array((string)$pagerows => $pagerows);
		$f = ($pagerows < 4) ? 5 : 2;
		$n = $pagerows * $f;
		if ($n < $rc)
			$choices[(string)$n] = $n;
		$n *= 2;
		if ($n < $rc)
			$choices[(string)$n] = $n;
		$choices[$this->Lang('all')] = 0;

		$tplvars += array(
		 'hasnav'.$i => 1,
		 'first'.$i => '<a href="javascript:pagefirst(tbl'.$i.')">'.$this->Lang('first').'</a>',
		 'prev'.$i => '<a href="javascript:pageback(tbl'.$i.')">'.$this->Lang('previous').'</a>',
		 'next'.$i => '<a href="javascript:pageforw(tbl'.$i.')">'.$this->Lang('next').'</a>',
		 'last'.$i => '<a href="javascript:pagelast(tbl'.$i.')">'.$this->Lang('last').'</a>',
		 'pageof'.$i => $this->Lang('pageof',$curpg,$totpg),
		 'rowchanger'.$i => $this->CreateInputDropdown($id,'pagerows'.$i,$choices,-1,$pagerows,
			'onchange="pagerows(tbl'.$i.',this);"').'&nbsp;&nbsp;'.$this->Lang('pagerows')
		);
		$tablevars[] = 'tbl'.$i;
		$pagerdata[] = sprintf("{currentid:'cpage%d', countid:'tpage%d', paginate:true, pagesize:%d}",$i,$i,$pagerows);
	} elseif ($rc > 1) {
		$pagerdata[] = '{}';
	}
	$include |= ($rc > 0);
}

if ($include) {
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.SSsort.min.js"></script>
EOS;

$initbls = '';
if ($tablevars) {
	$havetbls = 'var '.implode(',',$tablevars).';';
	foreach ($tablevars as $one) {
		switch ($one) {
		 case 'tbl1':
			$initbls .= $one.' = $(\'#datatable\')[0];'.PHP_EOL;
			break;
		 case 'tbl2':
			$initbls .= $one.' = $(\'#peopletable\')[0];'.PHP_EOL;
			break;
		 case 'tbl3':
			$initbls .= $one.' = $(\'#itemstable\')[0];'.PHP_EOL;
			break;
		 case 'tbl4':
			$initbls .= $one.' = $(\'#groupstable\')[0];'.PHP_EOL;
			break;
		}
	}
} else {
	$havetbls = '';
}

if ($pagerdata) {
	$jsfuncs[] = <<<EOS
function pagefirst(tbl) {
 $.SSsort.movePage(tbl,false,true);
}
function pagelast(tbl) {
 $.SSsort.movePage(tbl,true,true);
}
function pageforw(tbl) {
 $.SSsort.movePage(tbl,true,false);
}
function pageback(tbl) {
 $.SSsort.movePage(tbl,false,false);
}
function pagerows(tbl,dd) {
 $.SSsort.setCurrent(tbl,'pagesize',parseInt(dd.value));
}
{$havetbls}
EOS;
	$extras = '['.PHP_EOL.implode(','.PHP_EOL,$pagerdata).PHP_EOL.']';
} else {
	$extras = 'null';
}

$jsloads[] = <<<EOS
 $.SSsort.addParser({
  id: 'icon',
  is: function(s,node) {
   var \$i = $(node).find('img');
   return \$i.length > 0;
  },
  format: function(s,node) {
   var \$i = $(node).find('img');
   return \$i[0].src;
  },
  watch: false,
  type: 'text'
 });
 {$initbls}
 var extras = {$extras};
 $('table.table_sort').each(function(idx) {
  var cfg = {
   sortClass: 'SortAble',
   ascClass: 'SortUp',
   descClass: 'SortDown',
   oddClass: 'row1',
   evenClass: 'row2',
   oddsortClass: 'row1s',
   evensortClass: 'row2s'
  };
  if (extras) {
    $.extend(cfg,extras[idx]);
  }
  $(this).SSsort(cfg);
 });
EOS;
}

//hacky js here to work around tab-specific forms, i.e. no single page-tab object
/*$jsloads[] = <<<EOS
 $('input[type="submit"]').click(function() {
  var active = $('#page_tabs > .active');
  $(this).closest('form').append("<input type='hidden' name='{$id}active_tab' value='"+
   active.attr('id')+"' />");
  return true;
 });
EOS;
*/

$jsall = NULL;
$utils->MergeJS($jsincs,$jsfuncs,$jsloads,$jsall);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'adminpanel.tpl',$tplvars);
//inject constructed js after other content (pity we can't get to </body> or </html> from here)
if ($jsall)
	echo $jsall;
