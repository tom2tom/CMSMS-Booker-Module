<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: requestbooking
# Initiate booking
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/*
$params[]:
if arrive via redirect
'returnid'
'bookat'=> int 1473591600
'action'
for all form-inputs here:
 'account'
 'bookertype'
 'cancel'
 'captcha'
 'cart'
 'itempick'
 'comment'
 'contact'
 'contactnew'
 'name'
 'passwd'
 'requesttype'
 'subgrpcount'
 'submit'
 'until'
 'when'
*/
//parameter keys filtered out before redirect etc
$localparams = array(
	'account',
	'action',
	'bookat',
	'bookertype',
	'cancel',
	'captcha',
	'cart',
	'comment',
	'contact',
	'contactnew',
	'origreturnid',
	'name',
	'passwd',
	'register',
	'request',
	'requesttype',
//	'subgrpcount',
	'submit',
//	'task',
	'until',
	'when'
);

$utils = new Booker\Utils();
$utils->UnFilterParameters($params);

if (isset($params['cancel'])) {
	if (!(is_numeric($params['showfrom']) || strtotime($params['showfrom']))) {
		$params['message'] = $this->Lang('err_system').' '.$params['showfrom'];
		$params['showfrom'] = (int)(time()/86400);
	} elseif (!isset($params['message']))
		$params['message'] = ''; //force clearance

	do {
		$resume = array_pop($params['resume']);
	} while ($resume == $params['action'] && $params['resume']);
	if ($resume == $params['action']) {
		$resume = 'default'; //should never happen
	}
	$newparms = $utils->FilterParameters($params,$localparams);
	$this->Redirect($id,$resume,$params['returnid'],$newparms);
}

$tplvars = array();

if (isset($params['register'])) {
	$tplvars['message'] = $this->Lang('notyet');
}

if (isset($params['item_id'])) {
	$item_id = (int)$params['item_id'];
} else {
	$tplvars = array(
		'title_error' => $this->Lang('error'),
		'message' => $this->Lang('err_parm'),
		'pagenav' => ''
	);
	echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
	return;
}

//get item parameters for here or carting and/or scheduling and/or requesting
$idata = $utils->GetItemProperty($this,$item_id,array(
'name',
'description',
'image',
'membersname',
'available',
'rationcount',
'bookcount',
'grossfees',
'taxrate',
'latitude',
'longitude',
'timezone',
'dateformat',
'timeformat',
'approver',
'approvercontact',
'approvertell',
'bookertell',
'smsprefix',
'smspattern',
'paymentiface',
'feugroup',
'subgrpalloc',
'subgrpdata'
));
if ($idata) {
	$idata['item_id'] = $item_id;
	$idata = $idata + $utils->GetItemProperty($this,$item_id,array('slottype','slotcount'),TRUE);
	$idata = $idata + $utils->GetItemProperty($this,$item_id,array('leadtype','leadcount'),TRUE);
} else {
	$tplvars = array(
		'title_error' => $this->Lang('error'),
		'message' => $this->Lang('err_data'),
		'pagenav' => NULL
	);
	echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
	return;
}

$is_group = ($item_id >= Booker::MINGRPID);

if (!empty($params['bkgid'])) { //activated slot with current booking(s)
	//get some useful representative data
	$bkgid = $params['bkgid'];
	$sql = <<<EOS
SELECT D.*,B.name,B.publicid,B.address,B.phone FROM {$this->DataTable} D
JOIN {$this->BookerTable} B ON D.booker_id=B.booker_id
WHERE D.bkg_id=?
EOS;
	$bdata = $utils->SafeGet($sql,array($bkgid),'row');
} else { //TODO if first-pass
	$bdata = array();
	if (isset($params['bookat'])) {
		$bdata['slotstart'] = $params['bookat'];
	} elseif (isset($params['showfrom'])) {
		$bdata['slotstart'] = $params['showfrom'] + 10*3600; //TODO
	} else {
		$bdata['slotstart'] = time(); //TODO
	}
	$bdata['slotlen'] = $utils->GetInterval($this,$item_id,'slot');
}

$utils->DecodeParameters($params,
	array('account','comment','contact','contactnew','name','passwd'));

$now = $utils->GetZoneTime($idata['timezone']);

if (isset($params['cart'])) {
	$funcs = new Booker\Verify();
	//$is_new = !$past && $params['task'] == 'add' && (!$is_group || 1); //TODO || count vacant members > 0
	//$is_new = ($params['task'] == 'add'); //TODO refine ASAP per start-time, available slots etc
	$is_new = TRUE;
	list($res,$errmsg) = $funcs->VerifyData($this,$utils,$params,$item_id,$is_new,FALSE);
	if ($res) {
		$bs = $params['slotstart'];
		$be = $bs + $params['slotlen'] + 1; //1-past-end
		if (!$is_new || $be > $now) { //$bs > $now - 300 //some slop
			$funcs = new Booker\Userops();
			list($bookerid,$newbooker) = $funcs->GetParamsID($this,$params);
			if ($bookerid !== FALSE) {
				$params['booker_id'] = $bookerid;
				$funcs = new Booker\Payment();
				//determine how much to be paid (ignoring tax)
				$amounts = $funcs->Amounts($this,$utils,$item_id,$bookerid,$bs,$be);
				//ignore $amounts[1] i.e. total credit now, cuz' maybe we're doing multiple items
				$params['fee'] = $amounts[0];

				$cache = Booker\Cache::GetCache($this);
				$cart = $utils->RetrieveCart($cache,$params);
				$funcs = new Booker\Requestops();
				list($res,$errmsg) = $funcs->CartReq($this,$utils,$params,$idata,$cart);
				if ($res) {
					$params['resume'][] = $params['action']; //cancellation comes back here
					$params['task'] = 'add'; //button-labels will be 'cancel','submit'
					$utils->SaveCart($cart,$cache,$params);
					$newparms = $utils->FilterParameters($params,$localparams);
					$this->Redirect($id,'opencart',$params['returnid'],$newparms);
					exit;
				} else {
					$tplvars['message'] = $this->Lang('err_system');
				}
			} else { //invalid password for account
				sleep(2); //impede brute-forcers
				$tplvars['message'] = $this->Lang('invalid_type',$this->Lang('password'));
			}
		} else {
			$tplvars['message'] = $this->Lang('err_late');
		}
		$bdata['slotstart'] = $params['slotstart'];
		$bdata['slotlen'] = $params['slotlen'];
	} else { //problem(s) with the request data
		$tplvars['message'] = implode('<br >',$errmsg);
//TODO		$bdata['slotstart'] = ;
//		$bdata['slotlen'] = ;
	}
	//fall into repeat presentation
} elseif (isset($params['submit'])) {
	$funcs = new Booker\Verify();
	//$is_new = !$past && $params['task'] == 'add' && (!$is_group || 1); //TODO || count vacant members > 0
	//$is_new = ($params['task'] == 'add'); //TODO refine ASAP per start-time, available slots etc
	$is_new = TRUE;
	list($res,$errmsg) = $funcs->VerifyData($this,$utils,$params,$item_id,$is_new,FALSE);
	if ($res) {
		$bs = $params['slotstart'];
		$be = $bs + $params['slotlen'] + 1; //1-past-end
		if (!$is_new || $be > $now) { // $bs > $now - 300 some slop
			$funcs = new Booker\Userops();
			list($bookerid,$newbooker) = $funcs->GetParamsID($this,$params);
			if ($bookerid !== FALSE) {
				$params['booker_id'] = $bookerid;
				$rights = $funcs->GetRights($this,$bookerid); //before we change $funcs
				$funcs = new Booker\Payment();
				//determine how much to be paid (ignoring tax)
				$amounts = $funcs->Amounts($this,$utils,$item_id,$bookerid,$bs,$be);
				$payable = $amounts[0]; //ignore $amounts[1] i.e. total credit now, cuz' maybe we're doing multiple items
				$params['fee'] = $payable;

				$cache = Booker\Cache::GetCache($this);
				$cart = $utils->RetrieveCart($cache,$params);
				$cartwasempty = $cart->seemsEmpty();
				//add item to cart
				$funcs = new Booker\Requestops();
				list($res,$errmsg) = $funcs->CartReq($this,$utils,$params,$idata,$cart);
				if ($res) {
					$utils->SaveCart($cart,$cache,$params);
					$minpay = 1.0; //TODO support selectable min. payment
					if (!$cartwasempty) { //cart now has >1 item
						$params['resume'][] = $params['action']; //cancel-back-to-here
						$params['task'] = 'finish'; //button-labels will be 'cancel','continue'
						$newparms = $utils->FilterParameters($params,$localparams);
						//divert to cart display then probably to payment form
						$this->Redirect($id,'opencart',$params['returnid'],$newparms);
						exit;
					} else //cart now has 1 item
						if ($payable >= $minpay
							&& $rights && !empty($rights['postpay'])) { //booker must pre-pay
						$params['resume'][] = $params['action'];
						$newparms = $utils->FilterParameters($params,$localparams);
						//divert to payment form if possible, and from there, FinishReq()
						$utils->OpenPaymentForm($this,$id,$returnid,$newparms,$idata,$cart);
						//if we're back here, there's a problem
						$tplvars['message'] = $this->Lang('err_system');
					} else { //the cart item is non-[pre-]payable
						list($res,$msg) = $funcs->FinishReq($this,$utils,$params,TRUE);
						if ($res && !$msg) {
							$key = ($rights && !empty($rights['record'])) ? 'booking_feedback2':'booking_feedback';
							$msg = $this->Lang($key);
						}
						$params['message'] = $msg;
						$newparms = $utils->FilterParameters($params,$localparams);
						$this->Redirect($id,'announce',$params['returnid'],$newparms);
						exit;
					}
				} else { //problem adding cart item
					$tplvars['message'] = $errmsg;
				}
			} else { //invalid password for account
				sleep(2); //impede brute-forcers
				$tplvars['message'] = $this->Lang('invalid_type',$this->Lang('password'));
			}
		} else {
			$tplvars['message'] = $this->Lang('err_late');
		}
		$bdata['slotstart'] = $params['slotstart'];
		$bdata['slotlen'] = $params['slotlen'];
	} else { //problem(s) with the request data
		$tplvars['message'] = implode('<br >',$errmsg);
//TODO		$bdata['slotstart'] = ;
//		$bdata['slotlen'] = ;
	}
}
/* no UI for this
elseif (isset($params['find'])) { //empty $params['submit']
	$params['resume'][] = $params['action'];
	$newparms = $utils->FilterParameters($params,$localparams);
	$this->Redirect($id,'findbooking',$params['returnid'],$newparms);
}
*/

$be = $bdata['slotstart'] + $bdata['slotlen'];
$past = ($be <= $now);
//$is_new = !$past && $params['task'] == 'add' && (!$is_group || 1); //TODO || count vacant members > 0
$is_new = !$past && (!isset($params['bkgid']) || ($is_group && 1)); //TODO && count vacant members > 0

//setup for display here
$hidden = $utils->FilterParameters($params,$localparams);
//if (!empty($params['bkgid'])) not in localparams
//	$hidden['bkgid'] = $params['bkgid'];

$tplvars['startform'] = $this->CreateFormStart($id,'requestbooking',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();

//script accumulators
$jsfuncs = array();
$jsloads = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();

$jsloads[] = <<<EOS
 $('#needjs').css('display','none');

EOS;

$tplvars['needjs'] = $this->Lang('needjs');
$tplvars['title'] = $this->Lang('title_request');
$tplvars['textwhat'] = $utils->GetItemName($this,$idata);
if (!empty($idata['description'])) {
	$tplvars['desc'] = Booker\Utils::ProcessTemplateFromData($this,$idata['description'],$tplvars);
}
$urls = $utils->GetImageURLs($this,$idata['image'],$idata['name']);
if ($urls)
	$tplvars['pictures'] = $urls;

$mcount = 0;
$bcount = 0;
$groupextra = FALSE;
if (!$past) {
	if ($is_group) {
		$members = $utils->GetGroupItems($this,$item_id);
		if ($members) {
			$mcount = count($members);
			if ($mcount > 1) {
				$mname = $idata['membersname'];
				if (!$mname)
					$mname = $this->Lang('itemv_multi');
				$tplvars['membermsg'] = $this->Lang('help_memcount',$mcount,$mname);
			}

			$nowbooked = array();
			$funcs = new Booker\Bookingops();
			$allbooked = $funcs->GetBooked($this,$members,$bdata['slotstart'],$bdata['slotstart']+$bdata['slotlen']-1);
			$bcount = count($allbooked); //TODO maybe Schedule::ItemVacantCount()
			foreach ($allbooked as $one) {
				$what = $one['what'] ? $one['what']:$utils->GetItemNameForID($this,$one['item_id']);
				$nowbooked[$what] = $one['name'];
			}
			$groupextra = $mcount > count($nowbooked);
		}
	}
}

$tplvars['mustmsg'] = '<img src="'.$baseurl.'/images/information.png" alt="icon" border="0" /> '.
	$this->Lang('title_must');

$choosend = ($idata['bookcount'] != 1);
$hidden = array();

$dtw = new DateTime('@'.$now,NULL);
$example = $utils->IntervalFormat($this,$dtw,$idata['dateformat'],TRUE);
$overday = ($utils->GetInterval($this,$item_id,'slot') >= 84600);
if (!$overday)
	$example .= ' '.$dtw->format($idata['timeformat']);

if (isset($params['when'])) {
	$lvl = error_reporting(0);
	$res = $dtw->modify($params['when']);
	error_reporting($lvl);
	if (!$res) {
		$dtw->setTimestamp($bdata['slotstart']);
	}
} else {
	$dtw->setTimestamp($bdata['slotstart']);
}
$when = $utils->IntervalFormat($this,$dtw,$idata['dateformat'],TRUE).' '.$dtw->format($idata['timeformat']);

$dte = clone $dtw;
if (isset($params['until'])) {
	$lvl = error_reporting(0);
	$res = $dte->modify($params['until']);
	error_reporting($lvl);
	if (!$res) {
		$dte->setTimestamp($be);
	}
} elseif (isset($params['when'])) {
	$dte->setTimestamp($dtw->getTimestamp() + $bdata['slotlen']);
} else {
	$dte->setTimestamp($be);
}
$until = $utils->IntervalFormat($this,$dte,$idata['dateformat'],TRUE).' '.$dte->format($idata['timeformat']);

if ($past) {
	$tplvars['currentmsg'] = $this->Lang('nopastdesc');
} else {
	if (/*isset($params['bkgid']) && */$bcount) {
		if ($is_group) {
			$tplvars['nowbooked'] = $nowbooked;
			$d = $this->Lang('currentdesc3').Booker\Utils::ProcessTemplate($this,'currentbookings.tpl',$tplvars);
		} elseif ($choosend)
			$d = $this->Lang('currentdesc2',$bdata['name'],$when,$until);
		else
			$d = $this->Lang('currentdesc',$bdata['name'],$when);
		$tplvars['currentmsg'] = $d;
	}
}

$items = array();

if ($past) {
	$ob = $this->Lang('reqnotice');
} elseif (isset($params['bkgid']) && $bcount) { // !$past && set $params['bkgid']
	if ($groupextra) {
		$choices = array($this->Lang('reqadd')=>Booker::STATNEW);
		$sel = Booker::STATNEW;
	} else {
		$choices = array();
		$sel = Booker::STATCHG;
	}
	$choices = $choices + array(
		$this->Lang('reqchange')=>Booker::STATCHG,
		$this->Lang('reqdelete')=>Booker::STATDEL,
		$this->Lang('reqnotice')=>Booker::STATTELL
	);
	$ob = $this->CreateInputRadioGroup($id,'requesttype',$choices,$sel,'','<br />');
} else { //!$past && !set $params['bkgid']
	$ob = FALSE; //$this->Lang('title_request2',$idata['name']);
}

if ($ob) {
	$oneset = new stdClass();
	$oneset->class = NULL;
	$oneset->ttl = $this->Lang('title_request1');
	$oneset->mst = NULL;
	$oneset->inp = $ob;
	$items[] = $oneset;
}

if (isset($tplvars['membermsg'])) {
	$oneset = new stdClass();
	$oneset->class = NULL;
	$oneset->ttl = $this->Lang('title_howmany',$mname);
	if ($past) {
		$oneset->mst = NULL;
		$oneset->inp = $mcount;
	} elseif ($mcount > 1) {
		$oneset->mst = 1;
		$oneset->inp = $this->CreateInputText($id,'subgrpcount',1,3,5);
	}
	$items[] = $oneset;
}

$xl1 = strlen($example)+1;
$example = $this->Lang('tip_enter',$example);
$xl2 = strlen($example);
$oneset = new stdClass();
$oneset->class = NULL;
$t = ($past) ? 'title_started':'title_starting';
$oneset->ttl = $this->Lang($t);
$oneset->mst = !$past;
if ($past) {
    $hidden[] = $this->CreateInputHidden($id,'when',$when); //these always needed
	$oneset->inp = $when;
} else {
	$t = $this->CreateInputText($id,'when',$when,$xl2,$xl1,'title="'.$example.'"');
	$oneset->inp = str_replace('class="','class="dateinput ',$t);
}
$items[] = $oneset;

if ($choosend) {
	$oneset = new stdClass();
	$oneset->class = NULL;
	$t = ($past) ? 'title_ended':'title_ending';
	$oneset->ttl = $this->Lang($t);
	$oneset->mst = !$past;
	if ($past) {
		$hidden[] = $this->CreateInputHidden($id,'until',$until);
		$oneset->inp = $until;
	} else {
		$t = $this->CreateInputText($id,'until',$until,$xl2,$xl1,'title="'.$example.'"');
		$oneset->inp = str_replace('class="','class="dateinput ',$t);
	}
	$items[] = $oneset;
} else {
	$hidden[] = $this->CreateInputHidden($id,'until',$until); //always needed
}

//alternative for disabled registered-user UI
$hidden[] = $this->CreateInputHidden($id,'account','');
$hidden[] = $this->CreateInputHidden($id,'passwd','');
$hidden[] = $this->CreateInputHidden($id,'contactnew','');
/* DISABLE registered-user UI for now
$choices = array($this->Lang('title_registered')=>1,
	$this->Lang('title_occasional')=>2);
$t = (!empty($params['bookertype'])) ? (int)$params['bookertype']:1;
$ob = $this->CreateInputRadioGroup($id,'bookertype',$choices,$t,'','|||');
$btns = explode('|||',$ob);

$oneset = new stdClass();
$oneset->class = 'reqtitle';
$oneset->ttl = $btns[0];
$oneset->mst = NULL;
$oneset->inp = NULL;
$items[] = $oneset;
$t = ($t==1) ? 2:1;
$jsloads[] = <<<EOS
 $('.hide{$t}').css('visibility','collapse');
 $('#{$id}bookertype1').click(function() {
  $('.hide2').css('visibility','collapse');
  $('.hide1').css('visibility','visible');
 });

EOS;

$oneset = new stdClass();
$oneset->class = 'hide1';
$oneset->ttl = $this->Lang('title_account');
$oneset->mst = 1;
$t = (!empty($params['account'])) ? $params['account']:'';
$oneset->inp = $this->CreateInputText($id,'account',$t,15,20);
$items[] = $oneset;

$oneset = new stdClass();
$oneset->class = 'hide1';
$oneset->ttl = $this->Lang('title_passwd');
$oneset->mst = 1;
$t = (!empty($params['passwd'])) ? $params['passwd']:'';
$oneset->inp = $this->CreateInputText($id,'passwd',$t,15,20);
$items[] = $oneset;
$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/include/jquery-inputCloak.min.js"></script>';
$jsloads[] = <<<EOS
 $('#{$id}passwd').inputCloak({
  type:'see4',
  symbol:'\u25CF'
 });

EOS;

$oneset = new stdClass();
$oneset->class = 'hide1';
$oneset->ttl = $this->Lang('title_contacthow');
$oneset->mst = NULL;
$t = (!empty($params['contactnew'])) ? $params['contactnew']:'';
$oneset->inp = $this->CreateInputText($id,'contactnew',$t,30,50);
$items[] = $oneset;

$oneset = new stdClass();
$oneset->class = 'reqtitle';
$oneset->ttl = $btns[1];
$oneset->mst = NULL;
$oneset->inp = NULL;
$items[] = $oneset;
$jsloads[] = <<<EOS
 $('#{$id}bookertype2').click(function() {
  $('.hide1').css('visibility','collapse');
  $('.hide2').css('visibility','visible');
 });

EOS;
*/

$oneset = new stdClass();
$oneset->class = 'hide2';
$oneset->ttl = $this->Lang('title_sender');
$oneset->mst = 1;
$t = (!empty($params['name'])) ? $params['name']:'';
$oneset->inp = $this->CreateInputText($id,'name',$t,20,30); //name must conform to verifier js
$items[] = $oneset;

$oneset = new stdClass();
$oneset->class = 'hide2';
$oneset->ttl = $this->Lang('title_contacthow');
$oneset->mst = 1;
$t = (!empty($params['contact'])) ? $params['contact']:'';
$oneset->inp = $this->CreateInputText($id,'contact',$t,30,50);
$items[] = $oneset;

$oneset = new stdClass();
$oneset->class = NULL;
$oneset->ttl = $this->Lang('title_comment');
$oneset->mst = NULL;
$t = (!empty($params['comment'])) ? $params['comment']:'';
$oneset->inp = $this->CreateTextArea(FALSE,$id,$t,'comment','','','','',55,5,'','','style="width:22em;height:2em;"');
$items[] = $oneset;

$ob = cms_utils::get_module('Captcha');
if ($ob) {
	$oneset = new stdClass();
	$oneset->class = NULL;
	$oneset->ttl = $this->Lang('title_captcha');
	$oneset->mst = 1;
	$t = $this->CreateInputText($id,'captcha','',7,8);
	$t = preg_replace('~class="(.*)"~U','class="\\1 captcha"',$t);
	$oneset->inp = $t .'  '.$ob->getCaptcha();
	$items[] = $oneset;
}

$tplvars['tablerows'] = $items;
$tplvars['hidden'] = implode($hidden);

$tplvars['cartmsg'] = '<img src="'.$baseurl.'/images/information.png" alt="icon" border="0" /> '.
$this->Lang('help_cart');

$jsincs[] =<<<EOS
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/php-date-formatter.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.watermark.min.js"></script>
EOS;

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
$datetimefmt = $utils->DateTimeFormat(FALSE,FALSE,TRUE,!$overday,$idata['dateformat'],$idata['timeformat']);

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
 });
 var pk = $('#{$id}when').data('pikaday');
 if (pk) {
  pk._o.onClose = function() {
   if ('_d' in this && this._d) {
    var ob = new Date(this._d.getTime() + {$bdata['slotlen']}*1000);
    var dt = fmt.formatDate(ob,'{$datetimefmt}');
    $('#{$id}until').val(dt);
   }
  };
 }
 setTimeout(function() {
  $('.dateinput').watermark();
 },10);

EOS;

$funcs = new Booker\Verify();
$checkdates = !($past || (isset($params['bkgid']) && !$groupextra)); //$bcount?
$jsfuncs[] = $funcs->VerifyScript($this,$utils,$id,$item_id,$checkdates,FALSE,$idata['timezone'],FALSE);
//for email-validator
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/mailcheck.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/levenshtein.min.js"></script>

EOS;

$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
$tplvars['cart'] = $this->CreateInputSubmit($id,'cart',$this->Lang('cart'),
	'title="'.$this->Lang('tip_cartadd').'"');
//$tplvars['register'] = $this->CreateInputSubmit($id,'register',$this->Lang('register'),
//	'title="'.$this->Lang('tip_register').'"');
$tplvars['register'] = NULL;

$jsloads[] = <<<EOS
 $('#{$id}submit').bind('click',validate);
 $('#{$id}cart').bind('click',validate);

EOS;

$tplvars['choose'] = NULL; /*$utils->GetItemPicker($this,$id,'itempick',$params['firstpick'],$item_id);
if ($tplvars['choose']) {
	$jsloads[] = <<<EOS
 $('#{$id}itempick').change(function() {
  $(this).closest('form').trigger('submit');
 });

EOS;
}
*/

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
$customcss = $utils->GetStylesURL($this,$item_id);
if ($customcss) {
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />{/if}
EOS;
}
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
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,'requestbooking.tpl',$tplvars);
