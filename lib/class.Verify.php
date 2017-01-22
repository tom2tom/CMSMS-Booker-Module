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
	VerifyData:
	Validate relevant members of @params
	@mod reference to current module-object
	@utils: reference to Booker\Utils object
	@params: reference to array of request-parameters, sufficiently equivalent
	 to a HistoryTable row, for:
		a not-yet-recorded request OR
		a recorded request now being edited OR
		a recorded booking now being edited
	@item_id: resource or group identifier
	@is_new: boolean whether validating data for a new request
	@admin: boolean whether the caller is backend/admin
	Returns: 2-member array, 1st is boolean indicating success, 2nd '' or array of error messages
	*/
	public function VerifyData(&$mod, &$utils, &$params, $item_id, $is_new, $admin)
	{
		$msg = array();
		$dtw = new \DateTime('@0',NULL);
		$bs = 0;
		$be = 0;
/*supplied $params keys
		'subgrpcount'? 'when' 'until'? 'name' 'conformuser' 'displayclass'
		'conformstyle' 'contact' 'conformcontact' 'paid'
TODO support 'past' data without both date/time $params[]
*/
		//always want these $params[] keys: 'name','contact'
		//maybe-present keys
		//'subgrpcount','when','until'(maybe empty),
		if (isset($params['when'])) {
			$fv = $params['when'];
			if ($fv) {
				$lvl = error_reporting(0);
				$res = $dtw->modify($fv);
				error_reporting($lvl);
				if ($res) {
					$bs = $dtw->getTimestamp();
				} else {
					$msg[] = $mod->Lang('err_badstart');
				}
			} elseif ($is_new) //must be provided for new booking
				$msg[] = $mod->Lang('err_badstart');
		} else {
$this->Crash();
		}

		$idata = $utils->GetItemProperty($mod,$item_id,array('slottype','slotcount'),TRUE);
		$idata = $idata + $utils->GetItemProperty($mod,$item_id,'timezone');
		if (isset($params['until'])) {
			$fv = $params['until'];
			if ($fv) {
				$lvl = error_reporting(0);
				$res = $dtw->modify($fv);
				error_reporting($lvl);
				if ($res) {
					$be = $dtw->getTimestamp();
				} else {
					$msg[] = $mod->Lang('err_badend');
				}
			} elseif ($bs > 0) {
				//set default
				$be = $bs + $utils->GetCurrentSlotlen($bs,$idata['slottype'],$idata['slotcount']);
			} else
				$msg[] = $mod->Lang('err_badend');
		}

		if ($bs > 0 && $be > 0) {
			if ($be > $bs) {
				//rationalise specified times relative to slot length
				list($bs,$be) = $utils->TuneBlock($idata['slottype'],$idata['slotcount'],$bs,$be);
				$params['slotstart'] = $bs;
				$params['slotlen'] = $be - $bs;
				$timely = ($be > $bs);
				if ($timely && !$admin) {
					if ($idata['timezone']) {
						$t = $utils->GetZoneTime($idata['timezone']);
						$timely = ($bs >= $t);
					} else {
						$msg[] = $mod->Lang('err_system');
					}
				}
				if ($timely) {
					$funcs = new Schedule();
					if ($is_new || !isset($params['bkg_id'])) {
						$excl = FALSE;
					} else {
						$excl = $params['bkg_id'];
					}
					if ($funcs->ItemVacantCount($mod,$item_id,$bs,$be,$excl) == 0) {
						$msg[] = $mod->Lang('err_dup');
					} else {
						$dts = new \DateTime('@'.$bs,NULL);
						$dte = new \DateTime('@'.$be,NULL);
						//any booker
						if (!$funcs->ItemAvailable($mod,$utils,$item_id,0,$bs,$be)) {
							$msg[] = $mod->Lang('err_na');
						}
					}
				} else {
					$msg[] = $mod->Lang('err_badtime');
				}
			} else {
				$msg[] = $mod->Lang('err_badtime');
			}
		}

		$fv = trim($params['name']);
		if(!$fv) {
			$msg[] = ($admin) ?
				$mod->Lang('missing_type',$mod->Lang('name')):
				$mod->Lang('err_nosender');
		}

		if (isset($params['contact'])) {
			$fv = trim($params['contact']);
			if($fv) {
				if (!(preg_match(\Booker::PATNADDRESS,$fv)
				   || preg_match(\Booker::PATNPHONE,$fv)))
				$msg[] = ($admin) ?
					$mod->Lang('invalid_type',$mod->Lang('contact')):
					$mod->Lang('err_nocontact');
			} else {
				$msg[] = ($admin) ?
					$mod->Lang('missing_type',$mod->Lang('contact')):
					$mod->Lang('err_nocontact');
			}
		}

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
			return '';
		$parts = explode(',',$pref);
		foreach ($parts as &$one) {
			$one = '\''.trim($one).'\'';
		}
		unset($one);
		if (count($parts) > 1) {
			$parts = array_unique($parts);
			sort($parts, SORT_STRING);
		}
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
	Construct js string for in-browser verification of booking/request data
	If @admin, uses jquery-alertable dialogs for interaction
	@mod: reference to current module-object
	@utils: reference to Utils-class object
	@id: session identifier
	@item_id: requested-item identifier
	@withdates: boolean, whether to check start,end dates
	@nopast: boolean, whether to fail dates before 'now'
	@zonename: timezone identifier like 'europe/paris'
	@admin: boolean, whether for admin-page
	Returns: js string
	*/
	public function VerifyScript(&$mod, &$utils, $id, $item_id, $withdates, $nopast, $zonename, $admin)
	{
		//TODO adjust styling for error
		$js1 = <<<EOS
function showerr(msg,tg) {
 $.alertable.alert(msg, {
  okName: '{$mod->Lang('close')}'
 }).then(function() {
  tg.focus(); //TODO check ok
 });
}

EOS;
		$usererr = ($admin)?
		 $mod->Lang('missing_type',$mod->Lang('name')):
		 $mod->Lang('err_nosender');

		if ($withdates) {
			$overday = ($utils->GetInterval($mod,$item_id,'slot') >= 84600);
			if ($admin) {
				$dayfmt='';
				$timefmt='';
			} else {
				$idata = $utils->GetItemProperty($mod,$item_id,array('dateformat','timeformat'));
				$dayfmt = $idata['dateformat'];
				$timefmt = $idata['timeformat'];
			}
			$datetimefmt = $utils->DateTimeFormat(FALSE,$admin,TRUE,!$overday,$dayfmt,$timefmt);
			$t = $mod->Lang('longdays');
			$dnames = "'".str_replace(",","','",$t)."'";
			$t = $mod->Lang('shortdays');
			$sdnames = "'".str_replace(",","','",$t)."'";
			$t = $mod->Lang('longmonths');
			$mnames = "'".str_replace(",","','",$t)."'";
			$t = $mod->Lang('shortmonths');
			$smnames = "'".str_replace(",","','",$t)."'";
			$t = $mod->Lang('meridiem');
			$meridiem = "'".str_replace(",","','",$t)."'";

			$js2 = <<<EOS
function suretrim(str) {
 if (typeof String.prototype.trim === "function") {
  return str.trim();
 } else {
  return str.replace(/^\s+|\s+$/gm,'');
 }
}
function validate(ev) {
 var ok = true,
  f = '$datetimefmt',
  fmt = new DateFormatter({
   longDays: [$dnames],
   shortDays: [$sdnames],
   longMonths: [$mnames],
   shortMonths: [$smnames],
   meridiem: [$meridiem],
   ordinal: function(number) {
    var n = number % 10,
     suffixes = {
      1: 'st',
      2: 'nd',
      3: 'rd'
     };
    return Math.floor(number % 100 / 10) === 1 || !suffixes[n] ? 'th' : suffixes[n];
   }
  }),
  tg = document.getElementById('{$id}when'),
  ds = null,
  de = null;
 if (tg !== null) {
  var str = suretrim(tg.value);
  ds = fmt.parseDate(str,f); //null upon failure
  ok = (ds !== null);
 }
 if (ok) {
  tg = document.getElementById('{$id}until');
  if (tg !== null) {
   str = suretrim(tg.value);
   de = fmt.parseDate(str,f);
   ok = (de !== null);
  }
 }

EOS;
			if ($nopast) {
				$js2 .= <<<'EOS'
 if (ok) {
  dn = new Date();
  ok = (ds === null || (ds > dn && (de === null || de > ds)));
 }
EOS;
			} else {
				$js2 .= <<<'EOS'
 ok = ok && (ds === null || de === null || de > ds);

EOS;
			}
			$js2 .= <<<EOS
 if (!ok) {
  showerr('{$mod->Lang('err_badtime')}',tg);
 } else {
  tg = document.getElementById('{$id}name');
  str = suretrim(tg.value);
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
   tg = document.getElementById('{$id}name'),
   str = suretrim(tg.value);
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
  var clicker = this,
   tg = document.getElementById('{$id}contact');
  ok = Mailcheck.check(tg,{
{$domains}
   distanceFunction: function(str1,str2) {
    var lv = Levenshtein;
    return lv.get(str1,str2);
   },
   suggested: function(tg,suggest) {
    var msg = '{$mod->Lang('meaning_type','%s')}'.replace('%s','<strong>'+suggest.full+'</strong>');
    $.alertable.confirm(msg, {
     html: true,
     okName: '{$mod->Lang('yes')}',
     cancelName: '{$mod->Lang('no')}'
    }).then(function() {
     tg.value = suggest.full;
     $(clicker).trigger('click');
    }, function() {
     tg.focus();
    });
   },
   empty: function(tg) {
    showerr('{$mod->Lang('err_nocontact')}',tg);
   }
  });
 }

EOS;
		if ($admin)
			$js6 = '';
		else {
			$js6 = <<<EOS
 if (ok) {
  tg = document.getElementById('{$id}captcha');
  str = suretrim(tg.value);
  if (str == false) {
   showerr('{$mod->Lang('err_nocaptcha')}',tg);
   ok = false;
  }
 }

EOS;
		}
		$js7 = <<<'EOS'
 if (ok) {
  return true;
 }
 ev.stopImmediatePropagation();
 ev.preventDefault();
 return false;
}
EOS;
		return $js1.$js2.$js3.$js6.$js7;
	}
}
