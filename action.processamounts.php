<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: processamounts - view or edit booker payments & credit, or item payments
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/* $params[]
'booker_id' OR 'item_id'
'task'=> 'see' OR 'edit'
'resume'=>
*/

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
} elseif (isset($params['setpaid'])) {
	if ($booker_id) {
		if (isset($params['sel'])) {
		} else {
		}
	} elseif (isset($params['sel'])) {
	} else {
	}
} elseif (isset($params['changepaid'])) {
	if ($booker_id) {
		if (isset($params['sel'])) {
		} else {
		}
	} elseif (isset($params['sel'])) {
	} else {
	}
} elseif (isset($params['refund'])) {
	if ($booker_id) {
		if (isset($params['sel'])) {
		} else {
		}
	} elseif (isset($params['sel'])) {
	} else {
	}
} elseif (isset($params['setcredit'])) {
} elseif (isset($params['changecredit'])) {
} elseif (isset($params['range'])) {
	$dt = new DateTime('@0',NULL);
	$params['showfrom'] = 0; //TODO
	$params['showto'] = 0;
}

if (empty($params['showfrom'])) {
	$after = 0;
} else {
	$after = $params['showfrom'];
}
if (empty($params['showto'])) {
	$before = 0;
} else {
	$before = $params['showto'];
}

$tplvars = [];
$tplvars['mod'] = (($pmod) ? 1 : 0);

$utils = new Booker\Utils();
$params['active_tab'] = ($booker_id) ? 'people' : (($item_id < Booker::MINGRPID) ? 'items':'groups');
$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);

$tplvars['startform'] = $this->CreateFormStart($id, 'openamounts', $returnid, 'POST', '', '', '',
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
$funcs = new Booker\Payment();

$missing = '&lt;'.$this->Lang('missing').'&gt;';
$sql = <<<EOS
SELECT D.*,COALESCE(A.name,B.name,'{$missing}') AS name,A.publicid,COALESCE(I.name,'{$missing}') AS what,
COALESCE(O.comment,R.formula,'') AS description,
COALESCE(O.fee,R.fee,0.00) AS fee,COALESCE(O.feepaid,R.feepaid,0.00) AS feepaid
FROM $this->DispTable D
JOIN $this->BookerTable B ON D.booker_id=B.booker_id
LEFT JOIN $this->AuthTable A ON B.auth_id=A.id
JOIN $this->ItemTable I ON D.item_id=I.item_id
LEFT JOIN $this->OnceTable O ON D.bkg_id=O.bkg_id
LEFT JOIN $this->RepeatTable R ON D.bkg_id=R.bkg_id
EOS;
if ($booker_id) {
	$sql .= ' WHERE D.booker_id=? HAVING fee>0 OR feepaid>0 ORDER BY D.slotstart,what';
	$data = $utils->PlainGet($this,$sql,[$booker_id]);
	if ($data) {
		$row = reset($data);
		$t = $row['name'];
	} else {
		$t = $utils->GetUserName($this,$booker_id);
	}
} else { //processing item
	$sql .= ' WHERE D.item_id=? HAVING fee>0 OR feepaid>0 ORDER BY D.slotstart,name';
	$data = $utils->PlainGet($this,$sql,[$item_id]);
	if ($data) {
		$row = reset($data);
		$t = $row['what'];
	} else {
		$t = $utils->GetItemNameForID($this,$item_id);
	}
}
if (!$t) {
	$t = $missing;
}
$tplvars['title'] = $utils->CreateTitle($this,$t,'title_payments',$after,$before);

$rows = [];
if ($data) {
	if ($pmod) {
		$t = $this->Lang('tip_paidchange');
		$icon_change = '<img src="'.$baseurl.'/images/change.png" alt="'.$t.'" title="'.$t.'" border="0" />';
		$t = $this->Lang('tip_paidset');
		$icon_set = '<img src="'.$baseurl.'/images/set.png" alt="'.$t.'" title="'.$t.'" border="0" />';
		$t = $this->Lang('tip_paidrefund');
		$icon_refund = '<img src="'.$baseurl.'/images/return.png" alt="'.$t.'" title="'.$t.'" border="0" />';
	}
	$dt = new DateTime('@0',NULL);
	$handler = $params['action'];
	foreach ($data as &$one) {
		$oneset = new stdClass();
		$oneset->name = ($booker_id) ? $row['what'] : $row['name'];
		$t = 'TODO'; //TODO .starttoend $utils->IntervalFormat($this,$dt,'Y-M-D G:i');  OR .Repeat:formula
		$oneset->desc = $t;
		$oneset->fee = number_format($row['fee'],2); //TODO generalize
		$oneset->paid = number_format($row['feepaid'],2); //ditto
		if ($pmod) {
			if ($booker_id) {
				$i = (int)$row['booker_id'];
				$linkparms =  ['task'=>'edit','booker_id'=>$i];
			} else {
				$i = (int)$row['item_id'];
				$linkparms =  ['task'=>'edit','item_id'=>$i];
			}
			$oneset->inp = $this->CreateInputText($id,'val['.$i.']','',10);
			$oneset->chg = $this->CreateLink($id,$handler,'',$icon_change,$linkparms+['changepaid'=>1]); //TODO confirmable
			$oneset->set = $this->CreateLink($id,$handler,'',$icon_set,$linkparms+['setpaid'=>1]); //ditto
			$oneset->ref = $this->CreateLink($id,$handler,'',$icon_refund,$linkparms+['refund'=>1]); //ditto
			$oneset->sel = $this->CreateInputCheckbox($id,'sel['.$i.']',1,-1);
		}
		$rows[] = $oneset;
	}
	unset($one);
/*	$jsfuncs[] = <<<EOS
function deferlink(tg,title) {
 var \$a = $(tg).closest('a'),
  prompt = func(\$a),
  deflt = func(\$a),
  opts = {
   prompt: '<input id="alertable-input" type="text" name="value" value="' + deflt + '" />'
  };
 if (title !== undefined) {
  opts.modal = '<form id="alertable"><h4 id="alertable-title">' + title + '</h4>' +
   '<p id="alertable-message"></p><div id="alertable-prompt"></div>' +
   '<div id="alertable-buttons"></div></form>';
 }
 $.alertable.prompt(prompt,opts).then(function() {
  var cust = $('#alertable-input').val(),
   url = \$a.attr('href'),
   curl = url+'&{$id}custmsg='+encodeURIComponent(cust);
  \$a.attr('href',curl).trigger('click.deferred');
 });
}
EOS;
	$jsloads[] = <<<EOS
 $('#amounts .bkrdel > a').click(function(ev) {
  var tg = ev.target || ev.srcElement;
  deferlink(tg);
  return false;
 });
EOS;
*/
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
$example = $funcs->AmountFormat($this,$utils,$item_id,10.00);

if ($rc) {
	$tplvars['data'] = $rows;
	$tplvars['title_name'] = ($booker_id) ?
		$this->Lang('title_item') : $this->Lang('title_name');
	$tplvars['title_desc'] = $this->Lang('description');
	$symbol = preg_replace('/[\d., ]/','',$example);
	$tplvars['title_fee'] = $this->Lang('title_fee').' ('.$symbol.')';
	$tplvars['title_paid'] = $this->Lang('title_paid').' ('.$symbol.')';
	if ($pmod) {
		$tplvars['title_change'] = $this->Lang('change');
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;
	}
	if ($rc > 1) {
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
		if ($pmod) {
			$tplvars['header_checkbox'] =
				$this->CreateInputCheckbox($id, 'selectall', TRUE, FALSE, 'onclick="select_all(this);"');

			$jsfuncs[] = <<<EOS
function select_all(cb) {
 $('#amounts > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
		}
	}
	if ($pmod) {
		$tplvars['change'] = $this->CreateInputSubmit($id,'changepaid',$this->Lang('change'),
			'title="'.$this->Lang('tip_TODO').'"');
		$tplvars['set'] = $this->CreateInputSubmit($id,'setpaid',$this->Lang('set'),
			'title="'.$this->Lang('tipTODO').'"');
		$jsfuncs[] = <<<EOS
function any_selected() {
 var cb = $('#amounts input[name="{$id}sel[]"]:checked');
 return (cb.length > 0);
}
EOS;
//		$js to validate, confirm
		$jsloads[] = <<<EOS
EOS;
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

//$lang['tip_'] = 'set total adjustment to the entered amount';
//$lang['tip_'] = 'change total adjustment by the entered amount';

if ($booker_id) {
	$tplvars['adjust'] = TRUE;
	$tplvars['title_credit'] = $this->Lang('title_credit2');

	$amount = $funcs->TotalCredit($this,$booker_id);
	if ($amount >= 0) {
		$t = $funcs->AmountFormat($this,$utils,$item_id,$amount);
		$t = $this->Lang('current_credit',$this->Lang('credit'),$t);
	} else {
		$t = $funcs->AmountFormat($this, $utils, $item_id,-$amount);
		$t = $this->Lang('current_credit','deficit',$t);
	}
	$tplvars['current_credit'] = $t;
	if ($pmod) {
		$tplvars['input2'] = $this->CreateInputText($id,'inputcredit','',10,10,'title="'.'[+-] 12.34'.'"'); //TODO watermark
		$tplvars['change2'] = $this->CreateInputSubmit($id,'changecredit',$this->Lang('change'),
			'title="'.$this->Lang('tip_TODO').'"');
		$tplvars['set2'] = $this->CreateInputSubmit($id,'setcredit',$this->Lang('set'),
			'title="'.$this->Lang('tip_TODO').'"');
		$tplvars['help_credit'] = $this->Lang('help_credit','25.00','10.50');
//		$js to validate, confirm
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.watermark.min.js"></script>
EOS;
		$jsloads[] = <<<EOS
 setTimeout(function() {
  $('#{$id}inputcredit').watermark();
 },10);
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
