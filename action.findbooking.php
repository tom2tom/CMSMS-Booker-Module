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


//parameter keys used locally, but not to be cached before departure
$localparams = array(
	'cancel',
	'find', //not set here, but don't return anyway
	'findchooser',
	'findfirst',
	'findlast',
	'finduser',
	'findusertype',
	'search',
	'submit'
);

$cache = Booker\Cache::GetCache($this);
$utils = new Booker\Utils();
//$cart =
$utils->RetrieveParameters($cache,$params); //TODO parameters (context etc) for construction new cart-object

if (isset($params['cancel'])) { //user cancelled
	if (!(is_numeric($params['startat']) || strtotime($params['startat']))) {
		$params['message'] = $this->Lang('err_system').' '.$params['startat'];
		$params['startat'] = (int)(time()/86400);
	} elseif (!isset($params['message']))
		$params['message'] = '';
	$utils->SaveParameters($cache,array_diff_key($params,array_flip($localparams)),NULL);
	$this->Redirect($id,$params['action'],$returnid,
		array('storedparams'=>$params['storedparams']));
}

if (isset($params['submit'])) {
	//TODO what params to send back?
	$utils->SaveParameters($cache,array_diff_key($params,array_flip($localparams)),NULL);
	$this->Redirect($id,$params['action'],$returnid,
		array('storedparams'=>$params['storedparams']));
}

if (isset($params['item_id'])) {
	$item_id = (int)$params['item_id'];
	$is_group = ($item_id >= Booker::MINGRPID);
	$idata = $utils->GetItemProperty($this,$item_id,'*');
} else {
//TODO support any item
//	$idata = TODO
	$this->Crash();
}

//script accumulators
$jsfuncs = array();
$jsloads = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();
$tzone = new DateTimeZone('UTC');

$tplvars = array();

$utils->SaveParameters($cache,array_diff_key($params,array_flip($localparams)),NULL);
$tplvars['startform'] = $this->CreateFormStart($id,'findbooking',$returnid,
	'POST','','','',array(
	'item_id'=>$item_id,
	'storedparams'=>$params['storedparams']
	));
$tplvars['endform'] = $this->CreateFormEnd();
$hidden = ''; //TODO
$tplvars['hidden'] = $hidden;

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];
$tplvars['title'] = $this->Lang('title_find');

//generate selectors
$selects = array();

$oneset = new stdClass();
$oneset->title = $this->Lang('title_item');
$choices = $utils->GetItemFamily($this,$db,$item_id);
if ($choices) {
	if (count($choices) > 1) {
		asort($choices,SORT_NATURAL);
		$t1 = $this->CreateInputDropdown($id,'findchooser',array_flip($choices),-1,$item_id,'id="'.$id.'chooser"');
	} else {
		$t1 = $idata['name'];
	}
} else {
	$t1 = $idata['name'];
}
$oneset->input = $t1;
$selects[] = $oneset;

$oneset = new stdClass();
$oneset->title = $this->Lang('start');
$t1 = $this->CreateInputText($id,'findfirst','',10);
$t1 = str_replace('class="cms_textfield"','class="dateinput cms_textfield"',$t1);
$t2 = $this->CreateInputText($id,'findlast','',10);
$t2 = str_replace('class="cms_textfield"','class="dateinput cms_textfield"',$t2);
$oneset->input = $this->Lang('rangeinput',$t1,$t2);
$selects[] = $oneset;

$oneset = new stdClass();
$oneset->title = $this->Lang('title_user');
$choices = array($this->Lang('is')=>1,$this->Lang('islike')=>2);
$t1 = $this->CreateInputRadioGroup($id,'findusertype',$choices,1,'','&nbsp');
//override crappy default label-layout
$t1 = preg_replace('~label class="(.*)"~U','label class="\\1 radiolabel"',$t1);
$oneset->input = $t1.' '.$this->CreateInputText($id,'finduser','',15,45);
$selects[] = $oneset;

$tplvars['selects'] = $selects;

/* TODO STUFF
$bdata = array();
if (isset($params['bookat']))
	$bdata['slotstart'] = $params['bookat'];
else
	$bdata['slotstart'] = $params['startat'];
$bdata['slotlen'] = $utils->GetInterval($this,$item_id,'slot');
*/

if (isset($params['search'])) {
	$items = array();
	//TODO get actual matches
	$oneset = new stdClass();
	$oneset->rowclass = 'row1';
	$oneset->what = 'Resource 1';
	$oneset->when = '2016-9-13 @ 9:00 to 9:59';
	$oneset->who = 'Roger';
	$oneset->hidden = 1;
	$oneset->cb = $this->CreateInputCheckbox($id,'sel[]',1,-1,'class="pagecheckbox"');
	$items[] = $oneset;

	$oneset = new stdClass();
	$oneset->rowclass = 'row2';
	$oneset->what = 'Resource 2';
	$oneset->when = '2016-9-12 @ 15:00 to 16:59';
	$oneset->who = 'James';
	$oneset->hidden = 2;
	$oneset->cb = $this->CreateInputCheckbox($id,'sel[]',2,-1,'class="pagecheckbox"');
	$items[] = $oneset;

	$oneset = new stdClass();
	$oneset->rowclass = 'row1';
	$oneset->what = 'Resource 3';
	$oneset->when = '2016-9-12 @ 9:00 to 9:59';
	$oneset->who = 'Roger';
	$oneset->hidden = 3;
	$oneset->cb = $this->CreateInputCheckbox($id,'sel[]',3,-1,'class="pagecheckbox"');
	$items[] = $oneset;

	$count = count($items);
	if ($count) {
		$tplvars['finds'] = $items;
		$tplvars['whattitle'] = $this->Lang('title_item');
		$tplvars['whentitle'] = $this->Lang('title_when');
		$tplvars['whotitle'] = $this->Lang('title_user');

		if ($count > 1) {
			$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/include/jquery.metadata.min.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.SSsort.min.js"></script>
EOS;
			//TODO sorter func for type 'slotwhen'
			$jsloads[] = <<<EOS
 $.SSsort.addParser({
  id: 'slotwhen',
  is: function(s) {
   return true;
  },
  format: function(s,node) {
   var t = $.trim(s);
   return t; //TODOfunc(t);
  },
  watch: false,
  type: 'text'
 });
 $('table#details').SSsort({
  sortClass: 'SortAble',
  ascClass: 'SortUp',
  descClass: 'SortDown',
  oddClass: 'row1',
  evenClass: 'row2',
  oddsortClass: 'row1s',
  evensortClass: 'row2s'
 });

EOS;
			//TODO make page-rows count window-size-responsive
			$pagerows = $this->GetPreference('pref_pagerows',10);
			if ($pagerows && $count > $pagerows) {
				$tplvars['hasnav'] = 1;
				//setup for SSsort
				$choices = array(strval($pagerows) => $pagerows);
				$f = ($pagerows < 4) ? 5 : 2;
				$n = $pagerows * $f;
				if ($n < $rc)
					$choices[strval($n)] = $n;
				$n *= 2;
				if ($n < $rc)
					$choices[strval($n)] = $n;
				$choices[$this->Lang('all')] = 0;
				$tplvars['rowchanger'] =
					$this->CreateInputDropdown($id,'pagerows',$choices,-1,$pagerows,
					'onchange="pagerows(this);"').'&nbsp;&nbsp;'.$this->Lang('pagerows');
				$curpg='<span id="cpage">1</span>';
				$totpg='<span id="tpage">'.ceil($rc/$pagerows).'</span>';
				$tplvars += array(
					'pageof' => $this->Lang('pageof',$curpg,$totpg),
					'first' => '<a href="javascript:pagefirst()">'.$this->Lang('first').'</a>',
					'prev' => '<a href="javascript:pageback()">'.$this->Lang('previous').'</a>',
					'next' => '<a href="javascript:pageforw()">'.$this->Lang('next').'</a>',
					'last' => '<a href="javascript:pagelast()">'.$this->Lang('last').'</a>'
				);

				$jsfuncs[] = <<<EOS
function pagefirst() {
 $.SSsort.movePage($('#bookings')[0],false,true);
}
function pagelast() {
 $.SSsort.movePage($('#bookings')[0],true,true);
}
function pageforw() {
 $.SSsort.movePage($('#bookings')[0],true,false);
}
function pageback() {
 $.SSsort.movePage($('#bookings')[0],false,false);
}
function pagerows(cb) {
 $.SSsort.setCurrent($('#bookings')[0],'pagesize',parseInt(cb.value));
}

EOS;
			} else { //$count <= $pagerows
				$tplvars['hasnav'] = 0;
			}
		}//$count > 1
	} else { // no matches found
		$tplvars['nofinds'] = $this->Lang('nofinds');
	}
} else {
	$count = 0;
}
$tplvars['count'] = $count;

//TODO en/disable this according to user selection
$xtra = ($count) ? '' : 'disabled="disabled"';
$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('useselection'),$xtra);
$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
$tplvars['search'] = $this->CreateInputSubmit($id,'search',$this->Lang('find'));


		$jsloads[] = <<<EOS
 $('#{$id}submit').prop('disabled',true).bind('click',validate);
 $('table#details').find('input[type="checkbox"]').click(function(ev) {
  var st = $(this).attr('checked');
  if (st) {
    var \$cb = $(this),
     \$all = \$cb.closest('table').find('[type="checkbox"]');
    \$all.attr('checked',false);
    \$cb.attr('checked',true);
    $('#{$id}submit').prop('disabled',false);
  } else {
    $('#{$id}submit').prop('disabled',true);
  }
 });

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

$jsloads[] = <<< EOS
 $('.dateinput').Pikaday({
  container: document.getElementById('calendar'),
  format: 'YYYY-MM-DD',
  i18n: {
   previousMonth: '{$prevm}',
   nextMonth: '{$nextm}',
   months: [{$mnames}],
   weekdays: [{$dnames}],
   weekdaysShort: [{$sdnames}]
  }
 });

EOS;
/*
<div id="calendar"></div>
    container: this.parentNode,

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
  onClose: function() {
   var sel = $('#calendar').val();
   if (sel !== '') { //not cancelled
    var d = new Date(sel);
    var f = 'YYYY-MM-DD';
    var d2 = moment(d).format(f);
    $('#{$id}when').val(d2);
    d2 = moment(d).add({$bdata['slotlen']},'s').format(f);
    $('#{$id}until').val(d2);
   }
  }
 });

EOS;
*/
$jsfuncs[] = <<<EOS
function showerr(msg) {
 confirm(msg);
}
function validate(ev) {
 var s = $('input[name="{$id}when"]').val(),
     e = $('input[name="{$id}until"]').val();
 var ok = !isNaN(Date.parse(s));
 ok = ok && !isNaN(Date.parse(e));
 if (ok) {
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

$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/pikaday.css" />
EOS;
$customcss = $utils->GetStylesURL($this,$item_id);
if ($customcss)
	$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$customcss}" />
EOS;
//porting heredoc-var newlines is a problem for qouted strings! workaround ...
$stylers = str_replace("\n",' ',$stylers);
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
<script type="text/javascript" src="{$baseurl}/include/pikaday.js"></script>
<script type="text/javascript" src="{$baseurl}/include/jquery.pikaday.min.js"></script>
EOS;

if ($jsloads) {
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,'find.tpl',$tplvars);
