<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: swapfees
# Swap the order-numbers of two fees, specified in $params['condition_id'] etc
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

if (!isset($params['condition_id']))
	$this->Redirect($id,'fees','',array('message'=>$this->Lang('err_parm')));

$otheritm = array();
if (isset($params['next_cond_id']))
	$otheritm[] = $params['next_cond_id'];
elseif (isset($params['prev_cond_id']))
	$otheritm[] = $params['prev_cond_id'];
else
	$this->Redirect($id,'fees');

$sql = 'SELECT condorder FROM '.$this->PayTable.' WHERE condition_id=?';
$num2 = $db->GetOne($sql,$otheritm);
if ($num2 === FALSE)
	$this->Redirect($id,'fees','',array('message'=>$this->Lang('err_parm')));

$thisitm = array($params['condition_id']);
$num1 = $db->GetOne($sql,$thisitm);
if ($num1 === FALSE)
	$this->Redirect($id,'fees','',array ('message'=>$this->Lang('err_parm')));

array_unshift($thisitm,$num2);
array_unshift($otheritm,$num1);

$sql = 'UPDATE '.$this->PayTable.' SET condorder=? WHERE condition_id=?';
$db->Execute($sql,$thisitm);
$db->Execute($sql,$otheritm);

$this->Redirect($id,'fees');
?>
