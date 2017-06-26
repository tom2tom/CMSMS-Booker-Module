<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openrequest - view or edit a booking-request
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*first-time redirect, $params = array
'bkg_id'=>identifier
'resume' => string
'task'=>'see' OR 'edit'
upon return, $params = array with
'bkg_id'
'resume'
'task'
'when'
'until'
'name'
'conformuser'
'displayclass'
'conformstyle'
'contact'
'conformcontact'
'subgrpcount'
'feepaid' IF RELEVANT
'status'
'customentry'
one of:
'apply'
'approve'
'ask'
'cancel'
'cancel'
'find'
'listview'
'notify'
'reject'
'submit'
'tableview'
*/

if (!function_exists('RequestRedirParms')) {
 function RequestRedirParms($resume, &$params, $msg = FALSE)
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
		$pnew['active_tab'] = 'data';
		break;
	 default:
	}
	if ($msg)
		$pnew['message'] = $msg;
	return $pnew;
 }
}

$pmod = ($params['task'] == 'edit');
if (!$this->_CheckAccess('admin')) {
	if ($pmod && !$this->_CheckAccess('book')) exit;
	if (!$pmod && !$this->_CheckAccess('view')) exit;
}

$utils = new Booker\Utils();
$utils->DecodeParameters($params,array(
	'contact',
	'customentry',
	'name',
	'subgrpcount',
	'until',
	'when'
));

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
}

if (isset($params['cancel'])) {
	$resume = array_pop($params['resume']);
	$newparms = RequestRedirParms($resume,$params);
	$this->Redirect($id,$resume,'',$newparms);
}

$funcs = new Booker\Requestops();
if (isset($params['submit']) || isset($params['apply'])) {
	if (!$pmod) exit;
	//validate
	$is_new = TRUE; //TODO func($params['status'])
	$vfuncs = new Booker\Verify();
	list($res,$msg) = $vfuncs->VerifyData($this,$utils,$params,$params['item_id'],$is_new,TRUE);
	if ($res) {
		//TODO parameter-specific handling change of status etc 'feepaid' value may include currency-symbol
		$funcs->SaveOnce($this,$utils,$params,FALSE);
		//TODO message to lodger
		if (isset($params['submit']))
			$resume = array_pop($params['resume']);
			$newparms = RequestRedirParms($resume,$params);
			$this->Redirect($id,$resume,'',$newparms,$msg);
	} else {
		$params['message'] = $msg;
		$bdata = array(1); //to be populated from $params, later
	}
} elseif (isset($params['approve'])) {
	list($res,$msg) = $funcs->ApproveReq($this,$params['bkg_id'],$params['custmsg']);
	if ($res) {
		//DO STUFF
		$resume = array_pop($params['resume']);
		$newparms = RequestRedirParms($resume,$params,$msg);
		$this->Redirect($id,$resume,'',$newparms);
	} else {
		$params['message'] = $msg;
		$bdata = array(1);
	}
} elseif (isset($params['reject'])) {
	list($res,$msg) = $funcs->RejectReq($this,$params['bkg_id'],$params['custmsg']);
	if ($res) {
		$resume = array_pop($params['resume']);
		$newparms = array('resume'=>json_encode($params['resume'])); //TODO etc
		$this->Redirect($id,$resume,'',$newparms);
	} else {
		$params['message'] = $msg;
		$bdata = array(1);
	}
} elseif (isset($params['ask'])) {
	list($res,$msg) = $funcs->AskReq($this,$params['bkg_id'],$params['custmsg']);
	if ($res) {
		$resume = array_pop($params['resume']);
		$newparms = array('resume'=>json_encode($params['resume'])); //TODO etc
		$this->Redirect($id,$resume,'',$newparms);
	} else {
		$params['message'] = $msg;
		$bdata = array(1);
	}
} elseif (isset($params['notify'])) {
	$mfuncs = new Booker\Messager();
	$bkgid = FALSE; //TODO
	list($res,$msg) = $mfuncs->NotifyBooker($this,$bkgid,$params['custmsg']);
	if ($res) {
		$resume = array_pop($params['resume']);
		$newparms = array('resume'=>json_encode($params['resume'])); //TODO etc
		$this->Redirect($id,$resume,'',$newparms);
	} else {
		$params['message'] = $msg;
		$bdata = array(1);
	}
} elseif (isset($params['find'])) {
	$params['message'] = $this->Lang('notyet');
	$bdata = array(1);
} elseif (isset($params['tableview'])) {
	$params['message'] = $this->Lang('notyet');
	$bdata = array(1);
} elseif (isset($params['listview'])) {
	$params['message'] = $this->Lang('notyet');
	$bdata = array(1);
}

if (empty($bdata)) {
	//TODO not all OnceTable
	$sql = <<<EOS
SELECT O.*,COALESCE(A.name,B.name,'') AS name,COALESCE(A.address,B.address,'') AS address,B.publicid,B.phone
FROM $this->OnceTable O
JOIN $this->BookerTable B ON O.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.publicid=A.publicid
WHERE bkg_id=?
EOS;
	$bdata = $db->GetRow($sql,array($params['bkg_id']));
	if ($bdata) {
		$utils->GetUserProperties($this,$bdata);
	}
} else {
	$bdata = func($params); //TODO
}

$item_id = (int)$bdata['item_id'];
$is_group = ($item_id >= Booker::MINGRPID);
$is_new = ($bdata['status'] == Booker::STATNEW); //TODO ETC?

$tplvars = array(
	'mod' => $pmod
);

$params['active_tab'] = 'data';
$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);

$hidden = array(
	'item_id'=>$item_id,
	'bkg_id'=>$params['bkg_id'],
	'task'=>$params['task'],
	'resume'=>$resume,
	'custmsg'=>''
);
$tplvars['startform'] = $this->CreateFormStart($id,'openrequest',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();
/*
first-call $params = array
'bkg_id' => string '32'
'task' => string 'edit'
'action' => string 'openrequest'
*/

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

if ($pmod)
	$tplvars['compulsory'] = $this->Lang('compulsory_items');
else
	$missing = '&lt;'.$this->Lang('missing').'&gt;';

$idata = $utils->GetItemProperties($this,$item_id,
	array('name','description','bookcount','membersname','timezone'));

$key = ($is_new) ? 'title_booknewfor':'title_bookfor';
$typename = ($is_group) ? $this->Lang('group'):$this->Lang('item');
if (!empty($idata['name'])) {
	$tplvars['title'] = $this->Lang($key,$typename,$idata['name']);
} else {
	$t = $this->Lang('title_noname',$typename,$item_id);
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

$dt = new DateTime('@'.$bdata['slotstart'],NULL);
$fmt = 'Y-n-j G:i';
$t = $dt->format($fmt);
$overday = ($utils->GetInterval($this,$item_id,'slot') >= 84600);

$one = new stdClass();
$one->ttl = $this->Lang('title_starting');
if ($pmod) { //edit mode
	$one->mst = 1;
	$t = $this->CreateInputText($id,'when',$t,20,30);
	$one->inp = str_replace('class="','class="dateinput ',$t);

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
		$sl = (int)$bdata['slotlen'];
		$t2 = <<<EOS

 var pk = $('#{$id}when').data('pikaday');
 if (pk) {
  pk._o.onClose = function() {
   if ('_d' in this && this._d) {
    var ob = new Date(this._d.getTime() + {$sl}*1000);
    var dt = fmt.formatDate(ob,'{$datetimefmt}');
    $('#{$id}until').val(dt);
   }
  };
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
   var n = number % 10, suffixes = {1:'st',2:'nd',3:'rd'};
   return Math.floor(number % 100 / 10) === 1 || !suffixes[n] ? 'th' : suffixes[n];
  }
 });
 $('.dateinput').pikaday({
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
  }
 });{$t2}
EOS;
} else {
	$one->inp = ($t) ? $t:$missing;
}
$one->hlp = NULL; //$this->Lang('help_book_start');
$vars[] = $one;
//==
if ($choosend) {
	$dt->setTimestamp($bdata['slotstart'] + $bdata['slotlen']);
	$t = $dt->format($fmt);

	$one = new stdClass();
	$one->ttl = $this->Lang('title_ending');
	if ($pmod) {
		$one->mst = 1;
		$t = $this->CreateInputText($id,'until',$t,20,30);
		$one->inp = str_replace('class="','class="dateinput ',$t);
	} else {
		$one->inp = ($t) ? $t:$missing;
	}
	$one->hlp = NULL; //$this->Lang('help_book_end');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->ttl = $this->Lang('title_lodger');
$t = $bdata['name'] ? $bdata['name'] : $bdata['publicid'];
if ($pmod) {
	$one->mst = 1;
	$one->inp = $this->CreateInputText($id,'name',$t,20,64);
} else {
	$one->inp = ($t) ? $t:$missing;
}
$one->hlp = $this->Lang('help_lodger');
$vars[] = $one;
//==
if ($pmod) {
	$one = new stdClass();
	$one->ttl = $this->Lang('title_conformuser');
	$one->inp = $this->CreateInputCheckbox($id,'conformuser',1,-1);
	$one->hlp = $this->Lang('help_conformuser');
	$vars[] = $one;
}
/*
//==
if ($pmod) {
	$one = new stdClass();
	$one->ttl = $this->Lang('title_displayclass');
	$one->mst = 0;
	$choices = array(1,Booker::USERSTYLES,0);
	foreach ($choices as $k=>&$t) {
		$t = $k;
	}
	$t = 1;
	$one->inp = $this->CreateInputDropdown($id,'displayclass',$choices,-1,$t);
	$one->hlp = $this->Lang('help_displayclass');
	$vars[] = $one;
//==
	$one = new stdClass();
	$one->ttl = $this->Lang('title_conformstyle');
	$one->inp = $this->CreateInputCheckbox($id,'conformstyle',1,-1);
	$one->hlp = $this->Lang('help_conformstyle');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->ttl = $this->Lang('title_contact');
$t = $bdata['contact'];
if ($pmod) {
	$one->mst = 1;
	$one->inp = $this->CreateInputText($id,'contact',$t,30,128);
} else {
	$one->inp = ($t) ? $t:$missing;
}
$one->hlp = $this->Lang('help_book_contact');
$vars[] = $one;
//==
if ($pmod) {
	$one = new stdClass();
	$one->ttl = $this->Lang('title_conformcontact');
	$one->inp = $this->CreateInputCheckbox($id,'conformcontact',1,-1);
	$one->hlp = $this->Lang('help_conformcontact');
	$vars[] = $one;
}
*/
//==
$one = new stdClass();
$one->ttl = $this->Lang('title_comment');
$t = $bdata['comment'];
if ($pmod) {
	$one->mst = 0;
	$one->inp = $this->CreateTextArea(FALSE,$id,$t,'comment','','','','',50,5,'','','style="height:5em;"');
} else {
	$one->inp = $t;
}
$vars[] = $one;
//==
if ($is_group) {
	$n = $utils->GetGroupItems($this,$item_id);
	if ($n && count($n) > 1) {
		$one = new stdClass();
		$one->ttl = $this->Lang('title_howmany',$idata['membersname']);
		$t = $bdata['subgrpcount'];
		if ($pmod) {
			$one->mst = 0;
			$one->inp = $this->CreateInputText($id,'subgrpcount',$t,3,5);
		} else {
			$one->inp = $t;
		}
		$one->hlp = $this->Lang('help_memcount',count($n),$idata['membersname']);
		$vars[] = $one;
	}
}
//==
$funcs = new Booker\Payment();
if($funcs->MaybePayable($this,$utils,$item_id)) {
	$one = new stdClass();
	$one->ttl = $this->Lang('title_paid');
	$t = (float)$bdata['feepaid'];
	if ($pmod) {
		$one->mst = 0;
		$one->inp = $this->CreateInputText($id,'feepaid',$t,6,8);
	} else {
		$one->inp = ($t) ? $t:$this->Lang('nil');
	}
	$bs = $bdata['slotstart'];
	$t = $funcs->UsageFee($this,$utils,$item_id,$bdata['booker_id'],
		$bs,$bs + $bdata['slotlen']);
	$paid = $funcs->AmountFormat($this,$utils,$item_id,$t);
	$one->hlp = $this->Lang('help_feerecorded',$paid);
	$vars[] = $one;
}
//==
$funcs = new Booker\Status();
$one = new stdClass();
$one->ttl = $this->Lang('status');
if ($pmod) {
	$choices = $funcs->GetStatusChoices($this,5); //1|4
	$utils->mb_ksort($choices);
	$one->inp = $this->CreateInputDropdown($id,'status',$choices,-1,$bdata['status']);
} else {
	$one->inp = $funcs->GetStatusName($this,$bdata['status']);
}
$vars[] = $one;
//==
$dt->setTimestamp($bdata['lodged']);
$one = new stdClass();
$one->ttl = $this->Lang('lodged');
$one->inp = $dt->format($fmt);
$vars[] = $one;
//==
if ($bdata['approved'] > 0) {
	$dt->setTimestamp($bdata['approved']);
	$one = new stdClass();
	$one->ttl = $this->Lang('approved');
	$one->inp = $dt->format($fmt);
	$vars[] = $one;
}
//==
$tplvars['data'] = $vars;

//buttons
if ($pmod) {
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['apply'] = $this->CreateInputSubmit($id,'apply',$this->Lang('apply'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
	if (1) { //TODO e.g. not an info-request
		$tplvars['approve'] = $this->CreateInputSubmit($id,'approve',$this->Lang('approve'));
		$tplvars['reject'] = $this->CreateInputSubmit($id,'reject',$this->Lang('reject'));
	}
	$funcs = new Booker\Verify();
	$jsfuncs[] = $funcs->VerifyScript($this,$utils,$id,$item_id,TRUE,TRUE,$idata['timezone'],TRUE);
	$jsloads[] = <<<EOS
 var \$appbtn = $('#{$id}approve'),
  obs = [$('#{$id}submit'),$('#{$id}apply'),\$appbtn];
 $.each(obs,function(indx,\$ob) {
  \$ob.bind('click',validate);
 });
EOS;
} else {
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
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
		$what = $this->Lang('countof2',$bdata['subgrpcount'],$what);
	$dt->setTimestamp($bdata['slotstart']);
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

	$jsfuncs[] = <<<EOS
function modalsetup(\$tg) {
 var msg,clue,
  id = \$tg.attr('id'),
  action = id.replace('{$id}','');
 switch (action) {
  case 'approve':
   msg = '$approve';
   break;
  case 'reject':
   msg = '$reject';
   break;
  case 'ask':
   msg = '$ask';
   break;
  case 'notify':
   msg = '$notify';
   break;
  default:
   msg = '?';
   break;
 }
 clue = msg.substring(msg.lastIndexOf('['),msg.lastIndexOf(']')+1);
 return [msg,clue];
}
function deferbutton(tg,title) {
 var mstr = modalsetup($(tg)),
  opts = {
   prompt: '<input id="alertable-input" type="text" name="choice" value="' + mstr[1] + '" />'
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
EOS;
	$t = $this->Lang('title_feedback3');
	if ($pmod) {
		$jsloads[] = <<<EOS
 obs = [\$appbtn,$('#{$id}reject'),$('#{$id}ask'),$('#{$id}notify')];
 $.each(obs,function(indx,\$ob) {
   \$ob.click(function() {
  if (any_selected()) {
   deferbutton(this,'$t');
  }
  return false;
 });
EOS;
	} else { // !$pmod
		$jsloads[] = <<<EOS
 $('#{$id}ask,#{$id}notify').click(function() {
  if (any_selected()) {
   deferbutton(this,'$t');
  }
  return false;
 });
EOS;

	} //!$pmod
} else { //can't-send-messages
	$tplvars['pmsg'] = 0;
}

if ($pmod) {
	//for picker & mail-validation & confirmation
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/mailcheck.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/levenshtein.min.js"></script>
EOS;
}
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;
/* TODO check for frontend use of this action
$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.min.css" />
EOS;
//heredoc-var newlines are a problem for qouted strings! workaround ...
$stylers = preg_replace('/[\\n\\r]+/','',$stylers);
$t = <<<EOS
var linkadd = '$stylers',
 \$head = $('head'),
 \$linklast = \$head.find("link[rel='stylesheet']:last");
if (\$linklast.length) {
 \$linklast.after(linkadd);
} else {
 \$head.append(linkadd);
}
EOS;
echo $utils->MergeJS(FALSE,array($t),FALSE);
*/

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'openrequest.tpl',$tplvars);
if ($jsall)
	echo $jsall; //inject constructed js after other content
