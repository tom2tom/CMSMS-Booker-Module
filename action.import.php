<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: import
# Import item(s) or booking(s) data from .csv file
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!function_exists('ImportRedirParms')) {
 function ImportRedirParms($resume, &$params, $msg = FALSE)
 {
	$pnew = array_intersect_key($params,array(
	'item_id'=>1,
	'booker_id'=>1,
	'task'=>1,
	'active_tab'=>1));
	if (!empty($params['resume']))
		$pnew['resume'] = json_encode($params['resume']);
/*	switch ($resume) {
		default:
	}
*/
	if ($msg)
		$pnew['message'] = $msg;
	return $pnew;
 }
}

if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
} else {
	$params['resume'] = array('defaultadmin');
}

if (isset($params['cancel'])) {
	$resume = array_pop($params['resume']);
	$newparms = ImportRedirParms($resume,$params);
	$this->Redirect($id,$resume,'',$newparms);
}

if (!empty($params['importitm']))
	$itype = 1;
elseif (!empty($params['importbkr']))
	$itype = 2;
elseif (!empty($params['importbkg']))
	$itype = 3;
elseif (!empty($params['importfee']))
	$itype = 4;
elseif (!empty($params['importhist']))
	$itype = 5;
else
	exit;

if (isset($_FILES) && isset($_FILES[$id.'csvfile'])) {
	$funcs = new Booker\Import();
	switch ($itype) {
	 case 1:
		list($res,$prop) = $funcs->ImportItems($this,$id);
		$k = 'itemv_multi';
		break;
	 case 2:
		list($res,$prop) = $funcs->ImportBookers($this,$id);
		$k = 'booker_multi';
		break;
	 case 3:
		$single = (isset($params['item_id'])) ? (int)$params['item_id']:FALSE;
		list($res,$prop) = $funcs->ImportBookings($this,$id,$single);
		$k = 'booking_multi';
		break;
	 case 4:
		list($res,$prop) = $funcs->ImportFees($this,$id);
		$k = 'fee_multi';
		break;
	 case 5:
		list($res,$prop) = $funcs->ImportHistory($this,$id);
		$k = 'history_multi'; //TODO or request_multi?
		break;
	}

	if ($res) {
		$t = $this->Lang('import_result',$prop,$this->Lang($k));
		$msg = $this->_PrettyMessage($t,TRUE,FALSE);
	} else {
		$msg = $this->_PrettyMessage($prop,FALSE);
	}
	$resume = array_pop($params['resume']);
	$newparms = ImportRedirParms($resume,$params,$msg);
	$this->Redirect($id,$resume,'',$newparms);
}

$tplvars = array();

$from = end($params['resume']);

$utils = new Booker\Utils();
$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);

$hidden = array('resume'=>$resume);
switch ($from) {
 case 'processrequest': //requests
 case 'adminbooking': //bookings
 case 'adminbooker': //bookers
 case 'processitem': //items
	$hidden['active_tab'] = $params['active_tab'];
	break;
 case 'bookerbookings':
	$hidden['booker_id'] = $params['booker_id'];
 case 'itembookings':
	$hidden['item_id'] = $params['item_id'];
	$hidden['task'] = $params['task'];
	break;
 default:
$this->Crash();
}

switch ($itype) {
 case 1:
	$k = 'importitm';
	$k2 = 'title_importitems';
	$k3 = 'help_importitem';
	break;
 case 2:
	$k = 'importbkr';
	$k2 = 'title_importbookers';
	$k3 = 'help_importbooker';
	break;
 case 3:
	$k = 'importbkg';
 	$k2 = 'title_importbooks';
 	$k3 = 'help_importbooking';
	break;
 case 4:
	$k = 'importfee';
 	$k2 = 'title_importfees';
 	$k3 = 'help_importfee';
	break;
 case 5:
	$k = 'importhist';
 	$k2 = 'title_importhists';
 	$k3 = 'help_importhistory';
	break;
}
$hidden[$k] = 1;

$tplvars['startform'] = $this->CreateFormStart($id,'import',$returnid,'POST','multipart/form-data','','',
	$hidden);
$tplvars['endform'] = $this->CreateFormEnd();
$tplvars['title'] = $this->Lang($k2);
$tplvars['chooser'] = $this->CreateInputFile($id,'csvfile','text/csv',25);
$tplvars['apply'] = $this->CreateInputSubmit($id,'import',$this->Lang('upload'));
$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
$tplvars['help'] = $this->Lang($k3);

echo Booker\Utils::ProcessTemplate($this,'chooser.tpl',$tplvars);
