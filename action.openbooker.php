<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openbooker - view or edit data for a booker
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!function_exists('BookerRedirParms')) {
 function BookerRedirParms($resume, &$params, $msg = FALSE)
 {
	$pnew = array_intersect_key($params,[
	'item_id'=>1,
	'booker_id'=>1,
	'task'=>1,
	'active_tab'=>1]);
	if (!empty($params['resume']))
		$pnew['resume'] = json_encode($params['resume']);
	switch ($resume) {
	 case 'defaultadmin':
 		$pnew['active_tab'] = 'people';
		break;
	 default:
//	$this->Crash();
	}
	if ($msg)
		$pnew['message'] = $msg;
	return $pnew;
 }
}

$pmod = ($params['task'] == 'edit' || $params['task'] == 'add');
if (!$this->_CheckAccess('admin')) {
	if ($pmod && !$this->_CheckAccess('book')) exit;
	if (!$pmod && !$this->_CheckAccess('view')) exit;
}

if (isset($params['resume'])) {
	$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
	while (end($params['resume']) == $params['action']) {
		array_pop($params['resume']);
	}
} else { //got here via link
	$params['resume'] = ['defaultadmin']; //redirects can [eventually] get back to there
}

$bookerid = (int)$params['booker_id'];
if (isset($params['cancel'])) {
	$resume = array_pop($params['resume']);
	$newparms = BookerRedirParms($resume,$params);
	$this->Redirect($id,$resume,'',$newparms);
}

$is_new = ($bookerid == -1);

$afuncs = new Auther\Auth(NULL,$this->GetPreference('authcontext',0));
$cfuncs = new Booker\Crypter($this);
$utils = new Booker\Utils();
//$utils->DecodeParameters($params,array('name','account','address','phone')); //TODO Auther password

if (isset($params['submit']) || isset($params['apply'])) {
	if (!$pmod) exit;
/* $params[] available:
	'active'
	'address'
	'displayclass'
	'name'
	'password'
	'account'
	'type_postpay'
	'type_record'
	'type_type'
*/
	$ufuncs = new Booker\Userops($this);
	//validation
	$msg = [];
	$oldlogin = $params['oldaccount'];
	if ($oldlogin || $params['account']) {
		$t = $ufuncs->SanitizeName($params['name']); //cleanup registered name
	} else {
		$t = trim($params['name']);
	}
	if ($t) {
		$params['name'] = $t;
	} elseif (!$afuncs->GetConfig('name_required')) {
		$params['name'] = NULL;
	} else {
		$msg[] = $this->Lang('missing_type',$this->Lang('user'));
	}
	$t = trim($params['address']);
	if ($t) {
		$params['address'] = $t;
	} else {
		$params['address'] = NULL;
	}
	$t = str_replace(' ','',$params['phone']);
	if ($t) {
		if (preg_match(\Booker::PATNPHONE,$t)) {
			$params['phone'] = $t;
		} else {
			$params['phone'] = NULL;
			$msg[] = $this->Lang('invalid_type',$this->Lang('phone'));
		}
	} else {
		$params['phone'] = NULL;
	}

	$pw = trim($params['password']);
	$t = trim($params['account']);
	if ($t) {
		$params['account'] = $t;
		if ($is_new && !$pw) {
			$msg[] = $this->Lang('missing_type',$this->Lang('password'));
		} else {
			$checks = ['account'=>$t];
			if ($pw) {
				$checks['password'] = $pw;
			}
			if ($params['name']) {
				$checks['name'] = $params['name'];
			}
			$address = ($params['address']) ? $params['address'] : $params['phone'];
			if ($address) {
				$checks['address'] = $address;
			}
			$except = ($is_new) ? FALSE : $t;
			$res = $afuncs->ValidateAll($checks,$except,TRUE); //explicit
			if (!$res[0]) {
				$msg[] = $res[1];
			}
		}
	} else {
		$params['account'] = NULL;
		$pw = FALSE;
		if ($oldlogin) {  //newly-deregistered
			$data = $afuncs->GetUserProperties($oldlogin,'address',FALSE);
			if ($data) {
				$t = $data[0]['address'];
				if ($t) {
					if (!$params['phone'] && preg_match(Booker::PATNPHONE,$t)) {
						$params['phone'] = $t;
					} elseif (!$params['address']) {
						$params['address'] = $t; //anything will do
					}
				}
			}
		}
	}

	if ($afuncs->GetConfig('address_required')) {
		if (!($params['address'] || $params['phone'])) {
			$msg[] = $this->Lang('err_nocontact2');
		}
	}

	if (!$msg) {
		//$params[] are updated for storage in BookersTable, related variables are for Auther users
		$name = ($params['name']) ? $params['name']:FALSE;
		$address = ($params['address']) ? $params['address']:FALSE;
		$phone = ($params['phone']) ? $params['phone']:FALSE;
		$active = empty($params['active']) ? 0:1;
		$login = ($params['account']) ? trim($params['account']):FALSE;

		if ($is_new) {
			$bookerid = $ufuncs->AddUser($this,$name,$address,$phone,$active,$login,$pw);
		} else {
			$ufuncs->ChangeUser($this,$bookerid,$name,$address,$phone,$active,$oldlogin,$login,$pw);
		}
		//update local table-values not handled in $ufuncs::*User()
		$type = (int)$params['type_type'];
		$t = !empty($params['active']);
		$sql = 'UPDATE '.$this->BookerTable.' SET type=?,displayclass=?,active=? WHERE booker_id=?';
		//TODO $utils->SafeExec()
		$db->Execute($sql,[$type,(int)$params['displayclass'],$t,$bookerid]);
		$rights = [
			'record' => !empty($params['type_record']),
			'postpay' => !empty($params['type_postpay'])
		];
		$ufuncs->SetRights($this,$bookerid,$rights,$type);

		if (isset($params['submit'])) {
			$resume = array_pop($params['resume']);
			$newparms = BookerRedirParms($resume,$params);
			$this->Redirect($id,$resume,'',$newparms);
		}
	} else { //error
		$t = implode(' ',$msg);
		if (empty($params['message']))
			$params['message'] = $t;
		else
			$params['message'] .= '<br />'.$t;
	}
	$params['booker_id'] = $bookerid;
	$is_new = FALSE;
	$params['task'] = 'edit'; //in case we we adding
}

$tplvars = [
	'mod' => $pmod
];

$params['active_tab'] = 'people';
$tplvars['pagenav'] = $utils->BuildNav($this,$id,$returnid,$params['action'],$params);
$resume = json_encode($params['resume']);
$hidden = ['booker_id'=>$bookerid,'resume'=>$resume,'task'=>$params['task']];

//DEFER $tplvars['startform'] = $this->CreateFormStart($id,'openbooker',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();
if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

$tplvars['title'] = $this->Lang('title_booker_page');
if ($pmod) { //add/edit mode
	$tplvars['compulsory'] = $this->Lang('compulsory_items');
}
$missing = '&lt;'.$this->Lang('missing').'&gt;';

//script accumulators
$jsincs = [];
$jsfuncs = [];
$jsloads = [];
$baseurl = $this->GetModuleURLPath();

$ufuncs = new Booker\Userops($this);

if ($is_new) {
	$bdata = [
	 'name'=>'',
	 'account'=>'',
	 'address'=>'',
	 'phone'=>'',
	 'type'=>0,
	 'displayclass'=>0,
	 'active'=>1
	];
} else {
	$sql = <<<EOS
SELECT B.auth_id,COALESCE(B.name,A.name,'') AS name,COALESCE(B.address,A.address,'') AS address,B.phone,B.type,B.displayclass,B.active,A.account
FROM $this->BookerTable B
LEFT JOIN $this->AuthTable A ON B.auth_id=A.id
WHERE B.booker_id=?
EOS;
	$bdata = $utils->PlainGet($this,$sql,[$bookerid],'row');
	if (!$bdata) {
		$nav = $tplvars['pagenav'];
		$tplvars = [
		 'title_error'=>$this->Lang('error'),
		 'message'=>$this->Lang('err_data'),
		 'pagenav'=>$nav
		];
		echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
		return;
	}
}

//$hidden['oldname'] = $bdata['name'];
$hidden['oldaccount'] = $bdata['account'];
$tplvars['startform'] = $this->CreateFormStart($id,'openbooker',$returnid,'POST','','','',$hidden);

$yes = $this->Lang('yes');
$no = $this->Lang('no');

$vars = [];
// active
$oneset = new stdClass();
$state = (int)$bdata['active'];
if ($state == -1) {
	$val = -1;
	$oneset->ttl = $this->Lang('title_deletemarked');
} else {
	$val = 1;
	$oneset->ttl = $this->Lang('title_active');
}
$oneset->inp = ($pmod) ?
	$this->CreateInputCheckbox($id,'active',$val,$state):
	(($state)?$yes:$no);
$vars[] = $oneset;
// name C(64)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_name');
$oneset->mst = $pmod && $afuncs->GetConfig('name_required'); //show in edit-mode
$oneset->inp = ($pmod) ?
	$this->CreateInputText($id,'name',$bdata['name'],40,64):
	(($bdata['name'])?$bdata['name']:$missing);
$vars[] = $oneset;
// login/account
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_account');
$oneset->inp = ($pmod) ?
	$this->CreateInputText($id,'account',$bdata['account'],20,32):
	(($bdata['account'])?$bdata['account']:$missing);
$oneset->hlp = $this->Lang('help_account');
$vars[] = $oneset;
// password
if ($pmod) {
	$oneset = new stdClass();
	$oneset->ttl = $this->Lang('title_passnew');
//TODO $t = ($is_new) ? $afuncs->GetConfig('default_password') : '';
	$t = ($is_new) ? /*Booker\Userops::DEFAULTPASS*/'' : ''; //TODO
	$oneset->inp = $this->CreateInputText($id,'password',$t,20,32);
	$oneset->hlp = $this->Lang('help_passnew').'. '.$this->Lang('help_passwd');
	$vars[] = $oneset;

	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery-inputCloak.min.js"></script>
EOS;
	$jsloads[] = <<<EOS
 $('#{$id}password').inputCloak({
  type:'see4',
  symbol:'\u25CF'
 });
EOS;
}
// address C(96)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_address');
$oneset->mst = $pmod && $afuncs->GetConfig('address_required'); //show in edit-mode
$oneset->inp = ($pmod) ?
	$this->CreateInputText($id,'address',$bdata['address'],40,96):
	(($bdata['address'])?$bdata['address']:$missing);
$oneset->hlp = $this->Lang('help_address');
$vars[] = $oneset;
// phone C(24)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_phone');
$oneset->inp = ($pmod) ?
	$this->CreateInputText($id,'phone',$bdata['phone'],20,24):
	(($bdata['phone'])?$bdata['phone']:$this->Lang('none'));
$oneset->hlp = $this->Lang('help_phone');
$vars[] = $oneset;
// basetype func(type)
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_bookertype');
$state = $ufuncs->GetBaseType($this,$bookerid,$bdata['type']);
if ($pmod) {
	$choices = array_fill(0,10,0);
	foreach ($choices as $k=>&$t) {
		$t = $k;
	}
	unset($t);
	$oneset->inp = $this->CreateInputDropdown($id,'type_type',$choices,-1,$state);
} else {
	$oneset->inp = $state;
}
$oneset->hlp = $this->Lang('help_bookertype');
$vars[] = $oneset;
// record func(type),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_record');
$state = $ufuncs->HasRight($this,$bookerid,'record',$bdata['type']);
if ($pmod) {
	$oneset->inp = $this->CreateInputCheckbox($id,'type_record',1,$state);
} else {
	$oneset->inp = ($state) ? $yes:$no ;
}
$oneset->hlp = $this->Lang('help_record');
$vars[] = $oneset;
// postpay func(type),
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_postpay');
$state = $ufuncs->HasRight($this,$bookerid,'postpay',$bdata['type']);
if ($pmod) {
	$oneset->inp = $this->CreateInputCheckbox($id,'type_postpay',1,$state);
} else {
	$oneset->inp = ($state) ? $yes:$no ;
}
$oneset->hlp = $this->Lang('help_postpay');
$vars[] = $oneset;
// displayclass I(1) DEFAULT 0,
$oneset = new stdClass();
$oneset->ttl = $this->Lang('title_displayclass');
if ($pmod) {
	$choices = array_fill(1,Booker::USERSTYLES,0);
	foreach ($choices as $k=>&$t) {
		$t = $k;
	}
	unset($t);
	$oneset->inp = $this->CreateInputDropdown($id,'displayclass',$choices,-1,(int)$bdata['displayclass']);
} else {
	$oneset->inp = $bdata['displayclass'];
}
$oneset->hlp = $this->Lang('help_displayclass');
$vars[] = $oneset;

$tplvars['settings'] = $vars;

//buttons
if ($pmod) {
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['apply'] = $this->CreateInputSubmit($id,'apply',$this->Lang('apply'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
} else {
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
}

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,'openbooker.tpl',$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
