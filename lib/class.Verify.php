<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Verify - verify intended-booking information
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Verify
{
	/**
	VerifyAdmin:
	Validate relevant members of @params, sourced from an admin page
	@mod reference to current module-object
	@utils: reference to Booker\Utils object
	@params: reference to array of POST parameters
	@is_new: boolean whether processing a new booking
	Returns: 2-member array, 1st is boolean indicating success, 2nd '' or array of error messages
	*/
	public function VerifyAdmin(&$mod, &$utils, &$params, $item_id, $is_new)
	{
		$msg = array();
		$tz = new \DateTimeZone('UTC');
		$slen =  $utils->GetInterval($mod,$item_id,'slot');
/*supplied $params keys
		'subgrpcount'? 'when' 'until'? 'user' 'conformuser' 'displayclass'
		'conformstyle' 'contact' 'conformcontact' 'paid'
TODO support 'past' data without both date/time $params[]
*/
		//always want these $params[] keys: 'user','contact'
		//maybe-present keys
		//'subgrpcount','when','until'(maybe empty),
		if (isset($params['when'])) {
			$fv = $params['when'];
			if ($fv) {
				try {
					$dts = new \DateTime($fv,$tz);
				}
				catch (Exception $e) {
					$msg[] = $mod->Lang('err_badstart');
				}
			} elseif ($is_new) //must be provided for new booking
				$msg[] = $mod->Lang('err_badstart');
		}

		if (isset($params['until'])) {
			$fv = $params['until'];
			if ($fv) {
				try {
					$dte = new \DateTime($fv,$tz);
				}
				catch (Exception $e) {
					$msg[] = $mod->Lang('err_badend');
				}
			} elseif (isset($dts)) {
				$dte = clone $dts;
				$dte->modify('+'.($slen-1).' seconds');
			} else
				$msg[] = $mod->Lang('err_badend');
		}

		if (isset($dts) && isset($dte)) {
			if ($dte > $dts) {
				$funcs = new Schedule();
				//rationalise specified times relative to slot length
				$utils->TrimRange($dts,$dte,$slen);
				$params['when'] = $dts->getTimestamp();
				$params['until'] = $dte->getTimestamp();
				if ($is_new) {
					if ($funcs->ItemVacantCount($mod,$item_id,$dts,$dte) == 0) {
						$msg[] = $mod->Lang('err_dup');
					} elseif (!$funcs->ItemAvailable($mod,$utils,$item_id,$dts,$dte)) {
						$msg[] = $mod->Lang('err_na');
					}
				} else { //update
					$excl = (isset($params['bkg_id'])) ? $params['bkg_id'] : FALSE;
					if ($funcs->ItemVacantCount($mod,$item_id,$dts,$dte,$excl) == 0) {
						$msg[] = $mod->Lang('err_dup');
					} elseif (!$funcs->ItemAvailable($mod,$utils,$item_id,$dts,$dte)) {
						$msg[] = $mod->Lang('err_na');
					}
				}
			} else {
				$msg[] = $mod->Lang('err_badtime');
			}
		}

		if (!$params['user'])
			$msg[] = $mod->Lang('err_nosender');

		if (!$params['contact'])
			$msg[] = $mod->Lang('err_nocontact');

		if (isset($params['subgrpcount'])) {
			$fv = $params['subgrpcount'];
			if (!$fv) //TODO or too big
				$msg[] = $mod->Lang('err_parm');
		}

		if (!$msg)
			return array(TRUE,'');
		return array(FALSE,$msg);
	}

	/**
	VerifyPublic:
	Validate relevant members of @params, sourced from a frontend page
	@mod reference to current module-object
	@utils: reference to Booker\Utils object
	@params: reference to array of POST parameters
	@is_new: boolean whether processing a new booking-request
	Returns: 2-member array, 1st is boolean indicating success, 2nd '' or array of error messages
	*/
	public function VerifyPublic(&$mod, &$utils, &$params, $is_new)
	{
//TODO make validation handle $past == TRUE
//TODO CHECK validation enforces lead-time, lead-count limits ?
		$msg = array();
		$tz = new \DateTimeZone('UTC');
/* supplied $params keys
	'returnid' 'item_id' 'startat' 'range' 'view' 'origreturnid'
	'requesttype'? 'subgrpcount'? 'when'? 'until'? 'user' 'contact' 'captcha'? 'chooser'
*/
		//always want these $params keys: 'user','contact'
		//maybe-present keys
		//'requesttype','subgrpcount','when','until'(maybe empty),'captcha'
		if (isset($params['when'])) {
			$item_id = $params['item_id'];
			$fv = $params['when'];
			if ($fv) {
				try {
					$dts = new \DateTime($fv,$tz);
				} catch(Exception $e) {
					$msg[] = $mod->Lang('err_badstart');
				}
			} elseif ($is_new) //must be provided for new booking
				$msg[] = $mod->Lang('err_badstart');
		}

		if (isset($params['until'])) {
			$fv = $params['until'];
			if ($fv) {
				try {
					$dte = new \DateTime($fv,$tz);
				} catch(Exception $e) {
					$msg[] = $mod->Lang('err_badend');
				}
			} elseif (isset($dts)) {
				//set default
				$dte = clone $dts;
				$slen = $utils->GetInterval($mod,$item_id,'slot');
				$dte->modify('+'.$slen.' seconds');
			} else
				$msg[] = $mod->Lang('err_badend');
		}

		if (isset($dts) && isset($dte)) {
			$timely = ($dte > $dts);
			if ($timely && isset($params['item_id'])) {
				$idata = $utils->GetItemProperty($mod,$params['item_id'],'timezone');
				$t = $utils->GetZoneTime($idata['timezone']);
				$timely = ($dts->getTimestamp() >= $t);
			}

			if ($timely) {
				$funcs = new Schedule();
				//rationalise specified times relative to slot length
				if ($is_new) {
					if ($funcs->ItemVacantCount($mod,$item_id,$dts,$dte) == 0) {
						$msg[] = $mod->Lang('err_dup');
					} elseif (!$funcs->ItemAvailable($mod,$utils,$item_id,$dts,$dte)) {
						$msg[] = $mod->Lang('err_na');
					}
				} else { //update
					if ($funcs->ItemVacantCount($mod,$item_id,$dts,$dte,$params['slotid']) == 0) {
						$msg[] = $mod->Lang('err_dup');
					} elseif (!$funcs->ItemAvailable($mod,$utils,$item_id,$dts,$dte)) {
						$msg[] = $mod->Lang('err_na');
					}
				}
			} else {
				$msg[] = $mod->Lang('err_badtime');
			}
		}

		if (!$params['user'])
			$msg[] = $mod->Lang('err_nosender');

		if (!$params['contactuser'])
			$msg[] = $mod->Lang('err_nocontact');

		if (isset($params['subgrpcount'])) {
			$fv = $params['subgrpcount'];
			if (!$fv) //TODO or too big
				$msg[] = $mod->Lang('err_parm');
		}

		if (isset($params['requesttype'])) {
			if (!$params['requesttype'])
				$msg[] = $mod->Lang('err_system'); //radio-group value shouldn't be missing
		}

		if (isset($params['captcha'])) {
			$ob = \cms_utils::get_module('Captcha');
			if ($ob) {
				$valid = $ob->checkCaptcha($params['captcha']);
				unset($ob);
				if (!$valid)
					$msg[] = $mod->Lang('err_captcha');
			}
		}

		if (!$msg)
			return array(TRUE,'');
		return array(FALSE,$msg);
	}

	/*
	LocalDomains:
	@countycode: string 'AU','US' etc from DateTimeZone::getLocation, normally for
		the locale of a booked resource
	Returns: string with comma-separated top-level common domains for @countrycode
	*/
	private function LocalDomains($countrycode)
	{
		$str = strtolower($countrycode);
		switch ($str) {
		 case '':
		 case 'us':
			$d = '';
			break;
		 case 'jp':
		 case 'nz':
		 case 'uk':
			$d = ",co.$str,org.$str,net.$str,$str";
			break;
		 default:
			$d = ",com.$str,org.$str,net.$str,$str";
			break;
		}
		return 'com,org,net'.$d;
	}

	private function ConvertDomains($pref)
	{
		if (!$pref)
			return FALSE; //'""';
		$parts = explode(',',$pref);
		if (isset($parts[1])) { //>1 array-member
			$parts = array_unique($parts);
			ksort($parts);
		}
		foreach ($parts as &$one) {
			$one = '\''.trim($one).'\'';
		}
		unset($one);
		return implode(',',$parts);
	}

	/*
	EmailDomains:
	@mod: reference to current module-object
	@countycode: optional string 'AU','US' etc from DateTimeZone::getLocation,
		normally for the locale of a booked resource
	Returns: js string for inclusion in mailcheck.js code, potentially including:
		topLevelDomains,domains,secondLevelDomains
	*/
	private function EmailDomains(&$mod, $countrycode='')
	{
		$pref = $mod->GetPreference('pref_topdomains','co,com,net,org');
		$tops = self::LocalDomains($countrycode);
		if ($pref)
			$pref .= ','.$tops;
		else
			$pref = $tops;
		$parts = array();
		$topdomains = self::ConvertDomains($pref);
		//mailcheck requires domain-arrays, even if single-membered
		if ($topdomains)
			$parts[] = "   topLevelDomains: [$topdomains]";
		$pref = $mod->GetPreference('pref_domains');
		$domains = self::ConvertDomains($pref);
		if ($domains)
			$parts[] = "   domains: [$domains]";
		$pref = $mod->GetPreference('pref_subdomains');
		$subdomains = self::ConvertDomains($pref);
		if ($subdomains)
			$parts[] = "   secondLevelDomains: [$subdomains]";

		if ($parts)
			return implode(",\n",$parts).",";
		return '';
	}

	/**
	VerifyScript:
	Construct js string for verification of booking/request data
	If @admin, uses modalconfirm dialogs for interaction, with modal
	 div '#confgeneral' and buttons '#mc_conf' and '#mc_deny'
	@mod: reference to current module-object
	@id: session identifier
	@admin: boolean, whether for admin-page
	@withdates: boolean, whether to check start,end dates
	@nopast: boolean, whether to fail dates before 'now'
	@zonename: timezone identifier like 'europe/paris'
	Returns: js string
	*/
	public function VerifyScript(&$mod, $id, $admin, $withdates, $nopast, $zonename)
	{
		if ($admin) {
			$js1 = <<<EOS
function showerr(message,target) {
 $.modalconfirm.show({
  overlayID: 'confirm',
  popupID: 'confgeneral',
  seeButtons: 'deny',
  showTarget: target,
  preShow: function(tg,\$d) {
   var para = \$d.children('p:first')[0];
   para.innerHTML = message;
   \$d.find('#mc_deny').val('{$mod->Lang('close')}');
  },
  onDeny: function(tg) {
   setTimeout(function() {
     tg.focus();
   },10);
  }
 });
}

EOS;
		} else { //frontend, no modalconfirm
			$js1 = <<<EOS
function showerr(message,target) {
 alert(message);
 setTimeout(function() {
  tg.focus();
 },10);
}

EOS;
		}

		$usererr = ($admin)?
		 $mod->Lang('missing_type',$mod->Lang('name')):
		 $mod->Lang('err_nosender');

		if ($withdates) {
			$js2 = <<<EOS
var clicker = null;
function validate(ev) {
 var ok = true,
   f = 'D M YYYY h:mm',
   ds = null,
   de = null,
   tg = document.getElementById('{$id}when');
 if (tg !== null){
  var str = tg.value;
  if (typeof me.trim === "function") str = str.trim();
   var fs = moment(str).format(f);
   ds = new Date(fs);
   ok = ds instanceof Date && isFinite(ds);
 }
 if (ok) {
  tg = document.getElementById('{$id}until');
  if (tg !== null) {
   str = tg.value;
   if (typeof me.trim === "function") str = str.trim();
   fs = moment(str).format(f);
   var de = new Date(fs);
   ok = de instanceof Date && isFinite(de);
  }
 }

EOS;
			if ($nopast) {
				$js2 .= <<<EOS
 if (ok) {
  dn = new Date();
  ok = (ds === null || (ds > dn && (de === null || de > ds)));
 }

EOS;
			} else {
				$js2 .= <<<EOS
 ok = ok && (ds === null || de === null || de > ds);

EOS;
			}
			$js2 .= <<<EOS
 if (!ok) {
  showerr('{$mod->Lang('err_badtime')}',tg);
 } else {
  tg = document.getElementById('{$id}user');
  str = tg.value;
  if (typeof me.trim === "function") str = str.trim();
  if (str == false) {
    showerr('$usererr',tg);
    ok = false;
  }
 }

EOS;
		} else { //no date checks
 			$js2 = <<<EOS
function validate(ev) {
 var ok = true,
   tg = document.getElementById('{$id}user'),
   str = tg.value;
 if (typeof me.trim === "function") str = str.trim();
 if (str == false) {
  showerr('$usererr',tg);
  ok = false;
 }

EOS;
		}

		try {
			$lzone = new \DateTimeZone($zonename);
			$t = $lzone->getLocation();
			$t = $t['country_code'];
		} catch (Exception $e) {
			$t = '';
		}
		$domains = self::EmailDomains($mod,$t);
 		$js3 = <<<EOS
 if (ok) {
  clicker = this;
  $('#{$id}contact').mailcheck({
{$domains}
   distanceFunction: function(str1,str2) {
    var lv = Levenshtein;
    return lv.get(str1,str2);
   },
   suggested: function(tg,suggest) {

EOS;
		if ($admin) {
			$js4 = <<<EOS
    $.modalconfirm.show({
     overlayID: 'confirm',
     popupID: 'confgeneral',
     showTarget: tg,
     preShow: function(tg,\$d) {
      var para = \$d.children('p:first')[0],
       prompt = '{$mod->Lang('meaning_type','%s')}'.replace('%s','<strong>'+suggest.full+'</strong>');
      para.innerHTML = prompt;
      \$d.find('#mc_conf').val('{$mod->Lang('yes')}');
      \$d.find('#mc_deny').val('{$mod->Lang('no')}');
     },
     onConfirm: function(tg,\$d) {
      tg.value = suggest.full;
      setTimeout(function() {
       $(clicker).unbind('click',validate);
       clicker.click();
       $(clicker).bind('click',validate);
      },10);
     },
     onDeny: function(tg) {
      setTimeout(function() {
       tg.focus();
      },10);
     }
    });

EOS;
		} else { //frontend, no modalconfirm
			$js4 = <<<EOS
    var prompt = '{$mod->Lang('meaning_type','%s')}'.replace('%s',suggest.full);
    if (confirm(prompt)){
     tg.value = suggest.full;
     setTimeout(function() {
      $(clicker).unbind('click',validate);
      clicker.click();
      $(clicker).bind('click',validate);
     },10);
    } else {
     setTimeout(function() {
      tg.focus();
     },10);
    }

EOS;
		}
		$js5 = <<<EOS
   },
   empty: function(tg) {
    showerr('{$mod->Lang('err_nocontact')}',tg);
    return false;
   }
  });
  ok = false;
 }

EOS;
		if ($admin)
			$js6 = '';
		else {
			$js6 = <<<EOS
 if (ok) {
  tg = document.getElementById('{$id}captcha');
  if (tg !== null){
   str = tg.value;
   if (typeof me.trim === "function") str = str.trim();
   if (str == false) {
    showerr('{$mod->Lang('err_nocaptcha')}',tg);
    ok = false;
   }
  }
 }

EOS;
		}
		$js7 = <<<EOS
 if (ok) {
  return true;
 }
 ev.stopImmediatePropagation();
 ev.preventDefault();
 return false;
}

EOS;
		return $js1.$js2.$js3.$js4.$js5.$js6.$js7;
	}

}
