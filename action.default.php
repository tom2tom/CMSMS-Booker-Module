<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: default
# Default frontend action for the module
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
//parameter keys filtered out before redirect etc
$localparams = array(
	'action',
	'bkgid',
	'cart',
	'clickat',
	'find',
	'item',
	'listformat',
	'message',
	'module',
	'newlist',
	'pick',
	'rangepick',
	'request',
	'slide',
	'toggle',
//	'view',
	'zoomin',
	'zoomout'
);

$utils = new Booker\Utils();
//$cache = Booker\Cache::GetCache($this);
//params to scrub from the cache, if any, before merging that with current $params
$scrubs = array('booker_id','fee');
if (!empty($params['showfrom'])) //keep current value of this
	$scrubs[] = 'showfrom';
if (!empty($params['itempick'])) //ditto
	$scrubs[] = 'itempick';
if (!empty($params['newlist']))
	$scrubs[] = 'newlist';
$utils->UnFilterParameters($params,$scrubs);

if (!empty($params['itempick'])) {
	$item_id = (int)$params['itempick'];
} elseif (!empty($params['item'])) {
	$item_id = $utils->GetItemID($this,$params['item']);
} elseif (!empty($params['item_id'])) {
	$item_id = (int)$params['item_id'];
} else {
	$item_id = FALSE;
}
if (!$item_id) {
	$tplvars = array(
		'title_error' => $this->Lang('error'),
		'message' => $this->Lang('err_parm'),
		'pagenav' => NULL
	);
	echo Booker\Utils::ProcessTemplate($this,'error.tpl',$tplvars);
	return;
}

$params['item_id'] = $item_id;

// get all data for the resource/group
$idata = $utils->GetItemProperty($this,$item_id,'*');
// get/setup cart for bookings
$cache = Booker\Cache::GetCache($this);

$cart = $utils->RetrieveCart($cache,$params,'',$idata['grossfees']); //TODO item-specific context
$is_group = ($item_id >= Booker::MINGRPID);

if (!isset($params['firstpick'])) {
	$params['firstpick'] = ($is_group) ? $item_id : FALSE;
}

$dtw = new DateTime('@0',NULL);
if (!empty($params['clickat'])) { //table-cell clicked
	$dtw->modify($params['clickat']);
} elseif (isset($params['showfrom'])) {
	if (is_numeric($params['showfrom']))
		$dtw->setTimestamp($params['showfrom']);
	elseif (strtotime($params['showfrom']))
		$dtw->modify($params['showfrom']);
	else {
		$st = $utils->GetZoneTime($idata['timezone']);
		$dtw->setTimestamp($st);
		$params['message'] = $this->Lang('err_system').' '.$params['showfrom'];
	}
	$dtw->setTime(0,0,0);
} else {
	$st = $utils->GetZoneTime($idata['timezone']);
	$dtw->setTimestamp($st);
}
$dtw->modify('midnight'); //start of day
$params['showfrom'] = $dtw->getTimestamp();

$params['resume'] = array('default'); //redirects can [eventually] get back to here

$publicperiods = $utils->RangeNames($this,array(0,1,2,3));

if (isset($params['rangepick'])) //first pref, so we can detect changes
	$range = $params['rangepick'];
elseif (isset($params['range']))
	$range = $params['range'];
else
	$range = $utils->GetDefaultRange($this,$item_id);

if (is_numeric($range)) {
	$range = (int)$range;
	if ($range < 0 || $range >= count($publicperiods))
		$range = $utils->GetDefaultRange($this,$item_id);
} elseif ($range == '') {
	$range = Booker::RANGEDAY;
} else { //assume text
	$range = strtolower($params['range']); //english-only, no need for mb_convert_case()
	$t = array_search($range,$publicperiods);
	if ($t !== FALSE)
		$range = $t;
	else
		$range = $utils->GetDefaultRange($this,$item_id);
}
$params['range'] = $range;

if (isset($params['request'])) { //'book' button clicked or cell double-clicked
	$newparms = $utils->FilterParameters($params,$localparams);
	if (!empty($params['bkgid'])) { //target is already booked
		$newparms['bkr_bkgid'] = $params['bkgid']; //normally excluded, but this time we want it
	}
	if (!empty($params['clickat'])) {
		$dtw->modify($params['clickat']);
		$st = $dtw->getTimestamp();
		if ($range == Booker::RANGEYR) {
			//set nowish start-time on the selected day
			$t = $utils->GetZoneTime($idata['timezone']);
			$dtw->setTimestamp($t);
			$t = (int)$dtw->format('G');
			$slen = $utils->GetInterval($this,$item_id,'slot');
			$st += (int)($t*3600/$slen)*$slen + 3600;
		}
	} else { //no slot was selected
		//set nowish start-time as fallback
		$st = $utils->GetZoneTime($idata['timezone']);
		$slen = $utils->GetInterval($this,$item_id,'slot');
		$st = (int)($st/$slen) * $slen + $slen + 3600;
	}
	$newparms['bkr_bookat'] = $st;
	$this->Redirect($id,'requestbooking',$returnid,$newparms);
} elseif (isset($params['find'])) {
	if (!empty($params['clickat'])) {
		$dtw->modify($params['clickat']);
		$params['bookat'] = $dtw->getTimestamp();
	}
	$newparms = $utils->FilterParameters($params,$localparams);
	$this->Redirect($id,'findbooking',$returnid,$newparms);
} elseif (isset($params['cart'])) {
	$params['task'] = 'see'; //facilitate buttons-creation
	$newparms = $utils->FilterParameters($params,$localparams);
	$this->Redirect($id,'opencart',$returnid,$newparms);
}

//show bookings-data as table?
$showtable = (empty($params['view']) || $params['view'] == 'table');
if (isset($params['toggle']))
	$showtable = !$showtable;
if (isset($params['altview'])) { //should never happen if js is enabled
//	$this->Crash();
}
$params['view'] = ($showtable)?'table':'list';

if (!empty($params['slide'])) {
	$arr = $utils->DisplayIntervals(); //non-translated form
	$v = $arr[$range];
	$t = (int)$params['slide'];
	if (!($t == 1 || $t == -1))
		$v .= 's';
	$dtw->modify($t.' '.$v);
	$params['showfrom'] = $dtw->getTimestamp();
} elseif (!empty($params['zoomin'])) {
	if ($range > 0)
		$range -= 1;
} elseif (!empty($params['zoomout'])) {
	if ($range < count($publicperiods) - 1)
		$range += 1;
}

if (!empty($params['newlist']))
	$idata['listformat'] = $params['listformat'];

$jsfuncs = array(); //script accumulator
$jsloads = array(); //document-ready funcs
$jsincs = array(); //js includes
$baseurl = $this->GetModuleURLPath();

$jsloads[] = <<<EOS
 $('#needjs').css('display','none');
EOS;

$tplvars = array('needjs'=>$this->Lang('needjs'));

if (isset($params['message']))
	$tplvars['message'] = $params['message'];

$hidden = $utils->FilterParameters($params,$localparams);
$tplvars['startform'] = $this->CreateFormStart($id,'default',$returnid,'POST','','','',$hidden);
$tplvars['endform'] = $this->CreateFormEnd();

$hidden = array();
$names = array('showfrom');
if ($showtable) {
	$names[] = 'clickat';
	$names[] = 'bkgid';
} else {
	$names[] = 'newlist';
}
foreach ($names as $one) {
	$hidden[] = $this->CreateInputHidden($id,$one,'');
}
$tplvars['hidden'] = $hidden;

if (!empty($idata['name'])) {
	$t = $this->Lang('title_booksfor',$idata['name'],'');
} else {
	$typename = ($is_group) ? $this->Lang('group'):$this->Lang('item');
	$t = $this->Lang('title_noname',$typename,$item_id);
	$t = $this->Lang('title_booksfor',$t,'');
}

switch ($range) {
 case Booker::RANGEDAY:
 	$t .= ' '.$utils->IntervalFormat($this,$dtw,$idata['dateformat'],TRUE);
	break;
 case Booker::RANGEWEEK:
 case Booker::RANGEMTH:
 case Booker::RANGEYR:
	list($dtw,$dte) = $utils->GetRangeLimits($params['showfrom'],$range);
	$s = $dtw->format('Y');
	$dte->modify('-1 day');
	$withyr = ($dte->format('Y') != $s);
	$s = $utils->IntervalFormat($this,$dtw,$idata['dateformat'],$withyr);
	$e = $utils->IntervalFormat($this,$dte,$idata['dateformat'],TRUE);
	$t .= ' '.$this->Lang('showrange',$s,$e);
	break;
}
$tplvars['title'] = $t;
if (!empty($idata['description']))
	$tplvars['desc'] = Booker\Utils::ProcessTemplateFromData($this,$idata['description'],$tplvars);

$t = $utils->GetImageURLs($this,$idata['image'],$idata['name']);
if ($t)
	$tplvars['pictures'] = $t;

//buttons

//if-needed - pre-table buttons
//$tplvars['actions'] =  array('BTN1','BTN2','BTN3');
//2 post-table rows of action-buttons
$intrvl = $publicperiods[$range];
$mintrvl = $utils->RangeNames($this,$range,TRUE); //plural variant

$tplvars['actionstitle'] = $this->Lang('title_display');
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
$actions1[] = $this->CreateInputDropdown($id,'rangepick',array_flip($choices),-1,$range,'id="'.$id.'rangepick"');

$jsloads[] = <<<EOS
 $('#{$id}rangepick').change(function() {
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

$chooser = $utils->GetItemPicker($this,$id,'itempick',$params['firstpick'],$item_id);
if ($chooser) {
	$actions1[] = $chooser;
	$jsloads[] = <<<EOS
 $('#{$id}itempick').change(function() {
  $(this).closest('form').trigger('submit');
 });
EOS;
}

$actions1[] = $this->CreateInputSubmit($id,'toggle',$t,
	'title="'.$this->Lang('tip_otherview').'"');
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
		$this->Lang('resource+start')=>Booker::LISTRS,
		$this->Lang('user+resource')=>Booker::LISTUR,
		$this->Lang('user+start')=>Booker::LISTUS
		);
	} else {
		$choices = array(
		$this->Lang('start+user')=>Booker::LISTSU,
		$this->Lang('user+resource')=>Booker::LISTUR,
		$this->Lang('user+start')=>Booker::LISTUS
		);
	}
	$actions2[] = $this->CreateInputDropdown($id,'listformat',$choices,-1,$idata['listformat'],
		'id="'.$id.'listformat" title="'.$this->Lang('tip_listtype').'"');
	$jsloads[] = <<<EOS
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
$tplvars['actions2'] = $actions2;

$tplvars['book'] = $this->CreateInputSubmit($id,'request',$this->Lang('book'),
	'title="'.$this->Lang('tip_book').'"');
$xtra = ($cart->seemsEmpty()) ?
	'disabled="disabled" title="'.$this->Lang('tip_cartempty').'"':
	'title="'.$this->Lang('tip_cartshow').'"';
$tplvars['cart'] = $this->CreateInputSubmit($id,'cart',$this->Lang('cart'),$xtra);

$funcs2 = new Booker\Display($this);
if ($showtable) {
	$funcs2->Tabulate($tplvars,$idata,$params['showfrom'],$range);
	$tplname = 'defaulttable.tpl';
} else { //bookings-data text
	$funcs2->Listify($tplvars,$idata,$params['showfrom'],$range);
	$tplname = 'defaultlist.tpl';
}

if ($showtable) {
//CHECKME PURPOSE booking-table th click() handler
	$jsfuncs[] = <<<EOS
function slot_activate() {
 var idx = $(this).index();
 if (idx === 0) //labels col
  return;
 slot_record(this);
 var bkid = $(this).attr('id');
 if (typeof bkid != 'undefined')
  $('#{$id}bgkid').val(bkid);
 $('#{$id}request').click();
}
function slot_record(el) {
 var idx = $(el).index(),
  table = $('#scroller')[0],
  dt = table.rows[0].cells[idx].getAttribute('iso');
 idx = $(el).parent().index();
 dt += table.rows[idx+1].cells[0].getAttribute('iso');
 $('#{$id}clickat').val(dt);
}
var focus = null;
function slot_focus() {
 if (focus != null) {
  $(focus).removeClass('slotfocus');
 }
 focus = this;
 $(this).addClass('slotfocus');
 slot_record(this);
 var btn = $('#{$id}request');
 btn.addClass('btnfocus');
 setTimeout(function() {
  btn.removeClass('btnfocus');
  btn[0].focus();
 },4000);
}
function col_focus() {
 var idx = $(this).index(),
  table = $('#scroller')[0],
  dt = table.rows[0].cells[idx].getAttribute('iso');
 dt += table.rows[1].cells[0].getAttribute('iso');
 $('#{$id}clickat').val(dt);
}
EOS;
	$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/tableHeadFixer.min.js"></script>
EOS;
 	$jsloads[] = <<<EOS
 var \$table = $('#scroller');
 \$table.find('th.periodname').click(col_focus);
 \$table.find('td').click(slot_focus).dblclick(slot_activate);
 \$table.tableHeadFixer({'left':1});
EOS;
}

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/pikaday.jquery.min.js"></script>
<script type="text/javascript" src="{$baseurl}/lib/js/php-date-formatter.min.js"></script>
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

$jsloads[] = <<<EOS
 $('#{$id}pick').click(function(ev) {
   ev.preventDefault();
   return false;
 }).pikaday({
  field: document.getElementById('{$id}showfrom'),
  i18n: {
   previousMonth: '$prevm',
   nextMonth: '$nextm',
   months: [$mnames],
   weekdays: [$dnames],
   weekdaysShort: [$sdnames]
  },
  onClose: function() {
   if('_d' in this && this._d) {
    var fmt = new DateFormatter();
    var dt = fmt.formatDate(this._d,'Y-m-d');
    $('#{$id}showfrom').val(dt);
    $(this._o.trigger).closest('form').trigger('submit');
   }
  }
 });
EOS;

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;

$customcss = $utils->GetStylesURL($this,$item_id);
if ($customcss)
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
EOS;
//heredoc-var newlines are a problem for in-js quoted strings, so ...
$stylers = preg_replace('/[\\n\\r]+/','',$stylers);
$t = <<<EOS
var linkadd = '$stylers',
 \$head = $('head'),
 \$linklast = \$head.find("link[rel='stylesheet']:last");
if (\$linklast.length) {
 \$linklast.after(linkadd);
} else {
 \$head.append(linkadd);
}
EOS;
echo $utils->MergeJS(FALSE,array($t),FALSE);

$jsall = $utils->MergeJS($jsincs,$jsfuncs,$jsloads);
unset($jsincs);
unset($jsfuncs);
unset($jsloads);

echo Booker\Utils::ProcessTemplate($this,$tplname,$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
