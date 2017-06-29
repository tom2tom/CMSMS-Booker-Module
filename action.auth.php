<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openrequest - view or edit a booking-request
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/* first-pass $params = array
'returnid' => int 159
'bkr_returnid' => string '159'
'bkr_subgrpcount' => string '1'
'bkr_showfrom' => string '1486512000'
'bkr_itempick' => string '10001'
'bkr_range' => string '1'
'bkr_item_id' => string '10001'
'bkr_cartkey' => string 'bkr_Cart5896c3ba0e1e2'
'bkr_firstpick' => string '10001'
'bkr_resume' => string '[&quot;default&quot;]'
'bkr_view' => string 'table'
'task' => string 'register'
'action' => string 'auth'

later from authpanel some of the following all prefixed like [A-Za-z][A-Za-z0-9]\d{3}_
'success' + 'user_id'
'repeat' + 'token'
'message'
'focus'
'html'
'cancel'
*/

if (!function_exists('SaveBookerParms')) {
 function SaveBookerParms(&$cache, $key, $params)
 {
	if (!$key) {
		$key = Booker\Cache::GetKey(\Booker::PARMKEY);
	}
	$cache->set($key, $params, 43200);
	return $key;
}

 function RetrieveBookerParms(&$cache, $key)
 {
	return $cache->get($key);
 }
}

//parameter keys filtered out before redirect etc
$localparams = [
	'authdata',
	'bulletin',
	'cancel',
	'change',
	'focus',
	'html',
//	'message',
//	'recover',
//	'repeat',
//	'success',
//	'task',
//	'token',
//	'user_id' //etc as per authpanel feedback, above
];

$utils = new Booker\Utils();

if (isset($params['bkr_resume'])) { //first-time here
	$cache = Booker\Cache::GetCache($this);
	$utils->UnFilterParameters($params);
	$_SESSION['parmkey'] = SaveBookerParms($cache, FALSE, $params);
	$utils->DecodeParameters($params);
} elseif (isset($params['authdata'])) {
	$cache = Booker\Cache::GetCache($this);
	$saved = RetrieveBookerParms($cache, $_SESSION['parmkey']);
	$utils->DecodeParameters($saved);
	$finish = TRUE;

	$data = json_decode(base64_decode($params['authdata']));
	if ($data) { //stdClass
		if (isset($data->success)) {
			//TODO deal with stuff e.g. $data->message
			$ufuncs = new Booker\Userops($this);
			switch ($data->task) {
			 case 'register':
				$name = $data->name ? $data->name : FALSE;
				$phone = ($data->contact && preg_match(Booker::PATNPHONE,$data->contact)) ? $data->contact : FALSE;
				$address = ($data->contact && !$phone) ? $data->contact : FALSE;
				$ufuncs->AddUser($this, $name, $address, $phone, TRUE, $data->login, FALSE, TRUE);
				break;
			 case 'change':
				$bookerid = $ufuncs->GetKnown($this, $data->login, FALSE);
				if ($bookerid) {
					$name = $data->name ? $data->name : FALSE;
					$phone = ($data->contact && preg_match(Booker::PATNPHONE,$data->contact)) ? $data->contact : FALSE;
					$address = ($data->contact && !$phone) ? $data->contact : FALSE;
					$ufuncs->ChangeUser($this, $bookerid, $name, $address, $phone, FALSE, $data->login, $data->loginnew, FALSE, TRUE);
				} else {
					$saved['message'] = $this->Lang('err_system');
				}
				break;
			 case 'delete':
				$bookerid = $ufuncs->GetKnown($this, $data->login, FALSE);
				if ($bookerid) {
					$ufuncs->DeleteUser($this, $bookerid, TRUE);
				} else {
					$saved['message'] = $this->Lang('err_system');
				}
				break;
			 case 'login':
			 case 'recover':
			 case 'reset':
				break;
			 default:
				break;
			}
			unset($_SESSION['parmkey']); //TODO iff ok
		} elseif (isset($data->cancel)) {
			//TODO deal with stuff e.g. $data->message
			unset($saved['task']);
			unset($_SESSION['parmkey']);
		} elseif (isset($data->repeat)) {
			//re-run notice from authpanel
			$saved += (array)$data;
			$saved['task'] = $data->task; //force this
			unset($saved['repeat']);
			$newparms = $utils->FilterParameters($saved, $localparams);
			//self-redirect to generate a foramtted page
			$this->Redirect($id, $saved['action'], $saved['returnid'], $newparms);
			exit;
		}
	} else { //data error
		$saved['message'] = $this->Lang('err_system');
	}
	if ($finish) {
		$resume = array_pop($saved['resume']);
		$returnid = $saved['returnid'];
		$saved = $utils->FilterParameters($saved, $localparams);
		$this->Redirect($id, $resume, $returnid, $saved);
	}
}

$item_id = (int)$params['item_id'];

$baseurl = $this->GetModuleURLPath();
$baseurl2 = str_replace($this->GetName(), 'Auther', $baseurl);

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl2}/css/authpanel.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
EOS;
$customcss = $utils->GetStylesURL($this, $item_id);
if ($customcss) {
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
EOS;
}
//heredoc-var newlines are a problem for in-js quoted strings! so ...
$stylers = preg_replace('/[\\n\\r]+/', '', $stylers);

$js = <<<EOS
var linkadd = '$stylers',
 \$head = $('head'),
 \$linklast = \$head.find("link[rel='stylesheet']:last");
if (\$linklast.length) {
 \$linklast.after(linkadd);
} else {
 \$head.append(linkadd);
}
EOS;
echo $utils->MergeJS(FALSE,[$js],FALSE);

$context = $this->GetPreference('authcontext',0);
$task = (empty($params['task'])) ? 'login' : $params['task'];
$token = (empty($params['token'])) ? FALSE : $params['token'];

$ob = cms_utils::get_module('Auther'); //get the autoloader into play
if ($ob) {
	unset($ob);
} else {
	$this->Crash();
//	echo something; exit;
}

$funcs = new Auther\Setup();
list($authhtm,$authjs) = $funcs->GetPanel($context, $task, ['Booker','auth',$id], TRUE, $token);

$tplvars = ['authform' => $authhtm];

switch ($task) {
 case 'register':
	$key = 'title_register';
	break;
 case 'recover':
	$key = 'title_recover';
	break;
 case 'change':
	$key = 'title_change';
	break;
 case 'delete':
	$key = 'title_delete';
	break;
 case 'login':
	$key = FALSE;
	break;
 case 'reset':
	$key = 'title_reset';
	break;
}
$tplvars['title'] = ($key) ? $this->Lang($key) : NULL;
if (!empty($params['bulletin'])) {
	$t = htmlspecialchars_decode($params['bulletin'], ENT_XHTML);
	if (!preg_match('/\/[a-z]*>/i', $t)) {
		$t = '<p>'.$t.'</p>';
	}
	$tplvars['bulletin'] = $t;
}
if (!empty($params['message'])) {
	$tplvars['message'] = $params['message'];
}

echo Booker\Utils::ProcessTemplate($this, 'auth.tpl', $tplvars);

if ($authjs) {
	echo $authjs;
}
