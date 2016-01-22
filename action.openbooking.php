<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: openbooking
# View or edit bookings data for resource or group
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*first-time $params = array
 'item_id'=>$item_id
 'bkg_id'=> -1 (add) OR real id (edit)
 'repeat'=> '' OR '1'
 'resume'=> 'administer' OR 'inspect' OR absent (meaning 'administer')
*/

if(!$this->_CheckAccess()) exit;

$item_id = (int)$params['item_id'];
if(isset($params['resume']))
	$resume = $params['resume'];
else
	$resume = 'administer';

if(isset($params['cancel']))
	$this->Redirect($id,$resume,'',array('item_id'=>$item_id));

$is_group = ($item_id >= Booker::MINGRPID);
$type = ($is_group) ? $this->Lang('group'):$this->Lang('item');
$is_new = ($params['bkg_id'] == -1);
$viewmode = ($resume == 'inspect');
$funcs = new bkrshared();
$funcs3 = new bkrbookingops();

if(isset($params['submit']) || isset($params['apply']))
{
	if(!($this->_CheckAccess('admin') || $this->_CheckAccess('book'))) exit;
/* $params = array including
  'item_id' => string '8'
  'bkg_id' => string '9'
  'repeat' => string '' OR '1'
  'resume' => string 'administer'
  'when' => string '17 October 2015 12:00'
  'until' => string '17 October 2015 12:59'
  'user' => string 'Mary'
  'conformuser' => string '1' MAYBE
  'userclass' => string '1'
  'conformstyle' => string '1' MAYBE
  'contact' => string '@myfirm'
  'conformcontact' => string '1' MAYBE
*/
	$msg = array();
	$t = trim($params['user']);
	if($t)
		$params['user'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('user'));
	$t = trim($params['contact']);
	if($t)
		$params['contact'] = $t;
	else
		$msg[] = $this->Lang('missing_type',$this->Lang('contact'));

	if($params['repeat'])
	{
		$t = trim($params['formula']);
		if($t)
		{
			$funcs2 = new IntervalParser($this);
			$t = $funcs2->CheckCondition($t);
		}
		if($t)
		{
			if($is_new)
			{
				$sql2 = 'bkg_id,item_id,formula,user,contact,userclass';
				$fillers = '?,?,?,?,?,?';
				$bid = $db->GenID($this->DataTable.'_seq');
				$args = array(
					$bid,
					(int)$params['item_id'],
					$t,
					$params['user'],
					$params['contact'],
					(int)$params['userclass']
				);
				foreach(array('subgrpcount','paid') as $k)
				{
					if(isset($params[$k]))
					{
						$sql2 .= ",$k";
						$fillers .= ',?';
						$args[] = (int)$params[$k];
					}
				}
				$sql = 'INSERT INTO '.$this->RepeatTable.' ('.$sql2.') VALUES ('.$fillers.')';
				$db->Execute($sql,$args);
			}
			else //update
			{
				$funcs3->ConformBookingData($this,$params); //general update where needed, before we change user
				$sql2 = 'formula=?,user=?,contact=?,userclass=?';
				$args = array(
					$t,
					$params['user'],
					$params['contact'],
					(int)$params['userclass']
				);
				foreach(array('subgrpcount','paid') as $k)
				{
					if(isset($params[$k]))
					{
						$sql2 .= ",$k=?";
						$args[] = (int)$params[$k];
					}
				}
				$args[] = (int)$params['bkg_id'];
				$sql = 'UPDATE '.$this->RepeatTable.' SET '.$sql2.' WHERE bkg_id=?';
				$db->Execute($sql,$args);

				$dtn = new DateTime('now',new DateTimeZone('UTC'));
				$dtn->setTime(0,0,0);
				if($is_group)
				{
					$membrs = $funcs->GetGroupItems($this,$item_id);
					if($membrs)
					{
						$dtn->modify('-1 day');
						$st = $dtn->getTimestamp();
						$sql = 'UPDATE '.$this->ItemTable.' SET repeatsuntil=? WHERE item_id IN ('.
						implode(',',$membrs).') AND repeatsuntil>?';
						$db->Execute($sql,array($st,$st));
						$dtn->modify('+1 day');
					}
				}
				//remove future derived booking-slots
				$dtn->modify('+1 day');
				$st = $dtn->getTimestamp();
				$args = array($params['bkg_id'],$st);
				$sql = 'DELETE FROM '.$this->DataTable.' WHERE bkg_id=? AND slotstart>?';
				$funcs->SafeExec($sql,$args);
//TODO	send notice(s) if appropriate
			}
		}
		else
		{
			$msg[] = $this->Lang('err_parm'); //TODO better message
		}
	}
	else //onetime booking
	{
		$funcs2 = new bkrverify();
		list($res,$xmsg) = $funcs2->VerifyAdmin($mod,$funcs,$params,$item_id,$is_new);
		if($res)
		{
			$funcs3->SaveBkg($this,$params,$is_new);
/*
			if($is_new)
			{
//TODO	send notice if appropriate
			}
			else //update
			{
//TODO	send notice if appropriate
			}
*/
		}
		else
			$msg = array_merge($msg,$xmsg);
	}
	if($msg) //error
	{
		$t = implode(' ',$msg);
		if(empty($params['message']))
			$params['message'] = $t;
		else
			$params['message'] .= '<br />'.$t;
	}
	elseif(isset($params['submit']))
		$this->Redirect($id,$resume,'',array('item_id'=>$item_id));
}
elseif(isset($params['find']))
{
	//TODO
	$params['message'] = $this->Lang('notyet');
}

$tplvars = array();
$tplvars['startform'] =
		$this->CreateFormStart($id,'openbooking',$returnid,'POST','','','',array(
		 'item_id'=>$item_id,'bkg_id'=>$params['bkg_id'],
		 'repeat'=>$params['repeat'],'resume'=>$resume));
$tplvars['endform'] = $this->CreateFormEnd();

$this->_BuildNav($id,$params,$returnid,$tplvars);
if(!empty($params['message']))
	$tplvars['message'] = $params['message'];

$tplvars['mod'] = !$viewmode;
if(!$viewmode)
	$tplvars['compulsory'] = $this->Lang('help_compulsory');

$idata = $funcs->GetItemProperty($this,$item_id,"*");

if($params['repeat'])
	$sql = 'SELECT * FROM '.$this->RepeatTable.' WHERE bkg_id=?';
else
	$sql = 'SELECT * FROM '.$this->DataTable.' WHERE bkg_id=?';
$bdata = $funcs->SafeGet($sql,array($params['bkg_id']),'row');

$key = ($is_new) ? 'title_booknewfor':'title_bookfor';
if(!empty($idata['name']))
{
	$tplvars['title'] = $this->Lang($key,$type,$idata['name']);
}
else
{
	$t = $this->Lang('title_noname',$type,$idata['item_id']);
	$tplvars['title'] = $this->Lang($key,$t,'');
}

$t = '';
if(!empty($idata['description']))
	$t .= bkrshared::ProcessTemplateFromData($this,$idata['description'],$tplvars);
$tplvars['desc'] = $t;
//in this context, ignore any image

//script accumulators
$jsincs = array();
$jsfuncs = array();
$jsloads = array();
$baseurl = $this->GetModuleURLPath();

$vars = array();

if($params['repeat'])
{
	$one = new stdClass();
	$one->title = $this->Lang('title_description');
	$t = ($is_new) ? '':$bdata['formula'];
	if($viewmode)
		$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
	else
	{
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'formula',$t,50,256);
	}
	$one->help = $this->Lang('help_intervals');
	$vars[] = $one;
}
else //onetime booking
{
	$choosend = ($idata['bookcount'] != 1);

	$one = new stdClass();
	$one->title = $this->Lang('title_when');
	if($is_new)
	{
		$t = '';
	}
	else
	{
		$dt = new DateTime('1900-1-1',new DateTimeZone('UTC'));
		$dt->setTimestamp($bdata['slotstart']);
		$fmt = $idata['dateformat'].' '.$idata['timeformat'];
		$t = $dt->format($fmt);
	}
	if($viewmode)
	{
		$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
	}
	else
	{
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'when',$t,20,30);
		//for date-picker
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/moment.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>

EOS;
		$nextm = $this->Lang('nextm');
		$prevm = $this->Lang('prevm');
		//js wants quoted period-names
		$t = $this->Lang('longmonths');
		$mnames = "'".str_replace(",","','",$t)."'";
		$t = $this->Lang('longdays');
		$dnames = "'".str_replace(",","','",$t)."'";
		$t = $this->Lang('shortdays');
		$sdnames = "'".str_replace(",","','",$t)."'";
		if($choosend)
		{
			$sl = ($is_new) ? /*$idata['slotlen']*/3600:$bdata['slotlen']; //TODO
			$t2 = <<<EOS
    d2 = moment(d).add($sl,'s').format(f);
    $('#{$id}until').val(d2);

EOS;
		}
		else
		{
			$t2 = '';
		}
		$overday = ($funcs->GetInterval($this,$item_id,'slot') >= 84600);
		$momentfmt = ($overday) ? 'YYYY-M-D':'YYYY-M-D h:mm';

		$jsloads[] = <<<EOS
 new Pikaday({
  field: document.getElementById('calendar'),
  trigger: document.getElementById('{$id}when'),
  format: 'YYYY-MM-DD',
  i18n: {
   previousMonth: '{$prevm}',
   nextMonth: '{$nextm}',
   months: [{$mnames}],
   weekdays: [{$dnames}],
   weekdaysShort: [{$sdnames}]
  },
  onClose: function(){
   var sel = $('#calendar').val();
   if(sel !== '') { //not cancelled
    var d = new Date(sel);
    var f = '{$momentfmt}';
    var d2 = moment(d).format(f);
    $('#{$id}when').val(d2);
{$t2}
   }
  }
 });

EOS;
	}
	$one->help = $this->Lang('help_book_start');
	$vars[] = $one;
//==
	if($choosend)
	{
		$one = new stdClass();
		$one->title = $this->Lang('title_until');
		if(!$is_new)
		{
			$dt->setTimestamp($bdata['slotstart'] + $bdata['slotlen']);
			$t = $dt->format($fmt);
		}
		if($viewmode)
		{
			$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
		}
		else
		{
			$one->must = 0;
			$one->input = $this->CreateInputText($id,'until',$t,20,30);
		}
		$one->help = NULL; //$this->Lang('help_book_end');
		$vars[] = $one;
	}
} //end 1-time booking
//==
if($is_group)
{
	$one = new stdClass();
	$one->title = $this->Lang('title_subgrpcount');
	$t = ($is_new) ? '':(int)$bdata['subgrpcount'];
	if($viewmode)
		$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
	else
	{
		$one->must = 1;
		$one->input = $this->CreateInputText($id,'subgrpcount',$t,3,5);
	}
	$one->help = $this->Lang('help_subgrpcount');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('title_user');
$t = ($is_new) ? '':$bdata['user'];
if($viewmode)
{
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
}
else
{
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'user',$t,20,64);
}
$vars[] = $one;
//==
if(!($is_new || $viewmode))
{
	$one = new stdClass();
	$one->title = $this->Lang('title_conformuser');
	$one->input = $this->CreateInputCheckbox($id,'conformuser',1,-1);
	$one->help = $this->Lang('help_conformuser');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('userclass');
$t = ($is_new) ? 0:(int)$bdata['userclass'];
if($viewmode)
{
	$one->input = $t;
}
else
{
	$one->must = 0;
	$choices = array(1=>1,2=>2,3=>3,4=>4,5=>5);
	$one->input = $this->CreateInputDropdown($id,'userclass',$choices,-1,$t);
}
$one->help = $this->Lang('help_book_style');
$vars[] = $one;
//==
if(!($is_new || $viewmode))
{
	$one = new stdClass();
	$one->title = $this->Lang('title_conformstyle');
	$one->input = $this->CreateInputCheckbox($id,'conformstyle',1,-1);
	$one->help = $this->Lang('help_conformstyle');
	$vars[] = $one;
}
//==
$one = new stdClass();
$one->title = $this->Lang('title_contact');
$t = ($is_new) ? '':$bdata['contact'];
if($viewmode)
{
	$one->input = ($t) ? $t:'&lt;'.$this->Lang('missing').'&gt;';
}
else
{
	$one->must = 1;
	$one->input = $this->CreateInputText($id,'contact',$t,30,128);
}
$one->help = $this->Lang('help_book_contact');
$vars[] = $one;
//==
if(!($is_new || $viewmode))
{
	$one = new stdClass();
	$one->title = $this->Lang('title_conformcontact');
	$one->input = $this->CreateInputCheckbox($id,'conformcontact',1,-1);
	$one->help = $this->Lang('help_conformcontact');
	$vars[] = $one;
}
//==
if($idata['fee1'] != 0 || ($idata['fee2'] != 0 && $idata['fee2condition']))
{
	$one = new stdClass();
	$one->title = $this->Lang('title_paid');
	$t = ($is_new) ? 0:(int)$bdata['paid'];
	if($viewmode)
	{
		$one->input = ($t) ? $this->Lang('yes'):$this->Lang('no');
	}
	else
	{
		$one->must = 0;
		$one->input = $this->CreateInputCheckbox($id,'paid',1,$t);
	}
	$vars[] = $one;
}

$tplvars['data'] = $vars;

//buttons
if($viewmode)
{
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close'));
}
else //add/edit mode
{
	$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
	$tplvars['apply'] = $this->CreateInputSubmit($id,'apply',$this->Lang('apply'));
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
	$tplvars['find'] = $this->CreateInputSubmit($id,'find',$this->Lang('find'),
		'title="'.$this->Lang('tip_finditm').'"');
	if(!$params['repeat'])
	{
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.modalconfirm.min.js"></script>

EOS;
		$tplvars['yes'] = $this->Lang('yes');
		$tplvars['no'] = $this->Lang('no');
		$funcs2 = new bkrverify();
		$jsfuncs[] = $funcs2->VerifyScript($this,$id,TRUE,TRUE,FALSE,$idata['timezone']);
		$jsloads[] = <<<EOS
 var obs = [$('#{$id}submit'),$('#{$id}apply')];
 $.each(obs,function(indx,\$ob) {
  \$ob.bind('click',validate);
 });

EOS;
	}
}

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;

$tplvars['jsstyler'] = <<<EOS
var \$head = $('head'),
 \$linklast = \$head.find("link[rel='stylesheet']:last"),
 linkadd = '{$stylers}';
if (\$linklast.length) {
 \$linklast.after(linkadd);
} else {
 \$head.append(linkadd);
}
EOS;

if($jsloads)
{
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '})
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo bkrshared::ProcessTemplate($this,'openbooking.tpl',$tplvars);
?>
