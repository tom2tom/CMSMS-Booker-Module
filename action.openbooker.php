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

if (isset($params['cancel']))
{
	//TODO check $resume relevance
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>'people')); //TODO admin tab
}

//$viewmode = ($resume == 'inspect'); //TODO
$viewmode = isset($params['inspect']);

//$utils = new Booker\Utils();

if (isset($params['submit']) || isset($params['apply'])) {
	if (!$this->_CheckAccess('admin')) exit;
/* $params[] available:
	'active'
	'contact'
	'displayclass'
	'name'
	'passhash'
	'publicid'
	'type_postpay'
	'type_record'
	'type_type'
*/
	//validation
	$msg = array();
	$t = trim($params['name']);
	if ($t)
		$params['name'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('user'));
	$t = trim($params['contact']);
	if ($t)
		$params['contact'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('contact'));

	$pw = trim($params['passhash']);

	$t = trim($params['publicid']);
	if ($t) {
		$params['publicid'] = $t; //no duplication check - allow multi-users per account
		if ($is_new && !$pw) {
			$msg[] = $this->Lang('missing_type',$this->Lang('password'));
		}
	} else {
		$params['publicid'] = NULL;
		$pw = FALSE;
	}

	if (!$msg) {
		$funcs = new Booker\Userops($this,$db);
		$type = (int)$params['type_type'];
		$t = !empty($params['active']);
		if ($is_new) {
			$bid = $funcs->AddUser($params['name'],$params['publicid'],$pw);
			$sql = 'UPDATE '.$this->BookerTable.' SET contact=?,type=?,displayclass=?,active=? WHERE booker_id=?';
			$db->Execute($sql,array($params['contact'],$type,$params['displayclass'],$t,$bid));
		} else {
			$sql = 'UPDATE '.$this->BookerTable.' SET name=?,publicid=?,contact=?,type=?,displayclass=?,active=? WHERE booker_id=?';
			$db->Execute($sql,array($params['name'],$params['publicid'],$params['contact'],$type,$params['displayclass'],$t,$bid));
			if ($pw)
				$funcs->SetPassword($bid,'FORCE',$pw);
		}
		$rights = array(
			'record' => !empty($params['type_record']),
			'postpay' => !empty($params['type_postpay'])
		);
		$funcs->SetRights($bid,$rights,$type);

		if (isset($params['submit']))
			$this->Redirect($id,$resume,'',array('bid'=>$bid,'active_tab'=>'people'));
	} else { //error
		$t = implode(' ',$msg);
		if (empty($params['message']))
			$params['message'] = $t;
		else
			$params['message'] .= '<br />'.$t;
	}
}

$tplvars = array();
$tplvars['startform'] =
	$this->CreateFormStart($id,'openbooker',$returnid,'POST','','','',array(
		 'booker_id'=>$bid,'resume'=>$resume));
$tplvars['endform'] = $this->CreateFormEnd();

$this->_BuildNav($id,$params,$returnid,$tplvars);
if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$tplvars['mod'] = !$viewmode;
$tplvars['title'] = $this->Lang('title_booker_page');
if (!$viewmode)
	$tplvars['compulsory'] = $this->Lang('help_compulsory');
/*
//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$baseurl = $this->GetModuleURLPath();
*/
$funcs = new Booker\Userops($this,$db);

if ($is_new) {
	$bdata = array(
	 'publicid'=>'',
	 'name'=>'',
	 'contact'=>'',
	 'type'=>0,
	 'displayclass'=>0,
	 'active'=>1
	);
} else {
	$sql = 'SELECT publicid,name,contact,type,displayclass,active FROM '.$this->BookerTable.' WHERE booker_id=?';
	$bdata = $db->GetRow($sql,array($bid));
	if (!$bdata) {
		$nav = $tplvars['back_nav'];
		$tplvars = array(
		 'title_error'=>$this->Lang('error'),
		 'admin_nav'=>$nav,
		 'message'=>$this->Lang('err_data')
		);
		echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
		exit;
	}
}

$yes = $this->Lang('yes');
$no = $this->Lang('no');
//$none = $this->Lang('none');

$vars = array();
// active
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_active');
$state = (int)$bdata['active'];
$oneset->inp = ($viewmode) ?
	(($state)?$yes:$no):
	$this->CreateInputCheckbox($id,'active',1,$state);
$vars[] = $oneset;
// name C(64)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_name');
$oneset->mst = 1;
$oneset->inp = ($viewmode) ?
	(($bdata['name'])?$bdata['name']:$missing):
	$this->CreateInputText($id,'name',$bdata['name'],40,64);
$vars[] = $oneset;
// publicid C(32), aka account
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_publicid');
$oneset->inp = ($viewmode) ?
	(($bdata['publicid'])?$bdata['publicid']:$missing):
	$this->CreateInputText($id,'publicid',$bdata['publicid'],20,32);
$oneset->hlp = $this->Lang('help_publicid');
$vars[] = $oneset;
// passhash C(48),
if (!$viewmode) {
	$oneset = new stdClass();
	$oneset->ttl = $this->Lang('title_passnew');
	$t = ($is_new) ? 'changethis':'';
	$oneset->inp = $this->CreateInputText($id,'passhash',$t,18,18);
	$oneset->hlp = $this->Lang('help_passnew').'. '.$this->Lang('help_passwd');
	$vars[] = $oneset;
}
// contact C(128),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_contact');
$oneset->mst = 1;
$oneset->inp = ($viewmode) ?
	(($bdata['contact'])?$bdata['contact']:$missing):
	$this->CreateInputText($id,'contact',$bdata['contact'],40,128);
$oneset->hlp = $this->Lang('help_contact');
$vars[] = $oneset;
// basetype func(type)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_bookertype');
$state = $funcs->GetBaseType($bid,$bdata['type']);
if ($viewmode) {
	$oneset->inp = $state;
} else {
	$choices = array_fill(0,10,0);
	foreach ($choices as $k=>&$t) {
		$t = $k;
	}
	unset($t);
	$oneset->inp = $this->CreateInputDropdown($id,'type_type',$choices,-1,$state);
}
$oneset->hlp = $this->Lang('help_bookertype');
$vars[] = $oneset;
// record func(type),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_record');
$state = $funcs->HasRight($bid,'record',$bdata['type']);
if ($viewmode) {
	$oneset->inp = ($state) ? $yes:$no ;
} else {
	$oneset->inp = $this->CreateInputCheckbox($id,'type_record',1,$state);
}
$oneset->hlp = $this->Lang('help_record');
$vars[] = $oneset;
// postpay func(type),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_postpay');
$state = $funcs->HasRight($bid,'postpay',$bdata['type']);
if ($viewmode) {
	$oneset->inp = ($state) ? $yes:$no ;
} else {
$oneset->inp = $this->CreateInputCheckbox($id,'type_postpay',1,$state);
	}
$oneset->hlp = $this->Lang('help_postpay');
$vars[] = $oneset;
// displayclass I(1) DEFAULT 0,
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_displayclass');
if ($viewmode) {
	$oneset->inp = $state;
} else {
	$choices = array_fill(1,Booker::USERSTYLES,0);
	foreach ($choices as $k=>&$t) {
		$t = $k;
	}
	unset($t);
	$oneset->inp = $this->CreateInputDropdown($id,'displayclass',$choices,-1,(int)$bdata['displayclass']);
}
$oneset->hlp = $this->Lang('help_displayclass');
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
/*
if ($jsloads) {
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;
*/
echo Booker\Utils::ProcessTemplate($this,'openbooker.tpl',$tplvars);
