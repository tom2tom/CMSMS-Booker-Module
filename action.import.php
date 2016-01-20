<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: import
# Import item(s) or booking(s) data from .csv file
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if(!function_exists('GetRedirParms'))
{
 function GetRedirParms(&$params,$msg=FALSE)
 {
	$pnew = array();
	if(isset($params['item_id']))
		$pnew['item_id'] = $params['item_id'];
	if(isset($params['active_tab']))
		$pnew['active_tab'] = $params['active_tab'];
	if($msg)
		$pnew['message'] = $msg;
	return $pnew;
 }
}

if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;

if(isset($params['cancel']))
{
	$parms = GetRedirParms($params);
	$this->Redirect($id,$params['resume'],'',$parms);
}

$imitm = !empty($params['importitm']);
$imbkg = !empty($params['importbkg']);
if(!($imitm || $imbkg)) exit;

if(isset($_FILES) && isset($_FILES[$id.'csvfile']))
{
	$funcs = new bkrcsv();
	if($imitm)
	{
		list($res,$prop) = $funcs->ImportItems($this,$id);
	}
	else //$imbkg
	{
		$single = (isset($params['item_id'])) ? (int)$params['item_id']:FALSE;
		list($res,$prop) = $funcs->ImportBookings($this,$id,$single);
	}

	if($res)
	{
		$t = ($imitm) ? $this->Lang('itemv_multi'):$this->Lang('booking_multi');
		$t = $this->Lang('import_result',$prop,$t);
		$msg = $this->_PrettyMessage($t,TRUE,FALSE);
	}
	else
		$msg = $this->_PrettyMessage($prop,FALSE);
	$parms = GetRedirParms($params,$msg);
	$this->Redirect($id,$params['resume'],'',$parms);
}

$tplvars = array();
$this->_BuildNav($id,$params,$returnid,$tplvars);

$hidden = array();
switch($params['action'])
{
 case 'process': //items
 case 'adminbooking': //bookings
	$hidden['resume'] = 'defaultadmin';
	$hidden['active_tab'] = $params['active_tab'];
	break;
 case 'multibooking':
	$hidden['resume'] = 'administer';
	if(isset($params['item_id']))
		$hidden['item_id'] = $params['item_id'];
	break;
 default:
	$this->Crash();
}
if($imbkg)
	$hidden['importbkg'] = $params['importbkg'];
else //$imitm
	$hidden['importitm'] = $params['importitm'];

$tplvars['startform'] = $this->CreateFormStart($id,'import',$returnid,'POST',
	'multipart/form-data','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();
$k = ($imbkg) ? 'title_importbooks':'title_importitems';
$tplvars['title'] = $this->Lang($k);
$tplvars['chooser'] = $this->CreateInputFile($id,'csvfile','text/csv',25);
$tplvars['apply'] = $this->CreateInputSubmit($id,'import',$this->Lang('upload'));
$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
$k = ($imbkg) ? 'help_importbooking':'help_importitem';
$tplvars['help'] = $this->Lang($k);

echo bkrshared::ProcessTemplate($this,'chooser.tpl',$tplvars);
?>
