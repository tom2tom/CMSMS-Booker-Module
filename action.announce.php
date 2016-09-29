<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: announce - display message
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

//parameter keys filtered out before redirect etc
$localparams = array(
	'action',
	'cancel',
	'message'
);

$utils = new Booker\Utils();
$utils->UnFilterParameters($params);

if(isset($params['cancel']) || empty($params['message'])) {
	$resume = array_pop($params['resume']);
	$newparms = $utils->FilterParameters($params,$localparams);
	$this->Redirect($id,$resume,$params['returnid'],$newparms);
}

$hidden = $utils->FilterParameters($params,$localparams);
$tplvars = array(
//	'title' => $this->Lang('title_requeststatus'),
	'message'=> html_entity_decode($params['message'],ENT_QUOTES|ENT_HTML401),
	'startform' => $this->CreateFormStart($id,'findbooking',$returnid,'POST','','','',$hidden),
	'endform' => $this->CreateFormEnd(),
	'cancel' => $this->CreateInputSubmit($id,'cancel',$this->Lang('close'))
);

echo Booker\Utils::ProcessTemplate($this,'announce.tpl',$tplvars);
