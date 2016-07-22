<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: processrequest - perform operation on submitted booking-request(s)
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

$p = ($this->_CheckAccess('admin') || $this->_CheckAccess('book'));
$funcs = new Booker\Requestops();

if (isset($params['cancel'])) {
	if (!($p || $this->_CheckAccess())) exit;
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$params['active_tab']));
} elseif (isset($params['find'])) {
	$this->Redirect($id,'findbooking','',array( //TODO admin, not frontend
	 'active_tab'=>$params['active_tab']));
} elseif (isset($params['task'])) { //clicked link
	if ($params['task'] != 'see') {
		if (!$p) exit;
	} elseif (!($p || $this->_CheckAccess('view'))) exit;

	switch ($params['task']) {
	 case 'see':
		$this->Redirect($id,'openrequest','',array('req_id'=>$params['req_id'],'mode'=>'inspect'));
		break;
	 case 'add':
		$this->Redirect($id,'openrequest','',array('req_id'=>$params['req_id'],'mode'=>'add'));
		break;
	 case 'edit':
		$this->Redirect($id,'openrequest','',array('req_id'=>$params['req_id'],'mode'=>'edit'));
		break;
	 case 'approve':
		list($res,$msg) = $funcs->ApproveReq($this,$params['req_id'],$params['custmsg']);
		break;
	 case 'reject':
		list($res,$msg) = $funcs->RejectReq($this,$params['req_id'],$params['custmsg']);
		break;
	 case 'notify':
		list($res,$msg) = $funcs->NotifyReq($this,$params['req_id'],$params['custmsg']);
		break;
	 case 'delete':
		list($res,$msg) = $funcs->DeleteReq($this,$params['req_id'],$params['custmsg']);
		break;
	 default:
	 	$res = TRUE;
	}
	$parms = array('active_tab'=>$params['active_tab']);
	if (!$res) {
		$parms['message'] = $msg;
	}
	$this->Redirect($id,'defaultadmin','',$parms);
} elseif (isset($params['import'])) {
	if (!$p) exit;
	//was if (isset($params['importbkg']))
	$this->Redirect($id,'import','',array(
	//TODO
	));
}

if (!$p) exit; //NB permission for exports, too
$parms = array('active_tab'=>$params['active_tab']);
$sel = (isset($params['selreq'])) ? $params['selreq'] : FALSE;
if ($sel) {
	if (isset($params['approve'])) {
		list($res,$msg) = $funcs->ApproveReq($this,$sel,$params['custmsg']);
	} elseif (isset($params['reject'])) {
		list($res,$msg) = $funcs->RejectReq($this,$sel,$params['custmsg']);
	} elseif (isset($params['notify'])) {
		list($res,$msg) = $funcs->NotifyReq($this,$sel,$params['custmsg']);
	} elseif (isset($params['delete'])) {
		list($res,$msg) = $funcs->DeleteReq($this,$sel,$params['custmsg']);
	} else if (isset($params['export'])) {
		list($res,$msg) = array(FALSE,$this->Lang('notyet')); //TODO
	} else {
		$res = TRUE;
	}
	if (!$res) {
		$parms['message'] = $msg;
	}
} else { //nothing selected
	$parms['message'] = $this->_PrettyMessage('nosel',FALSE);
}
$this->Redirect($id,'defaultadmin','',$parms);
