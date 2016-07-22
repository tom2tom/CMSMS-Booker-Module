<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openbooker
# View or edit data for a booker
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!$this->_CheckAccess()) exit;

$bid = (int)$params['booker_id'];
$is_new = ($bid == -1);

if (isset($params['resume']))
	$resume = $params['resume'];
else
	$resume = 'defaultadmin';
$viewmode = ($resume == 'inspect'); //TODO

if (isset($params['cancel']))
	$this->Redirect($id,$resume,'',array('booker_id'=>$bid)); //TODO admin tab

$funcs = new Booker\Utils();
$pre = cms_db_prefix();

if (isset($params['submit']) || isset($params['apply'])) {
	if (!$this->_CheckAccess('admin')) exit;
	//validation
	$msg = array();
	$t = trim($params['user']);
	if ($t)
		$params['user'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('user'));
	$t = trim($params['contact']);
	if ($t)
		$params['contact'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('contact'));

	if ($msg) { //error
		$t = implode(' ',$msg);
		if (empty($params['message']))
			$params['message'] = $t;
		else
			$params['message'] .= '<br />'.$t;
	} else {
	//UPSERT table
		if (isset($params['submit']))
			$this->Redirect($id,$resume,'',array('bid'=>$bid));
	}
}

$tplvars = array();
$tplvars['startform'] =
	$this->CreateFormStart($id,'openbooker',$returnid,'POST','','','',array(
		 'bid'=>$bid,'resume'=>$resume));
$tplvars['endform'] = $this->CreateFormEnd();

$this->_BuildNav($id,$params,$returnid,$tplvars);
if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$tplvars['mod'] = !$viewmode;
if (!$viewmode)
	$tplvars['compulsory'] = $this->Lang('help_compulsory');
$tplvars['title'] = $this->Lang('TODO');

//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$baseurl = $this->GetModuleURLPath();


$sql = 'SELECT * FROM '.$pre.'module_bkr_bookers WHERE booker_id=?';
$bdata = $db->GetRow($sql,array($bid));
$vars = array();

// active I(1) DEFAULT 1
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
$oneset->inp = '';
$vars[] = $oneset;
// name C(64),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
$oneset->mst = 1;
$oneset->inp = '';
$vars[] = $oneset;
// publicid C(32), aka account
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
$oneset->inp = '';
$oneset->hlp = $this->Lang(''); //optional
$vars[] = $oneset;
// passwd C(64),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
$oneset->inp = '';
$vars[] = $oneset;
// contact C(128),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
$oneset->mst = 1;
$oneset->inp = '';
$oneset->hlp = ''; //optional
$vars[] = $oneset;
// type I(1) DEFAULT 0,
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
$oneset->mst = '';
$oneset->inp = '';
$oneset->hlp = '';
$vars[] = $oneset;
// postpay I(1) DEFAULT 0,
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
$oneset->inp = '';
$oneset->hlp = '';
$vars[] = $oneset;
// userclass I(1) DEFAULT 0,
$oneset = new stdClass();
$oneset->ttl = $this->Lang('');
//$oneset->mst = ; //optional
$oneset->inp = '';
$oneset->hlp = $this->Lang(''); //optional
$vars[] = $oneset;

$tplvars['settings'] = $vars;

//buttons
if ($viewmode) {
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
} else { //add/edit mode
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['apply'] = $this->CreateInputSubmit($id,'apply',$this->Lang('apply'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
}

if ($jsloads) {
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,'openbooker.tpl',$tplvars);
