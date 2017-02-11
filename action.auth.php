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

function SaveParms(&$cache, $key, $params)
{
	if (!$key) {
		$key = Booker\Cache::GetKey(\Booker::PARMKEY);
	}
	$cache->set($key, $params, 43200);
	return $key;
}

function RetrieveParms(&$cache, $key)
{
	return $cache->get($key);
}

//parameter keys filtered out before redirect etc
$localparams = array(
	'cancel',
	'change',
	'focus',
	'html',
	'message',
	'recover',
	'repeat',
	'success',
	'task',
	'token',
	'user_id' //etc as per authpanel feedback, above
);

$utils = new Booker\Utils();

if (isset($params['bkr_resume'])) { //first-time here
	$cache = Booker\Cache::GetCache($this);
	$utils->UnFilterParameters($params);
	$_SESSION['parmkey'] = SaveParms($cache, FALSE, $params);
	$utils->DecodeParameters($params); //ditto
} elseif (isset($params['success']) || isset($params['cancel'])) {
	if (isset($params['success'])) {
	//TODO deal with stuff e.g. maybe a feedback message?
	}
	$cache = Booker\Cache::GetCache($this);
	$saved = RetrieveParms($cache, $_SESSION['parmkey']);
	unset($_SESSION['parmkey']);
	$utils->DecodeParameters($saved);
	$resume = array_pop($saved['resume']);
	$returnid = $saved['returnid'];
	$saved = $utils->FilterParameters($saved, $localparams);
	$this->Redirect($id, $resume, $returnid, $saved);
} elseif (0) {
	//back from authpanel with instruction to re-create
	//TODO handle stuff e.g.
	//$task =
	//$token =
	//$message = ;
	//$html = ;
}

$item_id = (int)$params['item_id'];

$baseurl = $this->GetModuleURLPath();
$stylers = <<<EOS
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
echo $utils->MergeJS(FALSE,array($js),FALSE);

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

$tplvars = array('authform' => $authhtm);

if ($task == 'register') {
	$tplvars['title'] = $this->Lang('title_register');
} else {
	$tplvars['title'] = $this->Lang('title_recover');
}

$t = FALSE; //$utils->GetItemProperty($this, $item_id, 'bulletin'); //TODO
$tplvars['bulletin'] = ($t) ? $t : NULL;
if (isset($params['message'])) {
	$tplvars['message'] = $params['message'];
}

echo Booker\Utils::ProcessTemplate($this, 'auth.tpl', $tplvars);

if ($authjs) {
	echo $authjs;
}
