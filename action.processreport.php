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
'startchooser' => string
'endchooser'=> string
*/

$utils = new Booker\Utils();
$utils->DecodeParameters($params);

if (isset($params['close'])) {
//	$resume = array_pop($params['resume']);
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
}

$params['task'] = 'itmview'; //DEBUG
//report type
switch ($params['task']) {
 case 'itmview':
	$t = 'item-summary';
	break;
 case 'itmpay':
	$t = 'item-payments';
	break;
 case 'itmstat':
	$t = 'item-status';
	break;
 case 'bkrview':
	$t = 'booker-summary';
	break;
 case 'bkrpay':
	$t = 'booker-payments';
	break;
 case 'bkrstat':
	$t = 'booker-status';
	break;
 case 'rngview':
	$t = 'interval-summary';
	break;
 case 'rngpay':
	$t = 'interval-payments';
	break;
 case 'rngstat':
	$t = 'interval-status';
	break;
 default:
	echo $this->Lang('err_system');
	return;
}

if (isset($params['range'])) {
//TODO process $params['startchooser', 'endchooser']
}

$display = array(); //output populated by included file, or maybe empty

require 'report.'.$t.'.php';

if (isset($params['export'])) {
	if ($display) {
		//TODO STUFF
		exit;
	} else {
		$params['message'] = $this->Lang('err_data');
	}
}

$tplvars = array();

$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$tplvars['startform'] = $this->CreateFormStart($id,'processreport',$returnid,'POST','','','',
	array('task'=>$params['task'],'active_tab'=>$params['active_tab']));
//TODO 'resume'=>$params['resume']
$tplvars['endform'] = $this->CreateFormEnd();

$tplvars['title'] = 'PAGE TITLE GOES HERE';

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

	//TODO titles and help for these
	//TODO date-pick for these
	$tplvars['startchooser'] = $this->CreateInputText($id,'startchooser','',30,64); //TODO "ranger" class
	$tplvars['endchooser'] = $this->CreateInputText($id,'endchooser','',30,64); //ditto
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
	$t = $this->Lang('longdays');
	$dnames = "'".str_replace(",","','",$t)."'";
	$t = $this->Lang('shortdays');
	$sdnames = "'".str_replace(",","','",$t)."'";
	$jsloads[] = <<<EOS
 $('.ranger').pikaday({
  field: document.getElementById('{$id}showfrom'),
  i18n: {
   previousMonth: '$prevm',
   nextMonth: '$nextm',
   months: [$mnames],
   weekdays: [$dnames],
   weekdaysShort: [$sdnames]
  },
  onClose: function() {
   if('_d' in this && this._d) {
    var fmt = new DateFormatter();
    var dt = fmt.formatDate(this._d,'Y-m-d');
    $('#{$id}showfrom').val(dt);
   }
  }
 });
EOS;
	$tplvars['range'] = $this->CreateInputSubmit($id,'range',$this->Lang('interval'),
		'title="'.$this->Lang('tip_report_interval').'" onclick="return TODO();"');
	//TODO js to validate chooser(s) content
	if ($dc > 1) {
		$pagerows = $this->GetPreference('pagerows',10);
		$paged = ($pagerows > 5 && $dc > $pagerows);

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
		$jsfuncs[] = <<<'EOS'
function select_all(cb) {
 $('#datatable > tbody').find('input[type="checkbox"]').attr('checked',cb.checked);
}
EOS;
		if ($paged) {
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
 $.SSsort.movePage($('#submissions')[0],false,true);
}
function pagelast() {
 $.SSsort.movePage($('#submissions')[0],true,true);
}
function pageforw() {
 $.SSsort.movePage($('#submissions')[0],true,false);
}
function pageback() {
 $.SSsort.movePage($('#submissions')[0],false,false);
}
function pagerows(cb) {
 $.SSsort.setCurrent($('#submissions')[0],'pagesize',parseInt(cb.value));
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
		$tplvars['export'] = $this->CreateInputSubmit($id,'export',$this->Lang('export'),
			'title="'.$this->Lang('tip_export_selected_records').'" onclick="return any_selected();"');
		$jsfuncs[] = <<<EOS
function sel_count() {
 var cb = $('input[name="{$id}sel[]"]:checked');
 return cb.length;
}
function any_selected() {
 return (sel_count() > 0);
}
EOS;
	}
} else {
	$tplvars['nodata'] = $this->Lang('nodata');
}

$tplvars['close'] = $this->CreateInputSubmit($id, 'close', $this->Lang('close'));

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
