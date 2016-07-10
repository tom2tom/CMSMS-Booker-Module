<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: delbooking - delete booking, and do consequential stuff
# See also: multibooking action
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;

$msg = FALSE;
$funcs = new Booker\Bookingops();
if (!empty($params['repeat'])) { //doing a repeat-booking
	$msg = $funcs->DeleteRepeat($this,$params['bkg_id']);
	if ($msg === TRUE) {
//DO RELATED STUFF ?
	}
} else { //onetime
	$msg = $funcs->DeleteBkg($this,$params['bkg_id'],$params['custmsg']);
	if ($msg === TRUE) {
//TODO payment reconciliation, if enough notice is given
	}
}

$newparms = array('item_id'=>$params['item_id']);
if ($msg)
	$newparms['message'] = $msg;

$this->Redirect($id,'administer','',$newparms);
