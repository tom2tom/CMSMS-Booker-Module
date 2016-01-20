<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: price
# view or edit prices for the specified item or group ($params['id'])
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

$tab = $params['active_tab'];

if(!(isset($params['selitems']) || isset($params['selgroups'])))
	$this->Redirect($id,'defaultadmin','',array(
	 'active_tab'=>$tab,
	 'message'=>$this->_PrettyMessage('nosel',FALSE)));

if(isset($params['cancel']))
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$tab));
elseif(isset($params['submit']))
{
	//TODO save stuff
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$tab));
}
$is_group = ($item_id >= Booker::MINGRPID);

//TODO multi-item selection

$sql = 'SELECT fee1,fee2,fee1condition,fee2condition FROM '.$this->ItemTable.' WHERE item_id=?';
$pdata = $db->GetAll($sql,array($item_id));
if($pdata == FALSE)
	$this->Redirect($id,'defaultadmin','',array('active_tab'=>$tab,'message'=>$this->_PrettyMessage('err_system',FALSE)));

$pmod = $this->_CheckAccess('admin');
$tplvars = array(
	'mod' => $pmod,

	'startform' => $this->CreateFormStart($id,'price',$returnid),
	'endform' => $this->CreateFormEnd(),
	'hidden' => $this->CreateInputHidden($id,'item_id',$item_id)
);

$key = ($pmod) ? 'feemodtitle' : 'feeseetitle';
$t = ($is_group) ? $this->Lang('group') : $this->Lang('item');
$tplvars['title'] = $this->Lang($key,strtoupper($t));
//$tplvars['intro'] = $this->Lang('feeintro');
$tplvars['intro'] = $this->Lang('help_fees').'<br />'.
	$this->Lang('help_feeconditions');

$tplvars['pricetext1'] = $this->Lang('fee1');
$tplvars['pricetext2'] = $this->Lang('fee2');

$t = $this->Lang('application');
$pdata = $pdata[0];
$val = $pdata['fee1'];
$fixed = ($val < 0);
if($fixed)
	$val = -$val;
if($pmod)
{
	$i = $this->CreateInputCheckbox($id,'fee1fixed',true,$fixed).$this->Lang('feefixed').
	'&nbsp;'.$this->CreateInputText($id,'fee1',$val,6,8).'<br /><br />'.
	$t.': '.$this->CreateInputText($id,'fee1condition',$pdata['fee1condition'],50,50);
}
elseif($fixed)
	$i = $this->Lang('feefixed').': '.$val.'<br />'.$t.': '.$pdata['fee1condition'];
else
	$i = $val.'<br />'.$t.': '.$pdata['fee1condition'];
$tplvars['priceinput1'] = $i;

$val = $pdata['fee2'];
$fixed = ($val < 0);
if($fixed)
	$val = -$val;
if($pmod)
{
	$i = $this->CreateInputCheckbox($id,'fee2fixed',true,$fixed).$this->Lang('feefixed').
	'&nbsp;'.$this->CreateInputText($id,'fee2',$val,6,8).'<br /><br />'.
	$t.': '.$this->CreateInputText($id,'fee2condition',$pdata['fee2condition'],50,50);
}
elseif($fixed)
	$i = $this->Lang('feefixed').': '.$val.'<br />'.$t.': '.$pdata['fee2condition'];
else
	$i = $val.'<br />'.$t.': '.$pdata['fee2condition'];
$tplvars['priceinput2'] = $i;

if($pmod)
{
	$tplvars['submit'] =  $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['cancel'] =  $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
}
else
{
	$tplvars['submit'] = '';
	$tplvars['cancel'] =  $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
}

echo bkrshared::ProcessTemplate($this,'price.tpl',$tplvars);
?>
