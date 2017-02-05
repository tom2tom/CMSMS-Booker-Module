<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openrequest - view or edit a booking-request
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/* first-pass $params = array
'returnid' => int 159
'bkr_returnid' => string '159' (length=3)
'bkr_subgrpcount' => string '1' (length=1)
'bkr_showfrom' => string '1486512000'
'bkr_itempick' => string '10001'
'bkr_range' => string '1'
'bkr_item_id' => string '10001'
'bkr_cartkey' => string 'bkr_Cart5896c3ba0e1e2'
'bkr_firstpick' => string '10001' (length=5)
'bkr_resume' => string '[&quot;default&quot;]'
'bkr_view' => string 'table' (length=5)
'task' => string 'register' (length=8)
'action' => string 'auth' (length=4)
*/
//TODO preserve relevant $params across requests

//parameter keys filtered out before redirect etc
$localparams = array(
	'task'
);

$ob = cms_utils::get_module('Auther'); //get the autoloader into play
if ($ob) {
	unset($ob);
} else {
	$this->Crash();
}

$utils = new Booker\Utils();
$utils->UnFilterParameters($params);
$cache = Booker\Cache::GetCache($this);

$utils->DecodeParameters($params);
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

$cid = $this->GetPreference('pref_logincontext', NULL);
$cid = 1; //DEBUG
$task = (empty($params['task'])) ? 'login' : $params['task'];
$token = (empty($params['token'])) ? FALSE : $params['token'];

$funcs = new Auther\Setup();
list($authhtm,$authjs) = $funcs->GetPanel($cid, $task, ['Booker','auth'], TRUE, $token);
$tplvars = array(
	'title' => $this->Lang('title_register'),
	'authform' => $authhtm
);
if (isset($params['message'])) {
	$tplvars['message'] = $params['message'];
}

echo Booker\Utils::ProcessTemplate($this, 'register.tpl', $tplvars);

if ($authjs) {
	echo $authjs;
}
