<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: opencart - display & perhaps change content of bookings cart
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

//parameter keys for local use, not to be cached before departure
$localparams = array(
	'action',
	'cancel',
	'cartcomment',
	'cartsel',
	'delete',
	'submit',
	'task'
);

$utils = new Booker\Utils();
$cache = Booker\Cache::GetCache($this);
$utils->RetrieveParameters($cache,$params);
$cart = $utils->RetrieveCart($cache,$params);

if (isset($params['cancel'])) {
	//restore 'deleted' items
	$pending = $cart->getItems(function($item) {
		return $item->getStatus() < 0; //flagged as deleted
	});
	foreach ($pending as $item) {
		$item->setStatus('undeleted');
	}
	do {
		$resume = array_pop($params['resume']);
	} while ($resume == $params['action'] && $params['resume']);
	if ($resume == $params['action']) {
		$resume = 'default'; //should never happen
	}
	$utils->SaveParameters($cache,$params,$localparams);
	$this->Redirect($id,$resume,$params['returnid'],
		array('storedparams'=>$params['storedparams']));
} elseif (isset($params['submit'])) {
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
	if ($pending) {
		//no addition to $params['resume']
		$utils->SaveParameters($cache,$params,$localparams,$cart);
		$idata = $utils->GetItemProperty($this,$params['item_id'],'*');
		//divert to payment form, if possible, and from there to action.requestfinish
		$utils->OpenPaymentForm($this,$id,$returnid,$params,$idata,$cart);
		//if we're back here, there's a problem
		$params['message'] = $this->Lang('err_system');
	} else {
		do {
			$resume = array_pop($params['resume']);
		} while ($resume == $params['action'] && $params['resume']);
		if ($resume == $params['action']) {
			$resume = 'default'; //should never happen
		}
		$utils->SaveParameters($cache,$params,$localparams);
		$this->Redirect($id,$resume,$params['returnid'],
			array('storedparams'=>$params['storedparams'],
			'message'=>$this->Lang('nocartitems')));
	}
} elseif (isset($params['delete'])) {
	$pending = $cart->getItems();
	//flag items deleted
	foreach ($params['cartsel'] as $key=>$t) {
		$pending[$key]->setStatus('deleted');
	}
	$cache->set($params['cartkey'],$cart,43200);
}

$utils->SaveParameters($cache,$params,$localparams,$cart);

$jsloads = array();
$jsfuncs = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();

$hidden = array('storedparams'=>$params['storedparams'],'task'=>$params['task']);
$tplvars = array(
	'startform' => $this->CreateFormStart($id,'opencart',$returnid,'POST','','','',$hidden),
	'endform' => $this->CreateFormEnd(),
	'title' => $this->Lang('title_cart')
);
//$tplvars['desc'] = $this->Lang('DESC'); //help? if any

if (!empty($params['message']))
	$tplvars['message'] = $params['message'];

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

	//button labels
	switch ($params['task']) {
	 case 'add':
	 	$key1 = 'cancel';
		$key2 = 'submit';
		break;
	 case 'finish':
	 case 'continue':
	 	$key1 = 'cancel';
		$key2 = 'continue';
		break;
	 default:
//	 case 'see':
	 	$key1 = 'close';
		$key2 = 'submit';
		break;
	}

	$tplvars = $tplvars + array(
		'items' => $items,
		'count' => count($items),
		'payable' => $pay,
		// column-titles
		'whattitle' => $this->Lang('title_item'),
		'whentitle' => $this->Lang('title_period'),
		'feetitle' => $this->Lang('title_fee'),
		'cmttitle' => $this->Lang('title_comment'),
		'totaltitle' => $this->Lang('title_feesum'),
		// buttons
		'submit' => $this->CreateInputSubmit($id,'submit',$this->Lang($key2)),
		'cancel' => $this->CreateInputSubmit($id,'cancel',$this->Lang($key1)),
		'delete' => $this->CreateInputSubmit($id,'delete',$this->Lang('delete'),
			'title="'.$this->Lang('tip_delseltype',$this->Lang('booking_multi')).'"')
	);

	//js setup frontend, no modalconfirm
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
