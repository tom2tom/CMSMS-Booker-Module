<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: requestfinish
# Request or record booking and send messages
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

/*
if (!function_exists('http_response_code')) { //PHP<5.4
//see http://php.net/manual/en/function.http-response-code.php
 function http_response_code($code)
 {
	switch ($code) {
	 case 200: $text = 'OK'; break;
	 case 401: $text = 'Unauthorized'; break;
	 case 403: $text = 'Forbidden'; break;
	 case 404: $text = 'Not Found'; break;
	 case 405: $text = 'Method Not Allowed'; break;
	 case 406: $text = 'Not Acceptable'; break;
	 default: $code = NULL; break;
	}
	if ($code !== NULL) {
		$protocol = ((!empty($_SERVER['SERVER_PROTOCOL'])) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
		header($protocol.' '.$code.' '.$text);
		$GLOBALS['http_response_code'] = $code;
	}
 }
}
*/

if (!isset ($utils))
	$utils = new Booker\Utils();
$utils->UnFilterParameters($params);
$funcs = new Booker\Requestops();
list($res,$msg) = $funcs->FinishReq($this,$utils,$params,$params['success']);

/*
http_response_code(200); //always signal success to webhook-source
exit;
*/
