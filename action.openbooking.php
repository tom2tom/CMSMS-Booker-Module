<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openbooking
# View or edit bookings data for resource or group
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*first-time $params = array
'item_id'=>$item_id
'bkg_id'=> -1 (add) OR real id (see/edit)
'resume' => string 'itembookings' or ?
'task' => string 'edit' or 'see'
'repeat' => string '1' or ''
//'bookedit'=>1 in edit mode, not set for see mode

cancellation $params = array
'item_id' => string
'bkg_id' => string
'repeat' => string '1' or ''
'resume' => string 'itembookings'
'task' => string 'edit' or 'see'
if edit
'when' => string
'until' => string
'name' => string
'contact' => string
*/
if (!$this->_CheckAccess()) exit;

$item_id = (int)$params['item_id'];
if (!isset($params['resume']))
	$params['resume'] = 'itembookings';
$resume = $params['resume'];

if (isset($params['cancel'])) {
	$newparms = array('item_id'=>$item_id,'task'=>$params['task']);
	if ($resume == 'bookerbookings') {
		$newparms['booker_id'] = $params['booker_id'];
	}	
	$this->Redirect($id,$resume,'',$newparms);
}

$utils = new Booker\Utils();
$utils->DecodeParameters($params,array(
	'bkg_id',
	'contact',
	'formula',
	'name',
	'subgrpcount',
	'until',
	'when'
));


$is_group = ($item_id >= Booker::MINGRPID);
$type = ($is_group) ? $this->Lang('group'):$this->Lang('item');
$is_new = ($params['bkg_id'] == -1);
$viewmode = !($params['task'] == 'edit' || $params['task'] == 'add'); //empty($params['bookedit']);?
$funcs2 = new Booker\Bookingops();

if (isset($params['submit']) || isset($params['apply'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
/* $params = array including
  'item_id' => string '8'
  'bkg_id' => string '9'
  'repeat' => string '' OR '1'
  'resume' => string 'itembookings'
  'when' => string '17 October 2015 12:00'
  'until' => string '17 October 2015 12:59'
  'name' => string 'Mary'
  'conformuser' => string '1' MAYBE
  'displayclass' => string '1'
  'conformstyle' => string '1' MAYBE
  'contact' => string '@myfirm'
  'conformcontact' => string '1' MAYBE
*/
	$msg = array();
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

	if (empty($params['repeat'])) { //onetime booking
		$funcs3 = new Booker\Verify();
		list($res,$xmsg) = $funcs3->VerifyData($this,$utils,$params,$item_id,$is_new,TRUE);
		if ($res) {
	//TODO this is CRAP!! never save a booking direct to DataTable!
	//MUST save to HistoryTable then [re]schedule the booking!
//			if(
//			$funcs2->SaveBkg($this,$params,$is_new); //TODO extra arg $bulk_id if $is_group
/*			) {
				if ($is_new) {
//TODO	send notice if appropriate
				} else { //update
//TODO	send notice if appropriate
					Userops->ConformUserData($this,$params); //TODO check - downstream expects $params['name'] NOT 'user'
				}
			}
*/
$this->Crash();
			$res = FALSE;
			$msg[] = 'NOT SAVED - TODO';

		} else {
			$msg = array_merge($msg,$xmsg);
		}
	} else { //repeat booking
		$t = trim($params['formula']);
		if ($t) {
			$funcs3 = new Booker\WhenRuleLexer($this);
			$t = $funcs3->CheckDescriptor($t);
		}
		if ($t) {
			$funcs3 = new Booker\Userops();
			if ($is_new) {
				list($bookerid,$newbkr) = $funcs3->GetParamsID($this,$params);
				$sql2 = 'bkg_id,item_id,formula,booker_id';
				$fillers = '?,?,?,?';
				$bkgid = $db->GenID($this->DataTable.'_seq');
				$args = array(
					$bkgid,
					(int)$params['item_id'],
					$t,
					$bookerid
				);
				foreach (array('subgrpcount','paid') as $k) {
					if (isset($params[$k])) {
						$sql2 .= ",$k";
						$fillers .= ',?';
						$args[] = (int)$params[$k];
					}
				}
				$sql = 'INSERT INTO '.$this->RepeatTable.' ('.$sql2.') VALUES ('.$fillers.')';
		//TODO $utils->SafeExec()
				$db->Execute($sql,$args);
				$params['bkg_id'] == $bkgid;
				$is_new = FALSE;
			} else { //update
				$funcs3->ConformUserData($this,$params);
				$sql2 = 'formula=?';
				$args = array($t);
				foreach (array('subgrpcount','paid') as $k) {
					if (isset($params[$k])) {
						$sql2 .= ",$k=?";
						$args[] = (int)$params[$k];
					}
				}
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
						$db->Execute($sql,array($st,$st));
						$dtw->modify('+1 day');
					}
				}
				//remove future derived booking-slots
				$dtw->modify('+1 day');
				$st = $dtw->getTimestamp();
				$args = array($params['bkg_id'],$st);
				$sql = 'DELETE FROM '.$this->DataTable.' WHERE bkg_id=? AND slotstart>?';
				$utils->SafeExec($sql,$args);
//TODO	send notice(s) if appropriate
			}
		} else {
			$msg[] = $this->Lang('invalid_type',$this->Lang('booking')); //formula
		}
	}
	if ($msg) { //error
		$t = implode(' ',$msg);
		if (empty($params['message']))
			$params['message'] = $t;
		else
			$params['message'] .= '<br />'.$t;
	} elseif (isset($params['submit'])) {
		$this->Redirect($id,$resume,'',array('item_id'=>$item_id,'task'=>$params['task']));
	}
	$bdata = array(
		'name'=>$params['name'],
		'address'=>$params['contact'],
		'phone'=>$params['contact'],
		'displayclass'=>$params['displayclass'],
	);
	if (array_key_exists('subgrpcount',$params)) {
		$bdata['subgrpcount'] = $params['subgrpcount'];
	}
	$bdata['paid'] = !empty($params['paid']);
	if (empty($params['repeat'])) {
		$bdata['when'] = $params['when'];
		if (array_key_exists('until',$params)) {
			$bdata['until'] = $params['until'];
		}
	} else {
		$bdata['formula'] = $params['formula'];
	}
	$params['task'] = 'edit'; //in case we we adding
} elseif (isset($params['find'])) {
	//TODO
	$params['message'] = $this->Lang('notyet');
}

$tplvars = array();

$hidden = array('item_id'=>$item_id,'bkg_id'=>$params['bkg_id'],'resume'=>$resume,'task'=>$params['task']);
if (!empty($params['booker_id']))
	$hidden['booker_id'] = $params['booker_id'];
if (!empty($params['repeat']))
	$hidden['repeat'] = 1;
//if (!empty($params['bookedit']))
//	$hidden['bookedit'] = 1;
$tplvars['startform'] = $this->CreateFormStart($id,'openbooking',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();

$params['active_tab'] = 'people'; //for admin link
$tplvars['pagenav'] = $this->_BuildNav($id,$returnid,array('defaultadmin',$params['resume']),$params);
if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$tplvars['mod'] = !$viewmode;
if ($viewmode)
	$missing = '&lt;'.$this->Lang('missing').'&gt;';
else
	$tplvars['compulsory'] = $this->Lang('help_compulsory');

if (empty($bdata)) {
	$table = (empty($params['repeat'])) ? $this->DataTable : $this->RepeatTable;
	$sql = <<<EOS
SELECT D.*,B.name,B.publicid,B.address,B.phone,B.displayclass FROM {$table} D
JOIN {$this->BookerTable} B ON D.booker_id=B.booker_id
WHERE D.bkg_id=?
EOS;
	$bdata = $utils->SafeGet($sql,array($params['bkg_id']),'row');
}

$idata = $utils->GetItemProperty($this,$item_id,"*");
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

//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$baseurl = $this->GetModuleURLPath();

$vars = array();

if (empty($params['repeat'])) { //onetime booking
	$choosend = ($idata['bookcount'] != 1);

	$one = new stdClass();
	$one->title = $this->Lang('title_starting');
	if ($bdata) {
		if (array_key_exists('when',$bdata)) {
			$t = $bdata['when'];
		} else {
			$dtw = new DateTime('@'.$bdata['slotstart'],new DateTimeZone('UTC'));
			$t = $dtw->format('Y-n-j G:i'); //TODO ovrday support
		}
	} else {
		$t = '';
	}
	if ($viewmode) {
		$one->input = ($t) ? $t:$missing;
	} else {
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'when',$t,20,30);
		//for date-picker
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/php-date-formatter.min.js"></script>
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
			$sl = ($bdata && !empty($bdata['slotlen'])) ? $bdata['slotlen']:/*$idata['slotlen']*/3600; //TODO
			$t2 = <<<EOS
,
  onClose: function() {
   if('_d' in this && this._d) {
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
	$one->help = $this->Lang('help_book_start');
	$vars[] = $one;
//==
	if ($choosend) {
		$one = new stdClass();
		$one->title = $this->Lang('title_ending');
		if ($bdata) {
			if (array_key_exists('until',$bdata)) {
				$t = $bdata['until'];
			} else {
				$dtw->setTimestamp($bdata['slotstart'] + $bdata['slotlen']);
				$t = $dtw->format('Y-n-j G:i');
			}
		} else {
			$t = '';
		}
		if ($viewmode) {
			$one->input = ($t) ? $t:$missing;
		} else {
			$one->must = 0;
			$one->input = $this->CreateInputText($id,'until',$t,20,30);
		}
		$one->help = NULL; //$this->Lang('help_book_end');
		$vars[] = $one;
	}
} else { //repeat booking
	$one = new stdClass();
	$one->title = $this->Lang('title_description');
	$t = ($bdata) ? $bdata['formula']:'';
	if ($viewmode) {
		$one->input = ($t) ? $t:$missing;
	} else {
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'formula',$t,50,256);
	}
	$one->help = $this->Lang('help_intervals');
	$vars[] = $one;
}
//==
if ($is_group) {
	$one = new stdClass();
	$one->title = $this->Lang('title_subgrpcount');
	$t = ($bdata) ? (int)$bdata['subgrpcount']:'';
	if ($viewmode)
		$one->input = ($t) ? $t:$missing;
	else {
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'subgrpcount',$t,3,5);
	}
	$one->help = $this->Lang('help_subgrpcount');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('title_user');
$t = ($bdata) ? $bdata['name']:'';
if ($viewmode) {
	$one->input = ($t) ? $t:$missing;
} else {
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'name',$t,20,64);
	if (!$is_new)
		$one->help = $this->Lang('help_conformuser');
}
$vars[] = $one;
//==
$one = new stdClass();
$one->title = $this->Lang('title_contact');
if ($bdata) {
	$t = ($bdata['address']) ? $bdata['address'] : $bdata['phone'];
} else {
	$t = '';
}
if ($viewmode) {
	$one->input = ($t) ? $t:$missing;
} else {
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'contact',$t,30,128);
}
$one->help = $this->Lang('help_book_contact');
if (!($viewmode || $is_new)) {
	$one->help .= '<br />'.$this->Lang('help_conformcontact');
}
$vars[] = $one;
//TOTO UI for both address & phone
//==
$one = new stdClass();
$one->title = $this->Lang('title_displayclass');
$t = ($bdata) ? (int)$bdata['displayclass']:1;
if ($viewmode) {
	$one->input = $t;
} else {
	$one->must = 0;
	$styles = range(1,Booker::USERSTYLES);
	$choices = array_combine($styles,$styles);
	$one->input = $this->CreateInputDropdown($id,'displayclass',$choices,-1,$t);
}
$one->help = $this->Lang('help_displayclass');
if (!($viewmode || $is_new)) {
	$one->help .= '<br />'.$this->Lang('help_conformstyle');
}

$vars[] = $one;
//==
$condition = NULL; //TODO payable-condition time,requestor etc
$payable = $utils->GetItemPayable($this,$item_id,FALSE,$condition);
if ($payable) {
	$one = new stdClass();
	$one->title = $this->Lang('title_paid');
	$t = ($bdata) ? (int)$bdata['paid']:0;
	if ($viewmode) {
		$one->input = ($t) ? $this->Lang('yes'):$this->Lang('no');
	} else {
		$one->must = 0;
		$one->input = $this->CreateInputCheckbox($id,'paid',1,$t);
	}
	$vars[] = $one;
}

$tplvars['data'] = $vars;

//buttons
if ($viewmode) {
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
} else { //add/edit mode
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['apply'] = $this->CreateInputSubmit($id,'apply',$this->Lang('apply'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
	$tplvars['find'] = $this->CreateInputSubmit($id,'find',$this->Lang('find'),
		'title="'.$this->Lang('tip_finditm').'"');
	if (empty($params['repeat'])) { //onetime booking
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.modalconfirm.min.js"></script>

EOS;
		$tplvars['yes'] = $this->Lang('yes');
		$tplvars['no'] = $this->Lang('no');
		$funcs3 = new Booker\Verify();
		$jsfuncs[] = $funcs3->VerifyScript($this,$utils,$id,$item_id,TRUE,FALSE,$idata['timezone'],TRUE);
		$jsloads[] = <<<EOS
 var obs = [$('#{$id}submit'),$('#{$id}apply')];
 $.each(obs,function(indx,\$ob) {
  \$ob.bind('click',validate);
 });

EOS;
	}
}

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
//porting heredoc-var newlines is a problem for qouted strings! workaround ...
//$stylers = str_replace("\n",'',$stylers);
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

echo Booker\Utils::ProcessTemplate($this,'openbooking.tpl',$tplvars);
