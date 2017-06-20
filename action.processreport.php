<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: report
# Display bookings summary-information
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/*first-pass $params[]
'display' => string
OR
'export' => string
'task'=> string
'active_tab' => string
'resume' => string
later
'close' => string
OR
'export' => string
OR
'range' => string
'task'=> string
'showfrom' => string
'showto'=> string
'from' => string
'until' => string
*/

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
} else {
	$params['resume'] = array('defaultadmin'); //got here via link
}

if (isset($params['close'])) {
	$resume = array_pop($params['resume']);
	$this->Redirect($id,$resume,'',array('active_tab'=>$params['active_tab']));
}

if (isset($params['filter'])) {
	$params['resume'][] = $params['action'];
	//TODO other filter-params
	$this->Redirect($id,'filter','',array(
		'active_tab'=>$params['active_tab'],
		'resume'=>json_encode($params['resume'])
	));
}

$params['task'] = 'itmview'; //DEBUG
//report type
switch (substr($params['task'],0,3)) {
 case 'itm':
	$f1 = 'item';
	break;
 case 'bkr':
	$f1 = 'booker';
	break;
 case 'rng':
	$f1 = 'interval';
	break;
 default:
	echo $this->Lang('err_system');
	return;
}

switch (substr($params['task'],3)) {
 case 'view':
	$f2 = 'summary';
	break;
 case 'pay':
	$f2 = 'payments';
	break;
 case 'stat':
	$f2 = 'status';
	break;
 default:
	echo $this->Lang('err_system');
	return;
}

if (isset($params['range'])) {
//TODO process $params['showfrom', 'showto']
} elseif (isset($params['from'])) {
//TODO process $params['from', 'until']
}

$utils = new Booker\Utils();
$tplvars = array();
$display = array(); //output populated by included file, or maybe empty

require 'report.'.$f1.'-'.$f2.'.php';

if (isset($params['export'])) {
	if ($display) {
		//TODO STUFF
		exit;
	} else {
		$params['message'] = $this->Lang('err_data');
	}
}

$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$tplvars['startform'] = $this->CreateFormStart($id,'processreport',$returnid,'POST','','','',
	array('task'=>$params['task'],'active_tab'=>$params['active_tab']));
//TODO 'resume'=>$params[ 'resume' 'from' 'until' etc
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($params['message'])) {
	$tplvars['message'] = $params['message'];
}

//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$baseurl = $this->GetModuleURLPath();

$dc = count($display);
$tplvars['dcount'] = $dc;
if ($dc) {
	$tplvars['data'] = $display;
	$tplvars['colnames'] = $coltitles;

	if ($dc > 1) {
		$pagerows = (int)$this->GetPreference('pagerows',10);
		$paged = ($pagerows > 5 && $dc > $pagerows) ? 'true' : 'false';

		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.SSsort.min.js"></script>
EOS;
		$jsloads[] = <<<EOS
 $('#datatable').SSsort({
  oddClass: 'row1',
  evenClass: 'row2',
  paginate: {$paged},
  pagesize: {$pagerows},
  currentid: 'cpage',
  countid: 'tpage'
 });
EOS;
		if ($paged == 'true') {
			//more setup for SSsort
			$curpg='<span id="cpage">1</span>';
			$totpg='<span id="tpage">'.ceil($dc/$pagerows).'</span>';

			$choices = [strval($pagerows) => $pagerows];
			$f = ($pagerows < 4) ? 5 : 2;
			$n = $pagerows * $f;
			if ($n < $dc) {
				$choices[strval($n)] = $n;
			}
			$n *= 2;
			if ($n < $dc) {
				$choices[strval($n)] = $n;
			}
			$choices[$this->Lang('all')] = 0;

			$tplvars += [
				'hasnav'=>1,
				'first'=>'<a href="javascript:pagefirst()">'.$this->Lang('first').'</a>',
				'prev'=>'<a href="javascript:pageback()">'.$this->Lang('previous').'</a>',
				'next'=>'<a href="javascript:pageforw()">'.$this->Lang('next').'</a>',
				'last'=>'<a href="javascript:pagelast()">'.$this->Lang('last').'</a>',
				'pageof'=>$this->Lang('pageof', $curpg, $totpg),
				'rowchanger'=>$this->CreateInputDropdown($id, 'pagerows', $choices, -1, $pagerows, 'onchange="pagerows(this);"').'&nbsp;&nbsp;'.$this->Lang('pagerows')
			];

			$jsfuncs[] = <<<'EOS'
function pagefirst() {
 var t = document.getElementById('datatable');
 $.SSsort.movePage(t,false,true);
}
function pagelast() {
 var t = document.getElementById('datatable');
 $.SSsort.movePage(t,true,true);
}
function pageforw() {
 var t = document.getElementById('datatable');
 $.SSsort.movePage(t,true,false);
}
function pageback() {
 var t = document.getElementById('datatable');
 $.SSsort.movePage(t,false,false);
}
function pagerows(cb) {
 var t = document.getElementById('datatable');
 $.SSsort.setCurrent(t,'pagesize',parseInt(cb.value));
}
EOS;
		} else {
			$tplvars['hasnav'] = 0;
		}
		$tplvars['header_checkbox'] =
			$this->CreateInputCheckbox($id, 'selectall', TRUE, FALSE, 'onclick="select_all(this);"');
	} else {
		$tplvars['header_checkbox'] = NULL; //TODO into $colnames
	}

	if (1) { //TODO relevant test
		$tplvars['export'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'));
	}
} else {
	$tplvars['nodata'] = $this->Lang('nodata');
}

$tplvars['close'] = $this->CreateInputSubmit($id, 'close', $this->Lang('close'));

$tplvars['titlefrom'] = $this->Lang('start');
$t = $this->CreateInputText($id,'showfrom','',12,15);
$tplvars['showfrom'] = str_replace('class="','class="dateinput ',$t);
$tplvars['helpfrom'] = $this->Lang('help_reportfrom');
$tplvars['titleto'] = $this->Lang('end');
$t = $this->CreateInputText($id,'showto','',12,15);
$tplvars['showto'] = str_replace('class="','class="dateinput ',$t);
$tplvars['helpto'] = $this->Lang('help_reportto');
//for date-picker
$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
EOS;

$prevm = $this->Lang('prevm');
$nextm = $this->Lang('nextm');
//js wants quoted period-names
$t = $this->Lang('longmonths');
$mnames = "'".str_replace(",","','",$t)."'";
$t = $this->Lang('shortmonths');
$smnames = "'".str_replace(",","','",$t)."'";
$t = $this->Lang('longdays');
$dnames = "'".str_replace(",","','",$t)."'";
$t = $this->Lang('shortdays');
$sdnames = "'".str_replace(",","','",$t)."'";
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
   var n = number % 10, suffixes = {1: 'st', 2: 'nd', 3: 'rd'};
   return Math.floor(number % 100 / 10) === 1 || !suffixes[n] ? 'th' : suffixes[n];
  }
 });
 $('.dateinput').pikaday({
  format: 'Y-m-j',
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
EOS;
$tplvars['rangeset'] = $this->Lang('title_report_change');
$tplvars['range'] = $this->CreateInputSubmit($id,'range',$this->Lang('apply'),
	'title="'.$this->Lang('tip_report_interval').'"');

if (!empty($stylers)) {
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
	echo $utils->MergeJS(FALSE,array($t),FALSE);
}

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'report.tpl',$tplvars);
if ($jsall) {
	echo $jsall;
}
