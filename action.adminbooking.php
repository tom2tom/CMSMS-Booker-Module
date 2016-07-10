<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: adminbooking
# Admin-side bookings operations, other than view/edit
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!$this->_CheckAccess()) exit;

if (isset($params['cancel']))
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));

if (isset($params['find'])) {
	$this->Redirect($id,'defaultadmin','',array(
	 'active_tab'=>$params['active_tab'],
	 'message'=>$this->Lang('notyet')));
} else {
	if (!isset($params['selreq']))
		$this->Redirect($id,'defaultadmin','',array(
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('nosel',FALSE)));

	if (isset($params['delete'])) {
		$this->Redirect($id,'defaultadmin','',array(
		 'active_tab'=>$params['active_tab'],
		 'message'=>$this->Lang('notyet')));

		foreach ($selreq as $req_id) {
//			$this->Crash();
		}
	}
}

$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
