<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openrequest - view or edit a booking-request
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*first-time redirect, $params = array
'history_id'=>identifier
'task'=>'see' OR 'edit'
*/

if (!$this->_CheckAccess()) exit;

if (isset($params['cancel'])) {
	$this->Redirect($id,'defaultadmin');
}

$sql = 'SELECT H.*,B.name,B.publicid,B.address,B.phone FROM '.$this->HistoryTable.
	' H LEFT JOIN '.$this->BookerTable.' B ON H.booker_id=B.booker_id WHERE history_id=?';
$rdata = $db->GetRow($sql,array($params['history_id']));
$is_group = ($rdata['item_id'] >= Booker::MINGRPID);
$type = ($is_group) ? $this->Lang('group'):$this->Lang('item');
$is_new = ($rdata['status'] == Booker::STATNEW); //TODO ETC?
$viewmode = ($params['task'] == 'see');
$utils = new Booker\Utils();

$utils->DecodeParameters($params,array(
	'contact',
	'customentry',
	'name',
	'subgrpcount',
	'until',
	'when'
));

if (isset($params['submit']) || isset($params['apply'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	//validate
	$funcs = new Booker\Verify();
	list($res,$msg) = $funcs->VerifyData($this,$utils,$params,$rdata['item_id'],$is_new,TRUE);
	if ($res) {
		$funcs = new Booker\Requestops();
		$funcs->SaveReq($this,$utils,$params,FALSE);
		//TODO message to lodger
		if (isset($params['submit']))
			$this->Redirect($id,'defaultadmin');
	} else {
		//handle error message(s)
//		if (!empty($params['message'])
//
//		else
//			$params['message'] = ;
	}
} elseif (isset($params['approve'])) {
/*supplied $params[]
'history_id' => string '6'
'task' => string 'edit'
'when' => string '9 November 2015 11:00'
'until' => string '9 November 2015 11:59'
'name' => string 'HiThere'
'displayclass' => string '1'
'contact' => string '@myhandle'
'comment' => string 'asdad dfgdf dhdfg'
'custmsg' => string 'You must pay!'
*/
	//TODO as for apply
	//construct message params from
	//['custom'] etc
	//send
	//
}
if (isset($params['reject'])) {
} elseif (isset($params['ask'])) {
} elseif (isset($params['notify'])) {
}
if (isset($params['find'])) {
} elseif (isset($params['table'])) {
} elseif (isset($params['list'])) {
}

$tplvars = array();

$tplvars['startform'] = $this->CreateFormStart($id,'openrequest',$returnid,'POST','','','',
	array('history_id'=>$params['history_id'],'task'=>$params['task'],'custmsg'=>''));
$tplvars['endform'] = $this->CreateFormEnd();
/*
first-call $params = array
'history_id' => string '32'
'task' => string 'edit'
'action' => string 'openrequest'
*/
$resume = 'defaultadmin';
$params['active_tab'] = 'data';
$tplvars['pagenav'] = $this->_BuildNav($id,$returnid,$resume,$params);
if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

if (!$viewmode)
	$tplvars['compulsory'] = $this->Lang('help_compulsory');

$idata = $utils->GetItemProperty($this,$rdata['item_id'],"*");

$key = ($is_new) ? 'title_booknewfor':'title_bookfor';
if (!empty($idata['name'])) {
	$tplvars['title'] = $this->Lang($key,$type,$idata['name']);
} else {
	$t = $this->Lang('title_noname',$type,$idata['item_id']);
	$tplvars['title'] = $this->Lang($key,$t,'');
}

$t = '';
if (!empty($idata['description']))
	$t .= Booker\Utils::ProcessTemplateFromData($this,$idata['description'],$tplvars);
$tplvars['desc'] = $t;
//in this context, ignore any image

$baseurl = $this->GetModuleURLPath();
$tplvars['baseurl'] = $baseurl;
//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$choosend = ($idata['bookcount'] != 1);

$vars = array();

$dt = new DateTime('@'.$rdata['slotstart'],NULL);
$fmt = 'Y-n-j G:i';
$t = $dt->format($fmt);
if ($rdata['item_id'])
	$overday = ($utils->GetInterval($this,$rdata['item_id'],'slot') >= 84600);
else {
//TODO support 'un-targeted' bookings - preference?
	$overday = FALSE;
}

$one = new stdClass();
$one->title = $this->Lang('title_starting');
if ($viewmode) {
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
} else {
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'when',$t,20,30);

	$nextm = $this->Lang('nextm');
	$prevm = $this->Lang('prevm');
	//js wants quoted period-names
	$t = $this->Lang('longdays');
	$dnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('shortdays');
	$sdnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('longmonths');
	$mnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('shortmonths');
	$smnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('meridiem');
	$meridiem = "'".str_replace(",","','",$t)."'";
	$datetimefmt = $utils->DateTimeFormat(FALSE,TRUE,TRUE,!$overday);
	if ($choosend) {
		$sl = (int)$rdata['slotlen'];
		$t2 = <<<EOS
,
 onClose: function() {
  if ('_d' in this && this._d) {
   var ob = new Date(this._d.getTime() + {$sl}*1000);
   var dt = fmt.formatDate(ob,'{$datetimefmt}');
   $('#{$id}until').val(dt);
  }
 }

EOS;
	} else {
		$t2 = '';
	}

	$jsloads[] = <<<EOS
 var fmt = new DateFormatter({
  longDays: [{$dnames}],
  shortDays: [{$sdnames}],
  longMonths: [{$mnames}],
  shortMonths: [{$smnames}],
  meridiem: [{$meridiem}],
  ordinal: function (number) {
   var n = number % 10, suffixes = {1: 'st', 2: 'nd', 3: 'rd'};
   return Math.floor(number % 100 / 10) === 1 || !suffixes[n] ? 'th' : suffixes[n];
  }
 });
 $('#{$id}when').pikaday({
  format: '{$datetimefmt}',
  reformat: function(target,f) {
   return fmt.formatDate(target,f);
  },
  getdate: function(target,f) {
   return fmt.parseDate(target,f);
  },
  i18n: {
   previousMonth: '{$prevm}',
   nextMonth: '{$nextm}',
   months: [{$mnames}],
   weekdays: [{$dnames}],
   weekdaysShort: [{$sdnames}]
  }{$t2}
 });

EOS;
}
$one->help = NULL; //$this->Lang('help_book_start');
$vars[] = $one;
//==
if ($choosend) {
	$dt->setTimestamp($rdata['slotstart'] + $rdata['slotlen']);
	$t = $dt->format($fmt);

	$one = new stdClass();
	$one->title = $this->Lang('title_ending');
	if ($viewmode) {
		$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
	} else {
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'until',$t,20,30);
	}
	$one->help = NULL; //$this->Lang('help_book_end');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('title_lodger');
$t = $rdata['name'] ? $rdata['name'] : $rdata['publicid'];
if ($viewmode) {
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
} else {
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'name',$t,20,64);
}
$one->help = $this->Lang('help_lodger');
$vars[] = $one;
//==
if (!$viewmode) {
	$one = new stdClass();
	$one->title = $this->Lang('title_conformuser');
	$one->input = $this->CreateInputCheckbox($id,'conformuser',1,-1);
	$one->help = $this->Lang('help_conformuser');
	$vars[] = $one;
}
/*
//==
if (!$viewmode) {
	$one = new stdClass();
	$one->title = $this->Lang('title_displayclass');
	$one->must = 0;
	$choices = array(1,Booker::USERSTYLES,0);
	foreach ($choices as $k=>&$t) {
		$t = $k;
	}
	$t = 1;
	$one->input = $this->CreateInputDropdown($id,'displayclass',$choices,-1,$t);
	$one->help = $this->Lang('help_displayclass');
	$vars[] = $one;
//==
	$one = new stdClass();
	$one->title = $this->Lang('title_conformstyle');
	$one->input = $this->CreateInputCheckbox($id,'conformstyle',1,-1);
	$one->help = $this->Lang('help_conformstyle');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('title_contact');
$t = $rdata['contact'];
if ($viewmode) {
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
} else {
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'contact',$t,30,128);
}
$one->help = $this->Lang('help_book_contact');
$vars[] = $one;
//==
if (!$viewmode) {
	$one = new stdClass();
	$one->title = $this->Lang('title_conformcontact');
	$one->input = $this->CreateInputCheckbox($id,'conformcontact',1,-1);
	$one->help = $this->Lang('help_conformcontact');
	$vars[] = $one;
}
*/
//==
$one = new stdClass();
$one->title = $this->Lang('title_comment');
$one->must = 0;
$t = $rdata['comment'];
if ($viewmode) {
	$one->input = $t;
} else {
	$one->must = 0;
	$one->input = $this->CreateTextArea(FALSE,$id,$t,'comment','','','','',50,5,'','','style="height:5em;"');
}
$vars[] = $one;
//==
if ($is_group) {
	$n = $utils->GetGroupItems($this,$rdata['item_id']);
	if ($n && count($n) > 1) {
		$one = new stdClass();
		$one->title = $this->Lang('title_howmany',$idata['membersname']);
		$t = $rdata['subgrpcount'];
		if ($viewmode) {
			$one->input = $t;
		} else {
			$one->must = 1;
			$one->input = $this->CreateInputText($id,'subgrpcount',$t,3,5);
		}
		$one->help = $this->Lang('help_memcount',count($n),$idata['membersname']);
		$vars[] = $one;
	}
}
//==
$condition = NULL; //TODO payable-condition time,requestor etc
$payable = $utils->GetItemPayable($this,$rdata['item_id'],FALSE,$condition);
if ($payable) {
	$one = new stdClass();
	$one->title = $this->Lang('title_paid');
	$t = (int)$rdata['paid'];
	if ($viewmode) {
		$one->input = ($t) ? $this->Lang('yes'):$this->Lang('no');
	} else {
		$one->must = 0;
		$one->input = $this->CreateInputCheckbox($id,'paid',1,$t);
	}
	$vars[] = $one;
}
//==
switch ($rdata['status']) {
 case Booker::STATNONE:
	$t = $this->Lang('stat_none');
	break;
 case Booker::STATTEMP:
	$t = $this->Lang('stat_temp');
	break;
 case Booker::STATNEW:
	$t = $this->Lang('stat_new');
	break;
 case Booker::STATCHG:
	$t = $this->Lang('stat_chg');
	break;
 case Booker::STATDEL:
	$t = $this->Lang('stat_del');
	break;
 case Booker::STATTELL:
	$t = $this->Lang('stat_tell');
	break;
 case Booker::STATASK:
	$t = $this->Lang('stat_ask');
	break;
 case Booker::STATNOTPAID:
	$t = $this->Lang('stat_nopay');
	break;
 case Booker::STATOK:
	$t = $this->Lang('stat_ok');
	break;
 case Booker::STATCANCEL:
	$t = $this->Lang('stat_cancel');
	break;
 default:
	$t = $row['status'];
}
$one = new stdClass();
$one->title = $this->Lang('status');
$one->input = $t;
$vars[] = $one;
//==
$dt->setTimestamp($rdata['lodged']);
$one = new stdClass();
$one->title = $this->Lang('lodged');
$one->input = $dt->format($fmt);
$vars[] = $one;
//==
if ($rdata['approved'] > 0) {
	$dt->setTimestamp($rdata['approved']);
	$one = new stdClass();
	$one->title = $this->Lang('approved');
	$one->input = $dt->format($fmt);
	$vars[] = $one;
}
//==
$tplvars['data'] = $vars;

//buttons
if ($viewmode) {
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
} else { //edit mode
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['apply'] = $this->CreateInputSubmit($id,'apply',$this->Lang('apply'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
	if (1) { //TODO e.g. not an info-request
		$tplvars['approve'] = $this->CreateInputSubmit($id,'approve',$this->Lang('approve'));
		$tplvars['reject'] = $this->CreateInputSubmit($id,'reject',$this->Lang('reject'));
	}
	$funcs = new Booker\Verify();
	$jsfuncs[] = $funcs->VerifyScript($this,$utils,$id,$rdata['item_id'],TRUE,TRUE,$idata['timezone'],TRUE);
	$jsloads[] = <<<EOS
 var \$appbtn = $('#{$id}approve'),
  \$rejbtn = $('#{$id}reject'),
  obs = [$('#{$id}submit'),$('#{$id}apply'),\$appbtn,\$rejbtn];
 $.each(obs,function(indx,\$ob) {
  \$ob.bind('click',validate);
 });

EOS;
}
$tplvars['find'] = $this->CreateInputSubmit($id,'find',$this->Lang('find'),
	'title="'.$this->Lang('tip_finditm').'"');
$tplvars['table'] = $this->CreateInputSubmit($id,'tableview',$this->Lang('table'));
$tplvars['list'] = $this->CreateInputSubmit($id,'listview',$this->Lang('list'));
if ($this->havenotifier) { //can send messages
	$tplvars['pmsg'] = 1;
	//buttons
	$tplvars['ask'] = $this->CreateInputSubmit($id,'ask',$this->Lang('ask'));
	$tplvars['notify'] = $this->CreateInputSubmit($id,'notify',$this->Lang('notify'));

	$what = $utils->GetItemName($this,$idata);
	if ($is_group)
		$what = $this->Lang('countof2',$rdata['subgrpcount'],$what);
	$dt->setTimestamp($rdata['slotstart']);
	$on = $utils->IntervalFormat($this,$dt,'D j M');
	if ($overday) {
		$detail = $this->Lang('whatovrday',$what,$on);
	} else {
		$at = $dt->format('g:i A');
		$detail = $this->Lang('whatonday',$what,$on,$at);
	}
	$approve = $this->Lang('email_approve',$detail);
	$reject = $this->Lang('email_reject',$detail);
	$notify = $this->Lang('email_change',$detail); //ETC
	$ask = $this->Lang('email_ask',$detail);
	$delete = $this->Lang('email_cancel',$detail);

	//modal overlay
	$tplvars['modaltitle'] = $this->Lang('title_feedback');
	$tplvars['customentry'] = $this->CreateInputText($id,'customentry','',20,30);
	$tplvars['prompttitle'] = $this->Lang('title_prompt');

	$jsfuncs[] =<<<EOS
function modalsetup(tg,\$d) {
 var msg,
  id = $(this).attr('id'),
  action = id.replace('{$id}','');
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
  case 'notify':
   msg = "$notify";
   break;
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

EOS;
	$jsloads[] =<<<EOS
 var obs = [\$appbtn,\$rejbtn,$('#{$id}ask'),$('#{$id}notify')];
 $.each(obs,function(indx,\$ob) {
   \$ob.modalconfirm({
     overlayID: 'confirm',
     popupID: 'confmessage',
     confirmBtnID: 'mc_conf2',
     denyBtnID: 'mc_deny2',
     preShow: modalsetup,
     onConfirm: savecustom
   });
 });

EOS;
} else { //can't-send-messages
	$tplvars['pmsg'] = 0;
}
//modal dialog button names
$tplvars['yes'] = $this->Lang('yes');
$tplvars['no'] = $this->Lang('no');
$tplvars['proceed'] = $this->Lang('proceed');
$tplvars['abort'] = $this->Lang('cancel');

$tplvars['mod'] = !$viewmode;

if (!$viewmode) {
	//for picker & mail-validation & confirmation
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/php-date-formatter.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/mailcheck.js"></script>
<script type="text/javascript" src="{$baseurl}/include/levenshtein.min.js"></script>
EOS;
}
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.modalconfirm.min.js"></script>

EOS;
$tplvars['jsincs'] = $jsincs;

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
//porting heredoc-var newlines is a problem for qouted strings! workaround ...
$stylers = str_replace("\n",'',$stylers);
$tplvars['jsstyler'] = <<<EOS
var \$head = $('head'),
 \$linklast = \$head.find("link[rel='stylesheet']:last"),
 linkadd = '{$stylers}';
if (\$linklast.length) {
 \$linklast.after(linkadd);
} else {
 \$head.append(linkadd);
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

echo Booker\Utils::ProcessTemplate($this,'openrequest.tpl',$tplvars);
