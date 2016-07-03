<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: process - delete or activate or export selected resource(s) or group(s)
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if(isset($params['selitems']))
	$sel = $params['selitems'];
else if(isset($params['selgroups']))
	$sel = $params['selgroups'];
else
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab'],'message'=>$this->_PrettyMessage('nosel',FALSE)));

$funcs = new Booker\Itemops();
if(isset($params['delete']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('delete'))) exit;
	$funcs->DeleteItem($this,$sel);
}
elseif(isset($params['activate']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('add')
	  || $this->_CheckAccess('delete'))) exit;
	$funcs->ToggleItemActive($this,$sel);
}
/*elseif (isset($params['setfees']))
{
	diverted upstream
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('modify'))) exit;
}
elseif(isset($params['export']))
{
	only bookings-data export ATM
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('see'))) exit;
	$funcs->ExportItem($this,$sel);
	exit;
}
*/

$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));

?>
