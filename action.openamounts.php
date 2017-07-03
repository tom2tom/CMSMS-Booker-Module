<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openamounts - view or edit booker payments & credit, or item payments
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
	$item_id = (int)$params['booker_id'];
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
		if (isset($params['sel']) {
		} else {
		}
	} elseif (isset($params['sel']) {
	} else {
	}
} elseif (isset($params['changepaid'])) {
	if ($booker_id) {
		if (isset($params['sel']) {
		} else {
		}
	} elseif (isset($params['sel']) {
	} else {
	}
} elseif (isset($params['refund'])) {
	if ($booker_id) {
		if (isset($params['sel']) {
		} else {
		}
	} elseif (isset($params['sel']) {
	} else {
	}
} elseif (isset($params['setcredit'])) {
} elseif (isset($params['changecredit'])) {
} elseif (isset($params['range'])) {
}

$tplvars = [];
$tplvars['mod'] = (($pmod) ? 1 : 0);

$utils = new Booker\Utils();
$params['active_tab'] = ($booker_id) ? 'people' : (($item_id < Booker::MINGRPID) ? 'items':'groups');
$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);

$tplvars['startform'] = $this->CreateFormStart($id, 'openamounts', $returnid, 'POST', '', '', '',
	['booker_id' => $booker_id, 'item_id' => $item_id, 'resume' => $resume, 'task' => $params['task']]);
//TODO range etc
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($msg)) {
	$tplvars['message'] = $msg;
}

$jsfuncs = []; //script accumulators
$jsloads = [];
$jsincs = [];
$baseurl = $this->GetModuleURLPath();

$funcs = new Booker\Payment();
$example = $funcs->AmountFormat($this,$utils,$item_id,10.00); //TODO item_id when doing a booker

if ($booker_id) {
	$data = FALSE;
//	$tplvars['title'] = c.f. $this->CreateTitle('title_booker', 'title_payments', $after, $before);
} else { //processing item
	$data = FALSE;
//	$tplvars['title'] = c.f. $this->CreateTitle('title_item', 'title_payments', $after, $before);
}

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
	$handler = $params['action'];
	foreach ($data as &$one) {
		$oneset = new stdClass();
		$oneset->name = $row['A'];
		$oneset->desc = $X;
		$oneset->fee = $row['B']; //TODO format
		$oneset->paid = $row['C']; //ditto
		if ($pmod) {
			$i = $Y;
			$linkparms = ['TODO'];
			$oneset->inp = $this->CreateInputText($id,'val['.$i.']','',10);
			$oneset->chg = $this->CreateLink($id,$handler,'',$icon_change,$linkparms); //TODO confirmable
			$oneset->set = $this->CreateLink($id,$handler,'',$icon_set,$linkparms); //ditto
			$oneset->ref = $this->CreateLink($id,$handler,'',$icon_refund,$linkparms); //ditto
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
if ($rc) {
	$tplvars['data'] = $rows;
	if ($booker_id) {
		$tplvars['title_name'] = $this->Lang('title_name');
	} else {
		$tplvars['title_name'] = $this->Lang('title_item');
	}
	$symbol = preg_replace('/[\d., ]/','',$t);
	$tplvars['title_desc'] = $this->Lang('description');
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
			$tplvars['hasnav'] = 1;
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
				'pageof' => $this->Lang('pageof', $curpg, $totpg),
				'first' => '<a href="javascript:pagefirst()">'.$this->Lang('first').'</a>',
				'prev' => '<a href="javascript:pageback()">'.$this->Lang('previous').'</a>',
				'next' => '<a href="javascript:pageforw()">'.$this->Lang('next').'</a>',
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
		$jsloads[] = <<<EOS
//		$js to validate, confirm
EOS;
	}

}
if ($booker_id) {
	$tplvars['adjust'] = TRUE;
	$tplvars['title_credit'] = $this->Lang('TODO');
	$tplvars['current_credit'] = $this->Lang('TODO');
	if ($pmod) {
		$tplvars['input2'] = $this->CreateInputText($id,'inputcredit',$example,10);
		$tplvars['change2'] = $this->CreateInputSubmit($id,'changecredit',$this->Lang('change'),
			'title="'.$this->Lang('tip_TODO').'"');
		$tplvars['set2'] = $this->CreateInputSubmit($id,'setcredit',$this->Lang('set'),
			'title="'.$this->Lang('tip_TODO').'"');
		$tplvars['help_credit'] = $this->Lang('TODO');
//		$js to watermark, validate, confirm
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.watermark.min.js"></script>
EOS;
		$jsloads[] = <<<EOS
 setTimeout(function() {
  $('#inputcredit').watermark();
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
