<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: default
# Default frontend action for the module
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

$funcs = new bkrshared();

if(!empty($params['item']))
{
	$item_id = $funcs->GetItemID($this,$params['item']);
	$params['item_id'] = $item_id;
}
elseif(!empty($params['item_id']))
	$item_id = (int)$params['item_id'];
else
	$item_id = FALSE;
if(isset($params['chooser']))
{
	if($params['chooser'] !== $item_id)
		$item_id = (int)$params['chooser'];
}

if($item_id == FALSE)
{
	$tplvars = array(
		'admin_nav' => '',
		'title_error' => $this->Lang('error'),
		'message' => $this->Lang('err_parm')
	);
	echo bkrshared::ProcessTemplate($this,'error.tpl',$tplvars);
	exit;
}
$is_group = ($item_id >= Booker::MINGRPID);

$publicperiods = $funcs->RangeNames($this,array(0,1,2,3));

if(isset($params['ranger'])) //first pref, so we can detect changes
	$range = $params['ranger'];
elseif(isset($params['range']))
	$range = $params['range'];
else
	$range = $funcs->GetDefaultRange($this,$item_id);

if(is_numeric($range))
{
	$range = (int)$range;
	if ($range < 0 || $range >= count($publicperiods))
		$range = $funcs->GetDefaultRange($this,$item_id);
}
elseif($range == '')
	$range = 0;
else //assume text
{
	$range = strtolower($params['range']);
	$t = array_search($range,$publicperiods);
	if($t !== FALSE)
		$range = $t;
	else
		$range = $funcs->GetDefaultRange($this,$item_id);
}

// get all data for the resource/group
$idata = $funcs->GetItemProperty($this,$item_id,'*');

if(isset($params['startat']))
{
	$dts = new DateTime('1900-1-1',new DateTimeZone('UTC'));
	$dts->setTimestamp($params['startat']);
	$dts->setTime(0,0,0);
}
else
{
	$dts = new DateTime('midnight',new DateTimeZone('UTC')); //start of today
}
$params['startat'] = $dts->getTimestamp();

$showtable = (empty($params['view']) || $params['view'] == 'table');
if(isset($params['toggle']))
	$showtable = !$showtable;

if(isset($params['altview']))
{
//	$this->Crash(); //should never happen if js is enabled
}

if(!empty($params['slide']))
{
	$arr = $funcs->DisplayIntervals(); //non-translated form
	$v = $arr[$range];
	$t = (int)$params['slide'];
	if(!($t == 1 || $t == -1))
		$v .= 's';
	$dts->modify($t.' '.$v);
	$params['startat'] = $dts->getTimestamp();
}
elseif(!empty($params['zoomin']))
{
	if ($range > 0)
		$range -= 1;
}
elseif(!empty($params['zoomout']))
{
	if ($range < count($publicperiods) - 1)
		$range += 1;
}

if(!empty($params['slotid']))
	$this->Redirect($id,'requestbooking',$returnid,array(
	 'item_id'=>$item_id,
	 'startat'=>$params['startat'],
	 'range'=>$range,
	 'view'=>$params['view'],
	 'slotid'=>$params['slotid']));

if(!empty($params['clickat']))
{
	$t = strip_tags(html_entity_decode(trim($params['clickat'])));
	$dts->modify($t);
	$st = $dts->getTimestamp();
	if(isset($params['zoomin'])
	|| isset($params['zoomout'])
	|| isset($params['toggle'])
	|| $params['ranger'] != $params['range']
	|| isset($params['chooser']) && $params['chooser'] != $params['item_id'])
	{
		$params['startat'] = $st;
	}
	elseif(isset($params['find']))
	{
		$this->Redirect($id,'findbooking',$returnid,array(
		 'item_id'=>$item_id,
		 'startat'=>$params['startat'],
		 'range'=>$range,
		 'view'=>$params['view'],
		 'bookat'=>$st));
	}
	else
	{
		//send parameters needed for resumption after submission
		$this->Redirect($id,'requestbooking',$returnid,array(
		 'item_id'=>$item_id,
		 'startat'=>$params['startat'],
		 'range'=>$range,
		 'view'=>$params['view'],
		 'bookat'=>$st));
	}
}
elseif(isset($params['request']))
{
	//process unspecified booking
	$this->Redirect($id,'requestbooking',$returnid,array(
	 'item_id'=>$item_id,
	 'startat'=>$params['startat'],
	 'range'=>$range,
	 'view'=>$params['view'],
	 'bookat'=>$params['startat']));
}
elseif(isset($params['find']))
{
	$this->Redirect($id,'findbooking',$returnid,array(
	 'item_id'=>$item_id,
	 'startat'=>$params['startat'],
	 'range'=>$range,
	 'view'=>$params['view']));
}

if(!empty($params['newlist']))
	$idata['listformat'] = $params['listformat'];

$tplvars = array();

if(isset($params['message']))
	$tplvars['message'] = $params['message'];
TODO array or string for hidden items?
$hidden = array();
$hidden[] = $this->CreateInputHidden($id,'view',($showtable)?'table':'list');
$hidden[] = $this->CreateInputHidden($id,'startat',$params['startat']);
$hidden[] = $this->CreateInputHidden($id,'range',$range);
$hidden[] = $this->CreateInputHidden($id,'item',$item_id);
if($showtable)
{
	$hidden[] = $this->CreateInputHidden($id,'clickat','');
	$hidden[] = $this->CreateInputHidden($id,'slotid','');
}
else
{
	$hidden[] = $this->CreateInputHidden($id,'newlist','');
}
$tplvars['hidden'] = $hidden;

$tplvars['startform'] = $this->CreateFormStart($id,'default',$returnid);
$tplvars['endform'] = $this->CreateFormEnd();

$css = $funcs->GetStylesURL($this,$item_id);
if($css)
	$tplvars['customstyle'] = $css;

if(!empty($idata['name']))
{
	if($is_group)
		$t = $this->Lang('title_booksfor',$this->Lang('group'),$idata['name']);
	else
		$t = $this->Lang('title_booksfor',$idata['name'],'');
}
else
{
	$type = ($is_group) ? $this->Lang('group'):$this->Lang('item');
	$t = $this->Lang('title_noname',$type,$idata['item_id']);
	$t = $this->Lang('title_booksfor',$t,'');
}

$s = $funcs->IntervalFormat($this,$dts,$idata['dateformat']);
switch($range)
{
	case Booker::RANGEDAY:
		$t .= ' '.$s;
		break;
	case Booker::RANGEWEEK:
	case Booker::RANGEMTH:
	case Booker::RANGEYR:
		list($dts,$dte) = $funcs->RangeStamps($params['startat'],$range);
		$dte->modify('-1 day');
		$e = $funcs->IntervalFormat($this,$dte,$idata['dateformat']);
		$t .= ' '.$this->Lang('showrange',$s,$e);
		break;
}
$tplvars['title'] = $t;
if(!empty($idata['description']))
	$tplvars['desc'] = bkrshared::ProcessTemplateFromData($this,$idata['description'],$tplvars);

$t = $funcs->GetImageURLs($this,$idata['image'],$idata['name']);
if($t)
	$tplvars['pictures'] = $t;

$jsfuncs = array(); //script accumulator
$jsloads = array(); //document-ready funcs
$jsincs = array(); //js includes
$baseurl = $this->GetModuleURLPath();

//buttons

//if-needed - pre-table buttons
//$tplvars['actions'] =  array('BTN1','BTN2','BTN3');
//2 post-table rows of action-buttons
$intrvl = $publicperiods[$range];
$mintrvl = $funcs->RangeNames($this,$range,TRUE); //plural variant

$actions1 = array();
$actions1[] = $this->CreateInputSubmit($id,'slide','+1','title="'.$this->Lang('tip_forw1',$intrvl).'"');
if($range == Booker::RANGEDAY)
	$actions1[] = $this->CreateInputSubmit($id,'slide','+7', //NB numeric label value is used by action-processor
		'title="'.$this->Lang('tip_forwN',7,$mintrvl).'"');
elseif($range == Booker::RANGEWEEK)
	$actions1[] = $this->CreateInputSubmit($id,'slide','+4',
		'title="'.$this->Lang('tip_forwN',4,$mintrvl).'"');
$xtra = ($range == Booker::RANGEDAY) ? ' disabled="disabled"' : '';
$actions1[] = $this->CreateInputSubmit($id,'zoomin',$this->Lang('zoomin'),
	'title="'.$this->Lang('tip_zoomin').'"'.$xtra);

$choices = $funcs->RangeNames($this,array(0,1,2,3),FALSE,TRUE); //capitalised
$actions1[] = $this->CreateInputDropdown($id,'ranger',array_flip($choices),-1,$range,'id="'.$id.'ranger"');

$jsloads[] = <<<EOS
 $('#{$id}ranger').change(function(){
  $(this).closest('form').trigger('submit');
 });

EOS;

if($showtable)
{
	$t = '<img src="'.$baseurl.'/images/information.png" alt="icon" border="0" /> '.
		$this->Lang('help_focus');
	$tplvars['focushelp'] = $t;
	$t = $this->Lang('list');
}
else
	$t = $this->Lang('table');

$choices = $funcs->GetItemFamily($this,$db,$item_id);
if($choices && count($choices) > 1)
{
	$chooser = TRUE;
	$actions1[] = $this->CreateInputDropdown($id,'chooser',array_flip($choices),-1,$item_id,'id="'.$id.'chooser"');

	$jsloads[] = <<<EOS
 $('#{$id}chooser').change(function(){
  $(this).closest('form').trigger('submit');
 });

EOS;
}
else
	$chooser = FALSE;

$actions1[] = $this->CreateInputSubmit($id,'toggle',$t,
	'title="'.$this->Lang('tip_otherview').'"');
$actions1[] = $this->CreateInputSubmit($id,'find',$this->Lang('find'),
	'title="'.$this->Lang('tip_find').'"');
$tplvars['actions1'] =  $actions1;

$actions2 = array();
$actions2[] = $this->CreateInputSubmit($id,'slide','-1',
 'title="'.$this->Lang('tip_back1',$intrvl).'"');
if($range == Booker::RANGEDAY)
	$actions2[] = $this->CreateInputSubmit($id,'slide','-7',
   'title="'.$this->Lang('tip_backN',7,$mintrvl).'"');
elseif($range == Booker::RANGEWEEK)
	$actions2[] = $this->CreateInputSubmit($id,'slide','-4',
		'title="'.$this->Lang('tip_backN',4,$mintrvl).'"');
$xtra = ($range == Booker::RANGEYR) ? ' disabled="disabled"' : '';
$actions2[] = $this->CreateInputSubmit($id,'zoomout', $this->Lang('zoomout'),
 'title="'.$this->Lang('tip_zoomout').'"'.$xtra);
$actions2[] = $this->CreateInputSubmit($id,'pick',$this->Lang('calendar'),
   'title="'.$this->Lang('tip_calendar').'"');
if($showtable)
	$actions2[] = '';
else
{
	if($is_group)
	{
		$choices = array(
		$this->Lang('start+user')=>Booker::LISTSU,
		$this->Lang('start+resource')=>Booker::LISTSR,
		$this->Lang('resource+start')=>Booker::LISTRS,
		$this->Lang('user+start')=>Booker::LISTUS
		);
	}
	else
	{
		$choices = array(
		$this->Lang('start+user')=>Booker::LISTSU,
		$this->Lang('user+start')=>Booker::LISTUS
		);
	}
	$actions2[] = $this->CreateInputDropdown($id,'listformat',$choices,-1,$idata['listformat'],
		'id="'.$id.'listformat" title="'.$this->Lang('tip_listtype').'"');
	$jsloads[] =<<<EOS
 $('#{$id}listformat').change(function(){
	$('#{$id}newlist').val(1);
  $(this).closest('form').trigger('submit');
 });

EOS;
}

if($chooser)
	$actions2[] = ''; //alignment padding
$actions2[] = $this->CreateInputSubmit($id,'request',$this->Lang('book'),
	'title="'.$this->Lang('tip_book').'"');

$tplvars['actions2'] =  $actions2;

$funcs2 = new bkrdisplay($this);
if($showtable)
{
	//bookings-data table
	$funcs2->Tabulate($tplvars,$idata,$params['startat'],$range);
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.StickyTable.min.js"></script>

EOS;
}
else
{
	//bookings-data text
	$funcs2->Listify($tplvars,$idata,$params['startat'],$range);
}

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
$overday = ($funcs->GetInterval($this,$item_id,'slot') >= 84600);
$momentfmt = ($overday) ? 'YYYY-M-D':'YYYY-M-D h:mm';

$jsloads[] = <<<EOS
 var ob = document.getElementById('{$id}pick');
 new Pikaday({
  field: document.getElementById('calendar'),
  trigger: ob,
  format: 'YYYY-M-D',
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
    $('#{$id}startat').val(d2);
    $(this._o.trigger).closest('form').trigger('submit');
   }
  }
 });
 $(ob).click(function(evt){
   evt.preventDefault();
   return false;
 });

EOS;

if($showtable)
{
	//CHECKME PURPOSE booking-table th click() handler
	$jsfuncs[] = <<<EOS
function slot_record(el) {
 var indx = $(el).index();
 var table = $('table.booker')[0];
 var content = table.rows[0].cells[indx].innerHTML;
 indx = $(el).parent().index();
 content += ' ' + table.rows[indx+1].cells[0].innerHTML;
 $('#{$id}clickat').val(content);
}
function col_focus() {
 var indx = $(this).index();
 var table = $('table.booker')[0];
 var content = table.rows[0].cells[indx].innerHTML;
 content += ' ' + table.rows[1].cells[0].innerHTML;
 $('#{$id}clickat').val(content);
}

EOS;
	$jsfuncs[] = <<<EOS
function slot_focus() {
 slot_record(this);
}

EOS;
	$jsfuncs[] = <<<EOS
function slot_activate() {
 var indx = $(this).index();
 if(indx === 0) //labels col
 	return;
 slot_record(this);
 var slot = $(this).attr('id');
 if(typeof slot != 'undefined')
  $('#{$id}slotid').val(slot);
 $('#{$id}request').click();
}

EOS;
 	$jsloads[] = <<<EOS
 $('table.booker th.periodname').click(col_focus);
 $('table.booker td').click(slot_focus).dblclick(slot_activate);

EOS;
/* TODO */
	//this must be the last on-load func! dunno why it's bad ...
	$jsloads[] = <<<EOS
 $('#scroller').StickyTable({
  stickLeft: true
 });

EOS;
} //end showtable

$jsfuncs[] = '$(document).ready(function() {
';
$jsfuncs = array_merge($jsfuncs,$jsloads);
$jsfuncs[] = '})
;

$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;
$tplvars['baseurl'] = $baseurl;

if($showtable)
{
$tplvars['jsstyler'] = TODO
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
{if isset($customstyle)}<link rel="stylesheet" type="text/css" href="{$customstyle}" />{/if}
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/stickytable.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />

	echo bkrshared::ProcessTemplate($this,'defaulttable.tpl',$tplvars);
{
else
{
$tplvars['jsstyler'] = TODO
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
{if isset($customstyle)}<link rel="stylesheet" type="text/css" href="{$customstyle}" />{/if}
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />

	echo bkrshared::ProcessTemplate($this,'defaultlist.tpl',$tplvars);
}

?>
