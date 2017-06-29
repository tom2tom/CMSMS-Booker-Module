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
	$this->Redirect($id,'defaultadmin','',['active_tab'=>$params['active_tab']]);
} elseif (isset($params['task'])) { //clicked link
	switch ($params['task']) {
	 case 'see':
	 case 'add':
	 case 'edit':
	 	$params['resume'] = json_encode(['defaultadmin']);
		$this->Redirect($id,'openbooker','',$params);
		break;
	 case 'delete':
	 	$funcs = new Booker\Userops($this);
		$funcs->DeleteUser($this,$params['booker_id']);
		break;
	 case 'toggle':
	 	$funcs = new Booker\Userops($this);
		$funcs->SetActive($this,$params['booker_id'],!$params['active']);
		break;
	 case 'export';
		$funcs = new Booker\Bookingops();
		$sql = 'SELECT bkg_id FROM '.$this->OnceTable.' WHERE booker_id=?';
		$bkgid = $db->GetCol($sql,[$params['booker_id']]);
		list($res,$msg) = $funcs->ExportBkg($this,$bkgid);
		if ($res)
			exit;
		$this->Redirect($id,'defaultadmin','',[
		 'active_tab'=>'people',
		 'message'=>$this->_PrettyMessage($msg,FALSE,FALSE)]);
		break;
	}
	$this->Redirect($id,'defaultadmin','',['active_tab'=>'people']);
} elseif (isset($params['delete'])) {
	if (!isset($params['selbkr']))
		$this->Redirect($id,'defaultadmin','',[
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('nosel',FALSE)]);

 	$funcs = new Booker\Userops($this);
	if (!$funcs->DeleteUser($this,$params['selbkr']))
		$this->Redirect($id,'defaultadmin','',[
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('err_system',FALSE)]);
} else if (isset($params['export'])) {
	if (!isset($params['selbkr']))
		$this->Redirect($id,'defaultadmin','',[
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('nosel',FALSE)]);
	$funcs = new Booker\Export();
	list($res,$key) = $funcs->ExportBookers($this,$params['selbkr']);
	if ($res)
		exit;
	$this->Redirect($id,'defaultadmin','',[
	 'active_tab'=>$params['active_tab'],
	 'message'=>$this->_PrettyMessage($key,FALSE)]);
} else if (isset($params['exportbkg'])) {
	if (!isset($params['selbkr']))
		$this->Redirect($id,'defaultadmin','',[
		'active_tab'=>$params['active_tab'],
		'message'=>$this->_PrettyMessage('nosel',FALSE)]);
	$funcs = new Booker\Export();
	list($res,$key) = $funcs->ExportBookings($this,'*','*',$params['selbkr']);
	if ($res)
		exit;
	$this->Redirect($id,'defaultadmin','',[
	 'active_tab'=>$params['active_tab'],
	 'message'=>$this->_PrettyMessage($key,FALSE)]);
} elseif (isset($params['import'])) {
$this->Crash();
}

$this->Redirect($id,'defaultadmin','',['active_tab'=>$params['active_tab']]);
