<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openbooker - view or edit data for a booker
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!$this->_CheckAccess()) exit;

$bookerid = (int)$params['booker_id'];
$is_new = ($bookerid == -1);

if (isset($params['resume'])) {
	$resume = $params['resume'];
	if (!isset($params['active_tab'])) {
		$params['active_tab'] = '';
	}
} else {
	$resume = 'defaultadmin';
	$params['resume'] = $resume;
	$params['active_tab'] = 'people';
}

if (isset($params['cancel'])) {
	$this->Redirect($id,$resume,'',array('active_tab'=>$params['active_tab']));
}

$utils = new Booker\Utils();
$utils->DecodeParameters($params,array('name','publicid','address','phone'));

if (isset($params['submit']) || isset($params['apply'])) {
	if (!$this->_CheckAccess('admin')) exit;
/* $params[] available:
	'active'
	'address'
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
	$t = trim($params['address']);
	if ($t)
		$params['address'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('contact'));
	$t = trim($params['phone']);
	if ($t) {
		if (!preg_match('/^(\+\d{1,4} *)?[\d ]{5,15}$/',$t)) {
			$msg[] = $this->Lang('invalid_type',$this->Lang('phone'));
		} else {
			$params['phone'] = $t;
		}
	}

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
		$funcs = new Booker\Userops();
		$type = (int)$params['type_type'];
		$t = !empty($params['active']);
		if ($is_new) {
			$bookerid = $funcs->AddUser($this,$params['name'],$params['publicid'],$pw);
			$sql = 'UPDATE '.$this->BookerTable.' SET address=?,phone=?type=?,displayclass=?,active=? WHERE booker_id=?';
			//TODO $utils->SafeExec()
			$db->Execute($sql,array($params['address'],$params['phone'],$type,(int)$params['displayclass'],$t,$bookerid));
		} else {
			$sql = 'UPDATE '.$this->BookerTable.' SET name=?,publicid=?,address=?,phone=?,type=?,displayclass=?,active=? WHERE booker_id=?';
			//TODO $utils->SafeExec()
			$db->Execute($sql,array($params['name'],$params['publicid'],$params['address'],$params['phone'],$type,(int)$params['displayclass'],$t,$bookerid));
			if ($pw)
				$funcs->SetPassword($this,$bookerid,'FORCE',$pw);
		}
		$rights = array(
			'record' => !empty($params['type_record']),
			'postpay' => !empty($params['type_postpay'])
		);
		$funcs->SetRights($this,$bookerid,$rights,$type);

		if (isset($params['submit']))
			$this->Redirect($id,$resume,'',array('booker_id'=>$bookerid,'active_tab'=>'people')); //TODO $param['booker_id'] ???
		else
			$params['task'] = 'edit'; //in case we we adding
	} else { //error
		$t = implode(' ',$msg);
		if (empty($params['message']))
			$params['message'] = $t;
		else
			$params['message'] .= '<br />'.$t;
	}
}

$tplvars = array();
$tplvars['pagenav'] = $this->_BuildNav($id,$returnid,$resume,$params);

$hidden = array('booker_id'=>$bookerid,'resume'=>$resume,'task'=>$params['task']);
if (isset($params['active_tab']))
	$hidden['active_tab'] = $params['active_tab'];

$tplvars['startform'] = $this->CreateFormStart($id,'openbooker',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();
if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$viewmode = !($params['task'] == 'edit' || $params['task'] == 'add');
$tplvars['mod'] = !$viewmode;

$tplvars['title'] = $this->Lang('title_booker_page');
if ($viewmode)
	$missing = '&lt;'.$this->Lang('missing').'&gt;';
else
	$tplvars['compulsory'] = $this->Lang('help_compulsory');

//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$baseurl = $this->GetModuleURLPath();

$funcs = new Booker\Userops();

if ($is_new) {
	$bdata = array(
	 'name'=>'',
	 'publicid'=>'',
	 'address'=>'',
	 'phone'=>'',
	 'type'=>0,
	 'displayclass'=>0,
	 'active'=>1
	);
} else {
	$sql = 'SELECT name,publicid,address,phone,type,displayclass,active FROM '.$this->BookerTable.' WHERE booker_id=?';
	$bdata = $db->GetRow($sql,array($bookerid));
	if (!$bdata) {
		$nav = $tplvars['pagenav'];
		$tplvars = array(
		 'title_error'=>$this->Lang('error'),
		 'message'=>$this->Lang('err_data'),
		 'pagenav'=>$nav
		);
		echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
		return;
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

	$jsincs[] = '<script type="text/javascript" src="'.$baseurl.'/include/jquery-inputCloak.min.js"></script>';
	$jsloads[] = <<<EOS
 $('#{$id}passhash').inputCloak({
  type:'see4',
  symbol:'\u25CF'
 });

EOS;
}
// address C(96)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_address');
$oneset->mst = 1;
$oneset->inp = ($viewmode) ?
	(($bdata['address'])?$bdata['address']:$missing):
	$this->CreateInputText($id,'address',$bdata['address'],40,96);
$oneset->hlp = $this->Lang('help_address');
$vars[] = $oneset;
// phone C(24)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_phone');
$oneset->inp = ($viewmode) ?
	(($bdata['phone'])?$bdata['phone']:$none):
	$this->CreateInputText($id,'phone',$bdata['phone'],20,24);
$oneset->hlp = $this->Lang('help_phone');
$vars[] = $oneset;
// basetype func(type)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_bookertype');
$state = $funcs->GetBaseType($this,$bookerid,$bdata['type']);
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
$state = $funcs->HasRight($this,$bookerid,'record',$bdata['type']);
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
$state = $funcs->HasRight($this,$bookerid,'postpay',$bdata['type']);
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
