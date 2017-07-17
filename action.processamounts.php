<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: processamounts - view or edit booker payments & credit, or item payments
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!empty($params['booker_id'])) {
	$booker_id = (int)$params['booker_id'];
	$item_id = FALSE;
} elseif (!empty($params['item_id'])) {
	$item_id = (int)$params['item_id'];
	$is_group = ($item_id >= Booker::MINGRPID);
	$booker_id = FALSE;
} else {
	exit;
}

if ($params['task'] == 'see') {
	if ($this->_CheckAccess('view')) {
		$pmod = FALSE;
	} else {
		exit;
	}
} elseif ($params['task'] == 'edit') {
	if ($this->_CheckAccess('admin') || $this->_CheckAccess('book')) {
		$pmod = TRUE;
	} else {
		exit;
	}
} else {
	exit;
}

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'], ENT_QUOTES | ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
} else { //got here via link
	$params['resume'] = ['defaultadmin']; //redirects can [eventually] get back to there
}

if (isset($params['cancel'])) {
	$resume = array_pop($params['resume']);
	$this->Redirect($id,$resume,'',['TODO']);
}

$pfuncs = new Booker\Payment();

if (isset($params['setpaid'])) {
	if (isset($params['sel'])) {
		foreach ($params['sel'] as $bid=>$one) {
			$amt = $params['val'][$bid];
			if (is_numeric($amt)) {
				// set O/R.feepaid func($one,$item_id || $booker_id);
				$pfuncs->ChangePayment($this,$bid,$amt,FALSE,FALSE,TRUE);
			}
		}
	} else {
		$bid = key($params['setpaid']);
		$amt = $params['val'][$bid];
		if (is_numeric($amt)) {
			$pfuncs->ChangePayment($this,$bid,$amt,FALSE,FALSE,TRUE);
		}
	}
} elseif (isset($params['changepaid'])) {
	if (isset($params['sel'])) {
		foreach ($params['sel'] as $bid=>$one) {
			$amt = $params['val'][$bid];
			if (is_numeric($amt)) {
				$pfuncs->ChangePayment($this,$bid,$amt,TRUE,FALSE,TRUE);
			}
		}
	} else {
		$bid = key($params['changepaid']);
		$amt = $params['val'][$bid];
		if (is_numeric($amt)) {
			$pfuncs->ChangePayment($this,$bid,$amt,TRUE,FALSE,TRUE);
		}
	}
} elseif (isset($params['refund'])) {
	if (isset($params['sel'])) {
		foreach ($params['sel'] as $bid=>$one) {
			$amt = $params['val'][$bid];
			if ($amt === '') {
				$pfuncs->ChangePayment($this,$bid,'--',TRUE,TRUE,TRUE);
			} elseif (is_numeric($amt)) {
				$pfuncs->ChangePayment($this,$bid,$amt,TRUE,TRUE,TRUE);
			}
		}
	} else {
		$bid = key($params['refund']);
		$amt = $params['val'][$bid];
		if ($amt === '') {
			$pfuncs->ChangePayment($this,$bid,'--',TRUE,TRUE,TRUE);
		} elseif (is_numeric($amt)) {
			$pfuncs->ChangePayment($this,$bid,$amt,TRUE,TRUE,TRUE);
		}
	}
} elseif (isset($params['setcredit'])) {
	if (is_numeric($params['inputcredit'])) {
		$current = $pfuncs->TotalCredit($this,$booker_id);
		$pfuncs->AddCredit($this,$booker_id,$params['inputcredit']-$current);
	}
} elseif (isset($params['changecredit'])) {
	if (is_numeric($params['inputcredit'])) {
		$pfuncs->AddCredit($this,$booker_id,$params['inputcredit']);
	}
}

$y = $m = 0;
$after = $before = FALSE;
if (!empty($params['showfrom'])) {
	sscanf($params['showfrom'],'%d-%d',$y,$m);
	$dt = new DateTime('@0',NULL);
	$lvl = error_reporting(0);
	$res = $dt->modify($y.'-'.$m.'-01');
	error_reporting($lvl);
	if ($res) {
		//TODO bounds check(s)
		$params['showfrom'] = $dt->format('Y-m');
		$after = $dt->getTimestamp();
	} else {
		$params['showfrom'] = FALSE;
	}
}
if (!empty($params['showto'])) {
	sscanf($params['showto'],'%d-%d',$y,$m);
	if (!isset($dt)) {
		$dt = new DateTime('@0',NULL);
	}
	$lvl = error_reporting(0);
	$res = $dt->modify($y.'-'.$m.'-01');
	error_reporting($lvl);
	if ($res) {
		//TODO bounds check(s)
		$params['showto'] = $dt->format('Y-m');
		$dt->modify('+1 month');
		$before = $dt->getTimestamp() - 1;
	} else {
		$params['showto'] = FALSE;
	}
}

$tplvars = [];
$tplvars['mod'] = (($pmod) ? 1 : 0);

$utils = new Booker\Utils();
$params['active_tab'] = ($booker_id) ? 'people' : (($item_id < Booker::MINGRPID) ? 'items':'groups');
$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);

$tplvars['startform'] = $this->CreateFormStart($id,$params['action'],$returnid,'POST','','','',
	['booker_id' => $booker_id, 'item_id' => $item_id, 'task' => $params['task'],
	'resume' => $resume, 'showfrom' => $after, 'showto' => $before]);
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($msg)) {
	$tplvars['message'] = $msg;
}

$jsfuncs = []; //script accumulators
$jsloads = [];
$jsincs = [];
$baseurl = $this->GetModuleURLPath();

$noname = '&lt;'.$this->Lang('missing').'&gt;';
if ($booker_id) {
	$sql = <<<EOS
SELECT O.bkg_id,O.item_id,O.subgrpcount,O.slotstart,O.slotlen,NULL AS formula,O.fee,O.feepaid,
COALESCE(I.name,'$noname') AS what,I.membersname,
COALESCE(B.name,A.name,A.account,'$noname') AS name,B.auth_id
FROM $this->OnceTable O
JOIN $this->ItemTable I ON O.item_id=I.item_id
JOIN $this->BookerTable B ON O.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.auth_id=A.id
WHERE O.booker_id=? AND (O.fee>0.0 OR O.feepaid>0.0)
UNION
SELECT R.bkg_id,R.item_id,R.subgrpcount,0 AS slotstart, 0 AS slotlen,R.formula,R.fee,R.feepaid,
COALESCE(I.name,'$noname') AS what,I.membersname,
COALESCE(B.name,A.name,A.account,'$noname') AS name,B.auth_id
FROM $this->RepeatTable R
JOIN $this->ItemTable I ON R.item_id=I.item_id
JOIN $this->BookerTable B ON R.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.auth_id=A.id
WHERE R.booker_id=? AND (R.fee>0.0 OR R.feepaid>0.0)
ORDER BY slotstart,what
EOS;
	$data = $utils->PlainGet($this,$sql,[$booker_id,$booker_id]);
	if ($data) {
		$one = reset($data);
		$t = $one['name'];
	} else {
		$t = $utils->GetUserNameForID($this,$booker_id);
	}
} else { //processing item
	$sql = <<<EOS
SELECT O.bkg_id,O.booker_id,O.subgrpcount,O.slotstart,O.slotlen,NULL AS formula,O.fee,O.feepaid,
COALESCE(I.name,'$noname') AS what,I.membersname,
COALESCE(B.name,A.name,A.account,'$noname') AS name,B.auth_id
FROM $this->OnceTable O
JOIN $this->ItemTable I ON O.item_id=I.item_id
JOIN $this->BookerTable B ON O.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.auth_id=A.id
WHERE O.item_id=? AND (O.fee>0.0 OR O.feepaid>0.0)
UNION
SELECT R.bkg_id,R.booker_id,R.subgrpcount,0 AS slotstart, 0 AS slotlen,R.formula,R.fee,R.feepaid,
COALESCE(I.name,'$noname') AS what,I.membersname,
COALESCE(B.name,A.name,A.account,'$noname') AS name,B.auth_id
FROM $this->RepeatTable R
JOIN $this->ItemTable I ON R.item_id=I.item_id
JOIN $this->BookerTable B ON R.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.auth_id=A.id
WHERE R.item_id=? AND (R.fee>0.0 OR R.feepaid>0.0)
ORDER BY slotstart,name
EOS;
	$data = $utils->PlainGet($this,$sql,[$item_id,$item_id]);
	if ($data) {
		$one = reset($data);
		$t = $one['what'];
	} else {
		$t = $utils->GetItemNameForID($this,$item_id);
	}
}
if (!$t) {
	$t = $noname;
}
$tplvars['title'] = $utils->CreateTitle($this,$t,'title_payments',$after,$before);

$rows = [];
if ($data) {
	if ($pmod) {
		$icon_change = $baseurl.'/images/change.png';
		$icon_set = $baseurl.'/images/set.png';
		$icon_refund = $baseurl.'/images/return.png';

		$tip_change = $this->Lang('tip_paidchange');
		$tip_set = $this->Lang('tip_paidset');
		$tip_refund = $this->Lang('tip_paidrefund');
	}
	$dt = new DateTime('@0',NULL);
	$tpl1 = $this->Lang('whatcountof','%s','%d','%s');
	$tpl2 = $this->Lang('showrange','%s','%s');
	$tpl3 = $this->Lang('bkgtype_repeated').':';
	if ($item_id) {
		$ovrday = ($utils->GetInterval($this,$item_id,'slot') >= 84600);
	}
	foreach ($data as &$one) {
		$oneset = new stdClass();
		if ($booker_id) {
			if ($one['item_id'] < Booker::MINGRPID) {
				$oneset->name = $one['what'];
			} else {
				$oneset->name = sprintf($tpl1,$one['what'],$one['subgrpcount'],$one['membersname']);
			}
		} else {
			$oneset->name = $one['name'];
		}
		if ($one['formula'] === NULL) { //onetime booking
			$dt->setTimestamp($one['slotstart']);
			$t = $utils->IntervalFormat($mod,$dt,'Y-m-d');
			if (!$item_id) {
				$ovrday = ($utils->GetInterval($this,$one['item_id'],'slot') >= 84600);
			}
			if ($ovrday) {
				$dt->modify('+'.$one['slotlen'].'seconds');
				$e = $utils->IntervalFormat($mod,$dt,'Y-m-d');
			} else {
				$t .= ' '.$dt->format('G:i');
				$dt->modify('+'.$one['slotlen'].'seconds');
				$e = $dt->format('G:i');
			}
			$oneset->desc = sprintf($tpl2,$t,$e);
		} else {
			$oneset->desc = $tpl3.$one['formula'];
		}
		$oneset->fee = number_format($one['fee'],2); //TODO generalize
		$oneset->paid = number_format($one['feepaid'],2); //ditto
		if ($pmod) {
			$i = $one['bkg_id'];
			$oneset->inp = $this->CreateInputText($id,'val['.$i.']','',10);
			$oneset->chg = $this->_CreateInputLinks($id,'changepaid['.$i.']',$icon_change,FALSE,$tip_change);
			$oneset->set = $this->_CreateInputLinks($id,'setpaid['.$i.']',$icon_set,FALSE,$tip_set);
			$oneset->ref = $this->_CreateInputLinks($id,'refund['.$i.']',$icon_refund,FALSE,$tip_refund);
			$oneset->sel = $this->CreateInputCheckbox($id,'sel['.$i.']',1,-1);
		}
		$rows[] = $oneset;
	}
	unset($one);
} else {
	$tplvars['norecords'] = $this->Lang('nodata');
}

$rc = count($rows);
$tplvars['rc'] = $rc;

if ($booker_id) {
	if ($rc) {
		$one = reset($data);
		$item_id = $one['item_id'];
	} else {
		$item_id = Booker::MINGRPID; //TODO get 1st recorded non-group
	}
}

if ($pmod) {
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;
	$p = $this->Lang('confirm');
	$c = $this->Lang('close');
	$e = $this->Lang('invalid_type',$this->Lang('amount'));
}

if ($rc) {
	$tplvars['data'] = $rows;
	$tplvars['title_name'] = ($booker_id) ?
		$this->Lang('title_item') : $this->Lang('title_name');
	$tplvars['title_desc'] = $this->Lang('description');
	$example = $pfuncs->AmountFormat($this,$utils,$item_id,10.00);
	$symbol = preg_replace('/[\d., ]/','',$example);
	$tplvars['title_fee'] = $this->Lang('title_fee').' ('.$symbol.')';
	$tplvars['title_paid'] = $this->Lang('title_paid').' ('.$symbol.')';
	if ($pmod) {
		$tplvars['title_change'] = $this->Lang('title_amount');
		$tplvars['change'] = $this->CreateInputSubmit($id,'changepaid',$this->Lang('change'),
			'title="'.$this->Lang('tip_paidchange_sel').'"');
		$tplvars['set'] = $this->CreateInputSubmit($id,'setpaid',$this->Lang('set'),
			'title="'.$this->Lang('tip_paidset_sel').'"');
		//TODO consider refund button

		$jsfuncs[] = <<<EOS
function deferbutton(tg,msg) {
 $.alertable.confirm(msg,{
  okName:'{$this->Lang('proceed')}',
  cancelName:'{$this->Lang('cancel')}'
 }).then(function() {
  $(tg).trigger('click.deferred');
 });
}
EOS;
// var pr=; //TODO more-useful prompt(s) instead of $p
		$jsloads[] = <<<EOS
 $('#{$id}changepaid,#{$id}setpaid').click(function() {
  var \$cb = $('#amounts input[name^="{$id}sel["]:checked');
  if (\$cb.length > 0) {
   var ok=true;
   \$cb.each(function() {
    var \$in = $(this).closest('tr').find('input[name^="{$id}val["]'),
     amt = \$in[0].value;
    if (!$.isNumeric(amt)) {
     $.alertable.alert('$e',{
      okName:'$c'
     }).then(function() {
      \$in.focus();
     });
     ok=false;
     return false;
    }
   });
   if (ok) {
    deferbutton(this,'$p');
   }
  }
  return false;
 });
 $('#amounts .fakeicon').click(function() {
  if (this.name.indexOf('refund') !== -1) {
   deferbutton(this,'$p');
  } else {
   var \$in = $(this).closest('tr').find('input[name^="{$id}val["]'),
    amt = \$in[0].value;
   if ($.isNumeric(amt)) {
    deferbutton(this,'$p');
   } else {
    $.alertable.alert('$e',{
     okName:'$c'
    }).then(function() {
     \$in.focus();
    });
   }
  }
  return false;
 });
EOS;
	}
	if ($rc > 1) {
		if ($pmod) {
			$tplvars['header_checkbox'] =
				$this->CreateInputCheckbox($id, 'selectall', TRUE, FALSE, 'onclick="select_all(this);"');

			$jsfuncs[] = <<<EOS
function select_all(cb) {
 $('#amounts > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
		}
		//TODO make page-rows count window-size-responsive
		$pagerows = $this->GetPreference('pagerows', 10);
		if ($pagerows && $rc > $pagerows) {
			//setup for SSsort
			$choices = [strval($pagerows) => $pagerows];
			$f = ($pagerows < 4) ? 5 : 2;
			$n = $pagerows * $f;
			if ($n < $rc) {
				$choices[strval($n)] = $n;
			}
			$n *= 2;
			if ($n < $rc) {
				$choices[strval($n)] = $n;
			}
			$choices[$this->Lang('all')] = 0;
			$tplvars['rowchanger'] =
				$this->CreateInputDropdown($id, 'pagerows', $choices, -1, $pagerows,
				'onchange="pagerows(this);"').'&nbsp;&nbsp;'.$this->Lang('pagerows');
			$curpg = '<span id="cpage">1</span>';
			$totpg = '<span id="tpage">'.ceil($rc / $pagerows).'</span>';
			$tplvars += [
				'hasnav'=>1,
				'first' => '<a href="javascript:pagefirst()">'.$this->Lang('first').'</a>',
				'prev' => '<a href="javascript:pageback()">'.$this->Lang('previous').'</a>',
				'next' => '<a href="javascript:pageforw()">'.$this->Lang('next').'</a>',
				'pageof' => $this->Lang('pageof', $curpg, $totpg),
				'last' => '<a href="javascript:pagelast()">'.$this->Lang('last').'</a>'
			];
			$jsfuncs[] = <<<EOS
function pagefirst() {
 $.SSsort.movePage($('#amounts')[0],false,true);
}
function pagelast() {
 $.SSsort.movePage($('#amounts')[0],true,true);
}
function pageforw() {
 $.SSsort.movePage($('#amounts')[0],true,false);
}
function pageback() {
 $.SSsort.movePage($('#amounts')[0],false,false);
}
function pagerows(cb) {
 $.SSsort.setCurrent($('#amounts')[0],'pagesize',parseInt(cb.value));
}
EOS;
		} else {
			$tplvars['hasnav'] = 0;
		}

		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.SSsort.min.js"></script>
EOS;
		$jsloads[] = <<<EOS
 $.SSsort.addParser({
  id: 'textinput',
  is: function(s,node) {
   var n = node.childNodes[0];
   return (n && n.nodeName.toLowerCase() == 'input' && n.type.toLowerCase() == 'text');
  },
  format: function(s,node) {
   return $.trim(node.childNodes[0].value);
  },
  watch: true,
  type: 'text'
 });
 $('#amounts').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s',
  paginate: true,
  pagesize: $pagerows,
  currentid: 'cpage',
  countid: 'tpage'
 });
EOS;
	} else {
		$tplvars['hasnav'] = 0;
	}
}

$tplvars['title_range'] = $this->Lang('title_report_change');
$tplvars['titlefrom'] = $this->Lang('start');
$t = $this->CreateInputText($id,'showfrom','',12,15);
$tplvars['showfrom'] = str_replace('class="','class="dateinput ',$t);
$tplvars['helpfrom'] = $this->Lang('help_reportfrom');
$tplvars['titleto'] = $this->Lang('end');
$t = $this->CreateInputText($id,'showto','',12,15);
$tplvars['showto'] = str_replace('class="','class="dateinput ',$t);
$tplvars['helpto'] = $this->Lang('help_reportto');
$tplvars['range'] = $this->CreateInputSubmit($id,'range',$this->Lang('apply'),
	'title="'.$this->Lang('tip_report_interval').'"');
//for date-picker
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikamonth.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikamonth.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
EOS;

$prevyr = $this->Lang('prevy');
$nextyr = $this->Lang('nexty');
//js wants quoted period-names
$t = $this->Lang('longmonths');
$mnames = "'".str_replace(",","','",$t)."'";
$t = $this->Lang('shortmonths');
$smnames = "'".str_replace(",","','",$t)."'";
$jsloads[] = <<<EOS
 var fmt = new DateFormatter({
  longMonths: [$mnames],
  shortMonths: [$smnames],
 });
 $('.dateinput').pikamonth({
  format: 'Y-m',
  reformat: function(target,f) {
   return fmt.formatDate(target,f);
  },
  getdate: function(target,f) {
   return fmt.parseDate(target,f);
  },
  i18n: {
   previousYear: '$prevyr',
   nextYear: '$nextyr',
   months: [$mnames],
   monthsShort: [$smnames]
  }
 });
EOS;

if ($booker_id) {
	$tplvars['adjust'] = TRUE;
	$tplvars['title_credit'] = $this->Lang('title_credit2');

	$amount = $pfuncs->TotalCredit($this,$booker_id);
	if ($amount >= 0) {
		$t = $pfuncs->AmountFormat($this,$utils,$item_id,$amount);
		$t = $this->Lang('current_credit',$this->Lang('credit'),$t);
	} else {
		$t = $pfuncs->AmountFormat($this, $utils, $item_id,-$amount);
		$t = $this->Lang('current_credit','deficit',$t);
	}
	$tplvars['current_credit'] = $t;
	if ($pmod) {
		$tplvars['input2'] = $this->CreateInputText($id,'inputcredit','',10,10,'title="'.'[+-] 12.34'.'"');
		$tplvars['change2'] = $this->CreateInputSubmit($id,'changecredit',$this->Lang('change'),
			'title="'.$this->Lang('tip_creditchange').'"');
		$tplvars['set2'] = $this->CreateInputSubmit($id,'setcredit',$this->Lang('set'),
			'title="'.$this->Lang('tip_creditset').'"');
		$tplvars['help_credit'] = $this->Lang('help_credit','25.00','10.50');

		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.watermark.min.js"></script>
EOS;
// var pr=; //TODO more-useful prompt instead of $p
		$jsloads[] = <<<EOS
 setTimeout(function() {
  $('#{$id}inputcredit').watermark();
 },10);
 $('#{$id}changecredit,#{$id}setcredit').click(function() {
  var \$in = $('#{$id}inputcredit'),
   amt = \$in[0].value;
  if ($.isNumeric(amt)) {
   deferbutton(this,'$p');
  } else {
   $.alertable.alert('$e',{
    okName:'$c'
   }).then(function() {
	\$in.focus();
   });
  }
  return false;
 });
EOS;
	}
} else {
	$tplvars['adjust'] = FALSE;
}
$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));

$jsall = $utils->MergeJS($jsincs, $jsfuncs, $jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this, 'amounts.tpl', $tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
