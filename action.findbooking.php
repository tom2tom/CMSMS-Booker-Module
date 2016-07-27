<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: findbooking
# Find bookings which match specified criteria
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*
if arrive via frontend redirect
$params array
 'item_id'=>
 'startat'=>,
 'range'=>
 'view'=>
 MAYBE
 'bookat'=>
or upon return from form
 'item_id'=>
 'startat'=>
 'range'=>
 'view'=>
 'submit'=> OR 'cancel'=>
MORE
OR admin action
'find' =>
'active_tab' =>
'action' => string 'adminbooking'
*/
//parameter keys used locally, but not to be cached before departure
$localparams = array(
	'cancel',
	'find', //not set here, but don't return anyway
	'findchooser',
	'findfirst',
	'findlast',
	'finduser',
	'findusertype',
	'search',
	'searchsel',
	'submit'
);

$cache = Booker\Cache::GetCache($this);
$utils = new Booker\Utils();
$utils->RetrieveParameters($cache,$params);

if (isset($params['cancel'])) { //user cancelled
	if (!(is_numeric($params['startat']) || strtotime($params['startat']))) {
		$params['message'] = $this->Lang('err_system').' '.$params['startat'];
		$params['startat'] = (int)(time()/86400);
	} elseif (!isset($params['message']))
		$params['message'] = '';
	$utils->SaveParameters($cache,$params,$localparams);
	$this->Redirect($id,$params['action'],$params['returnid'],
		array('storedparams'=>$params['storedparams']));
}

if (isset($params['submit'])) {
	$params['chooser'] = $params['findchooser'];
	if (!empty($params['searchsel'])) {
		$use = (int)reset($params['searchsel']);
		if ($use) {
			$use = $db->GetOne('SELECT slotstart FROM '.$this->DataTable.' WHERE bkg_id=?',array($use));
			if ($use) {
				$params['startat'] = (int)$use;
			}
		}
//		$params['slotid'] = $use; //go directly to 'request' view
	}
	$utils->SaveParameters($cache,$params,$localparams);
	$this->Redirect($id,$params['action'],$params['returnid'],
		array('storedparams'=>$params['storedparams']));
}

if (isset($params['item_id'])) {
	$item_id = (int)$params['item_id'];
	$is_group = ($item_id >= Booker::MINGRPID);
	$idata = $utils->GetItemProperty($this,$item_id,'name');
	$choices = $utils->GetItemFamily($this,$db,$item_id);
} else {
	$idata = array('name'=>$this->Lang('all'));
	$choices = $db->GetAssoc('SELECT item_id,name FROM '.$mod->ItemTable.' WHERE active>0');
}

//script accumulators
$jsfuncs = array();
$jsloads = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();
$tableid = 'details';

$tplvars = array();

$utils->SaveParameters($cache,$params,$localparams);
$tplvars['startform'] = $this->CreateFormStart($id,'findbooking',$returnid,
	'POST','','','',array(
	'item_id'=>$item_id,
	'storedparams'=>$params['storedparams']
	));
$tplvars['endform'] = $this->CreateFormEnd();
$hidden = NULL; //TODO
$tplvars['hidden'] = $hidden;

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];
$tplvars['title'] = $this->Lang('title_find');

//generate selectors
$selects = array();

$oneset = new stdClass();
$oneset->title = $this->Lang('title_item');
if ($choices) {
	if (count($choices) > 1) {
		asort($choices,SORT_NATURAL);
		$t2 = isset($params['findchooser']) ? $params['findchooser'] : $item_id;
		$t1 = $this->CreateInputDropdown($id,'findchooser',array_flip($choices),-1,$t2,'id="'.$id.'chooser"');
	} else {
		$t1 = $idata['name'];
	}
} else {
	$t1 = $idata['name'];
}
$oneset->input = $t1;
$selects[] = $oneset;

$oneset = new stdClass();
$oneset->title = $this->Lang('start');

$t1 = isset($params['findfirst']) ? $params['findfirst'] : '';
$t1 = $this->CreateInputText($id,'findfirst',$t1,10);
$t1 = str_replace('class="cms_textfield"','class="dateinput cms_textfield"',$t1);
$t2 = isset($params['findlast']) ? $params['findlast'] : '';
$t2 = $this->CreateInputText($id,'findlast',$t2,10);
$t2 = str_replace('class="cms_textfield"','class="dateinput cms_textfield"',$t2);
$oneset->input = $this->Lang('rangeshow',$t1,$t2);
$selects[] = $oneset;

$oneset = new stdClass();
$oneset->title = $this->Lang('title_user');
$choices = array($this->Lang('is')=>1,$this->Lang('islike')=>2);

// 'finduser' => string like 'Tom'
$t1 = isset($params['findusertype']) ? $params['findusertype'] : 1;
$t1 = $this->CreateInputRadioGroup($id,'findusertype',$choices,$t1,'','&nbsp');
//override crappy default label-layout
$t1 = preg_replace('~label class="(.*)"~U','label class="\\1 radiolabel"',$t1);
$t2 = isset($params['finduser']) ? $params['finduser'] : '';
$oneset->input = $t1.' '.$this->CreateInputText($id,'finduser',$t2,15,45);
$selects[] = $oneset;

$tplvars['selects'] = $selects;

if (isset($params['search'])) {
/* use $params[] members:
 'findchooser' => int item_id
 'findfirst' => string e.g. '2016-07-14'
 'findlast' => string e.g. ''
 'findusertype' => int 1 or 2 for exact or partial name-match
 'finduser' => string like 'Tom'
 */
	$utils = new Booker\Utils();
	$cond = array();
	$t = (int)$params['findchooser'];
	if ($t < Booker::MINGRPID) {
		$cond[] = 'B.item_id='.$t;
	} else {
		$members = $utils->GetGroupItems($this,$t);
		if ($members) {
			$fillers = implode(',',$members);
			$cond[] = 'B.item_id IN('.$fillers.')';
		}
	}
	$t = $params['finduser'];
	if ($t) {
		if ($params['findusertype'] == 1) { //exact match
			$cond[] = 'B.user=\''.$t.'\'';
		} else {
			$cond[] = 'B.user LIKE \'%'.$t.'%\''; //TODO caseless match LOWER ...
		}
	}
	$tz = new DateTimeZone('UTC');
	if ($params['findfirst']) { // => string like '2016-07-14'
		$dts = new DateTime($params['findfirst'],$tz);
		$cond[] = 'B.slotstart>='.$dts->getTimestamp();
	}
	if ($params['findlast']) {
		$dts = new DateTime($params['findlast'].' 23:59:59',$tz);
		$cond[] = 'B.slotstart<='.$dts->getTimestamp();
	}
	$sql = 'SELECT B.bkg_id,B.slotstart,B.slotlen,B.user,I.name FROM '.$this->DataTable.
	' B LEFT JOIN '.$this->ItemTable.' I ON B.item_id=I.item_id';
	if ($cond) {
		$sql .= ' WHERE '.implode(' AND ',$cond);
	}
	if ($t)
		$sql .= ' ORDER BY B.slotstart,B.user';
	else
		$sql .= ' ORDER BY B.slotstart,I.name';

	$rs = $db->SelectLimit($sql,100);
	$items = array();
	if ($rs) {
		$daynames = $utils->DayNames($this,range(0,6),TRUE); //onetime lookup short-form day-names, for speed
		$class = 'row1';
		while ($one = $rs->FetchRow()) {
			$oneset = new stdClass();
			$oneset->rowclass = $class;
			$oneset->what = $one['name'];
			$t = (int)$one['slotstart'];
			$oneset->when = $utils->RangeDescriptor($this,$t,$t+$one['slotlen'],$daynames); //Mon 2016-9-13 from 9:00 to 9:59';
			$oneset->who = $one['user'];
			$oneset->sel = $this->CreateInputCheckbox($id,'searchsel[]',(int)$one['bkg_id'],-1,'class="pagecheckbox"');
			$items[] = $oneset;
			$class = ($class = 'row1') ? 'row2':'row1';
		}
		$rs->Close();
	}

	$count = count($items);
	if ($count) {
		$tplvars['finds'] = $items;
		$tplvars['whattitle'] = $this->Lang('title_item');
		$tplvars['whentitle'] = $this->Lang('title_starting');
		$tplvars['whotitle'] = $this->Lang('title_user');

		if ($count > 1) {
			$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.SSsort.min.js"></script>
EOS;
			//TODO make page-rows count window-size-responsive
			$pagerows = $this->GetPreference('pref_pagerows',10);
			if ($pagerows && $count > $pagerows) {
				$tplvars['hasnav'] = 1;
				//setup for SSsort
				$choices = array(strval($pagerows) => $pagerows);
				$f = ($pagerows < 4) ? 5 : 2;
				$n = $pagerows * $f;
				if ($n < $count)
					$choices[strval($n)] = $n;
				$n *= 2;
				if ($n < $count)
					$choices[strval($n)] = $n;
				$choices[$this->Lang('all')] = 0;
				$tplvars['rowchanger'] =
					$this->CreateInputDropdown($id,'pagerows',$choices,-1,$pagerows,
					'onchange="pagerows(this);"').'&nbsp;&nbsp;'.$this->Lang('pagerows');
				$curpg='<span id="cpage">1</span>';
				$totpg='<span id="tpage">'.ceil($count/$pagerows).'</span>';
				$tplvars += array(
					'pageof' => $this->Lang('pageof',$curpg,$totpg),
					'first' => '<a href="javascript:pagefirst()">'.$this->Lang('first').'</a>',
					'prev' => '<a href="javascript:pageback()">'.$this->Lang('previous').'</a>',
					'next' => '<a href="javascript:pageforw()">'.$this->Lang('next').'</a>',
					'last' => '<a href="javascript:pagelast()">'.$this->Lang('last').'</a>'
				);

				$jsfuncs[] = <<<EOS
function pagefirst() {
 $.SSsort.movePage($('#{$tableid}')[0],false,true);
}
function pagelast() {
 $.SSsort.movePage($('#{$tableid}')[0],true,true);
}
function pageforw() {
 $.SSsort.movePage($('#{$tableid}')[0],true,false);
}
function pageback() {
 $.SSsort.movePage($('#{$tableid}')[0],false,false);
}
function pagerows(cb) {
 $.SSsort.setCurrent($('#{$tableid}')[0],'pagesize',parseInt(cb.value));
}

EOS;
				$extras = <<<EOS
,
  currentid: 'cpage',
  countid: 'tpage',
  paginate: true,
  pagesize: {$pagerows}
EOS;
			} else { //$count <= $pagerows
				$tplvars['hasnav'] = 0;
				$extras = '';
			}
			//TODO sorter func for type 'slotwhen'
			$jsloads[] = <<<EOS
 $.SSsort.addParser({
  id: 'slotwhen',
  is: function(s) {
   return true;
  },
  format: function(s,node) {
   var t = $.trim(s);
   return t; //TODOfunc(t);
  },
  watch: false,
  type: 'text'
 });
 $('table#{$tableid}').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s'{$extras}
 });

EOS;
		}//$count > 1
	} else { // no matches found
		$tplvars['nofinds'] = $this->Lang('nofinds');
	}
} else {
	$count = 0;
}
$tplvars['count'] = $count;

$xtra = ($count) ? '' : 'disabled="disabled"';
$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('useselection'),$xtra);
$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
$tplvars['search'] = $this->CreateInputSubmit($id,'search',$this->Lang('find'));


$jsloads[] = <<<EOS
 $('#{$id}submit').prop('disabled',true).bind('click',validate);
 $('table#{$tableid}').find('input[type="checkbox"]').click(function(ev) {
  var st = $(this).attr('checked');
  if (st) {
    var \$cb = $(this),
     \$all = \$cb.closest('table').find('[type="checkbox"]');
    \$all.attr('checked',false);
    \$cb.attr('checked',true);
    $('#{$id}submit').prop('disabled',false);
  } else {
    $('#{$id}submit').prop('disabled',true);
  }
 });

EOS;

//for picker

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/moment.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.pikaday.min.js"></script>
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

$jsloads[] = <<<EOS
 $('.dateinput').Pikaday({
  container: document.getElementById('calendar'),
  format: 'YYYY-MM-DD',
  i18n: {
   previousMonth: '{$prevm}',
   nextMonth: '{$nextm}',
   months: [{$mnames}],
   weekdays: [{$dnames}],
   weekdaysShort: [{$sdnames}]
  }
 });

EOS;

$jsfuncs[] = <<<EOS
function showerr(msg) {
 confirm(msg);
}
function validate(ev) {
 var \$os = $('input[name="{$id}findfirst"]'),
  s = \$os.val(),
  \$oe = $('input[name="{$id}findlast"]'),
  e = \$oe.val(),
  ds, de, ok;
 if (s) {
  ds = (!isNaN(Date.parse(s))) ? new Date(s) : null;
 } else {
  ds = false;
 }
 if (e) {
  de = (!isNaN(Date.parse(e))) ? new Date(e) : null;
 } else {
  de = false;
 }
 ok = (ds && de) ? (de >= ds) : (ds !== null && de !== null);
 if (ok) {
  var f = 'YYYY-MM-DD',
   dn;
  if (ds) {
   dn = moment(ds).format(f);
   \$os.val(dn);
  }
  if (de) {
   dn = moment(de).format(f);
   \$oe.val(dn);
  }
  return true;
 }
 showerr('{$this->Lang('err_badtime')}');
 ev.stopImmediatePropagation();
 ev.preventDefault();
 return false;
}

EOS;

$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
EOS;
$customcss = $utils->GetStylesURL($this,$item_id);
if ($customcss)
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
EOS;

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

echo Booker\Utils::ProcessTemplate($this,'find.tpl',$tplvars);
