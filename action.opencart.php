<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: opencart - display & perhaps change content of bookings cart
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

//parameter keys for local use, not to be cached before departure
$localparams = array(
	'cancel',
	'cart',
	'cartcomment',
	'cartsel',
	'delete',
	'submit',
	'hidden'
);

$cache = Booker\Cache::GetCache($this);
$utils = new Booker\Utils();
$cart = $utils->RetrieveParameters($cache,$params);

if (isset($params['cancel'])) {
	//restore 'deleted' items
	$pending = $cart->getItems(function($item) {
		return $item->getStatus() < 0; //flagged as deleted
	});
	foreach ($pending as $item) {
		$item->setStatus('undeleted');
	}

	$utils->SaveParameters($cache,$params,$localparams);
	$this->Redirect($id,$params['action'],$params['returnid'],
		array('storedparams'=>$params['storedparams']));
}

if (isset($params['submit'])) {
	$pending = $cart->getItems();
	foreach ($pending as $key=>$item) {
		if ($item->getStatus() < 0) { //flagged as deleted
			$cart->removeItem($key);
			unset($pending[$key]);
		} else {
			//freshen associated comment
			$item->data->comment = $params['cartcomment'][$key];
		}
	}
	$utils->SaveParameters($cache,$params,$localparams);
	$this->Crash(); //TODO work with interface - async ??
	//then empty cart on success
}

if (isset($params['delete'])) {
	$pending = $cart->getItems();
	//flag items deleted
	foreach ($params['cartsel'] as $key=>$t) {
		$pending[$key]->setStatus('deleted');
	}
	$cache->set($params['cartkey'],$cart,43200);
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
//$tplvars['desc'] = $this->Lang('DESC'); //help? if any

if (!$cart->seemsEmpty()) {
	//get resource details from table
	$pending = $cart->getItems(function($item) {
		return $item->getStatus() >= 0; //not flagged as deleted
	});
	//get supporting data for all carted item_ids
	$itmids = array();
	foreach ($pending as $item) {
		$t = $item->getCartType(); //type property records item_id for carted item
		$itmids[$t] = 1;
	}
	$fillers = str_repeat('?,',count($itmids)-1);
	$lookup = $db->GetAssoc('SELECT item_id,name,image FROM '.
		$this->ItemTable.' WHERE item_id IN ('.$fillers.'?) AND active>=0',array_keys($itmids));
	$n = $cart->getRoundingDecimals();
	//total payment, if any
	$totals = $cart->getTotals(function($item) {
		return $item->getStatus() >= 0; //not flagged as deleted
	});
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
		$data = $item->data;
		$t = $data->start;
		$oneset->when = $utils->RangeDescriptor($this,$t,$t+$data->slen);
		//calc fee if any TODO tax calc
		$t = $cart->getItemPrice($item);
		if ($t) {
			$oneset->fee = sprintf('%.'.$n.'F',$t);
		} else {
			$oneset->fee = $nil;
		}
		$comment = isset($data->comment) ? $data->comment : '';
		$len = isset($data->maxlen) ? $data->maxlen : 30;
		$oneset->comment = $this->CreateInputText($id,'cartcomment['.$key.']',$comment,20,$len);
		$oneset->cb = $this->CreateInputCheckbox($id,'cartsel['.$key.']',1,-1);
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
		'delete' => $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$this->Lang('tip_delseltype',$this->Lang('booking_multi')).'"')
	);
	//buttons TODO
	//	submit & back when $params['cart'] i.e. initiated by 'see cart'
	//	submit & cancel when $params['add'] i.e. initiated by 'add to cart'
	//	(finish or continue) & cancel when $params['submit'] i.e. initiated by '?'
	if (isset($params['cart']) || isset($params['add']))
		$tplvars['submit'] = $this->CreateInputSubmit($id,'submit',$this->Lang('submit')); //proceed to pay
	else
		$tplvars['submit'] = NULL;
	if (isset($params['add']))
		$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('cancel'));
	else
		$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close')); //or 'Back' to go back

	//js setup
	$t = $this->Lang('delsel_confirm',$this->Lang('booking_multi'));
	$jsloads[] = <<<EOS
 $('#{$id}delete').click(function(ev) {
  var \$sel = $('#cart').find('input:checked');
  if(\$sel.length > 0) {
    return confirm('{$t}');
  }
  return false;
 });

EOS;

} else { //empty cart
	$tplvars['count'] = 0;
	$tplvars['noitems'] = $this->Lang('nocartitems');
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close')); //or back
}

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
