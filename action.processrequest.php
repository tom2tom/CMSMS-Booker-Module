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
	$newparms = array('resume'=>'defaultadmin','active_tab'=>$params['active_tab']);
	if (!empty($params['selreq'])) {
		$sql = 'SELECT item_id FROM '.$this->HistoryTable.' WHERE history_id=?';
		$hid = reset($params['selreq']);
		$item_id = $this->dbHandle->GetOne($sql,array($hid));
		$newparms['item_id'] = $item_id;
	}
	$this->Redirect($id,'findbooking','',$newparms);
} elseif (isset($params['task'])) { //clicked link
	if ($params['task'] != 'see') {
		if (!$p) exit;
	} elseif (!($p || $this->_CheckAccess('view'))) exit;

	$params['active_tab'] = 'data';
	switch ($params['task']) {
	 case 'see':
		$this->Redirect($id,'openrequest','',array('history_id'=>$params['history_id'],'task'=>'see'));
		break;
	 case 'edit':
		$this->Redirect($id,'openrequest','',array('history_id'=>$params['history_id'],'task'=>'edit'));
		break;
/*	 case 'add':
		$this->Redirect($id,'openrequest','',array('history_id'=>$params['history_id'],'task'=>'add'));
		break;
*/
	 case 'approve':
		list($res,$msg) = $funcs->ApproveReq($this,$params['history_id'],$params['custmsg']);
		break;
	 case 'reject':
		list($res,$msg) = $funcs->RejectReq($this,$params['history_id'],$params['custmsg']);
		break;
	 case 'notify':
		list($res,$msg) = $funcs->NotifyReq($this,$params['history_id'],$params['custmsg']);
		break;
	 case 'delete':
		list($res,$msg) = $funcs->DeleteReq($this,$params['history_id'],$params['custmsg']);
		break;
	 default:
	 	$res = TRUE;
	}
	$newparms = array('active_tab'=>$params['active_tab']);
	if (!$res) {
		$newparms['message'] = $msg;
	}
	$this->Redirect($id,'defaultadmin','',$newparms);
} elseif (isset($params['export'])) {
$this->Crash();
} elseif (isset($params['import'])) {
	if (!$p) exit;
	//was if (isset($params['importbkg']))
	$this->Redirect($id,'import','',array(
	//TODO
	));
}

if (!$p) exit; //NB permission for exports, too
$newparms = array('active_tab'=>$params['active_tab']);
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
/*		$funcs = new Booker\Export();
		list($res,$key) = $funcs->ExportBookings($this,$sel,'*','*');
		if ($res)
			exit;
		$this->Redirect($id,'defaultadmin','',array(
		 'active_tab'=>$params['active_tab'],
		 'message'=>$this->_PrettyMessage($key,FALSE)));
*/
		list($res,$msg) = array(FALSE,$this->Lang('notyet')); //TODO
	} else {
		$res = TRUE;
	}
	if (!$res) {
		$newparms['message'] = $msg;
	}
} else { //nothing selected
	$newparms['message'] = $this->_PrettyMessage('nosel',FALSE);
}
$this->Redirect($id,'defaultadmin','',$newparms);
