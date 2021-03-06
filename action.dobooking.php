<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: dobooking
# Request/record/cart/delete a booking
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/*
$params[]:
if arrive via redirect
'returnid'
'bookat'=> int 1473591600 or -1 to choose or missing if choosing an item (and time)
'item' => -1 to choose or missing
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
//	'bookat', used after cancellation
	'bookertype',
	'cancel',
	'captcha',
	'cart',
	'change',
	'comment',
	'contact',
	'contactnew',
	'delete',
	'origreturnid',
	'name',
	'passwd',
	'recover',
	'register',
	'request',
	'requesttype',
	'subgrpcount',
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

if (isset($params['register']) || isset($params['recover']) || isset($params['change'])) {
	$params['resume'][] = $params['action']; //cancellation comes back here
	$newparms = $utils->FilterParameters($params,$localparams);
	if (isset($params['register'])) {
		$newparms['task'] = 'register';
	} elseif (isset($params['recover'])) {
		$newparms['task'] = 'recover';
	} else { //isset($params['change'])
		$newparms['task'] = 'change';
	}
	$this->Redirect($id,'auth',$params['returnid'],$newparms);
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
$idata = $utils->GetItemProperties($this,$item_id,array(
'name',
'description',
'image',
'membersname',
'available',
'slottype','slotcount',
'leadtype','leadcount',
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
'subgrpdata',
'bulletin'
),TRUE);
if ($idata) {
	$idata['item_id'] = $item_id;
} else {
	$tplvars = array(
		'title_error' => $this->Lang('error'),
		'message' => $this->Lang('err_data'),
		'pagenav' => NULL
	);
	echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
	return;
}

$utils->DecodeParameters($params,
	array('account','comment','contact','contactnew','name','passwd'));

$is_group = ($item_id >= Booker::MINGRPID);
$now = $utils->GetZoneTime($idata['timezone']);

if (isset($params['cart'])) {
	$funcs = new Booker\Verify();
	//$is_new = !$past && $params['task'] == 'add' && (!$is_group || 1); //TODO || count vacant members > 0
	//$is_new = ($params['task'] == 'add'); //TODO refine ASAP per start-time, available slots etc
	$is_new = TRUE;
	list($res,$errmsg) = $funcs->VerifyData($this,$utils,$params,$item_id,$is_new,FALSE);
	if ($res) {
		$bs = $params['slotstart'];
		$be = $bs + $params['slotlen']; //NOT 1-past-end
		if (!$is_new || $be > $now) { //$bs > $now - 300 //some slop
			$funcs = new Booker\Userops($this);
			list($bookerid,$newbooker) = $funcs->GetParamsID($this,$params); //TODO conform $params
			if ($bookerid !== FALSE) {
				$params['booker_id'] = $bookerid;
				$funcs = new Booker\Payment();
				//determine how much to be paid (ignoring tax)
				//ignore $params['subgrpcount'] cuz it's processed in the cart
				$params['fee'] = $funcs->UsageFee($this,$utils,$item_id,$bookerid,$bs,$be);

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
		$be = $bs + $params['slotlen']; //NOT 1-past-end
		if (!$is_new || $be > $now) { // $bs > $now - 300 some slop ?
			$funcs = new Booker\Userops($this);
			list($bookerid,$newbooker) = $funcs->GetParamsID($this,$params); //TODO conform $params
			if ($bookerid !== FALSE) {
				$params['booker_id'] = $bookerid;
				$rights = $funcs->GetRights($this,$bookerid); //before we change $funcs
				$passreset = !empty($params['account']) &&
					$funcs->GetForced($this,$params['account']);
				$funcs = new Booker\Payment();
				//determine how much to be paid (ignoring tax)
				$payable = $funcs->UsageFee($this,$utils,$item_id,$bookerid,$bs,$be);
				$params['fee'] = $payable; //ignore $params['subgrpcount'] cuz it's handled in the cart
				$minpay = $this->GetPreference('minpay');
				if ($payable > $minpay || ($minpay > 0 && $payable == $minpay)) {
					$owed = $funcs->AmountFormat($this,$utils,$item_id,$payable);
				} else {
					$owed = FALSE;
				}
				$cache = Booker\Cache::GetCache($this);
				$cart = $utils->RetrieveCart($cache,$params);
				$cartwasempty = $cart->seemsEmpty();
				//add item to cart
				$funcs = new Booker\Requestops();
				list($res,$errmsg) = $funcs->CartReq($this,$utils,$params,$idata,$cart);
				if ($res) {
					$utils->SaveCart($cart,$cache,$params);
					if ($passreset) {
						$params['resume'][] = $params['action']; //cancellation comes back here
						$params['task'] = 'reset';
						$params['bulletin'] = htmlspecialchars(
							'<p style="color:#F00">'.$this->Lang('reset_subtitle').'</p>', ENT_XHTML);
						$newparms = $utils->FilterParameters($params,$localparams);
						$this->Redirect($id,'auth',$params['returnid'],$newparms);
					}
					if (!$cartwasempty) { //cart now has >1 item
						$params['resume'][] = 'announce';
						$params['task'] = 'finish'; //button-labels will be 'cancel','continue'
						$newparms = $utils->FilterParameters($params,$localparams);
						//divert to cart display then probably to payment form
						$this->Redirect($id,'opencart',$params['returnid'],$newparms);
						exit;
					} else //cart now has 1 item
						if ($owed && $rights && empty($rights['postpay'])) { //booker must pre-pay
						$params['resume'][] = 'announce';
						$newparms = $utils->FilterParameters($params,$localparams);
						//divert to payment form if possible, and from there, FinishReq()
						$utils->OpenPaymentForm($this,$id,$returnid,$newparms,$idata,$cart);
						//if we're back here, there's a problem
						array_pop($params['resume']);
						$tplvars['message'] = $this->Lang('err_system');
					} else { //the cart item is non-[pre-]payable
//						if (!$is_new) { $params['bkg_id'] = TODO; }
						$params['amount'] = 0.0;
						list($res,$msg) = $funcs->FinishReq($this,$utils,$params,TRUE);
						if ($res && !$msg) {
							$key = ($rights && !empty($rights['record'])) ? 'booking_feedback2':'booking_feedback';
							$msg = $this->Lang($key);
						}
						if ($res && $owed) {
							$msg .= '<br />'.$this->Lang('booking_feedback3',$owed);
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
} elseif (isset($params['delete'])) {
	$funcs = new Booker\Verify();
	list($res,$errmsg) = $funcs->VerifyData($this,$utils,$params,$item_id,FALSE,FALSE);
	if ($res) {
		$funcs = new Booker\Userops($this);
		list($bookerid,$newbooker) = $funcs->GetParamsID($this,$params);
		if (!($bookerid === FALSE || $newbooker)) {
			$passreset = !empty($params['account']) &&
				$funcs->GetForced($this,$params['account']);
			if ($passreset) {
				$params['resume'][] = $params['action']; //cancellation comes back here
				$params['task'] = 'reset';
				$params['bulletin'] = htmlspecialchars(
					'<p style="color:#F00">'.$this->Lang('reset_subtitle').'</p>', ENT_XHTML);
				$newparms = $utils->FilterParameters($params,$localparams);
				$this->Redirect($id,'auth',$params['returnid'],$newparms);
			}
			$record = $funcs->HasRight($this,$bookerid,'record'); //before $funcs changes

			$funcs = new Booker\Schedule();
			$bs = $params['slotstart'];
			$be = $bs + $params['slotlen'];
			$data = $funcs->CountBooked($this,$item_id,$bs,$be,$bookerid);
			if ($data) {
				$funcs = new Booker\BookingChange();
				$alldone = TRUE;
				$allmsg = array();
				$bids = array_column($data,'bkg_id');
				$cids = array_count_values($bids);
				foreach ($cids as $bkg_id=>$num) {
					if ($params['subgrpcount'] < $num) {
						$num = (int)$params['subgrpcount'];
					}
					if ($num < 1) {
						$num = 1;
					}
					$reqdata = array(
						'bkg_id' => $bkg_id,
						'booker_id' => $bookerid,
						'item_id' => $item_id,
						'subgrpcount' => $num,
						'slotstart' => $bs,
						'slotlen' => $params['slotlen'],
						'comment' => $params['comment']
					);
					list($res,$msg) = $funcs->CancelBkg($this,$utils,$item_id,$reqdata,$record);
					if (!$res) {
						$alldone = FALSE;
					}
					if ($msg) {
						$allmsg[] = $msg;
					}
				}

				if ($allmsg) {
					$params['message'] = implode('<br />',$allmsg);
				} elseif ($alldone) {
					$params['message'] = $this->Lang('bookings_deleted',count($bids)); //TODO better info
				} else {
					$params['message'] = $this->Lang('error'); //TODO more info
				}
				$newparms = $utils->FilterParameters($params,$localparams);
				$this->Redirect($id,'announce',$params['returnid'],$newparms);
				exit;
			} else {
				$tplvars['message'] = $this->Lang('nodata');
			}
		} else {
			$tplvars['message'] = $this->Lang('err_account'); //or ('invalid_type',$this->Lang('booker'));
		}
		sleep(2); //impede brute-forcers
	} else { //problem(s) with the request data
		$tplvars['message'] = implode('<br >',$errmsg);
	}
//TODO		$bdata['slotstart'] = ;
//		$bdata['slotlen'] = ;
/* no UI for this elseif (isset($params['find'])) {
	$params['resume'][] = $params['action'];
	$newparms = $utils->FilterParameters($params,$localparams);
	$this->Redirect($id,'findbooking',$params['returnid'],$newparms);
*/
}

if (!isset($params['when'])) { //first-pass
	if (empty($params['bkgid'])) { //not activated slot with current booking(s)
		$bdata = array();
		if (isset($params['bookat'])) {
			if ($params['bookat'] > 0) {
				$bdata['slotstart'] = $params['bookat'];
			} else {
				//set nowish start-time as fallback
				$slen = $utils->GetInterval($this,$item_id,'slot');
				$bdata['slotstart'] = (int)($now/$slen) * $slen + $slen + 3600;
			}
		} elseif (isset($params['showfrom'])) {
			$bdata['slotstart'] = $params['showfrom'] + 10*3600; //TODO
		} else {
			$bdata['slotstart'] = time(); //TODO
		}
		$bdata['slotlen'] = $utils->GetInterval($this,$item_id,'slot');
		$bdata['what'] = $utils->GetItemNameForID($this,$item_id);
	} else {
		//get some useful representative data
		$bkgid = $params['bkgid'];
		$sql = <<<EOS
SELECT O.*,I.name AS what,COALESCE(A.name,B.name,'') AS name,COALESCE(A.address,B.address,'') AS address,B.publicid,B.phone
FROM $this->OnceTable O
JOIN $this->ItemTable I ON O.item_id=I.item_id
JOIN $this->BookerTable B ON O.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.publicid=A.publicid
WHERE O.bkg_id=?
EOS;
		$bdata = $utils->SafeGet($sql,array($bkgid),'row');
		if ($bdata) {
			$utils->GetUserProperties($this,$bdata);
		}
	}
} else {
	$bdata = array();
	$dtw = new DateTime('@0',NULL);
	$lvl = error_reporting(0);
	$res = $dtw->modify($params['when']);
	if ($res) {
		$bdata['slotstart'] = $dtw->getTimestamp();
	} else {
		$bdata['slotstart'] = $now;
	}
	$res = $dtw->modify($params['when']);
	error_reporting($lvl);
	if ($res) {
		$t = $dtw->getTimestamp() - $bdata['slotstart'];
		if($t < 0)
			$t = 3600;
		$bdata['slotlen'] = $t;
	} else {
		$bdata['slotlen'] = 3600;
	}
}

$be = $bdata['slotstart'] + $bdata['slotlen'];
$past = ($be <= $now);
//$is_new = !$past && $params['task'] == 'add' && (!$is_group || 1); //TODO || count vacant members > 0
$is_new = !$past && (!isset($params['bkgid']) || ($is_group && 1)); //TODO && count vacant members > 0

//setup for display here
$hidden = $utils->FilterParameters($params,$localparams);
//if (!empty($params['bkgid'])) not in localparams
//	$hidden['bkgid'] = $params['bkgid'];

$tplvars['startform'] = $this->CreateFormStart($id,'dobooking',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();

$t = $idata['bulletin'];
$tplvars['bulletin'] = ($t) ? $t:NULL;

//script accumulators
$jsfuncs = array();
$jsloads = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();

$jsloads[] = <<<'EOS'
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
$fcount = 0;
if (!$past) {
	if ($is_group) {
		$members = $utils->GetGroupItems($this,$item_id);
		$mcount = count($members);
		if ($mcount > 0) {
			$funcs = new Booker\Schedule();
			$fcount = $funcs->ItemVacantCount($this,$item_id,$bdata['slotstart'],$be-1);
			$bcount = $mcount - $fcount;
		}
		$mname = $idata['membersname'];
		if (!$mname) {
			$mname = $this->Lang('itemv_multi');
		}
	} else {
		$funcs = new Booker\Schedule();
		$fcount = $funcs->ItemVacantCount($this,$item_id,$bdata['slotstart'],$be-1);
		$bcount = $mcount - $fcount;
	}
}

$tplvars['mustmsg'] = '<img src="'.$baseurl.'/images/info-small.png" alt="info icon" border="0" /> '.
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
} elseif ($is_group) {
	if ($mcount > 1 && $mcount == $fcount) { //>1 member, all available
		$d = $this->Lang('currentdesc4',$mcount);
	} elseif ($mcount > 1 && $fcount > 1) { //>1 member, >1 available
		$d = $this->Lang('currentdesc5',$fcount,$mcount);
	} elseif ($fcount == 1) { //any members, 1 available
		$d = $this->Lang('currentdesc6',$mcount);
	} else { //any members, 0 available
		$d = $this->Lang('currentdesc7',$mcount);
	}
} elseif ($bcount) {
	$what = ($bdata['what']) ? $bdata['what'] : $utils->GetItemNameForID($this,$item_id);
	if ($choosend) {
		$t = $this->Lang('currentdesc2',$what,$when,$until);
	} else {
		$t = $this->Lang('currentdesc',$what);
	}
	$tplvars['currentmsg'] = $t;
}

$items = array();

if ($past) {
	$ob = $this->Lang('reqnotice');
} elseif (isset($params['bkgid']) && $bcount) { // !$past && set $params['bkgid']
	if ($fcount > 0) {
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

if ($mcount > 0) {
	$oneset = new stdClass();
	$oneset->class = NULL;
	$oneset->ttl = $this->Lang('title_howmany',$mname);
	if ($past) {
		$oneset->mst = NULL;
		$oneset->inp = $d;
	} elseif ($mcount > 1) {
		$oneset->mst = 1;
		$t = (empty($params['subgrpcount'])) ? 1 : $params['subgrpcount'];
		$oneset->inp = $this->CreateInputText($id,'subgrpcount',$t,3,5).' '.$d;
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
//$hidden[] = $this->CreateInputHidden($id,'account','');
//$hidden[] = $this->CreateInputHidden($id,'passwd','');
//$hidden[] = $this->CreateInputHidden($id,'contactnew','');
//* DISABLE registered-user UI for now
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
  $('#{$id}delete').prop('disabled',false);
 });
EOS;

$amod = cms_utils::get_module('Auther');
if ($amod) {
	$afuncs = new Auther\Auth($amod,$this->GetPreference('authcontext',0));
	unset($amod);
	$rec = $afuncs->IsRecoverable();
} else {
	$rec = FALSE;
}

$oneset = new stdClass();
$oneset->class = 'hide1';
$oneset->ttl = $this->Lang('title_account');
$oneset->mst = 1;
$t = (!empty($params['account'])) ? $params['account']:'';
$oneset->inp = $this->CreateInputText($id,'account',$t,20,30);
$items[] = $oneset;

$oneset = new stdClass();
$oneset->class = 'hide1';
$oneset->ttl = $this->Lang('title_passwd');
$oneset->mst = !$rec;
$t = (!empty($params['passwd'])) ? $params['passwd']:'';
$oneset->inp = $this->CreateInputText($id,'passwd',$t,20,40);
$items[] = $oneset;
$oneset = new stdClass();
$oneset->class = 'hide1';
$oneset->ttl = '';
$oneset->mst = NULL;
$oneset->inp = ($rec) ?
 $this->_CreateInputLinks($id,'bkr_recover',FALSE,TRUE,$this->Lang('recover_lost')):
 $this->Lang('reregister');
$items[] = $oneset;
$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/lib/js/jquery-inputCloak.min.js"></script>';
$jsloads[] = <<<EOS
 $('#{$id}passwd').inputCloak({
  type:'see4',
  symbol:'\u25CF'
 });
EOS;

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
  $('#{$id}delete').prop('disabled',true);
 });
EOS;
//*/

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

$t = $this->Lang('tip_info');
$tplvars['carticon'] = '<a href="" onclick="return helptogl(this);"><img src="'
	.$baseurl.'/images/info-small.png" alt="info-toggle icon" title="'.$t.'" border="0" /></a>';
$tplvars['carthelp'] = $this->Lang('help_cart');

$jsfuncs[] = <<<EOS
function helptogl(el) {
 var \$cd = $(el).closest('div'),
  \$hd = \$cd.next()
 if (\$hd[0].style.display != 'none') {
  \$cd.css('float','');
  \$hd.slideUp(200);
 } else {
  \$cd.css('float','left');
  \$hd.slideDown(200);
 }
 return false;
}
EOS;

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.watermark.min.js"></script>
EOS;

$datetimefmt = $utils->DateTimeFormat(FALSE,FALSE,TRUE,!$overday,$idata['dateformat'],$idata['timeformat']);
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

$jsloads[] = <<<EOS
 var fmt = new DateFormatter({
  longDays: [$dnames],
  shortDays: [$sdnames],
  longMonths: [$mnames],
  shortMonths: [$smnames],
  meridiem: [$meridiem],
  ordinal: function (number) {
   var n = number % 10,
   suffixes = {
    1: 'st',
    2: 'nd',
    3: 'rd'
   };
   return Math.floor(number % 100 / 10) === 1 || !suffixes[n] ? 'th' : suffixes[n];
  }
 });
 $('.dateinput').pikaday({
  format: '$datetimefmt',
  reformat: function(target,f) {
   return fmt.formatDate(target,f);
  },
  getdate: function(target,f) {
   return fmt.parseDate(target,f);
  },
  i18n: {
   previousMonth: '$prevm',
   nextMonth: '$nextm',
   months: [$mnames],
   weekdays: [$dnames],
   weekdaysShort: [$sdnames]
  }
 });
 var pk = $('#{$id}when').data('pikaday');
 if (pk) {
  pk._o.onClose = function() {
   if ('_d' in this && this._d) {
    var ob = new Date(this._d.getTime() + {$bdata['slotlen']} * 1000);
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
$checkdates = !($past || (isset($params['bkgid']) && $fcount == 0)); //$bcount?
$jsfuncs[] = $funcs->VerifyScript($this,$utils,$id,$item_id,$checkdates,FALSE,$idata['timezone'],FALSE);
//for email-validator & alerter
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/mailcheck.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/levenshtein.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;

$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
$tplvars['cart'] = $this->CreateInputSubmit($id,'cart',$this->Lang('cart'),
	'title="'.$this->Lang('tip_cartadd').'"');
$tplvars['delete'] = $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
	'title="'.$this->Lang('tip_delbooking').'"');
$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
$tplvars['register'] = $this->CreateInputSubmit($id,'bkr_register',$this->Lang('register'),
	'title="'.$this->Lang('tip_register').'"');
$tplvars['change'] = $this->CreateInputSubmit($id,'bkr_change',$this->Lang('change'),
	'title="'.$this->Lang('tip_change').'"');
//$tplvars['register'] = NULL;

$jsloads[] = <<<EOS
 $('#{$id}submit,#{$id}cart,#{$id}delete').bind('click',validate);
 $('#{$id}delete').click(function() {
  var btn = this;
  $.alertable.confirm('{$this->Lang('confirm_cancel')}',{
   okName: '{$this->Lang('yes')}',
   cancelName: '{$this->Lang('no')}'
  }).then(function() {
   $(btn).trigger('click.deferred');
  });
  return false;
 });
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
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/alertable.min.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.min.css" />
EOS;
$customcss = $utils->GetStylesURL($this,$item_id);
if ($customcss) {
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
EOS;
}
//heredoc-var newlines are a problem for in-js quoted strings! so ...
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

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'dobooking.tpl',$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
