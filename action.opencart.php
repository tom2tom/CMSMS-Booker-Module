<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Action: opencart - display & perhaps change content of bookings cart
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

//parameter keys filtered out before redirect etc
$localparams = array(
	'action',
	'cancel',
	'cartcomment',
	'cartsel',
	'delete',
	'submit'
//	'task'
);

$utils = new Booker\Utils();
$utils->UnFilterParameters($params);
$cache = Booker\Cache::GetCache($this);
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
	$newparms = $utils->FilterParameters($params,$localparams); //no cart update
	$this->Redirect($id,$resume,$params['returnid'],$newparms);
}

$utils->DecodeParameters($params,'cartcomment');

if (isset($params['submit'])) {
	$pending = $cart->getItems();
//	$pay = 0.0;
	foreach ($pending as $key=>$item) {
		if ($item->getStatus() < 0) { //flagged as deleted
			$cart->removeItem($key);
			unset($pending[$key]);
		} else {
			//freshen associated comment
			$item->data->comment = $params['cartcomment'][$key];
			//$pay += $item->price * $item->quantity;
		}
	}
	if ($pending) {
		$totals = $cart->getTotals(function($item) {
			return $item->getStatus() >= 0; //not flagged as deleted
		});
		$pay = $totals['totals'][0]; //total gross payment
		$minpay = $this->GetPreference('minpay');
		if ($pay > $minpay || ($minpay > 0.0 && $minpay == $pay)) {
			//no addition to $params['resume']
			$utils->SaveCart($cart,$cache,$params);
			$newparms = $utils->FilterParameters($params,$localparams);
			$idata = $utils->GetItemProperties($this,$params['item_id'],'*');
			//divert to payment form, if possible, and from there, run method.requestfinish
			$utils->OpenPaymentForm($this,$id,$returnid,$newparms,$idata,$cart);
			//if we're back here, there's a problem
			$params['message'] = $this->Lang('err_system');
		} else {
			$utils->SaveCart($cart,$cache,$params);
			$funcs = new Booker\Requestops();
			list($res,$msg) = $funcs->FinishReq($this, $utils, $params, TRUE);
			if ($res && !$msg) {
				$funcs = new Booker\Userops($this);
				$key = ($funcs->HasRight($this,$params['booker_id'],'record')) ?
						'booking_feedback2':'booking_feedback';
				$msg = $this->Lang($key);
			}
			$params['message'] = $msg;
			$newparms = $utils->FilterParameters($params,$localparams);
			$this->Redirect($id,'announce',$params['returnid'],$newparms);
			exit;
		}
	} else { //cart effectively empty
		$cart->clear(); //force actual empty
		$utils->SaveCart($cart,$cache,$params);
		do {
			$resume = array_pop($params['resume']);
		} while ($resume == $params['action'] && $params['resume']);
		if ($resume == $params['action']) {
			$resume = 'default'; //should never happen
		}
		$params['message'] = $this->Lang('nocartitems');
		$newparms = $utils->FilterParameters($params,$localparams);
		$this->Redirect($id,$resume,$params['returnid'],$newparms);
	}
} elseif (isset($params['delete'])) {
	$pending = $cart->getItems();
	//flag items deleted
	foreach ($params['cartsel'] as $key=>$t) {
		$pending[$key]->setStatus('deleted');
	}
}

$utils->SaveCart($cart,$cache,$params);

$jsloads = array();
$jsfuncs = array();
$jsincs = array();
$baseurl = $this->GetModuleURLPath();

$jsloads[] = <<<EOS
 $('#needjs').css('display','none');
EOS;

$hidden = $utils->FilterParameters($params,$localparams);
$tplvars = array(
	'needjs' => $this->Lang('needjs'),
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

	$minpay = $this->GetPreference('minpay');
	$pay = $totals['totals'][0]; //gross amount TODO account for credit
	if ($pay > $minpay || ($minpay > 0.0 && $minpay == $pay)) {
		$pay = sprintf('%.'.$n.'F',$pay);
	} else {
		$pay = FALSE;
	}

	$nil = $this->Lang('nil');

	$items = array();
	foreach ($pending as $key=>$item) {
		$oneset = new stdClass();
		$item_id = $item->getCartType();
		$oneset->pic = $lookup[$item_id]['image']; //TODO thumbnail
		$t = $lookup[$item_id]['name'];
		if ($item_id >= \Booker::MINGRPID) {
			$t = $this->Lang('countof2',$item->quantity,$t); //TODO assumes public property
		}
		$oneset->name = $t;
		$reqdata = $item->data->request;
		$t = $reqdata->slotstart;
		$oneset->when = $utils->RangeDescriptor($this,$t,$t+$reqdata->slotlen);
		//calc fee if any TODO tax calc
		$t = $cart->getItemPrice($item);
		if ($t) {
			$oneset->fee = sprintf('%.'.$n.'F',$t);
		} else {
			$oneset->fee = $nil;
		}
		$comment = isset($reqdata->comment) ? $reqdata->comment : '';
		$len = isset($item->data->maxlen) ? $item->data->maxlen : 30;
		$oneset->comment = $this->CreateInputText($id,'cartcomment['.$key.']',$comment,20,$len);
		$oneset->cb = $this->CreateInputCheckbox($id,'cartsel['.$key.']',1,-1);
		$items[] = $oneset;
	}

	//button labels
	switch ($params['task']) {
	 case 'add':
	 	$key1 = 'close';
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

	$tplvars += array(
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

	$t = $this->Lang('confirm_del_sel',$this->Lang('booking_multi'));
	$jsloads[] = <<<EOS
 $('#{$id}delete').click(function() {
  var \$sel = $('#cart').find('input:checked');
  if(\$sel.length > 0) {
   var tg = this;
   $.alertable.confirm('$t',{
    okName: '{$this->Lang('proceed')}',
    cancelName: '{$this->Lang('cancel')}'
   }).then(function() {
    $(tg).trigger('click.deferred');
   });
  }
  return false;
 });
EOS;

	$stylers = <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/alertable.css" />
EOS;

$jsincs[] = <<<EOS
<script type="text/javascript" src="{$baseurl}/lib/js/jquery.alertable.min.js"></script>
EOS;

} else { //empty cart
	$tplvars['count'] = 0;
	$tplvars['noitems'] = $this->Lang('nocartitems');
	$tplvars['cancel'] = $this->CreateInputSubmit($id,'cancel',$this->Lang('close')); //or back
	$stylers = '';
}

$stylers .= <<<EOS
<link rel="stylesheet" type="text/css" href="{$baseurl}/css/public.css" />
EOS;
//heredoc-var newlines are a problem for quoted in-js strings, so ...
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

echo Booker\Utils::ProcessTemplate($this,'opencart.tpl',$tplvars);
if ($jsall) {
	echo $jsall; //inject constructed js after other content
}
