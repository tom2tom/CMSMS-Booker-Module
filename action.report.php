<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: report
# Display bookings information
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (isset($params['close'])) {

} elseif (isset($params['export'])) {

}

$tplvars = array();

$this->_BuildNav($id, $returnid, $params, $tplvars);
$tplvars['startform'] = $this->CreateFormStart($id, 'report', $returnid, 'POST', '', '', '',
	array(TODO));
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($params['message'])) {
	$tplvars['message'] = $params['message'];
}

$funcs = new Booker\Dataops();
$data = $funcs->FilterData($this, $flags, $itemid = FALSE, $userid = FALSE, $typeid = FALSE);

$rows = array();
if ($data) {
	//script accumulators
	$jsincs = array();
	$jsfuncs = array();
	$jsloads = array();
	$baseurl = $this->GetModuleURLPath();

	$tplvars['startchooser'] = $this->CreateInput();
	$tplvars['endchooser'] = $this->CreateInput();
	//TODO
	$tplvars['range'] = $this->CreateInputSubmit($id, 'range', $this->Lang('export'),
		'title="'.$this->Lang('tip_export_selected_records').
		'" onclick="return any_selected();"');

	$colnames = array(
		$this->Lang('title_X'),
		$this->Lang('title_X'),
		$this->Lang('title_X')
	);
	$colsorts = array(1,1,1);
	$tplvars['colnames'] = $colnames;
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
