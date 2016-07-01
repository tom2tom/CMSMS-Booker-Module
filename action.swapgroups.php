<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: swapgroups
# swap proximity or likeorder values for 2 rows in GroupTable
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*
confusingly, $params['ref_id'] = id of resource/group being processed
$params['*item_id'] are id's of parent or child of that id, and for which a relevant field-value is to be swapped
 */
if (!isset($params['item_id']) || !isset($params['ref_id']))
	$this->Redirect($id,'defaultadmin','',array('message'=>$this->Lang('err_parm')));
$item_id = $params['ref_id'];
$otherargs = array($item_id);
if (isset($params['next_item_id']))
	$otherargs[] = $params['next_item_id'];
elseif (isset($params['prev_item_id']))
	$otherargs[] = $params['prev_item_id'];
else
	$this->Redirect($id,'openitem','',array('item_id'=>$item_id,'message'=>$this->Lang('err_parm')));

if($params['change'] == 'parent')
	$sql = 'SELECT gid,proximity FROM '.$this->GroupTable.' WHERE child=? AND parent=?';
else //'child'
	$sql = 'SELECT gid,likeorder FROM '.$this->GroupTable.' WHERE parent=? AND child=?';
$otherrow = $db->GetRow($sql,$otherargs);
if ($otherrow === FALSE)
	$this->Redirect($id,'openitem','',array('item_id'=>$item_id,'message'=>$this->Lang('err_parm')));

$thisargs = array($item_id,$params['item_id']);
$thisrow = $db->GetRow($sql,$thisargs);
if ($thisrow === FALSE)
	$this->Redirect($id,'openitem','',array('item_id'=>$item_id,'message'=>$this->Lang('err_parm')));

$thisargs2 = array(end($thisrow),$thisrow['gid']);
$otherargs2 = array(end($otherrow),$otherrow['gid']);

if($params['change'] == 'parent')
	$sql2 = 'UPDATE '.$this->GroupTable.' SET proximity=? WHERE gid=?';
else
	$sql2 = 'UPDATE '.$this->GroupTable.' SET likeorder=? WHERE gid=?';

$db->Execute($sql2,$thisargs2);
$db->Execute($sql2,$otherargs2);

$this->Redirect($id,'openitem','',array('item_id'=>$item_id));

?>
