<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: multibooking - process selected booking(s)
# See also - actions for single-booking equivalent processing
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/* $params supplied
'item_id' =>
'resume' => 'administer'
'repeat' => ''
'pagerows' => '10'
'sel' => array (size=3)
  0 =>
  1 =>
  2 =>
'export' => ETC
*/
if (!$this->_CheckAccess()) exit;

if (!isset($params['sel'])) { //nothing selected
	$msg = $this->Lang('notypesel',$this->Lang('booking_multi'));
	$this->Redirect($id,$params['resume'],'',array(
	 'item_id'=>$params['item_id'],
	 'message'=> $this->_PrettyMessage($msg,FALSE,FALSE)));
}

$funcs = new Booker\Bookingops();
$msg = FALSE;
if (isset($params['delete'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;

	if (!empty($params['repeat'])) { //is repeat-booking
		list($res,$msg) = $funcs->DeleteRepeat($this,$params['sel']);
		if ($res) {
	//DO STUFF ?
			$msg = $this->Lang('result_deleted',count($params['sel']));
			$msg = $this->_PrettyMessage($msg,TRUE,FALSE);
		}
	} else { //onetime
		list($res,$msg) = $funcs->DeleteBkg($this,$params['sel'],$params['custmsg']);
		if ($res) {
	//TODO payment reconciliation, if enough notice is given
			$msg = $this->Lang('result_deleted',count($params['sel']));
			$msg = $this->_PrettyMessage($msg,TRUE,FALSE);
		}
	}
} elseif (isset($params['notify'])) {
//	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
	list($res,$msg) = $funcs->NotifyBooker($this,$params['sel'],$params['custmsg']);
} elseif (isset($params['export'])) {
	if (!($this->_CheckAccess('admin') || $this->_CheckAccess('view'))) exit;
	list($res,$msg) = $funcs->ExportBkg($this,$params['sel']);
	if ($res)
		exit;
}

$newparms = array('item_id'=>$params['item_id']);
if ($msg)
	$newparms['message'] = $msg;

$this->Redirect($id,'administer','',$newparms);
