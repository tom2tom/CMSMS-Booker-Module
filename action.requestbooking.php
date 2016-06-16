<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: requestbooking
# Initiate booking
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*
if arrive via redirect
$params array
 'item_id'=>, CHECKME NULL if init via button-click
 'startat'=>,
 'range'=>,
 'view'=>,
 MAYBE
 'slotid'=>
 OR MAYBE
 'bookat'=>
*/
if(!empty($params['nosend']))	//user cancelled
{
	if(!(is_numeric($params['startat']) || strtotime($params['startat'])))
	{
		$params['message'] = $this->Lang('err_system').' '.$params['startat'];
		$params['startat'] = (int)(time()/86400);
	}
	elseif(!isset($params['message']))
		$params['message'] = '';

	$parms = array(
	'startat'=>$params['startat'],
	'range'=>$params['range'],
	'view'=>$params['view'],
	'item_id'=>$params['item_id'],
	'message'=>$params['message']
	);
	$this->Redirect($id,'default',$returnid,$parms);
}

$item_id = (int)$params['item_id'];
$is_group = ($item_id >= Booker::MINGRPID);
$funcs = new bkrshared();

if(isset($params['slotid']))
{
	//for a group, here we get some useful representative data
	$bkg_id = $params['slotid'];
	$sql = 'SELECT * FROM '.$this->DataTable.' WHERE bkg_id=?';
	$bdata = $funcs->SafeGet($sql,array($bkg_id),'row');
}
else
{
	$bdata = array();
	if(isset($params['bookat']))
		$bdata['slotstart'] = $params['bookat'];
	else
		$bdata['slotstart'] = $params['startat'];
	$bdata['slotlen'] = $funcs->GetInterval($this,$item_id,'slot');
}

$idata = $funcs->GetItemProperty($this,$item_id,'*');
$overday = ($funcs->GetInterval($this,$item_id,'slot') >= 84600);
$nd = $bdata['slotstart'] + $bdata['slotlen'];
$localnow = $funcs->GetZoneTime($idata['timezone']);
$past = ($nd <= $localnow);
$is_new = !($past || isset($params['requesttype']));
$tzone = new DateTimeZone('UTC');
$tplvars = array();

if(!empty($params['send']))
{
	$funcs2 = new bkrverify();
	//TODO make this handle $past == TRUE
	list($res,$errmsg) = $funcs2->VerifyPublic($this,$funcs,$params,$is_new);
	if($res)
	{
		$save = FALSE;
		$ob = cms_utils::get_module('FrontEndUsers');
		if ($ob)
		{
			$uid = $ob->LoggedInID();
			if ($uid !== FALSE)
			{
				$t = (int)$idata['feugroup'];
				if ($t == -1) //any group
					$save = TRUE;
				elseif ($t != 0) //none
				{
					$gid = $ob->GetGroupID($t);
					$save = $ob->MemberOfGroup($uid,$gid);
				}
				if($save)
					$by = $ob->GetUserName($uid); //default
			}
			unset($ob);
		}
		if($save)
		{
$this->Crash();
/*
			if(bkrverify::VerifyAdmin??($this,$shares,$params,$item_id,$is_new))
				$ares = bkrbookingops::SaveBkg($this,$params,$is_new)
*/
		}
		else
		{
			//localise 'now'
			$params['lodged'] = $funcs->GetZoneTime($idata['timezone']);
			$rdata = FALSE; //passed-by-ref
			$funcs2 = new bkrrequestops();
//			$ares =
			$funcs2->SaveReq($this,$params,$rdata,TRUE);
		}

		if(!empty($idata['approvercontact']))
		{
			try { $funcs2 = new MessageSender(); }
			catch (Exception $e) { $funcs2 = FALSE; }
			if($funcs2)
			{
/*
				try {
					$localzone = new DateTimeZone($idata['timezone']);
					$parms = $localzone->getLocation();
					$country = $parms['country_code'];
				} catch (Exception $e) {
					$country = FALSE;
				}
*/
				$from = FALSE; //always use default sender
				if(preg_match('/\w+@\w+/',$idata['approvercontact']))
					$to = array($idata['approver']=>$idata['approvercontact']);
				else
					$to = $idata['approvercontact'];
				$textparms = array('prefix'=>$idata['smsprefix'],'pattern'=>$idata['smspattern']);
				$tweetparms = array();
				$what = (isset($params['subgrpcount'])) ?
					sprintf('%d %s',$params['subgrpcount'],$idata['membersname']):
					$funcs->GetItemName($this,$idata);
				$dt = new DateTime($bdata['slotstart'],$tzone);
				$on = $funcs->IntervalFormat($this,$dt,'D j M');
				if(!$overday)
					$at = $dt->format('g:i A');

				if($save)
				{
$this->Crash();
//TODO
					$mailparms = array('subject'=>$X,'body'=>$Y);
					$msg = $Z;
					$textparms['body'] = $msg;
					$tweetparms['body'] = $msg;
				}
				else
				{
					if(isset($params['slotid'])) //existing booking
					{
						$mailparms = array('subject'=>$this->Lang('email_reqchange_title'));
						if($overday)
						{
							$mailparms['body'] = $this->Lang('email_reqchange',$what,$on);
							$msg = $this->Lang('text_reqchange',$what,$on);
							$textparms['body'] = $msg;
							$tweetparms['body'] = $msg;
						}
						else
						{
							$mailparms['body'] = $this->Lang('email_reqchangeat',$what,$on,$at);
							$msg = $this->Lang('text_reqchangeat',$what,$on,$at);
							$textparms['body'] = $msg;
							$tweetparms['body'] = $msg;
						}
					}
					else //new
					{
						$mailparms = array('subject'=>$this->Lang('email_request_title'));
						if($overday)
						{
							$mailparms['body'] = $this->Lang('email_request',$what,$on);
							$msg = $this->Lang('text_request',$what,$on);
							$textparms['body'] = $msg;
							$tweetparms['body'] = $msg;
						}
						else
						{
							$mailparms['body'] = $this->Lang('email_requestat',$what,$on,$at);
							$msg = $this->Lang('text_requestat',$what,$on,$at);
							$textparms['body'] = $msg;
							$tweetparms['body'] = $msg;
						}
					}
				}
//			list($res,$errmsg) =
				$funcs2->Send($from,$to,$textparms,$mailparms,$tweetparms);
			}
		}

		$parms = array(
		 'message'=>$this->Lang('booking_feedback'),
		 'startat'=>$params['startat'],
		 'range'=>$params['range'],
		 'view'=>$params['view'],
		 'item_id'=>$item_id
		);
		$this->Redirect($id,'default',$returnid,$parms);
	}
	else //data error
	{
		$tplvars['errmessage'] = implode('<br >',$errmsg);
		//fall into repeat presentation
	}
}
elseif(isset($params['find']))
{
	$this->Redirect($id,'findbooking',$returnid,array(
	 'item_id'=>$item_id,
	 'startat'=>$params['startat'],
	 'range'=>$params['range'],
	 'view'=>$params['view']));
}

$hidden = array(
	'item_id'=>$item_id,
	'startat'=>$params['startat'],
	'range'=>$params['range'],
	'view'=>$params['view']);
if(isset($params['slotid']))
	$hidden['slotid'] = $params['slotid'];

$tplvars['startform'] = $this->CreateFormStart($id,'requestbooking',$returnid,
	'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();

$baseurl = $this->GetModuleURLPath();
//script accumulators
$jsfuncs = array();
$jsloads = array();
$jsincs = array();

$tplvars['title'] = $funcs->GetItemName($this,$idata);
if(!empty($idata['description']))
{
	$tplvars['desc'] = bkrshared::ProcessTemplateFromData($this,$idata['description'],$tplvars);
}
$urls = $funcs->GetImageURLs($this,$idata['image'],$idata['name']);
if($urls)
	$tplvars['pictures'] = $urls;

$mcount = 0;
$groupextra = FALSE;
if(!$past)
{
	if($is_group)
	{
		$members = $funcs->GetGroupItems($this,$item_id);
		if($members)
		{
			$mcount = count($members);
			if($mcount > 1)
			{
				$mname = $idata['membersname'];
				if(!$mname)
					$mname = $this->Lang('itemv_multi');
				$tplvars['membermsg'] = $this->Lang('help_memcount',$mcount,$mname);
			}

			$nowbooked = array();
			$funcs2 = new bkrbookingops();
			$allbooked = $funcs2->GetBooked($this,$members,$bdata['slotstart'],$bdata['slotstart']+$bdata['slotlen']);
			foreach($allbooked as $one)
			{
				$name = $one['name'] ? $one['name']:$funcs->GetItemNameForID($this,$one['item_id']);
				$nowbooked[$name] = $one['user'];
			}
			$groupextra = $mcount > count($nowbooked);
		}
	}
}

$tplvars['mustmsg'] = '<img src="'.$baseurl.'/images/information.png" alt="icon" border="0" /> '.
	$this->Lang('title_must');
$tplvars['title'] = $this->Lang('title_request');
$tplvars['title_what'] = $this->Lang('title_request1');
if($past)
{
	$tplvars['past'] = 1;
	$tplvars['title_count'] = '';
	$tplvars['title_when'] = $this->Lang('title_whenpast');
	$tplvars['title_until'] = $this->Lang('title_untilpast');
}
else
{
	if($mcount > 1)
		$tplvars['title_count'] = $this->Lang('title_howmany',$mname);
	$tplvars['title_when'] = $this->Lang('title_when');
	$tplvars['title_until'] = $this->Lang('title_until');
}
$tplvars['title_sender'] = $this->Lang('title_sender');
$tplvars['title_contact'] = $this->Lang('title_contactyou');
$tplvars['title_comment'] = $this->Lang('title_comment');

if($past)
{
	$tplvars['inputwhat'] = $this->Lang('reqnotice');
}
elseif(isset($params['slotid']))
{
	if($groupextra)
	{
		$choices = array($this->Lang('reqadd')=>Booker::STATNEW);
		$sel = Booker::STATNEW;
	}
	else
	{
		$choices = array();
		$sel = Booker::STATCHG;
	}
	$choices = $choices + array(
		$this->Lang('reqchange')=>Booker::STATCHG,
		$this->Lang('reqdelete')=>Booker::STATDEL,
		$this->Lang('reqnotice')=>Booker::STATTELL
	);
	$tplvars['inputwhat'] = $this->CreateInputRadioGroup($id,'requesttype',$choices,$sel,'','<br />');
}
else
{
	$tplvars['inputwhat'] = $this->Lang('title_request2',$idata['name']);
}
$tplvars['textwhat'] = $idata['name'];

$choosend = ($idata['bookcount'] != 1);

$dt = new DateTime('1900-1-1',$tzone);
$dt->setTimestamp($bdata['slotstart']);
$t = $funcs->IntervalFormat($this,$dt,$idata['dateformat']).' '.$dt->format($idata['timeformat']);
if($choosend)
{
	$dt->setTimestamp($nd);
	$t2 = $funcs->IntervalFormat($this,$dt,$idata['dateformat']).' '.$dt->format($idata['timeformat']);
}
if($past)
{
	$tplvars['currentmsg'] = $this->Lang('nopastdesc');
	$tplvars['inputwhen'] = $t; //TODO
	if($choosend)
		$tplvars['inputuntil'] = $t2; //ditto
}
else
{
	if($mcount > 1)
		$tplvars['inputcount'] = $this->CreateInputText($id,'subgrpcount',1,3,5);
	if(isset($params['slotid']))
	{
		if($is_group)
		{
			$tplvars['nowbooked'] = $nowbooked;
			$d = $this->Lang('currentdesc3').bkrshared::ProcessTemplate($this,'currentbookings.tpl',$tplvars);
		}
		elseif($choosend)
			$d = $this->Lang('currentdesc2',$bdata['user'],$t,$t2);
		else
			$d = $this->Lang('currentdesc',$bdata['user'],$t);
		$tplvars['currentmsg'] = $d;
	}
	if(isset($params['when']))
		$t = $params['when'];
	$tplvars['inputwhen'] = $this->CreateInputText($id,'when',$t,20,30);
	if($choosend)
	{
		if(isset($params['until']))
			$t2 = $params['until'];
		$tplvars['inputuntil'] = $this->CreateInputText($id,'until',$t2,20,30);
	}

	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/moment.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>

EOS;
	$nextm = $this->Lang('nextm');
	$prevm = $this->Lang('prevm');
	//js wants quoted period-names
	$t = $this->Lang('longmonths');
	$mnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('longdays');
	$dnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('shortdays');
	$sdnames = "'".str_replace(",","','",$t)."'";
	$momentfmt = ($overday) ? 'YYYY-MM-DD':'YYYY-MM-DD h:mm';

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
    d2 = moment(d).add({$bdata['slotlen']},'s').format(f);
    $('#{$id}until').val(d2);
   }
  }
 });

EOS;
}
$t = (!empty($params['user'])) ? $params['user']:'';
$tplvars['inputsender'] = $this->CreateInputText($id,'user',$t,20,30); //name must conform to verifier js
$t = (!empty($params['contact'])) ? $params['contact']:'';
$tplvars['inputcontact'] = $this->CreateInputText($id,'contact',$t,30,50);
$t = (!empty($params['comment'])) ? $params['comment']:'';
$tplvars['inputcomment'] = $this->CreateTextArea(FALSE,$id,$t,'comment','','','','',55,5,'','','style="height:5em;"');
$ob = cms_utils::get_module('Captcha');
if($ob)
{
	$tplvars['title_captcha'] = $this->Lang('title_captcha');
	$t = $this->CreateInputText($id,'captcha','',7,8);
	$t = preg_replace('~class="(.*)"~U','class="\\1 captcha"',$t);
	$tplvars['inputcaptcha'] = $t;
	$tplvars['captcha'] = $ob->getCaptcha();
}

$funcs2 = new bkrverify();
$checkdates = !($past || (isset($params['slotid']) && !$groupextra));
$jsfuncs[] = $funcs2->VerifyScript($this,$id,FALSE,$checkdates,FALSE,$idata['timezone']);
//for email-validator
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/mailcheck.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/levenshtein.min.js"></script>

EOS;

$tplvars['send'] =  $this->CreateInputSubmit($id,'send',$this->Lang('submit'));
$jsloads[] = <<<EOS
 $('#{$id}send').bind('click',validate);

EOS;

$tplvars['cancel'] =  $this->CreateInputSubmit($id,'nosend',$this->Lang('cancel'));
$choices = $funcs->GetItemFamily($this,$db,$item_id);
if($choices && count($choices) > 1)
{
	$tplvars['choose'] =
		$this->CreateInputDropdown($id,'chooser',array_flip($choices),-1,$item_id,'id="'.$id.'chooser"');

	$jsloads[] = <<<EOS
 $('#{$id}chooser').change(function(){
  $(this).closest('form').trigger('submit');
 });

EOS;
}

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
$customcss = $funcs->GetStylesURL($this,$item_id);
if($customcss)
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />{/if}
EOS;

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

if($jsloads)
{
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo bkrshared::ProcessTemplate($this,'requestbooking.tpl',$tplvars);
?>
