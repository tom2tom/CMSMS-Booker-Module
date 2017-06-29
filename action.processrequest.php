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
	$this->Redirect($id,'defaultadmin','',['active_tab'=>$params['active_tab']]);
} elseif (isset($params['find'])) {
	$newparms = ['resume'=>'defaultadmin','active_tab'=>$params['active_tab']];
	if (!empty($params['selreq'])) {
		$sql = 'SELECT item_id FROM '.$this->OnceTable.' WHERE bkg_id=?';
		$bid = reset($params['selreq']);
		$item_id = $this->dbHandle->GetOne($sql,[$bid]);
		$newparms['item_id'] = $item_id;
	}
	$this->Redirect($id,'findbooking','',$newparms);
} elseif (isset($params['task'])) { //clicked link
	if ($params['task'] != 'see') {
		if (!$p) exit;
	} elseif (!($p || $this->_CheckAccess('view'))) exit;

	$params['active_tab'] = 'data';
	$params['resume'] = json_encode(['defaultadmin']);
	switch ($params['task']) {
	 case 'see':
		$this->Redirect($id,'openrequest','',['bkg_id'=>$params['bkg_id'],
			'resume'=>$params['resume'],'task'=>'see']);
		break;
	 case 'edit':
		$this->Redirect($id,'openrequest','',['bkg_id'=>$params['bkg_id'],
			'resume'=>$params['resume'],'task'=>'edit']);
		break;
/*	 case 'add':
		$this->Redirect($id,'openrequest','',array('bkg_id'=>$params['bkg_id'],
		'resume'=>$params['resume'],'task'=>'add'));
		break;
*/
	 case 'approve':
		list($res,$msg) = $funcs->ApproveReq($this,$params['bkg_id'],$params['custmsg']);
		break;
	 case 'reject':
		list($res,$msg) = $funcs->RejectReq($this,$params['bkg_id'],$params['custmsg']);
		break;
	 case 'ask':
		list($res,$msg) = $funcs->AskReq($this,$params['bkg_id'],$params['custmsg']);
		break;
	 case 'delete':
		list($res,$msg) = $funcs->DeleteReq($this,$params['bkg_id'],$params['custmsg']);
		break;
	 default:
	 	$res = TRUE;
	}
	$newparms = ['active_tab'=>$params['active_tab']];
	if (!$res) {
		$newparms['message'] = $msg;
	}
	$this->Redirect($id,'defaultadmin','',$newparms);
} elseif (isset($params['export'])) {
$this->Crash();
} elseif (isset($params['import'])) {
	if (!$p) exit;
	//was if (isset($params['importbkg']))
	$this->Redirect($id,'import','',[
	//TODO
	]);
}

if (!$p) exit; //NB permission for exports, too
$newparms = ['active_tab'=>$params['active_tab']];
$sel = (isset($params['selreq'])) ? $params['selreq'] : FALSE;
if ($sel) {
	if (isset($params['approve'])) {
		list($res,$msg) = $funcs->ApproveReq($this,$sel,$params['custmsg']);
	} elseif (isset($params['reject'])) {
		list($res,$msg) = $funcs->RejectReq($this,$sel,$params['custmsg']);
	} elseif (isset($params['ask'])) {
		list($res,$msg) = $funcs->AskReq($this,$sel,$params['custmsg']);
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
		list($res,$msg) = [FALSE,$this->Lang('notyet')]; //TODO
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
