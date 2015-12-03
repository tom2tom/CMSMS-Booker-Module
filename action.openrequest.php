<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openrequest - view or edit a booking-request
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*first-time redirect, $params = array
'req_id'=>identifier
'mode'=>'inspect' OR 'edit'
*/
if(!$this->_CheckAccess()) exit;

if(isset($params['cancel']))
{
	$this->Redirect($id,'defaultadmin');
}

$sql = 'SELECT * FROM '.$this->RequestTable.' WHERE req_id=?';
$rdata = $db->GetRow($sql,array($params['req_id']));
$is_group = ($rdata['item_id'] >= Booker::MINGRPID);
$type = ($is_group) ? $this->Lang('group'):$this->Lang('item');
$is_new = ($rdata['status'] == Booker::STATNEW); //ETC?
$viewmode = ($params['mode'] == 'inspect');
$funcs = new bkrshared();

if(isset($params['submit']) || isset($params['apply']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	//validate
	$funcs2 = new bkrverify();
	list($res,$msg) = $funcs2->VerifyAdmin($this,$funcs,$params,$rdata['item_id'],$is_new);
	if($res)
	{
		$funcs2 = new bkrrequestops();
		$funcs2->SaveReq($this,$params,$rdata,FALSE);
		//TODO message to lodger
		if(isset($params['submit']))
			$this->Redirect($id,'defaultadmin');
	}
	else
	{
		//handle error message(s)
//		if(!empty($params['message'])
//
//		else
//			$params['message'] = ;
	}
}
elseif(isset($params['approve']))
{
/*supplied $params[]
'req_id' => string '6'
'mode' => string 'edit'
'when' => string '9 November 2015 11:00'
'until' => string '9 November 2015 11:59'
'user' => string 'HiThere'
'userclass' => string '1'
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
if(isset($params['reject']))
{
}
elseif(isset($params['ask']))
{
}
elseif(isset($params['notify']))
{
}
if(isset($params['find']))
{
}
elseif(isset($params['table']))
{
}
elseif(isset($params['list']))
{
}

$smarty->assign('startform',
	$this->CreateFormStart($id,'openrequest',$returnid,'POST','','','',array(
		'req_id'=>$params['req_id'],'mode'=>$params['mode'],'custmsg'=>'')));
$smarty->assign('endform',$this->CreateFormEnd());

$this->_BuildNav($id,$params,$returnid);
if(!empty($params['message']))
	$smarty->assign('message',$params['message']);

if(!$viewmode)
	$smarty->assign('compulsory',$this->Lang('help_compulsory'));

$idata = $funcs->GetItemProperty($this,$rdata['item_id'],"*");

$key = ($is_new) ? 'title_booknewfor':'title_bookfor';
if(!empty($idata['name']))
{
	$smarty->assign('title',$this->Lang($key,$type,$idata['name']));
}
else
{
	$t = $this->Lang('title_noname',$type,$idata['item_id']);
	$smarty->assign('title',$this->Lang($key,$t,''));
}

$t = '';
if(!empty($idata['description']))
	$t .= $this->ProcessTemplateFromData($idata['description']);
$smarty->assign('desc',$t);
//in this context, ignore any image

$baseurl = $this->GetModuleURLPath();
$smarty->assign('modurl',$baseurl);
//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$choosend = ($idata['bookcount'] != 1);

$vars = array();

$dt = new DateTime('1900-1-1',new DateTimeZone('UTC'));
$dt->setTimestamp($rdata['slotstart']);
$fmt = $idata['dateformat'].' '.$idata['timeformat'];
$t = $dt->format($fmt);
if($rdata['item_id'])
	$overday = ($funcs->GetInterval($this,$rdata['item_id'],'slot') >= 84600);
else
{
//TODO support 'un-targeted' bookings - preference?
	$overday = FALSE;
}

$one = new stdClass();
$one->title = $this->Lang('title_when');
if($viewmode)
{
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
}
else
{
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'when',$t,20,30);

	$nextm = $this->Lang('nextm');
	$prevm = $this->Lang('prevm');
	//js wants quoted period-names
	$t = $this->Lang('longmonths');
	$mnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('longdays');
	$dnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('shortdays');
	$sdnames = "'".str_replace(",","','",$t)."'";
	if($choosend)
	{
		$sl = (int)$rdata['slotlen'];
		$t2 = <<<EOS
    d2 = moment(d).add($sl,'s').format(f);
    $('#{$id}until').val(d2);

EOS;
	}
	else
	{
		$t2 = '';
	}
	$momentfmt = ($overday) ? 'YYYY-M-D':'YYYY-M-D h:mm';

	$jsloads[] = <<<EOS
 new Pikaday({
  field: document.getElementById('calendar'),
  trigger: document.getElementById('{$id}when'),
  format: 'YYYY-MM-DD',
  i18n: {
   previousMonth: '{$prevm}',
   nextMonth: '{$nextm}',
   months: [{$mnames}],
   weekdays: [{$dnames}],
   weekdaysShort: [{$sdnames}]
  },
  onClose: function(){
   var sel = $('#calendar').val();
   if(sel !== '') { //not cancelled
    var d = new Date(sel);
    var f = '{$momentfmt}';
    var d2 = moment(d).format(f);
    $('#{$id}when').val(d2);
{$t2}
   }
  }
 });

EOS;
}
$one->help = NULL; //$this->Lang('help_book_start');
$vars[] = $one;
//==
if($choosend)
{
	$dt->setTimestamp($rdata['slotstart'] + $rdata['slotlen']);
	$t = $dt->format($fmt);

	$one = new stdClass();
	$one->title = $this->Lang('title_until');
	if($viewmode)
	{
		$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
	}
	else
	{
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'until',$t,20,30);
	}
	$one->help = NULL; //$this->Lang('help_book_end');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('title_sender2');
$t = $rdata['sender'];
if($viewmode)
{
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
}
else
{
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'user',$t,20,64);
}
$one->help = $this->Lang('help_lodger');
$vars[] = $one;
//==
if(!$viewmode)
{
	$one = new stdClass();
	$one->title = $this->Lang('title_conformuser');
	$one->input = $this->CreateInputCheckbox($id,'conformuser',1,-1);
	$one->help = $this->Lang('help_conformuser');
	$vars[] = $one;
}
//==
if(!$viewmode)
{
	$one = new stdClass();
	$one->title = $this->Lang('userclass');
	$t = 1;
	$one->must = 0;
	$choices = array(1=>1,2=>2,3=>3,4=>4,5=>5);
	$one->input = $this->CreateInputDropdown($id,'userclass',$choices,-1,$t);
	$one->help = $this->Lang('help_book_style');
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
if($viewmode)
{
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
}
else
{
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'contact',$t,30,128);
}
$one->help = $this->Lang('help_book_contact');
$vars[] = $one;
//==
if(!$viewmode)
{
	$one = new stdClass();
	$one->title = $this->Lang('title_conformcontact');
	$one->input = $this->CreateInputCheckbox($id,'conformcontact',1,-1);
	$one->help = $this->Lang('help_conformcontact');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('title_comment');
$one->must = 0;
$t = $rdata['comment'];
if($viewmode)
{
	$one->input = $t;
}
else
{
	$one->must = 0;
	$one->input = $this->CreateTextArea(FALSE,$id,$t,'comment','','','','',50,5,'','','style="height:5em;"');
}
$vars[] = $one;
//==
if($is_group)
{
	$n = $funcs->GetGroupItems($this,$rdata['item_id']);
	if($n && count($n) > 1)
	{
		$one = new stdClass();
		$one->title = $this->Lang('title_howmany',$idata['membersname']);
		$t = $rdata['subgrpcount'];
		if($viewmode)
		{
			$one->input = $t;
		}
		else
		{
			$one->must = 1;
			$one->input = $this->CreateInputText($id,'subgrpcount',$t,3,5);
		}
		$one->help = $this->Lang('help_memcount',count($n),$idata['membersname']);
		$vars[] = $one;
	}
}
//==
if($idata['fee1'] != 0 || ($idata['fee2'] != 0 && $idata['fee2condition']))
{
	$one = new stdClass();
	$one->title = $this->Lang('title_paid');
	$t = (int)$rdata['paid'];
	if($viewmode)
	{
		$one->input = ($t) ? $this->Lang('yes'):$this->Lang('no');
	}
	else
	{
		$one->must = 0;
		$one->input = $this->CreateInputCheckbox($id,'paid',1,$t);
	}
	$vars[] = $one;
}
//==
switch($rdata['status'])
{
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
 case Booker::STATNOPAY:
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
if($rdata['approved'] > 0)
{
	$dt->setTimestamp($rdata['approved']);
	$one = new stdClass();
	$one->title = $this->Lang('approved');
	$one->input = $dt->format($fmt);
	$vars[] = $one;
}
//==
$smarty->assign('data',$vars);

//buttons
if($viewmode)
{
	$smarty->assign('cancel',$this->CreateInputSubmit($id,'cancel',$this->Lang('close')));
}
else //edit mode
{
	$smarty->assign('submit',$this->CreateInputSubmit($id,'submit',$this->Lang('submit')));
	$smarty->assign('apply',$this->CreateInputSubmit($id,'apply',$this->Lang('apply')));
	$smarty->assign('cancel',$this->CreateInputSubmit($id,'cancel',$this->Lang('cancel')));
	if(1) //e.g. not an info-request
	{
		$smarty->assign('approve',$this->CreateInputSubmit($id,'approve',$this->Lang('approve')));
		$smarty->assign('reject',$this->CreateInputSubmit($id,'reject',$this->Lang('reject')));
	}
	$funcs2 = new bkrverify();
	$jsfuncs[] = $funcs2->VerifyScript($this,$id,TRUE,TRUE,TRUE,$idata['timezone']);
	$jsloads[] = <<<EOS
 var \$appbtn = $('#{$id}approve'),
  \$rejbtn = $('#{$id}reject'),
  obs = [$('#{$id}submit'),$('#{$id}apply'),\$appbtn,\$rejbtn];
 $.each(obs,function(indx,\$ob) {
  \$ob.bind('click',validate);
 });

EOS;
}
$smarty->assign('find',$this->CreateInputSubmit($id,'find',$this->Lang('find'),
	'title="'.$this->Lang('tip_finditm').'"'));
$smarty->assign('table',$this->CreateInputSubmit($id,'tableview',$this->Lang('table')));
$smarty->assign('list',$this->CreateInputSubmit($id,'listview',$this->Lang('list')));
$ob = cms_utils::get_module('Notifier');
if($ob)
{
	//can-send-messages
	$smarty->assign('pmsg',1);
	//buttons
	$smarty->assign('ask',$this->CreateInputSubmit($id,'ask',$this->Lang('ask')));
	$smarty->assign('notify',$this->CreateInputSubmit($id,'notify',$this->Lang('notify')));

	$what = (isset($params['subgrpcount'])) ?
		sprintf('%d %s',$params['subgrpcount'],$idata['membersname']):
		$funcs->GetItemName($this,$idata);
	$dt->setTimestamp($rdata['slotstart']);
	$on = $funcs->IntervalFormat($this,$dt,'D j M');
	if($overday)
	{
		$approve = $this->Lang('email_approve',$what,$on);
		$reject = $this->Lang('email_reject',$what,$on);
		$notify = $this->Lang('email_changed',$what,$on); //ETC
		$ask = $this->Lang('email_ask',$what,$on);
	}
	else
	{
		$at = $dt->format('g:i A');
		$approve = $this->Lang('email_approveat',$what,$on,$at);
		$reject = $this->Lang('email_rejectat',$what,$on,$at);
		$notify = $this->Lang('email_changedat',$what,$on,$at); //ETC
		$ask = $this->Lang('email_askat',$what,$on,$at);
	}
	//modal overlay
	$smarty->assign('modaltitle',$this->Lang('title_feedback'));
	$smarty->assign('customentry',$this->CreateInputText($id,'customentry','',20,30));
	$smarty->assign('prompttitle',$this->Lang('title_prompt'));

	$jsfuncs[] =<<<EOS
function modalsetup(tg,\$d) {
 var msg,
  id = $(this).attr('id'),
  action = id.replace('{$id}','');
 switch(action) {
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
}
else //can't-send-messages
{
	$smarty->assign('pmsg',0);
}
//modal dialog button names
$smarty->assign('yes',$this->Lang('yes'));
$smarty->assign('no',$this->Lang('no'));
$smarty->assign('proceed',$this->Lang('proceed'));
$smarty->assign('abort',$this->Lang('cancel'));

$smarty->assign('mod',!$viewmode);

if(!$viewmode)
{
	//for picker & mail-validation & confirmation
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/moment.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/mailcheck.js"></script>
<script type="text/javascript" src="{$baseurl}/include/levenshtein.min.js"></script>

EOS;
}
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.modalconfirm.js"></script>

EOS;
$smarty->assign('jsincs',$jsincs);

if($jsloads)
{
	$jsfuncs[] =<<<EOS
$(document).ready(function() {

EOS;
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] =<<<EOS
});

EOS;
}
$smarty->assign('jsfuncs',$jsfuncs);

echo $this->ProcessTemplate('openrequest.tpl');
?>
