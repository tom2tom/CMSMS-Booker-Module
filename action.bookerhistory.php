<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: bookerhistory - display & process requests/bookings by a specific booker
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (isset($params['cancel'])) {
}
if (isset($params['X'])) {
}

$funcs = new Booker\Utils();

$hidden = $this->CreateInputHidden($id,'item_id',$item_id); //MORE

$tplvars = array(
	'mod' => $pmod,
	'startform' => $this->CreateFormStart($id,'openfees',$returnid),
	'endform' => $this->CreateFormEnd(),
	'hidden' => $hidden,
	'title' => $this->Lang('TODO')
);
if (isset($params['message']))
	$tplvars['message'] = $params['message'];

$jsloads = array();
$jsfuncs = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();
$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
	cms_utils::get_theme_object();
//TODO icons setup
$iconsee = $theme->DisplayImage('icons/system/view.gif','%s','','','systemicon');
if ($mod || $pper) {
	$edittip= $this->Lang('tip_edittype','%s');
	$iconedit = $theme->DisplayImage('icons/system/edit.gif','%s','','','systemicon');
}
if ($pmod) {
//	$t = $this->Lang('edit');
//	$icon_open = '<img src="'.$baseurl.'/images/calendar-edit.png" alt="'.$t.'" title="'.$t.'" border="0" />';
	$icon_delete = $theme->DisplayImage('icons/system/delete.gif',$this->Lang('delete'),'','','systemicon');
} else {
//	$t = $this->Lang('view');
//	$icon_open = '<img src="'.$baseurl.'/images/calendar.png" alt="'.$t.'" title="'.$t.'" border="0" />';
}
//$icon_export = $theme->DisplayImage('icons/system/export.gif',$this->Lang('export'),'','','systemicon');
//$t = $this->Lang('tip_notifyuser');
//$icon_tell = '<img src="'.$baseurl.'/images/notice.png" alt="'.$t.'" title="'.$t.'" border="0" />';


$bdata = $db->GetArray($slq,$args);
$data = array();
foreach ($bdata as $one) {
	$oneset = new stdClass();

	$data[] = $oneset;
}

$count = count($data);
$tplvars['count'] = $count;
if ($count) {
	//TODO permissions, column-titles, buttons, custom js, pager etc
} else {
	$tplvars['nodata'] = $this->Lang('TODO');
}
//TODO date-range selectors

if ($jsloads) {
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,'bookerhistory.tpl',$tplvars);
