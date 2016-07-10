<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: notifybooker - send message to recorded users of resource or group or specific booking
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*
supplied $params
'item_id'=> from defaultadmin or administer action
'bkg_id'=> from administer only
*/
if (!($this->_CheckAccess('admin') || $this->_CheckAccess('see'))) exit;

if (isset($params['bkg_id']))
	$bid = (int)$params['bkg_id'];
else {
	$funcs = new Booker\Shared();
	$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE item_id=?';
	$bid = $funcs->SafeGet($sql,array($params['item_id']),'col');
	if (!$bid) {
		$name = $funcs->GetItemNameForID($mod,$params['item_id']);
		$msg = $this->Lang('nodata_one',$name);
		$msg = $this->_PrettyMessage($msg,FALSE,FALSE);
		$tab = ($params['item_id'] >= Booker::MINGRPID) ? 'groups':'items';
		$this->Redirect($id,'defaultadmin','',array('active_tab'=>$tab,'message'=>$msg));
	}
}

$funcs = new Booker\Bookingops();
list($res,$msg) = $funcs->NotifyBooker($this,$bid,$params[custmsg]);

if (isset($params['bkg_id'])) {
	$resume = 'administer';
	$newparms = array('item_id'=>$params['item_id']);
} else {
	$resume = 'defaultadmin';
	$tab = ($params['item_id'] >= Booker::MINGRPID) ? 'groups':'items';
	$newparms = array('active_tab'=>$tab);
}
if ($msg)
	$newparms['message'] = $msg;

$this->Redirect($id,$resume,'',$newparms);
