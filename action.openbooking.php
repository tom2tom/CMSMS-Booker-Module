<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openbooking
# View or edit bookings data for resource or group
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*first-time redirect, $params[] =
'item_id' => string
'bkg_id' => string
'resume' => string
'task'=>'see' OR 'edit'
'repeat' IF APPROPRIATE
upon return, $params[] =
'item_id'
'bkg_id'
'resume'
'task'
'repeat' IF APPROPRIATE
'booker_id' MAYBE
'when' IF APPROPRIATE
'until' IF APPROPRIATE
'formula' IF APPROPRIATE
'subgrpcount'
'name'
'contact'
'displayclass'
'feepaid' IF APPROPRIATE
'status'
one of:
'submit'
'cancel'
'find'
*/

if (!function_exists('BookingRedirParms')) {
 function BookingRedirParms($resume, &$params, $msg = FALSE)
 {
	$pnew = array_intersect_key($params,[
	'item_id'=>1,
	'booker_id'=>1,
	'task'=>1,
	'active_tab'=>1]);
	if (!empty($params['resume']))
		$pnew['resume'] = json_encode($params['resume']);
	switch ($resume) {
	 case 'defaultadmin':
		$t = ($params['item_id'] < Booker::MINGRPID) ? 'items':'groups';
		$pnew['active_tab'] = $t;
		break;
//	 case 'itembookings':
//		break;
//	 case 'bookerbookings':
//		break;
	 default:
	}
	if ($msg)
		$pnew['message'] = $msg;
	return $pnew;
 }
}

$pmod = ($params['task'] == 'edit' || $params['task'] == 'add');
if (!$this->_CheckAccess('admin')) {
	if ($pmod && !$this->_CheckAccess('book')) exit;
	if (!$pmod && !$this->_CheckAccess('view')) exit;
}

$utils = new Booker\Utils();
$utils->DecodeParameters($params,[
	'bkg_id',
	'contact',
//	'displayclass',
	'formula',
	'name',
//	'status',
	'subgrpcount',
	'until',
	'when'
]);

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
}

if (isset($params['cancel'])) {
	$resume = array_pop($params['resume']);
	$newparms = BookingRedirParms($resume,$params);
	$this->Redirect($id,$resume,'',$newparms);
}

$item_id = (int)$params['item_id'];
$is_group = ($item_id >= Booker::MINGRPID);
$is_new = ($params['bkg_id'] == -1);

if (isset($params['submit'])) {
	if (!$pmod) exit;
/* $params = array including
 'item_id' => string '8'
 'bkg_id' => string '9' OR '-1'
 'repeat' => string '' OR '1'
 'resume' => string 'itembookings'
 'when' => string '17 October 2015 12:00'
 'until' => string '17 October 2015 12:59'
 'name' => string 'Mary'
 'displayclass' => string '1'
 'status' => string '3'
 'contact' => string '@myfirm'
*/
	$msg = [];
	$t = trim($params['name']);
	if ($t)
		$params['name'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('user'));
	$t = trim($params['contact']);
	if ($t)
		$params['contact'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('contact'));

	if (empty($params['subgrpcount'])) {
		if ($is_group)
			$params['subgrpcount'] = count($utils->GetGroupItems($this,$item_id));
		else
			$params['subgrpcount'] = 1;
	}
	//TODO parameter-specific handling change of status etc 'feepaid' value may include currency-symbol
	if (empty($params['repeat'])) { //onetime booking
		$vfuncs = new Booker\Verify();
		list($res,$xmsg) = $vfuncs->VerifyData($this,$utils,$params,$item_id,$is_new,TRUE);
		if ($res) {
	//TODO process according to func($params['status'])
			$oldbkg = $params['bkg_id']; //don't lose this
			$params['status'] = Booker::STATNEW;
			$funcs = new Booker\Schedule();
			if ($is_group) {
				$res = $funcs->ScheduleGroup($this,$utils,$item_id,$params);
			} else {
				$res = $funcs->ScheduleResource($this,$utils,$item_id,$params);
			}
			if ($res) {
				if ($is_new) {
					//TODO send notice if appropriate
					$is_new = FALSE;
				} else {
					$funcs = new Booker\Bookingops();
					$custommsg = 'TODO';
					$funcs->DeleteBkg($this,$oldbkg,$custommsg);
					//TODO send notice if appropriate
				}
				//TODO upsert DispTable
				$funcs = new Booker\Userops($this);
				$funcs->ConformUserData($this,$params); //TODO check - downstream expects $params['name'] NOT 'user'
			} else {
				//TODO send notice if appropriate
			}
		} else {
			$msg = array_merge($msg,$xmsg);
		}
	} else { //repeat booking
		$t = trim($params['formula']);
		if ($t) {
			$funcs = new Booker\WhenRuleLexer($this);
			$t = $funcs->CheckDescriptor($t);
		}
		if ($t) {
	//TODO process according to func($params['status'])
			$funcs = new Booker\Userops($this);
			if ($is_new) {
				list($bookerid,$newbkr) = $funcs->GetParamsID($this,$params); //TODO conform $params
				if ($bookerid) {
					$sql2 = 'bkg_id,item_id,formula,booker_id';
					$fillers = '?,?,?,?';
					$bkgid = $db->GenID($this->OnceTable.'_seq');
					$args = [
						$bkgid,
						(int)$params['item_id'],
						$t,
						$bookerid
					];
					foreach (['subgrpcount'] as $k) {
						if (isset($params[$k])) {
							$sql2 .= ",$k";
							$fillers .= ',?';
							$args[] = (int)$params[$k];
						}
					}
					//TODO $params['statpay']
					$sql = 'INSERT INTO '.$this->RepeatTable.' ('.$sql2.') VALUES ('.$fillers.')';
		//TODO $utils->SafeExec()
					$db->Execute($sql,$args);
					$params['bkg_id'] == $bkgid;
					$is_new = FALSE;
				} else {
					$msg[] = $this->Lang('invalid_type',$this->Lang('booker')); //duplicate name??
					$params['status'] = Booker::STATERR; //TODO
				}
			} else { //update
				$funcs->ConformUserData($this,$params);
				$sql2 = 'formula=?';
				$args = [$t];
				foreach (['subgrpcount'] as $k) {
					if (isset($params[$k])) {
						$sql2 .= ",$k=?";
						$args[] = (int)$params[$k];
					}
				}
				//TODO $params['statpay']
				$args[] = (int)$params['bkg_id'];
				$sql = 'UPDATE '.$this->RepeatTable.' SET '.$sql2.' WHERE bkg_id=?';
	//TODO $utils->SafeExec()
				$db->Execute($sql,$args);
				$dtw = new DateTime('now',new DateTimeZone('UTC'));
				$dtw->setTime(0,0,0);
				if ($is_group) {
					$membrs = $utils->GetGroupItems($this,$item_id);
					if ($membrs) {
						$dtw->modify('-1 day');
						$st = $dtw->getTimestamp();
	//TODO checkedfrom too
						$sql = 'UPDATE '.$this->RepeatTable.' SET checkedto=? WHERE bkg_id IN ('.
							implode(',',$membrs).') AND checkedto>?';
	//TODO $utils->SafeExec()
						$db->Execute($sql,[$st,$st]);
						$dtw->modify('+1 day');
					}
				}
				//remove future derived booking-slots
				$dtw->modify('+1 day');
				$st = $dtw->getTimestamp();
				$args = [$params['bkg_id'],$st];
				$sql = 'DELETE FROM '.$this->DispTable.' WHERE bkg_id=? AND slotstart>=?';
				$utils->SafeExec($sql,$args);
//TODO send notice(s) if appropriate
			}
		} else {
			$msg[] = $this->Lang('invalid_type',$this->Lang('booking')); //formula
			$params['status'] = Booker::STATERR; //TODO
		}
	} //repeat booking

	if (!$msg) { //all ok
		$resume = array_pop($params['resume']);
		$newparms = BookingRedirParms($resume,$params);
		$this->Redirect($id,$resume,'',$newparms);
	}

	$t = implode(' ',$msg);
	if (empty($params['message'])) {
		$params['message'] = $t;
	} else {
		$params['message'] .= '<br />'.$t;
	}
	$bdata = [1]; //to be populated later
} elseif (isset($params['find'])) {
	//TODO
	$params['message'] = $this->Lang('notyet');
	$bdata = [1];
}

if (empty($bdata)) {
	$table = (empty($params['repeat'])) ? $this->OnceTable : $this->RepeatTable;
	$sql = <<<EOS
SELECT T.*,COALESCE(A.name,B.name,'') AS name,COALESCE(A.address,B.address,'') AS address,B.publicid,B.phone,B.displayclass
FROM $table T
JOIN $this->BookerTable B ON T.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.publicid=A.publicid
WHERE T.bkg_id=?
EOS;
	$bdata = $utils->SafeGet($sql,[$params['bkg_id']],'row');
	if ($bdata) {
		$utils->GetUserProperties($this,$bdata);

		$bdata['contact'] = ($bdata['address']) ? $bdata['address'] : $bdata['phone'];
		if (isset($bdata['slotstart'])) {
			$dtw = new DateTime('@'.$bdata['slotstart'],NULL);
			$bdata['when'] = $dtw->format('Y-n-j G:i'); //TODO ovrday support
			$dtw->modify('+'.$bdata['slotlen'].' seconds');
			$bdata['until'] = $dtw->format('Y-n-j G:i');
		}
	} else {
		$bdata = [
			'name'=>'',
			'contact'=>'',
			'when'=>'',
			'until'=>'',
			'formula'=>'',
			'subgrpcount'=>1,
			'displayclass'=>1,
			'status'=>Booker::STATNONE,
			'statpay'=>Booker::STATFREE,
			];
	}
} else {
	$bdata = [
		'name'=>$params['name'],
		'contact'=>$params['contact'],
		'subgrpcount' => $params['subgrpcount'],
		'displayclass'=>$params['displayclass'],
		'status'=>Booker::STATNONE,
		'statpay'=>Booker::STATFREE,
	];
	if (empty($params['repeat'])) {
		$bdata['when'] = $params['when'];
		$bdata['until'] = $params['until'];
	} else {
		$bdata['formula'] = $params['formula'];
	}
	if (array_key_exists('feepaid',$params)) {
		$bdata['feepaid'] = $params['feepaid'];
	}
	if (array_key_exists('status',$params)) {
		$bdata['status'] = $params['status'];
	}
}

$tplvars = [
	'mod'=>$pmod
];

$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);

$hidden = [
	'item_id'=>$item_id,
	'bkg_id'=>$params['bkg_id'],
	'task'=>$params['task'],
	'resume'=>$resume
];
if (!empty($params['booker_id']))
	$hidden['booker_id'] = $params['booker_id'];
if (!empty($params['repeat']))
	$hidden['repeat'] = 1;
//if (!empty($params['bookedit']))
//	$hidden['bookedit'] = 1;
$tplvars['startform'] = $this->CreateFormStart($id,'openbooking',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$idata = $utils->GetItemProperties($this,$item_id,
	['name','description','bookcount','timezone']);

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

if ($pmod) //add/edit mode
	$tplvars['compulsory'] = $this->Lang('compulsory_items');
else
	$missing = '&lt;'.$this->Lang('missing').'&gt;';

//script accumulators
$jsincs = [];
$jsfuncs = [];
$jsloads = [];
$baseurl = $this->GetModuleURLPath();

$vars = [];

if (empty($params['repeat'])) { //onetime booking
	$choosend = ($idata['bookcount'] != 1);

	$one = new stdClass();
	$one->ttl = $this->Lang('title_starting');
	$t = $bdata['when'];
	if ($pmod) {
		$one->mst = 1;
		$t = $this->CreateInputText($id,'when',$t,20,30);
		$one->inp = str_replace('class="','class="dateinput ',$t);
		//for date-picker
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
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
		$overday = ($utils->GetInterval($this,$item_id,'slot') >= 84600);
		$datetimefmt = $utils->DateTimeFormat(FALSE,TRUE,TRUE,!$overday);
		if ($choosend) {
			$sl = ($bdata && !empty($bdata['slotlen'])) ? $bdata['slotlen']:
				$utils->GetInterval($this,$item_id,'slot');
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
		} else { //!$pmod
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
	} else { //!$pmod
		$one->inp = ($t) ? $t:$missing;
	}
	$one->hlp = $this->Lang('help_book_start');
	$vars[] = $one;
//==
	if ($choosend) {
		$one = new stdClass();
		$one->ttl = $this->Lang('title_ending');
		$t = $bdata['until'];
		if ($pmod) {
			$one->mst = 0;
			$t = $this->CreateInputText($id,'until',$t,20,30);
			$one->inp = str_replace('class="','class="dateinput ',$t);
		} else {
			$one->inp = ($t) ? $t:$missing;
		}
		$one->hlp = NULL; //$this->Lang('help_book_end');
		$vars[] = $one;
	}
} else { //repeat booking
	$one = new stdClass();
	$one->ttl = $this->Lang('title_description');
	$t = $bdata['formula'];
	if ($pmod) {
		$one->mst = 1;
		$one->inp = $this->CreateInputText($id,'formula',$t,50,256);
	} else {
		$one->inp = ($t) ? $t:$missing;
	}
	$one->hlp = $this->Lang('help_intervals');
	$vars[] = $one;
}
//==
if ($is_group) {
	$one = new stdClass();
	$one->ttl = $this->Lang('title_subgrpcount');
	$t = (int)$bdata['subgrpcount'];
	if ($pmod) {
		$one->mst = 1;
		$one->inp = $this->CreateInputText($id,'subgrpcount',$t,3,5);
	} else {
		$one->inp = ($t) ? $t:$missing;
	}
	$one->hlp = $this->Lang('help_subgrpcount');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->ttl = $this->Lang('title_user');
$t = $bdata['name'];
if ($pmod) {
	$one->mst = 1;
	$one->inp = $this->CreateInputText($id,'name',$t,20,64);
	if (!$is_new)
		$one->hlp = $this->Lang('help_conformuser');
} else {
	$one->inp = ($t) ? $t:$missing;
}
$vars[] = $one;
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
if ($pmod && !$is_new) {
	$one->hlp .= '<br />'.$this->Lang('help_conformcontact');
}
$vars[] = $one;
//TOTO UI for both address & phone
//==
$one = new stdClass();
$one->ttl = $this->Lang('title_displayclass');
$t = (int)$bdata['displayclass'];
if ($pmod) {
	$one->mst = 0;
	$styles = range(1,Booker::USERSTYLES);
	$choices = array_combine($styles,$styles);
	$one->inp = $this->CreateInputDropdown($id,'displayclass',$choices,-1,$t);
} else {
	$one->inp = $t;
}
$one->hlp = $this->Lang('help_displayclass');
if ($pmod && !$is_new) {
	$one->hlp .= '<br />'.$this->Lang('help_conformstyle');
}
$vars[] = $one;
//==
$funcs = new Booker\Payment();
if ($funcs->MaybePayable($this,$utils,$item_id)) {
	$one = new stdClass();
	$one->ttl = $this->Lang('title_paid');
	if (isset($bdata['feepaid'])) {
		$t = (float)$bdata['feepaid'];
	} else {
		$t = 0;
	}
	if ($pmod) {
		$one->mst = 0;
		$one->inp = $this->CreateInputText($id,'feepaid',$t,6,8);
	} else {
		$one->inp = ($t) ? $t:$this->Lang('nil');
	}
	if (isset($bdata['slotstart'])) {
	 	$bs = $bdata['slotstart'];
		$t = $funcs->UsageFee($this,$utils,$item_id,$bdata['booker_id'],
			$bs,$bs + $bdata['slotlen']);
		$paid = $funcs->AmountFormat($this,$utils,$item_id,$t);
		$one->hlp = $this->Lang('help_feerecorded',$paid);
	}
	$vars[] = $one;
//TODO status, statpay fields
}
//==
if (empty($params['repeat'])) { //onetime booking
	$one = new stdClass();
	$one->ttl = $this->Lang('status');
	$funcs = new Booker\Status();
	$t = (int)$bdata['status'];
	if ($pmod) {
		$choices = $funcs->GetStatusChoices($this,6); //2|4
		$utils->mb_ksort($choices);
		$one->inp = $this->CreateInputDropdown($id,'status',$choices,-1,$t);
	} else {
		$one->inp = $funcs->GetStatusName($this,$t);
	}
	$vars[] = $one;
}

$tplvars['data'] = $vars;

//buttons
if ($pmod) {
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['apply'] = NULL; //no 'apply' operation for bookings
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
	$tplvars['find'] = $this->CreateInputSubmit($id,'find',$this->Lang('find'),
		'title="'.$this->Lang('tip_finditm').'"');
	if (empty($params['repeat'])) { //onetime booking
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;
		$vfuncs = new Booker\Verify();
		$jsfuncs[] = $vfuncs->VerifyScript($this,$utils,$id,$item_id,TRUE,FALSE,$idata['timezone'],TRUE);
		$jsloads[] = <<<EOS
$('#{$id}submit').bind('click',validate);
EOS;
	}
} else {
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
}

if (!empty($stylers)) {
	//heredoc-var newlines are a problem for quoted strings! workaround ...
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
	echo $utils->MergeJS(FALSE,[$t],FALSE);
}

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'openbooking.tpl',$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
