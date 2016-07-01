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
//TODO support swapping not-yet-grouped items i.e. one or both not in GroupTable
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

$thisargs = array(end($otherrow),$thisrow['gid']);
$otherargs = array(end($thisrow),$otherrow['gid']);

if($thisargs[0] && $otherargs[0])
{
	if($params['change'] == 'parent')
		$sql = 'UPDATE '.$this->GroupTable.' SET proximity=? WHERE gid=?';
	else
		$sql = 'UPDATE '.$this->GroupTable.' SET likeorder=? WHERE gid=?';

	$db->Execute($sql,$thisargs);
	$db->Execute($sql,$otherargs);
}

$this->Redirect($id,'openitem','',array('item_id'=>$item_id)); //TODO 'active_tab'=>$params['active_tab'] N/A here

?>
