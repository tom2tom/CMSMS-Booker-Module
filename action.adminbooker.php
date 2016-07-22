<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: adminbooker
# Admin-side booker operations
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!$this->_CheckAccess()) exit;

if (isset($params['cancel'])) {
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
} elseif (isset($params['task'])) { //clicked link
	switch ($params['task']) {
	 case 'see':
		break;
	 case 'add':
	 case 'edit':
		break;
	 case 'delete':
		break;
	}
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
} elseif (isset($params['delete'])) {
	if (!isset($params['selbkr']))
		$this->Redirect($id,'defaultadmin','',array(
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('nosel',FALSE)));

	$this->Redirect($id,'defaultadmin','',array(
	 'active_tab'=>$params['active_tab'],
	 'message'=>$this->Lang('notyet')));

	foreach ($selbkr as $booker_id) {
//		$this->Crash();
	}
} else if (isset($params['export'])) {
	if (!isset($params['selbkr']))
		$this->Redirect($id,'defaultadmin','',array(
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('nosel',FALSE)));

	$this->Redirect($id,'defaultadmin','',array(
	 'active_tab'=>$params['active_tab'],
	 'message'=>$this->Lang('notyet')));
} elseif (isset($params['import'])) {
	//TODO
}

$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
