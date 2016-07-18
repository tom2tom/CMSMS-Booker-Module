<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: opencart - display & perhaps change content of bookings cart
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
/* supplied $params = array (size=5)
  'returnid' => int 159
  'item_id' => int 10001
  'range' => string '1' (length=1)
  'storedparams' => string 'bkr_Params5787028c46966' (length=23)
  'action' => string 'opencart' (length=8)
*/

//parameter keys for local use, not to be cached before departure
//TODO handle name of selected checkbox(es)
$localparams = array(
	'cancel',
	'cart',
	'delete',
	'hidden',
	'submit'
);

$cache = Booker\Cache::GetCache($this);
$utils = new Booker\Utils();
$cart = $utils->RetrieveParameters($cache,$params);
/*$params now = array (size=15)
  'returnid' => int 159
  'item_id' => int 10001
  'range' => string '1' (length=1)
  'storedparams' => string 'bkr_Params5787028c46966' (length=23)
  'action' => string 'default' (length=7) where we originated
  'view' => string 'table' (length=5)
  'startat' => int 1468454400
  'item' => string 'allcourts' (length=9)
  'clickat' => string '' (length=0)
  'slotid' => string '' (length=0)
  'ranger' => int 1
  'chooser' => int 10001
  'cart' => string 'Cart' (length=4)
  'module' => string 'Booker' (length=6)
  'cartkey' => string 'bkr_Cart5787028c469be'  */

if (isset($params['cancel'])) {
	//TODO handle/restore deleted items
	$utils->SaveParameters($cache,$params,$localparams);
	$this->Redirect($id,$params['action'],$params['returnid'],
		array('storedparams'=>$params['storedparams']));
}

if (isset($params['submit'])) {
	$this->Crash(); //TODO work with interface - async ??
	//empty cart on success
}

$jsloads = array();
$jsfuncs = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();
$tplvars = array();

$tplvars['startform'] = $this->CreateFormStart($id,'opencart',$returnid,
	'POST','','','',array(
	'item_id'=>$params['item_id'],
	'storedparams'=>$params['storedparams']
	));
$tplvars['endform'] = $this->CreateFormEnd();
//$tplvars['hidden'] = '';

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];
$tplvars['title'] = $this->Lang('title_cart');
//$tplvars['desc'] = $this->Lang('DESC'); //if any

if (!$cart->seemsEmpty()) {
	//get resource details from table
	$pending = $cart->getItems(function($item) {
		return $item->getStatus() >= 0; //not flagged as deleted
	});
	$itmids = array();
	foreach ($pending as $item) {
		$t = $item->getCartType(); //type property records item_id for carted item
		$itmids[$t] = 1;
	}
	$fillers = str_repeat('?,',count($itmids)-1);
	$lookup = $db->GetAssoc('SELECT item_id,name,image,rationcount FROM '.
		$this->ItemTable.' WHERE item_id IN ('.$fillers.'?) AND active>=0',array_keys($itmids));
	$totals = $cart->getTotals(function($item) {
		return $item->getStatus() >= 0; //not flagged as deleted
	});
	$n = $cart->getRoundingDecimals();
	$pay = $totals['totals'][0]; //gross amount
	if ($pay > 0.01) {
		$pay = sprintf('%.'.$n.'F',$pay);
	} else {
		$pay = FALSE;
	}
	$nil = $this->Lang('nil');

	$items = array();
	foreach ($pending as $key=>$item) {
		$oneset = new stdClass();
		$iid = $item->getCartType();
		$oneset->pic = $lookup[$iid]['image']; //TODO thumbnail
		$t = $lookup[$iid]['name'];
		if ($iid >= \Booker::MINGRPID) {
			$t = $this->Lang('countof2',$item->quantity,$t); //TODO assumes public property
		}
		$oneset->name = $t;
		$t = $item->start;
		$oneset->when = $utils->RangeDescriptor($this,$t,$t+$item->slen-1);
		//calc fee if any TODO tax calc
		$t = $cart->getItemPrice($item); //TODO manage tax rate
		if ($t) {
			$oneset->fee = sprintf('%.'.$n.'F',$t);
		} else {
			$oneset->fee = $nil;
		}
		$oneset->comment = $this->CreateInputText($id,'comment[]','',20,30); //TODO any pre-existing comment?
		$oneset->cb = $this->CreateInputCheckbox($id,'sel[]',1,-1);
		$oneset->hidden = $key;
		$items[] = $oneset;
	}

	$tplvars = $tplvars + array(
		'items' => $items,
		'count' => count($items),
		'payable' => $pay,
		// column-titles
		'whattitle' => $this->Lang('title_item'),
		'whentitle' => $this->Lang('title_when'),
		'feetitle' => $this->Lang('title_fee'),
		'cmttitle' => $this->Lang('title_comment'),
		'totaltitle' => $this->Lang('title_feesum'),
		'delete' => $this->CreateInputSubmit($id,'delete',$this->Lang('delete')) //AJAX initiator action.deletecartitem.php
	);
	//buttons TODO
	//	submit & back when $params['cart'] i.e. initiated by 'see cart'
	//	submit & cancel when $params['add'] i.e. initiated by 'add to cart'
	//	(finish or continue) & cancel when $params['submit'] i.e. initiated by '?'
	if (isset($params['cart']) || isset($params['add']))
		$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit')); //proceed to pay
	else
		$tplvars['submit'] = NULL;
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close')); //or Back to go back
	//js setup
	$t = $this->Lang('delsel_confirm',$this->Lang('booking_multi'));
	$u = $this->create_url($id,'deletecartitem',$returnid,array('storedparams'=>$params['storedparams'],'itemkeys'=>'XX'));
	$offs = strpos($u,'?mact=');
	$u = str_replace(array('&amp;','cntnt01'),array('&','m1_'),substr($u,$offs+1));
	$offs = strpos($u,'XX');
	$u = substr($u,0,$offs);
	$jsloads[] = <<<EOS
 $('#{$id}delete').click(function(ev) {
  var \$sel = $('#cart').find('input:checked');
  if(\$sel.length > 0) {
    if(confirm('{$t}')) {
      var keys = [];
	  \$sel.each(function() {
	    var ob = $(this).next('span');
        if (ob) {
         keys.push(ob.html());
        }
      });
      var ajaxdata = '{$u}'+keys.join();
	  $.ajax({
	   type: 'POST',
	   url: 'moduleinterface.php',
	   data: ajaxdata,
	   success: showcart,
	   dataType: 'html'
	  });
	}
  }
  ev.stopImmediatePropagation();
  ev.preventDefault();
	return false;
 });

EOS;
	$jsfuncs[] = <<<EOS
function showcart (data,status) {
 if (status=='success') {
  if (data != '') {
   $('#cart > tbody').html(data);
  } else {
   $('#itemstable').css('display','none');
   $('#emptymessage').css('display','block');
   $('{$id}submit').prop('disabled',true);
   $('{$id}delete').prop('disabled',true);
  }
 } else {
  $('#itemstable').closest('form').prepend('<p style="font-weight:bold;color:red;">OOPS!</p><br />');
 }
}

EOS;

} else { //empty cart
	$tplvars['count'] = 0;
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close')); //or back
}
$tplvars['noitems'] = $this->Lang('nocartitems');

	$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
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


if ($jsloads) {
	$jsfuncs[] = '$(document).ready(function() {
';
	$jsfuncs = array_merge($jsfuncs,$jsloads);
	$jsfuncs[] = '});
';
}
$tplvars['jsfuncs'] = $jsfuncs;
$tplvars['jsincs'] = $jsincs;

echo Booker\Utils::ProcessTemplate($this,'opencart.tpl',$tplvars);
