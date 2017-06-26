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

$utils = new Booker\Utils();
$newparms = $params;
if (!empty($params['paramskey'])) {
	$olds = json_decode($params['paramskey']);
	unset($params['paramskey']);
	if ($olds) {
		$arr = (array)$olds;
		$utils->UnFilterParameters($arr);
		$newparms = array_merge($newparms,$arr);
	}
}

$funcs = new Booker\Requestops();
list($res,$msg) = $funcs->FinishReq($mod,$utils,$newparms,!empty($newparms['result']));
if ($msg) {
	if (!empty($params['message'])) {
		$params['message'] .= '<br />'.$msg;
	} else {
		$params['message'] = $msg;
	}
}

unset($params['result']); //CHECKME relevance for return?

if (isset($olds)) {
	$params = array_merge($params,(array)$olds);
}

/*
http_response_code(200); //always signal success to webhook-source
exit;
*/
