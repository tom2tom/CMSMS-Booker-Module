<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# checkslot - ajax processor to generate html describing item-availability
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

//grab stuff cuz' we've bypassed a normal session-start
$fp = __DIR__;
$c = strpos($fp, '/modules');
$inc = substr($fp, 0, $c+1).'include.php';
require $inc;

$item_id = (int)filter_var($_REQUEST['item_id']);
$bs = (int)filter_var($_REQUEST['start'], FILTER_SANITIZE_NUMBER_INT);
if (isset($_REQUEST['end'])) {
	$be = (int)filter_var($_REQUEST['end'], FILTER_SANITIZE_NUMBER_INT);
} else {
	$be = 1;
}

$mod = cms_utils::get_module('Booker');
$utils = new Booker\Utils();
$msg = $utils->GetBusyMessage ($mod,$item_id,$bs,$be-1);

//clear all page-content echoed before now
$handlers = ob_list_handlers();
if ($handlers) {
	$l = count($handlers);
	for ($c = 0; $c < $l; $c++)
		ob_end_clean();
}

echo $msg;
exit;
