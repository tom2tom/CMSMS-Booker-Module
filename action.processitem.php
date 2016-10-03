<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: processitem - perform operation on item(s) or group(s)
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

$p = FALSE; //TODO relevant permission
$funcs = new Booker\Itemops();

//NB some links are diverted in DoAction to action.administer
if (isset($params['task'])) { //clicked link
	$params['active_tab'] = ($params['$item_id'] >= Booker::MINGRPID) ? 'groups':'items';
	switch ($params['task']) {
	 case 'add':
	 case 'edit':
	 case 'copy':
	 case 'see':
	 	$params['resume'] = json_encode(array('defaultadmin'));
		$this->Redirect($id,'openitem','',$params);
		break;
	 case 'delete':
		$funcs->DeleteItem($this,$params['$item_id']);
		break;
	 case 'toggle': //handled in module
		$this->_ActivateItem($id,$params,$returnid);
		break;
	 case 'export':
$this->Crash();
		break;
	}
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
}

if (isset($params['selitm']))
	$sel = $params['selitm'];
else if (isset($params['selgrp']))
	$sel = $params['selgrp'];
else
	$this->Redirect($id,'defaultadmin','',array(
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('nosel',FALSE)));

if (isset($params['delete'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('delete'))) exit;
	$funcs->DeleteItem($this,$sel);
} elseif (isset($params['activate'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('add')
	  || $this->_CheckAccess('delete'))) exit;
	$funcs->ToggleItemActive($this,$sel);
} elseif (isset($params['export'])) {
	$funcs = new Booker\Export();
	list($res,$key) = $funcs->ExportItems($this,$sel);
	if ($res)
		exit;
	$this->Redirect($id,'defaultadmin','',array(
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage($key,FALSE)));
} else if (isset($params['exportbkg'])) {
	$funcs = new Booker\Export();
	list($res,$key) = $funcs->ExportBookings($this,$sel,'*','*');
	if ($res)
		exit;
	$this->Redirect($id,'defaultadmin','',array(
	 'active_tab'=>$params['active_tab'],
	 'message'=>$this->_PrettyMessage($key,FALSE)));
}
/*elseif (isset($params['setfees'])) {
	diverted upstream
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('modify'))) exit;
}
*/

$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
