<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: findbooking
# Find bookings which match specified criteria
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/*
if arrive via frontend redirect, $params array
 'itempick' => int
 'action'=>'findbooking',
 'returnid'=>page no.
and upon return from form
 'findpick'=>,
 'findfirst'=>,
 'findlast'=>,
 'finduser'=>,
 'findusertype'=>,
 'item_id'=>,
 'pagerows'=>,
 'search'=>,
 'searchsel'=>,
 'submit'=> OR 'cancel'=>
OR admin action
'active_tab' =>
'action' => string 'adminbooking'
*/
//parameter keys filtered out before redirect etc
$localparams = [
	'action',
	'cancel',
	'findfirst',
	'findlast',
	'findpick',
	'finduser',
	'findusertype',
	'pagerows',
	'search',
	'searchsel',
	'submit'
];

$utils = new Booker\Utils();
$admin = isset($params['active_tab']); //TODO
if (!$admin) { //if frontend
//	$cache = Booker\Cache::GetCache($this);
	$utils->UnFilterParameters($params);
}

/*$params[] after retrieval
 'returnid' => int
 'itempick' => int 10001
 'action' => string
 'range' => int 0
 'item_id' => int 10001
 'cartkey' => string
 'resume' => array
     0 => string 'default'
*/

if (isset($params['cancel'])) { //user cancelled
	if (!$admin) { //frontend
		do {
			$resume = array_pop($params['resume']);
		} while ($resume == $params['action'] && $params['resume']);
		if ($resume == $params['action']) {
			$resume = 'default'; //should never happen
		}
		$newparms = $utils->FilterParameters($params,$localparams);
		$this->Redirect($id,$resume,$params['returnid'],$newparms);
	} else {
		$newparms = [];
		if (isset($params['active_tab']))
			$newparms['active_tab'] = $params['active_tab'];
		$this->Redirect($id,$params['resume'],'',$newparms);
	}
}

$utils->DecodeParameters($params,'finduser');

if (isset($params['submit'])) {
	$params['itempick'] = $params['findpick'];
	if (!empty($params['searchsel'])) {
		$use = (int)reset($params['searchsel']);
		if ($use) {
			$use = $db->GetOne('SELECT slotstart FROM '.$this->DispTable.' WHERE bkg_id=?',[$use]);
			if ($use) {
				$params['showfrom'] = (int)$use;
			}
		}
//		$params['bkgid'] = $use; //go directly to 'request' view
	}
	do {
		$resume = array_pop($params['resume']);
	} while ($resume == $params['action'] && $params['resume']);
	if ($resume == $params['action']) {
		$resume = 'default'; //should never happen
	}
	$newparms = $utils->FilterParameters($params,$localparams);
	$this->Redirect($id,$resume,$params['returnid'],$newparms);
}

$overday = ($utils->GetInterval($this,$params['item_id'],'slot') >= 84600);
$idata = $utils->GetItemProperties($this,$params['item_id'],['dateformat','timeformat','timezone']);
$now = $utils->GetZoneTime($idata['timezone']);
$dts = new DateTime('@'.$now,NULL);
if ($admin) {
	$dayfmt='';
	$timefmt='';
	$nowformat = ($overday) ? 'YYYY-M-D':'YYYY-M-D H:mm';
	$example = $utils->IntervalFormat($this,$dts,$nowformat,FALSE);
} else {
	$dayfmt =  $idata['dateformat'];
	$timefmt = $idata['timeformat'];
	$example = $utils->IntervalFormat($this,$dts,$dayfmt,TRUE);
	if (!$overday)
		$example .= ' '.$dts->format($timefmt);
}
$datetimefmt = $utils->DateTimeFormat(FALSE,$admin,TRUE,!$overday,$dayfmt,$timefmt);

//script accumulators
$jsfuncs = [];
$jsloads = [];
$jsincs = [];
$baseurl = $this->GetModuleURLPath();
$tableid = 'details';

$jsloads[] = <<<EOS
 $('#needjs').css('display','none');
EOS;

$hidden = $utils->FilterParameters($params,$localparams);
$tplvars = [
	'needjs' => $this->Lang('needjs'),
	'startform' => $this->CreateFormStart($id,'findbooking',$returnid,'POST','','','',$hidden),
	'endform' => $this->CreateFormEnd(),
	'hidden' => NULL
];

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];
$tplvars['title'] = $this->Lang('title_find');

//generate selectors
$selects = [];

$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_item');
$current = (isset($params['findpick'])) ? $params['findpick'] : $params['itempick'];
$chooser = $utils->GetItemPicker($this,$id,'findpick',$params['firstpick'],$current);
if ($chooser) {
	$oneset->inp = $chooser;
} elseif (isset($params['item_id'])) {
	$oneset->inp = $utils->GetItemNameForID($params['item_id']);
} else {
	$oneset->inp = $this->Lang('all');
}
$selects[] = $oneset;

$oneset = new stdClass();
$oneset->ttl = $this->Lang('start');

$xl1 = strlen($example)+1;
$t = $this->Lang('tip_enter',$example);
$xl2 = strlen($t);
$xl = strlen($example);
$t1 = isset($params['findfirst']) ? $params['findfirst'] : '';
$t1 = $this->CreateInputText($id,'findfirst',$t1,$xl2,$xl1,'title="'.$t.'"');
$t1 = str_replace('class="','class="dateinput ',$t1);

$t2 = isset($params['findlast']) ? $params['findlast'] : '';
$t2 = $this->CreateInputText($id,'findlast',$t2,$xl2,$xl1,'title="'.$t.'"');
$t2 = str_replace('class="','class="dateinput ',$t2);

$oneset->inp = $this->Lang('showrange',$t1,$t2);
$selects[] = $oneset;

$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_user');
$choices = [$this->Lang('is')=>1,$this->Lang('islike')=>2];

// 'finduser' => string like 'Tom'
$t1 = isset($params['findusertype']) ? $params['findusertype'] : 1;
$t1 = $this->CreateInputRadioGroup($id,'findusertype',$choices,$t1,'','&nbsp');
//override crappy default label-layout
$t1 = preg_replace('~label class="(.*)"~U','label class="\\1 radiolabel"',$t1);
$t2 = isset($params['finduser']) ? $params['finduser'] : '';
$oneset->inp = $t1.' '.$this->CreateInputText($id,'finduser',$t2,15,45);
$selects[] = $oneset;

$tplvars['selects'] = $selects;

if (isset($params['search'])) {
/* use $params[] members:
 'findpick' => int item_id
 'findfirst' => string e.g. '2016-07-14'
 'findlast' => string e.g. ''
 'findusertype' => int 1 or 2 for exact or partial name-match
 'finduser' => string like 'Tom'
*/
	$cond = [];
	$t = (int)$params['findpick'];
	if ($t < Booker::MINGRPID) {
		$cond[] = 'D.item_id='.$t;
	} else {
		$members = $utils->GetGroupItems($this,$t);
		if ($members) {
			$fillers = implode(',',$members);
			$cond[] = 'D.item_id IN('.$fillers.')';
		}
	}
	$t = $params['finduser'];
	if ($t) {
		if ($params['findusertype'] == 1) { //exact match
			$cond[] = 'B.name=\''.$t.'\'';
		} else {
			$cond[] = 'B.name LIKE \'%'.$t.'%\''; //TODO caseless match LOWER ...
		}
	}
	$tz = new DateTimeZone('UTC');
	if ($params['findfirst']) { // => string like '2016-07-14'
		$dts = new DateTime($params['findfirst'],$tz);
		$cond[] = 'D.slotstart>='.$dts->getTimestamp();
	}
	if ($params['findlast']) {
		$dts = new DateTime($params['findlast'].' 23:59:59',$tz);
		$cond[] = 'D.slotstart<='.$dts->getTimestamp();
	}
	$noname = '&lt;'.$this->mod->Lang('noname').'&gt;';

	$sql = <<<EOS
SELECT D.bkg_id,D.slotstart,D.slotlen,B.auth_id,COALESCE(B.name,A.name,A.account,'$noname') AS name,I.name AS what
FROM $this->DispTable D
JOIN $this->BookerTable B ON D.booker_id=B.booker_id
JOIN $this->ItemTable I ON D.item_id=I.item_id
LEFT JOIN $this->AuthTable A ON B.auth_id=A.id
EOS;
	if ($cond) {
		$sql .= ' WHERE '.implode(' AND ',$cond);
	}
	if ($t)
		$sql .= ' ORDER BY D.slotstart,B.name';
	else
		$sql .= ' ORDER BY D.slotstart,I.name';

	$rs = $db->SelectLimit($sql,100);
	$items = [];
	if ($rs) {
		$daynames = $utils->DayNames($this,range(0,6),TRUE); //onetime lookup short-form day-names, for speed
		$class = 'row1';
		while ($one = $rs->FetchRow()) {
			$utils->GetUserProperties($this,$one);
			$oneset = new stdClass();
			$oneset->rowclass = $class;
			$oneset->what = $one['what'];
			$t = (int)$one['slotstart'];
			$oneset->when = $utils->RangeDescriptor($this,$t,$t+$one['slotlen'],$daynames); //Mon 2016-9-13 from 9:00 to 9:59';
			$oneset->who = $one['name'];
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
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.SSsort.min.js"></script>
EOS;
			//TODO make page-rows count window-size-responsive
			$pagerows = $this->GetPreference('pagerows',10);
			if ($pagerows && $count > $pagerows) {
				$tplvars['hasnav'] = 1;
				//setup for SSsort
				$choices = [strval($pagerows) => $pagerows];
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
				$tplvars += [
					'pageof' => $this->Lang('pageof',$curpg,$totpg),
					'first' => '<a href="javascript:pagefirst()">'.$this->Lang('first').'</a>',
					'prev' => '<a href="javascript:pageback()">'.$this->Lang('previous').'</a>',
					'next' => '<a href="javascript:pageforw()">'.$this->Lang('next').'</a>',
					'last' => '<a href="javascript:pagelast()">'.$this->Lang('last').'</a>'
				];

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
$tplvars['search'] = $this->CreateInputSubmit($id,'search',$this->Lang('find'));

if ($admin)  { //admin search
	$tplvars['submit'] = NULL;
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
} else { //frontend
	$xtra = ($count) ? '' : 'disabled="disabled"';
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('useselection'),$xtra);
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
}

 $stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/alertable.min.css" />
EOS;

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;

//TODO adjust dialog styling for error
$jsfuncs[] = <<<EOS
function showerr(msg) {
 $.alertable.alert(msg,{
  okName: '{$this->Lang('close')}'
 });
}
EOS;

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

$jsfuncs[] = <<<EOS
function validate(ev) {
 var f = '$datetimefmt',
  \$os = $('#{$id}findfirst'),
  s = \$os.val(),
  \$oe = $('#{$id}findlast'),
  e = \$oe.val(),
  ds, de, ok;
 if (s) {
  ds = fmt.parseDate(s,f); //null upon failure
 } else {
  ds = false;
 }
 if (e) {
  de = fmt.parseDate(e,f);
 } else {
  de = false;
 }
 ok = (ds && de) ? (de >= ds) : (ds !== null && de !== null);
 if (ok) {
  var dt;
  if (ds) {
   dt = fmt.formatDate(s,f);
   \$os.val(dt);
  }
  if (de) {
   dt = fmt.formatDate(e,f);
   \$oe.val(dt);
  }
  return true;
 }
 showerr('{$this->Lang('err_badtime')}');
 ev.stopImmediatePropagation();
 ev.preventDefault();
 return false;
}
EOS;

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

$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.min.css" />
EOS;

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.watermark.min.js"></script>
EOS;

$nextm = $this->Lang('nextm');
$prevm = $this->Lang('prevm');

$jsloads[] = <<<EOS
 var fmt = new DateFormatter({
  longDays: [$dnames],
  shortDays: [$sdnames],
  longMonths: [$mnames],
  shortMonths: [$smnames],
  meridiem: [$meridiem],
  ordinal: function (number) {
   var n = number % 10, suffixes = {1: 'st', 2: 'nd', 3: 'rd'};
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
 setTimeout(function() {
  $('.dateinput').watermark();
 },10);
EOS;

$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
EOS;
if (isset($params['item_id'])) {
	$customcss = $utils->GetStylesURL($this,$params['item_id']);
	if ($customcss)
		$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
EOS;
}

//heredoc-var newlines are a problem for in-js quoted strings, so ...
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

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'find.tpl',$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
