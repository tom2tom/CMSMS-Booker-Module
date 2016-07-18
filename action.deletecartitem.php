<?php
#----------------------------------------------------------------------
# Module: Bookings - a resource booking module
# Action: pseudo-delete cart items (actually, set status property < 0) 
# Update cart contents after AJAX call in response to delete-button click
#----------------------------------------------------------------------
# See file bookings.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

$cache = Booker\Cache::GetCache(this);
$utils = new Booker\Utils();
$cart = $utils->RetrieveParameters($cache,$params);

$foreach ($params['itemkeys'] as $key) {
	try {
		$item = $cart->getItem($key);
	} catch (Exception $e) {
		continue;
	}
	$item->setStatus($item->getStatus() - 30); //set status < 0
}

if (!$cart->seemsEmpty()) {
//TODO recreate table-body content - c.f. action.opencart
	echo $utils::ProcessTemplate($this,'cartitemsbody.tpl',$tplvars);
	exit;
}
echo 0;
exit;
