<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: findbooking
# Find bookings which match specified criteria
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/*
if arrive via frontend redirect
$params array
 'item_id'=>
 'startat'=>,
 'range'=>
 'view'=>
 MAYBE
 'bookat'=>
or upon return from form
 'item_id'=>
 'startat'=>
 'range'=>
 'view'=>
 'submit'=> OR 'cancel'=>
MORE
OR admin action
'find' =>
'active_tab' =>
'action' => string 'adminbooking'
*/

if(isset($params['cancel']))	//user cancelled
{
	if(!(is_numeric($params['startat']) || strtotime($params['startat'])))
	{
		$params['message'] = $this->Lang('err_system').' '.$params['startat'];
		$params['startat'] = (int)(time()/86400);
	}
	elseif(!isset($params['message']))
		$params['message'] = '';

	$parms = array(
	'startat'=>$params['startat'],
	'range'=>$params['range'],
	'view'=>$params['view'],
	'item_id'=>$params['item_id'],
	'message'=>$params['message']
	);
	$this->Redirect($id,'default',$returnid,$parms);
}

if(isset($params['item_id']))
{
	$item_id = (int)$params['item_id'];
	$is_group = ($item_id >= Booker::MINGRPID);
}
else
{
//TODO support any item
	$this->Crash();
}

$tplvars = array();
$funcs = new Booker\Shared();
$idata = $funcs->GetItemProperty($this,$item_id,'*');
$tzone = new DateTimeZone('UTC');

if(isset($params['submit']))
{
	if (!empty($params['sender']))
	{
		if ($valid) //STILL
		{
				try {
					$dts = new DateTime($params['when'],$tzone);
				} catch(Exception $e) {
					$key = 'err_badstart';
				}
				if($params['until'])
				{
					try {
						$dte = new DateTime($params['until'],$tzone);
					} catch(Exception $e) {
						$key = 'err_badend';
					}
				}
				else
				{
					$slen = $funcs->GetInterval($this,$item_id,'slot');
					$dte = clone $dts;
					$dte->modify('+'.$slen.' seconds');
				}
			}
			$this->Redirect($id,'default',$returnid,$parms);
	}
	else
		$key = 'err_nosender';

	$tplvars['message'] = $this->Lang($key);
	//fall into repeat presentation
}

$tplvars['startform'] = $this->CreateFormStart($id,'findbooking',$returnid,
	'POST','','','',array(
	'item_id'=>$item_id,
	'startat'=>$params['startat'],
	'range'=>$params['range'],
	'view'=>$params['view']
	));
$tplvars['endform'] = $this->CreateFormEnd();

$tplvars['title'] = $this->Lang('title_find');
//script accumulators
$jsfuncs = array();
$jsloads = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();

//TODO STUFF
$bdata = array();
if(isset($params['bookat']))
	$bdata['slotstart'] = $params['bookat'];
else
	$bdata['slotstart'] = $params['startat'];
$bdata['slotlen'] = $funcs->GetInterval($this,$item_id,'slot');

$tplvars['submit'] =  $this->CreateInputSubmit($id,'submit',$this->Lang('submit'));
$tplvars['cancel'] =  $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));

$jsloads[] = <<<EOS
 $('#{$id}submit').bind('click',validate);

EOS;

$jsfuncs[] = <<<EOS
function showerr(msg) {
 confirm(msg);
}
function validate(ev) {
 var s = $('input[name="{$id}when"]').val(),
     e = $('input[name="{$id}until"]').val();
 var ok = !isNaN(Date.parse(s));
 ok = ok && !isNaN(Date.parse(e));
 if(ok) {
  var ds = new Date(s),
      de = new Date(e),
      dn = new Date();
  ok = de > ds && ds > dn;
 }
 if (!ok) {
  showerr('{$this->Lang('err_badtime')}');
 } else {
  if ($('#{$id}sender').val() == '') {
   showerr('{$this->Lang('err_nosender')}');
   ok = false;
  } else if ($('#{$id}contact').val() == '') {
   showerr('{$this->Lang('err_nocontact')}');
   ok = false;
  } else if ($('{$id}captcha').val() == '') {
   showerr('{$this->Lang('err_nocaptcha')}');
   ok = false;
  }
 }
 if (ok) {
  return true;
 }
 ev.stopImmediatePropagation();
 ev.preventDefault();
 return false;
}

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
$overday = ($funcs->GetInterval($this,$item_id,'slot') >= 84600);
$momentfmt = ($overday) ? 'YYYY-MM-DD':'YYYY-MM-DD h:mm';

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
    d2 = moment(d).add({$bdata['slotlen']},'s').format(f);
    $('#{$id}until').val(d2);
   }
  }
 });

EOS;

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
$customcss = $funcs->GetStylesURL($this,$item_id);
if($customcss)
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
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

//for picker
$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/moment.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/pikaday.min.js"></script>
EOS;

if($jsloads)
{
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo Booker\Shared::ProcessTemplate($this,'find.tpl',$tplvars);
?>

