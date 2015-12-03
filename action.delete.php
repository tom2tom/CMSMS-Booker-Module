<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: delete - delete resource or group, and do consequential changes
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if(!($this->_CheckAccess('admin') || $this->_CheckAccess('delete'))) exit;

$funcs = new bkritemops();
$item_id = (int)$params['item_id'];
$funcs->DeleteItem($this,$item_id);

$p = ($item_id >= Booker::MINGRPID) ? 'groups' : 'items';
$this->Redirect($id,'defaultadmin','',array('active_tab'=>$p));
?>
