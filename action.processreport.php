<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: report
# Display bookings information
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

if (isset($params['close'])) {
//	$resume = array_pop($params['resume']);
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
}
//report type
/* selection-flags
Booker\Dataops::BKGFUTURE
Booker\Dataops::BKGPAST
Booker\Dataops::BKGFREE
Booker\Dataops::BKGUNPAID
Booker\Dataops::BKGPAID
Booker\Dataops::BKGONCE
Booker\Dataops::BKGREPT
Booker\Dataops::BKGSINGL
Booker\Dataops::BKGGROUP
Booker\Dataops::BKGITEM
Booker\Dataops::BKGUSER
Booker\Dataops::BKGUTYPE
*/
$t1 = substr($params['task'], 0, 3); //{'itm' bkr' 'rng'}
$t2 = substr($params['task'], 3); //{'view' 'pay' 'stat'}
/*switch ($t1) {
 case 'itm':
	switch ($t2) {
	 case 'view':
		$flags = Booker\Dataops::BKGALL;
		break;
	 case 'pay':
		$flags = Booker\Dataops::BKGALL;
		break;
	 case 'stat':
		$flags = Booker\Dataops::BKGALL;
		break;
	 default:
		$flags = 0;
		break;
	}
	break;
 case 'bkr':
	switch ($t2) {
	 case 'view':
		$flags = Booker\Dataops::BKGALL;
		break;
	 case 'pay':
		$flags = Booker\Dataops::BKGALL;
		break;
	 case 'stat':
		$flags = Booker\Dataops::BKGALL;
		break;
	 default:
		$flags = Booker\Dataops::BKGALL;
		break;
	}
	break;
 case 'rng':
	switch ($t2) {
	 case 'view':
		$flags = Booker\Dataops::BKGALL;
		break;
	 case 'pay':
		$flags = Booker\Dataops::BKGALL;
		break;
	 case 'stat':
		$flags = Booker\Dataops::BKGALL;
		break;
	 default:
		$flags = Booker\Dataops::BKGALL;
		break;
	}
	break;
 default:
	$flags = Booker\Dataops::BKGALL;
	break;
}
*/

if (isset($params['range'])) {
//TODO process $params['startchooser', 'endchooser']
}

$funcs = new Booker\Dataops();
$data = $funcs->FilterData($this, Booker\Dataops::BKGALL); //, $itemid = FALSE, $userid = FALSE, $typeid = FALSE);

if (isset($params['export'])) {
	//TODO STUFF
	exit;
}

$tplvars = array();

$this->_BuildNav($id, $returnid, $params, $tplvars);
$tplvars['startform'] = $this->CreateFormStart($id, 'processreport', $returnid, 'POST', '', '', '',
	array(task=>$params['task'],'active_tab'=>$params['active_tab'],'resume'=>$params['resume']));
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($params['message'])) {
	$tplvars['message'] = $params['message'];
}

$rows = array();
if ($data) {
	//script accumulators
	$jsincs = array();
	$jsfuncs = array();
	$jsloads = array();
	$baseurl = $this->GetModuleURLPath();

	$tplvars['startchooser'] = NULL; //$this->CreateInput();
	$tplvars['endchooser'] = NULL; //$this->CreateInput();
	//TODO strings
	$tplvars['range'] = $this->CreateInputSubmit($id, 'range', $this->Lang('export'),
		'title="'.$this->Lang('tip_export_selected_records').
		'" onclick="TODO"');
/* each row of $data
'name'	string	"Coaching" or NULL
'publicid' string or NULL
'address'	string	"TBA" or NULL
'phone' string or NULL
'what'	string	"Court 5"
'slotstart'	string	"1497538800"
'slotlen'	string	"14399"
'status'	string	"10"
'fee'	string	"0.00"
'feepaid'	string	"0.00"
'payment'	string	"40"
*/
	//$colnames = func($t1,$t2)
	$colnames = array(
		$this->Lang('title_X'),
		$this->Lang('title_X'),
		$this->Lang('title_X')
	);
	$tplvars['colnames'] = $colnames;

	$colsorts = array_fill(0, count($colnames), 1); //all sortable
	$tplvars['colsorts'] = $colsorts;

	$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
		cms_utils::get_theme_object();
	$icon_view = $theme->DisplayImage('icons/system/view.gif', $this->Lang('view'), '', '', 'systemicon');
	$icon_export = $theme->DisplayImage('icons/system/export.gif', $this->Lang('export'), '', '', 'systemicon');

	foreach ($data as &$one) {
		$fields = array();
		$rid = (int)$one['record_id'];
		$oneset = new stdClass();
		ksort($fields); //conform order to titles
		$oneset->fields = $fields;
		$oneset->view = $this->CreateLink($id, 'browse_record', '', $icon_view,
			['record_id'=>$rid, 'browser_id'=>$bid, 'form_id'=>$fid]);
		$oneset->export = $this->CreateLink($id, 'export_record', '', $icon_export,
			['record_id'=>$rid, 'browser_id'=>$bid, 'form_id'=>$fid]);
		$oneset->sel = $this->CreateInputCheckbox($id, 'sel[]', $rid, -1);
		$rows[] = $oneset;
	}
	unset($one);
}

$tplvars['data'] = $rows;
$rc = count($rows);
$tplvars['rcount'] = $rc;
if ($rc) {
	if ($rc > 1) {
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.SSsort.min.js"></script>
EOS;
		$jsloads[] = <<<EOS
 $('#datatable').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s',
  paginate: true,
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
		$tplvars['header_checkbox'] =
			$this->CreateInputCheckbox($id, 'selectall', TRUE, FALSE, 'onclick="select_all(this);"');
	} else {
		$tplvars['header_checkbox'] = NULL;
	}

	if ($pagerows && $rc>$pagerows) {
		//more setup for SSsort
		$curpg='<span id="cpage">1</span>';
		$totpg='<span id="tpage">'.ceil($rc/$pagerows).'</span>';

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

	$jsfuncs[] = <<<EOS
function sel_count() {
 var cb = $('input[name="{$id}sel[]"]:checked');
 return cb.length;
}
function any_selected() {
 return (sel_count() > 0);
}
EOS;
	if (1) { //TODO test
		$tplvars['export'] = $this->CreateInputSubmit($id, 'export', $this->Lang('export'),
		'title="'.$this->Lang('tip_export_selected_records').
		'" onclick="return any_selected();"');
	}
} else {
	$tplvars['nodata'] = $this->Lang('nodata');
}

$tplvars['close'] = $this->CreateInputSubmit($id, 'close', $this->Lang('close'));

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'report.tpl',$tplvars);
if ($jsall) {
	echo $jsall;
}
