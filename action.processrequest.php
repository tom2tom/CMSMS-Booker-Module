<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: processrequest - perform some operation on a submitted booking-request
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*
LINK-CLICK actions
 'rapprove'
 'rreject' 
 'rsee' view
 'redit' edit
 'rnotify' notify submitter
 'rdelete' delete
 'custmsg'
'adminbooking' action SUBMIT $params
 'find'
 'approve' (SELECTED)
 'reject' (SELECTED)
 'notify' (SELECTED)
 'delete' (SELECTED)
 'custmsg'
*/
$parms = array();
$sel = (isset($params['selreq'])) ? $params['selreq'] : FALSE;
$funcs = new bkrrequestops();
$res = TRUE;
//link-actions
if($params['action'] == 'rapprove')
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	list($res,$msg) = $funcs->ApproveReq($this,$params['req_id'],$params['custmsg']);
}
elseif($params['action'] == 'rreject')
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	list($res,$msg) = $funcs->RejectReq($this,$params['req_id'],$params['custmsg']);
}
elseif($params['action'] == 'rsee') //view
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book') || $this->_CheckAccess('view'))) exit;
	$this->Redirect($id,'openrequest','',array('req_id'=>$params['req_id'],'mode'=>'inspect'));
}
elseif($params['action'] == 'redit') //edit
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	$this->Redirect($id,'openrequest','',array('req_id'=>$params['req_id'],'mode'=>'edit'));
}
elseif($params['action'] == 'rnotify') //notify submitter
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	list($res,$msg) = $funcs->NotifyReq($this,$params['req_id'],$params['custmsg']);
}
elseif($params['action'] == 'rdelete') //delete
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	list($res,$msg) = $funcs->DeleteReq($this,$params['req_id'],$params['custmsg']);
}
//adminbooking' action SUBMIT $params
elseif(isset($params['find']))
{
	$this->Redirect($id,'actionTODO','',array()); //TODO
}
elseif(isset($params['approve']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	if($sel)
		list($res,$msg) = $funcs->ApproveReq($this,$sel,$params['custmsg']);
	else
		$parms['message'] = $this->_PrettyMessage('nosel',FALSE);
}
elseif(isset($params['reject']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	if($sel)
		list($res,$msg) = $funcs->RejectReq($this,$sel,$params['custmsg']);
	else
		$parms['message'] = $this->_PrettyMessage('nosel',FALSE);
}
elseif(isset($params['notify']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	if($sel)
		list($res,$msg) = $funcs->NotifyReq($this,$sel,$params['custmsg']);
	else
		$parms['message'] = $this->_PrettyMessage('nosel',FALSE);
}
elseif(isset($params['delete']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	if($sel)
		list($res,$msg) = $funcs->DeleteReq($this,$sel,$params['custmsg']);
	else
		$parms['message'] = $this->_PrettyMessage('nosel',FALSE);
}
if(!$res)
	$parms['message'] = $msg;

if($parms)
	$this->Redirect($id,'defaultadmin','',$parms);
else
	$this->Redirect($id,'defaultadmin');
?>
