<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: default
# Default frontend action for the module
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/*
//DEBUG tests
//$funcs = new Booker\RepeatTester($this);
$funcs = new Booker\CacheTester($this);
$funcs->Run();
*/

//parameter keys used locally, but not to be cached before departure
$localparams = array(
	'cart',
	'clickat',
	'item',
	'ranger',
	'slide',
	'slotid',
	'toggle',
	'zoomin',
	'zoomout'
);

$cache = Booker\Cache::GetCache($this);
$utils = new Booker\Utils();
$t = (isset($params['toggle'])) ? 'view' : FALSE; //don't want this one to be lost during restore
$cart = $utils->RetrieveParameters($cache,$params,$t);

if (!empty($params['item'])) {
	$item_id = $utils->GetItemID($this,$params['item']);
	$params['item_id'] = $item_id;
} elseif (!empty($params['item_id']))
	$item_id = (int)$params['item_id'];
else
	$item_id = FALSE;
if (isset($params['chooser'])) {
	if ($params['chooser'] !== $item_id)
		$item_id = (int)$params['chooser'];
}

if ($item_id == FALSE) {
	$tplvars = array(
		'admin_nav' => '',
		'title_error' => $this->Lang('error'),
		'message' => $this->Lang('err_parm')
	);
$this->Crash();
	echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
	exit;
}
$is_group = ($item_id >= Booker::MINGRPID);

$publicperiods = $utils->RangeNames($this,array(0,1,2,3));

//TODO customise $cart properties
//$cart->setContext($context);
//$cart->setPricesWithTax($pricesWithTax);

if (isset($params['ranger'])) //first pref, so we can detect changes
	$range = $params['ranger'];
elseif (isset($params['range']))
	$range = $params['range'];
else
	$range = $utils->GetDefaultRange($this,$item_id);

if (is_numeric($range)) {
	$range = (int)$range;
	if ($range < 0 || $range >= count($publicperiods))
		$range = $utils->GetDefaultRange($this,$item_id);
} elseif ($range == '')
	$range = 0;
else { //assume text
	$range = strtolower($params['range']); //english-only, no need for mb_convert_case()
	$t = array_search($range,$publicperiods);
	if ($t !== FALSE)
		$range = $t;
	else
		$range = $utils->GetDefaultRange($this,$item_id);
}

// get all data for the resource/group
$idata = $utils->GetItemProperty($this,$item_id,'*');

if (isset($params['startat'])) {
	$dts = new DateTime('1900-1-1',new DateTimeZone('UTC'));
	if (is_numeric($params['startat']))
		$dts->setTimestamp($params['startat']);
	elseif (strtotime($params['startat']))
		$dts->modify($params['startat']);
	else {
		$dts->setTimestamp(time());
		$params['message'] = $this->Lang('err_system').' '.$params['startat'];
	}
	$dts->setTime(0,0,0);
} else {
	$dts = new DateTime('midnight',new DateTimeZone('UTC')); //start of today
}
$params['startat'] = $dts->getTimestamp();

$showtable = (empty($params['view']) || $params['view'] == 'table');
if (isset($params['toggle']))
	$showtable = !$showtable;

if (isset($params['altview'])) {
//	$this->Crash(); //should never happen if js is enabled
}

if (!empty($params['slide'])) {
	$arr = $utils->DisplayIntervals(); //non-translated form
	$v = $arr[$range];
	$t = (int)$params['slide'];
	if (!($t == 1 || $t == -1))
		$v .= 's';
	$dts->modify($t.' '.$v);
	$params['startat'] = $dts->getTimestamp();
} elseif (!empty($params['zoomin'])) {
	if ($range > 0)
		$range -= 1;
} elseif (!empty($params['zoomout'])) {
	if ($range < count($publicperiods) - 1)
		$range += 1;
}

if (!empty($params['slotid'])) {
	$params['item_id'] = $item_id;
	$params['range'] = $range;
	$utils->SaveParameters($cache,$params,$localparams,$cart);
	$this->Redirect($id,'requestbooking',$returnid,array(
	 'storedparams'=>$params['storedparams']
	 ));
}

if (!empty($params['cart'])) {
	$params['item_id'] = $item_id;
	$params['range'] = $range;
	$utils->SaveParameters($cache,$params,$localparams,$cart);
	$this->Redirect($id,'opencart',$returnid,array(
	 'storedparams'=>$params['storedparams']
	 ));
}

if (!empty($params['clickat'])) {
	$t = strip_tags(html_entity_decode(trim($params['clickat'])));
	$dts->modify($t);
	$st = $dts->getTimestamp();
	if (isset($params['zoomin'])
	|| isset($params['zoomout'])
	|| isset($params['toggle'])
	|| $params['ranger'] != $params['range']
	|| isset($params['chooser']) && $params['chooser'] != $params['item_id']) {
		$params['startat'] = $st;
	} elseif (isset($params['find'])) {
		$params['item_id'] = $item_id;
		$params['range'] = $range;
		$utils->SaveParameters($cache,$params,$localparams,$cart);
		$this->Redirect($id,'findbooking',$returnid,array(
		 'bookat'=>$st,
		 'storedparams'=>$params['storedparams']
		 ));
	} else {
		//send parameters needed for resumption after submission
		$params['item_id'] = $item_id;
		$params['range'] = $range;
		$utils->SaveParameters($cache,$params,$localparams,$cart);
		$this->Redirect($id,'requestbooking',$returnid,array(
		 'bookat'=>$st,
		 'storedparams'=>$params['storedparams']
		));
	}
} elseif (isset($params['request'])) {
	//process unspecified booking
	$params['item_id'] = $item_id;
	$params['range'] = $range;
	$utils->SaveParameters($cache,$params,$localparams,$cart);
	$this->Redirect($id,'requestbooking',$returnid,array(
	 'bookat'=>$params['startat'],
	 'storedparams'=>$params['storedparams']
	));
} elseif (isset($params['find'])) {
	$params['item_id'] = $item_id;
	$params['range'] = $range;
	$utils->SaveParameters($cache,$params,$localparams,$cart);
	$this->Redirect($id,'findbooking',$returnid,array(
	 'storedparams'=>$params['storedparams']
	));
}

if (!empty($params['newlist']))
	$idata['listformat'] = $params['listformat'];

$tplvars = array();

if (isset($params['message']))
	$tplvars['message'] = $params['message'];

$utils->SaveParameters($cache,$params,$localparams,$cart);
$hidden = array();
$hidden[] = $this->CreateInputHidden($id,'view',($showtable)?'table':'list');
$hidden[] = $this->CreateInputHidden($id,'startat',$params['startat']);
$hidden[] = $this->CreateInputHidden($id,'range',$range);
$hidden[] = $this->CreateInputHidden($id,'item',$item_id);
if ($showtable) {
	$hidden[] = $this->CreateInputHidden($id,'clickat','');
	$hidden[] = $this->CreateInputHidden($id,'slotid','');
} else {
	$hidden[] = $this->CreateInputHidden($id,'newlist','');
}
$hidden[] = $this->CreateInputHidden($id,'storedparams',$params['storedparams']);
$tplvars['hidden'] = $hidden;

$tplvars['startform'] = $this->CreateFormStart($id,'default',$returnid);
$tplvars['endform'] = $this->CreateFormEnd();

if (!empty($idata['name'])) {
	if ($is_group)
		$t = $this->Lang('title_booksfor',$this->Lang('group'),$idata['name']);
	else
		$t = $this->Lang('title_booksfor',$idata['name'],'');
} else {
	$type = ($is_group) ? $this->Lang('group'):$this->Lang('item');
	$t = $this->Lang('title_noname',$type,$idata['item_id']);
	$t = $this->Lang('title_booksfor',$t,'');
}

$s = $utils->IntervalFormat($this,$dts,$idata['dateformat']);
switch ($range) {
 case Booker::RANGEDAY:
	$t .= ' '.$s;
	break;
 case Booker::RANGEWEEK:
 case Booker::RANGEMTH:
 case Booker::RANGEYR:
	list($dts,$dte) = $utils->RangeStamps($params['startat'],$range);
	$dte->modify('-1 day');
	$e = $utils->IntervalFormat($this,$dte,$idata['dateformat']);
	$t .= ' '.$this->Lang('showrange',$s,$e);
	break;
}
$tplvars['title'] = $t;
if (!empty($idata['description']))
	$tplvars['desc'] = Booker\Utils::ProcessTemplateFromData($this,$idata['description'],$tplvars);

$t = $utils->GetImageURLs($this,$idata['image'],$idata['name']);
if ($t)
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
$mintrvl = $utils->RangeNames($this,$range,TRUE); //plural variant

$actions1 = array();
$actions1[] = $this->CreateInputSubmit($id,'slide','+1','title="'.$this->Lang('tip_forw1',$intrvl).'"');
if ($range == Booker::RANGEDAY)
	$actions1[] = $this->CreateInputSubmit($id,'slide','+7', //NB numeric label value is used by action-processor
		'title="'.$this->Lang('tip_forwN',7,$mintrvl).'"');
elseif ($range == Booker::RANGEWEEK)
	$actions1[] = $this->CreateInputSubmit($id,'slide','+4',
		'title="'.$this->Lang('tip_forwN',4,$mintrvl).'"');
$xtra = ($range == Booker::RANGEDAY) ? ' disabled="disabled"' : '';
$actions1[] = $this->CreateInputSubmit($id,'zoomin',$this->Lang('zoomin'),
	'title="'.$this->Lang('tip_zoomin').'"'.$xtra);

$choices = $utils->RangeNames($this,array(0,1,2,3),FALSE,TRUE); //capitalised
$actions1[] = $this->CreateInputDropdown($id,'ranger',array_flip($choices),-1,$range,'id="'.$id.'ranger"');

$jsloads[] = <<<EOS
 $('#{$id}ranger').change(function() {
  $(this).closest('form').trigger('submit');
 });

EOS;

if ($showtable) {
	$t = '<img src="'.$baseurl.'/images/information.png" alt="icon" border="0" /> '.
		$this->Lang('help_focus');
	$tplvars['focushelp'] = $t;
	$t = $this->Lang('list');
} else
	$t = $this->Lang('table');

$choices = $utils->GetItemFamily($this,$db,$item_id);
if ($choices && count($choices) > 1) {
	$chooser = TRUE;
	asort($choices,SORT_NATURAL);
	$actions1[] = $this->CreateInputDropdown($id,'chooser',array_flip($choices),-1,$item_id,'id="'.$id.'chooser"');

	$jsloads[] = <<<EOS
 $('#{$id}chooser').change(function() {
  $(this).closest('form').trigger('submit');
 });

EOS;
} else
	$chooser = FALSE;

$actions1[] = $this->CreateInputSubmit($id,'toggle',$t,
	'title="'.$this->Lang('tip_otherview').'"');

$xtra = ($cart->seemsEmpty()) ?
	'disabled="disabled" title="'.$this->Lang('tip_cartempty').'"':
	'title="'.$this->Lang('tip_cartshow').'"';
$actions1[] = $this->CreateInputSubmit($id,'cart',$this->Lang('cart'),$xtra);
$tplvars['actions1'] = $actions1;

$actions2 = array();
$actions2[] = $this->CreateInputSubmit($id,'slide','-1',
 'title="'.$this->Lang('tip_back1',$intrvl).'"');
if ($range == Booker::RANGEDAY)
	$actions2[] = $this->CreateInputSubmit($id,'slide','-7',
   'title="'.$this->Lang('tip_backN',7,$mintrvl).'"');
elseif ($range == Booker::RANGEWEEK)
	$actions2[] = $this->CreateInputSubmit($id,'slide','-4',
		'title="'.$this->Lang('tip_backN',4,$mintrvl).'"');
$xtra = ($range == Booker::RANGEYR) ? ' disabled="disabled"' : '';
$actions2[] = $this->CreateInputSubmit($id,'zoomout', $this->Lang('zoomout'),
 'title="'.$this->Lang('tip_zoomout').'"'.$xtra);
$actions2[] = $this->CreateInputSubmit($id,'pick',$this->Lang('calendar'),
   'title="'.$this->Lang('tip_calendar').'"');
if ($showtable)
	$actions2[] = '';
else {
	if ($is_group) 	{
		$choices = array(
		$this->Lang('start+user')=>Booker::LISTSU,
		$this->Lang('start+resource')=>Booker::LISTSR,
		$this->Lang('resource+start')=>Booker::LISTRS,
		$this->Lang('user+start')=>Booker::LISTUS
		);
	} else {
		$choices = array(
		$this->Lang('start+user')=>Booker::LISTSU,
		$this->Lang('user+start')=>Booker::LISTUS
		);
	}
	$actions2[] = $this->CreateInputDropdown($id,'listformat',$choices,-1,$idata['listformat'],
		'id="'.$id.'listformat" title="'.$this->Lang('tip_listtype').'"');
	$jsloads[] =<<<EOS
 $('#{$id}listformat').change(function() {
	$('#{$id}newlist').val(1);
  $(this).closest('form').trigger('submit');
 });

EOS;
}

//if ($chooser)
//	$actions2[] = ''; //alignment padding
$actions2[] = $this->CreateInputSubmit($id,'find',$this->Lang('find'),
	'title="'.$this->Lang('tip_find').'"');
$actions2[] = $this->CreateInputSubmit($id,'request',$this->Lang('book'),
	'title="'.$this->Lang('tip_book').'"');

$tplvars['actions2'] = $actions2;

$funcs2 = new Booker\Display($this);
if ($showtable) {
	//bookings-data table
	$funcs2->Tabulate($tplvars,$idata,$params['startat'],$range);
if (1)
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.stickyscroll.js"></script>
EOS;
//<script type="text/javascript" src="{$baseurl}/include/responsive-tables.js"></script>
	else //DEBUG
		$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery-ui.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/table-scroll.js"></script>
EOS;
} else {
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
$overday = ($utils->GetInterval($this,$item_id,'slot') >= 84600);
$momentfmt = ($overday) ? 'YYYY-MM-DD':'YYYY-MM-DD h:mm';

$jsloads[] = <<<EOS
 var ob = document.getElementById('{$id}pick');
 new Pikaday({
  field: document.getElementById('calendar'),
  trigger: ob,
  format: 'YYYY-MM-DD',
  i18n: {
   previousMonth: '{$prevm}',
   nextMonth: '{$nextm}',
   months: [{$mnames}],
   weekdays: [{$dnames}],
   weekdaysShort: [{$sdnames}]
  },
  onClose: function() {
   var sel = $('#calendar').val();
   if (sel !== '') { //not cancelled
    var d = new Date(sel);
    var f = '{$momentfmt}';
    var d2 = moment(d).format(f);
    $('#{$id}startat').val(d2);
    $(this._o.trigger).closest('form').trigger('submit');
   }
  }
 });
 $(ob).click(function(evt) {
   evt.preventDefault();
   return false;
 });

EOS;

if ($showtable) {
	$tplname = 'defaulttable.tpl';

	$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
//<link rel="stylesheet" type="text/css" href="{$baseurl}/responsive-tables.css" />
//<link rel="stylesheet" type="text/css" href="{$baseurl}/css/stickytable.css" />
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
 if (indx === 0) //labels col
 	return;
 slot_record(this);
 var slot = $(this).attr('id');
 if (typeof slot != 'undefined')
  $('#{$id}slotid').val(slot);
 $('#{$id}request').click();
}

EOS;
 	$jsloads[] = <<<EOS
 $('table.booker th.periodname').click(col_focus);
 $('table.booker td').click(slot_focus).dblclick(slot_activate);

EOS;
/* TODO */
if (1)
	$jsloads[] = <<<EOS
 $('#scroller').stickyscroll({
  fixedColumnsLeft: 1
 });

EOS;
else //DEBUG
	$jsloads[] = <<<EOS
 $('#scroller').table_scroll({
  fixedColumnsLeft: 1
 });

EOS;
} else { //list view
	$tplname = 'defaultlist.tpl';
	$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
}
$customcss = $utils->GetStylesURL($this,$item_id);
if ($customcss)
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
EOS;
//porting heredoc-var newlines is a problem for qouted strings! workaround ...
$stylers = str_replace("\n",'',$stylers);
$tplvars['jsstyler'] = <<<EOS
var linkadd = '{$stylers}',
 \$head = $('head'),
 \$linklast = \$head.find("link[rel='stylesheet']:last");
if (\$linklast.length) {
 \$linklast.after(linkadd);
} else {
 \$head.append(linkadd);
}
EOS;

$jsfuncs[] = '$(document).ready(function() {
';
$jsfuncs = array_merge($jsfuncs,$jsloads);
$jsfuncs[] = '});
';

$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,$tplname,$tplvars);
