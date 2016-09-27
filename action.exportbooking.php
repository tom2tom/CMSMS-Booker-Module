<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: exportbooking - export bookings-data for resource or group or specific booking
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*
supplied $params
'item_id'=> from defaultadmin or administer action
'bkg_id'=> from administer only
*/
if ($this->_CheckAccess('admin') || $this->_CheckAccess('see')) {
	if (isset($params['bkg_id']))
		$bkgid = (int)$params['bkg_id'];
	else {
		$utils = new Booker\Utils();
		$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE item_id=?';
		$bkgid = $utils->SafeGet($sql,array($params['item_id']),'col');
		if (!$bkgid) {
			$name = $utils->GetItemNameForID($mod,$params['item_id']);
			$msg = $this->Lang('nodata_one',$name);
			$msg = $this->_PrettyMessage($msg,FALSE,FALSE);
			$tab = ($params['item_id'] >= Booker::MINGRPID) ? 'groups':'items';
			$this->Redirect($id,'defaultadmin','',array('active_tab'=>$tab,'message'=>$msg));
		}
	}
	$funcs = new Booker\Bookingops();
	list($res,$msg) = $funcs->ExportBkg($this,$bkgid);
	if (!$res) {
		if (isset($params['resume'])) {
			$params['resume'] = json_decode(html_entity_decode($params['resume'],ENT_QUOTES|ENT_HTML401));
			while (end($params['resume']) == $params['action']) {
				array_pop($params['resume']);
			}
			$resume = array_pop($params['resume']);
		} else {
			$resume = 'defaultadmin';
		}
		switch ($resume) {
		 case 'itembookings':
			$newparms = array('item_id'=>$params['item_id'],'task'=>$params['task'],'message'=>$msg);
			break;
		 case 'bookerbookings':
			$newparms = array('item_id'=>$params['item_id'],'booker_id'=>$params['booker_id'],
			'task'=>$params['task'],'message'=>$msg);
			break;
		 case 'defaultadmin':
			$t = ($params['item_id'] >= Booker::MINGRPID) ? 'groups':'items';
			$newparms = array('active_tab'=>$t,'message'=>$msg);
			break;
		 default:
$this->Crash();
		}
		$this->Redirect($id,$resume,'',$newparms);
	}
}
exit;
