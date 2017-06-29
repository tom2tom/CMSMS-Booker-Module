<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: CSV - functions for import/export of module data
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker;

class Import
{
	const DEFAULTPASS = 'change2468ASAP!'; //this exceeds complexity 3, but not 4

	private function ToChr($match)
	{
		$st = ($match[0][0] == '&') ? 2:1;
		return chr(substr($match[0],$st,2));
	}

	/*
	GetSplitLine:
	Gets line from csv file, splits it into array, reinstates all 'commas' in field values
	@fh: connection/handle for file being processed
	Returns: array or FALSE
	*/
	private function GetSplitLine(&$fh)
	{
		do {
			$fields = fgetcsv($fh,4096);
			if (is_null($fields) || $fields == FALSE) {
				return FALSE;
			}
		} while (!isset($fields[1]) && is_null($fields[0])); //blank line
		$some = FALSE;
		//convert any separator supported by exporter
		foreach ($fields as &$one) {
			if ($one) {
				$some = TRUE;
				$one = trim(preg_replace_callback(
					['/&#\d\d;/','/%\d\d%/'],[$this,'ToChr'],$one));
			}
		}
		unset($one);
		if ($some) {
			return $fields;
		}
		return FALSE; //ignore lines with all fields empty
	}

	/**
	ImportItems:
	Import resource(s) and/or group(s) data from uploaded CSV file. Can handle
	re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = count of imports or lang key for message
	*/
	public function ImportItems(&$mod, $id)
	{
		$filekey = $id.'csvfile';
		if (isset($_FILES) && isset($_FILES[$filekey])) {
			$file_data = $_FILES[$filekey];
			$parts = explode('.',$file_data['name']);
			$ext = end($parts);
			if ($file_data['type'] != 'text/csv'
			 || !($ext == 'csv' || $ext == 'CSV')
				 || $file_data['size'] <= 0 || $file_data['size'] > 25600 //$max*1000
				 || $file_data['error'] != 0) {
				return [FALSE,'err_file'];
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh) {
				return [FALSE,'err_perm'];
			}
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return [FALSE,'err_file'];
			}
			//file-column-name to fieldname translation
			$translates = [
			 '#Isgroup'=>'isgroup', //not a real field
			 'Alias'=>'alias',
			 '#Name'=>'name',
			 'Description'=>'description',
			 'Keywords'=>'keywords',
			 'Membersnamed'=>'membersname',
			 'Choosername'=>'pickname',
			 'Inchooser'=>'pickthis',
			 'Choosemembers'=>'pickmembers',
			 'Image'=>'image',
			 'Available'=>'available',
			 'Slottype'=>'slottype',
			 'Slotcount'=>'slotcount',
			 'BookingSlots'=>'bookcount',
			 'Leadtype'=>'leadtype',
			 'Leadcount'=>'leadcount',
			 'Rationcount'=>'rationcount',
			 'Keeptype'=>'keeptype',
			 'Keepcount'=>'keepcount',
			 'Grossfees'=>'grossfees',
			 'Taxrate'=>'taxrate',
			 'PayInterface'=>'paymentiface',
			 'Latitude'=>'latitude',
			 'Longitude'=>'longitude',
			 'Timezone'=>'timezone',
			 'Dateformat'=>'dateformat',
			 'Timeformat'=>'timeformat',
			 'Listformat'=>'listformat',
			 'Stylesfile'=>'stylesfile',
			 'Approver'=>'approver',
			 'Approvercontact'=>'approvercontact',
			 'SMSprefix'=>'smsprefix',
			 'SMSpattern'=>'smspattern',
			 'FormInterface'=>'formiface',
			 'Feugroup'=>'feugroup',
			 'Owner'=>'owner',
			 'Cleargroup'=>'cleargroup',
			 'Allocategroup'=>'subgrpalloc',
			 'Notice'=>'bulletin',
			 'Ingroups'=>'ingroups', //not a real field
 			 'Update'=>'update' //not a real field
			];
			/* non-public
			'item_id'
			'subgrpdata'
			'active'
			*/

			// TODO $mod->FeeTable data for items

			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return [FALSE,'err_file'];
			}
			//setup for interpretation
			$offers = []; //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE) {
					$offers[$col] = $priv;
				} elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return [FALSE,'err_file'];
				}
			}

			$utils = new Utils();
			$periods = [-3=>'any',-2=>'all',-1=>'fixed'] + $utils->TimeIntervals();
			$deftypes = ['slottype'=>1,'leadtype'=>2,'keeptype'=>5];
			$icount = 0;
			$db = $mod->dbHandle;
			$sqlg = 'INSERT INTO '.$mod->GroupTable.' (child,parent,likeorder,proximity) VALUES (?,?,?,?)';
			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = [];
					$save = FALSE;
					$is_group = FALSE;
					$in_grps = FALSE;
					$update = FALSE;
					foreach ($imports as $i=>$one) {
						$k = $offers[$i];
						if ($one) {
							switch ($k) {
							 case 'isgroup':
								$is_group = !($one == 'no' || $one == 'NO');
								break;
							 case 'alias': //(no duplication check)
							 case 'name': //# ditto
							 case 'description':
							 case 'keywords':
							 case 'image':
							 case 'available': //NO sanity check
							 case 'timezone':
							 case 'dateformat':
							 case 'timeformat':
							 case 'stylesfile':
							 case 'approver':
							 case 'approvercontact': //TODO block injection
							 case 'membersname':
							 case 'pickname':
							 case 'paymentiface':
 							 case 'formiface':
							 case 'smsprefix':
							 case 'smspattern':
							 case 'bulletin':
								$data[$k] = trim($one);
								$save = TRUE;
								break;
							 case 'slottype':
							 case 'leadtype':
							 case 'keeptype':
								$v = trim($one);
								$t = array_search($v,$periods);
								if ($t === FALSE) {
									if (array_key_exists($v,$periods)) {
										if ($v >= 0) {
											$t = (int)$v;
										} else {
											$t = -1;
										}
									} else {
										$t = $deftypes[$k];
									}
								} elseif ($t < 0) {
									$t = -1;
								}
								$data[$k] = $t;
								$save = TRUE;
								break;
							 case 'slotcount':
								$data[$k] = ($data['slottype'] >= 0) ? (int)$one : NULL;
								$save = TRUE;
								break;
							 case 'leadcount':
								$data[$k] = ($data['leadtype'] >= 0) ? (int)$one : NULL;
								$save = TRUE;
								break;
							 case 'keepcount':
								$data[$k] = ($data['keeptype'] >= 0) ? (int)$one : NULL;
								$save = TRUE;
								break;
							 case 'bookcount':
							 case 'rationcount':
							 case 'listformat':
							 case 'subgrpalloc':
								$data[$k] = (int)$one;
								$save = TRUE;
								break;
							 case 'taxrate':
							 	if (is_numeric($one)) {
									$data[$k] = (float)$one;
								} elseif (strpos($one,'%') !== FALSE) {
									$t = str_replace('%','',$one);
								 	if (is_numeric($t)) {
										$data[$k] = $t/100;
									} else {
										$data[$k] = 0.0;
									}
								} else {
									$data[$k] = 0.0;
								}
							 	break;
							 case 'latitude':
							 case 'longitude':
								$data[$k] = (float)$one;
								$save = TRUE;
								break;
							 case 'feugroup': //identifier, TODO convert to I
								$data[$k] = 0;
								$save = TRUE;
								break;
							 case 'owner': //identifier TODO convert to I
								$data[$k] = 0;
								$save = TRUE;
								break;
							 case 'pickthis':
							 case 'pickmembers':
							 case 'grossfees':
							 case 'cleargroup':
								$data[$k] = ($one == 'no' || $one == 'NO') ? 0:1;
								$save = TRUE;
								break;
							 case 'ingroups':
								$in_grps = $one; //parse later
								break;
							 case 'update':
							 	if (is_numeric($one)) {
									$update = (int)$one;
								} else {
									$update = !($one == 'no' || $one == 'NO');
								}
								break;
							 default:
								return [FALSE,'err_file'];
							}
						} else {
							switch ($k) {
							 case 'pickthis':
								$data[$k] = ($is_group) ? 1:0;
								break;
							 case 'slottype':
							 case 'leadtype':
							 case 'keeptype':
								$data[$k] = $deftypes[$k];
								break;
							 case 'taxrate':
								$data[$k] = 0.0;
								break;
							 case 'grossfees':
								$data[$k] = 1;
								break;
							 case 'pickmembers': //no pickable members
							 case 'cleargroup': //no clear group
								$data[$k] = 0;
								break;
							 case 'listformat':
								$data[$k] = ($is_group) ? \Booker::LISTRS:\Booker::LISTSU;
								break;
							 case 'isgroup': //ignore fake fields
							 case 'ingroups':
							 case 'update':
								break;
							 default:
								$data[$k] = NULL;
							}
						}
					}

					if ($save) {
						$done = FALSE;
						if ($update) { //TODO robust UPSERT
							if (is_numeric($update)) {
								$sql = 'SELECT item_id FROM '.$mod->ItemTable.' WHERE item_id=?';
								$item_id = $utils->SafeGet($sql,[$update],'one');
							} else {
								$item_id = FALSE;
							}
							if (!$item_id) {
								$item_id = $utils->GetItemID($mod,$data['name']);
							}
							if ($item_id) {
								//TODO cache $item_id=>$data['name']
								$namers = implode('=?,',array_keys($data));
								$sql = 'UPDATE '.$mod->ItemTable.' SET '.$namers.'=? WHERE item_id=?';
								$args = array_values($data);
								$args[] = $item_id;
								if ($utils->SafeExec($sql,$args)) {
									$icount++;
									$done = TRUE;
								}
							}
						}
						if (!$done) {
							$namers = implode(',',array_keys($data));
							$fillers = str_repeat('?,',count($data)-1);
							$sql = 'INSERT INTO '.$mod->ItemTable.' (item_id,'.$namers.',active) VALUES (?,'.$fillers.'?,1)';
							$args = array_values($data);
							$t = ($is_group) ? $mod->ItemTable.'_gseq':$mod->ItemTable.'_seq';
							$item_id = $db->GenID($t);
							array_unshift($args,$item_id);
							if ($utils->SafeExec($sql,$args)) {
								$icount++;
							} else {
								return [FALSE,'err_system'];
							}
						}
						if ($in_grps) {
							//setup groups table
							$sql = [];
							$args = [];
							$data = explode('||',$in_grps); //TODO actual separator
							foreach ($data as $i=>$one) {
								//find id of this name
								$t = $utils->GetItemID($mod,$one);
								if ($t === FALSE) {
									return [FALSE,'err_file'];
								}
								$sql[] = $sqlg;
								$args[] = [$item_id,$t,-1,$i+1]; //likeorder unknowable in this context, proximity assumes blank canvas!
							}
							$utils->SafeExec($sql,$args);
						}
					}
				}
			}
			fclose($fh);
			if ($icount) {
				return [TRUE,$icount];
			}
			return [FALSE,'none'];
		}
		return [FALSE,'error'];
	}

	/**
	ImportFees:
	Import fee-rules data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = count of imports or lang key for message
	*/
	public function ImportFees(&$mod, $id)
	{
		$filekey = $id.'csvfile';
		if (isset($_FILES) && isset($_FILES[$filekey])) {
			$file_data = $_FILES[$filekey];
			$parts = explode('.',$file_data['name']);
			$ext = end($parts);
			if ($file_data['type'] != 'text/csv'
			 || !($ext == 'csv' || $ext == 'CSV')
				 || $file_data['size'] <= 0 || $file_data['size'] > 25600 //arbitrary size
				 || $file_data['error'] != 0) {
				return [FALSE,'err_file'];
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh) {
				return [FALSE,'err_perm'];
			}
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return [FALSE,'err_file'];
			}
			//file-column-name to fieldname translation
			$translates = [
			 '#ID'=>'item_id', //interpreted
			 'Description'=>'description',
			 'Duration'=>'slottype', //interpreted
			 'Count'=>'slotcount',
			 '#Fee'=>'fee',
			 'Condition'=>'feecondition',
			 'Type'=>'usercondition',
			 'Update'=>'update' //not a real field
			];
			/* non-public
			 =>'signature'
			 =>'condorder,
			 =>'active'
			*/
			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return [FALSE,'err_file'];
			}
			//setup for interpretation
			$offers = []; //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE) {
					$offers[$col] = $priv;
				} elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return [FALSE,'err_file'];
				}
			}

			$funcs = new Payment();
			$utils = new Utils();
			$periods = [-3=>'any',-2=>'all',-1=>'fixed'] + $utils->TimeIntervals();
			$minpay = $mod->GetPrefence('minpay');
			$icount = 0;

			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = [];
					$save = FALSE;
					$update = FALSE;
					foreach ($imports as $i=>$one) {
						$k = $offers[$i];
						if ($one) {
							switch ($k) {
							 case 'item_id':
								$t = $utils->GetItemID($mod,trim($one));
								if ($t === FALSE) {
 									return [FALSE,'err_file'];
								}
								$data[$k] = $t;
								$save = TRUE;
								break;
							 case 'description':
							 case 'feecondition': //no validation here!
							 case 'usercondition': //ditto
								$data[$k] = trim($one);
								$save = TRUE;
								break;
							 case 'fee':
							 	$t = (float)number_format((float)$one,2,'.','');
								if ($minpay > 0.0 && $t < $minpay) {
									$t = 0.0;
								}
								$data[$k] = $t;
								$save = TRUE;
							 	break;
							 case 'slottype':
								$t = array_search(trim($one),$periods);
								if ($t < 0)
									$t = -1;
								elseif ($t === FALSE)
									$t = 1; //default = hour TODO something more specific
								$data[$k] = $t;
								$save = TRUE;
								break;
							 case 'slotcount':
								if (isset($data['slottype']) && $data['slottype'] < 0) {
									$data[$k] = NULL;
								} else {
									$data[$k] = (int)$one;
								}
								$save = TRUE;
								break;
							 case 'update':
							 	if (is_numeric($one)) {
									$update = (int)$one;
								} else {
									$update = !($one == 'no' || $one == 'NO');
								}
								break;
							default:
								return [FALSE,'err_file'];
							}
						} else {
							switch ($k) {
							 case 'slottype':
 								$data[$k] = 1; //default = hour TODO something more specific
								break;
							 case 'slotcount':
							if (isset($data['slottype']) && $data['slottype'] < 0) {
									$data[$k] = NULL;
								} else {
									$data[$k] = 1;
								}
								break;
							 case 'update': //ignore this
								break;
//							 case 'feecondition':
//							 case 'description':
							 default:
 								$data[$k] = NULL;
								break;
							}
						}
					}
					if ($save) {
						$done = FALSE;
						if ($update) { //TODO robust UPSERT
							if (is_numeric($update)) {
								$sql = 'SELECT fee_id FROM '.$mod->FeeTable.' WHERE fee_id=?';
								$cid = $utils->SafeGet($sql,[$update],'one');
							} else {
								$cid = FALSE;
							}
/*							if (!$cid) {
								$sql = 'SELECT fee_id FROM '.$mod->FeeTable.' WHERE TODO';
								$args = $TODO;
								$cid = $utils->SafeGet($sql,$args,'one');
							}
*/
							if ($cid) {
								//TODO cache $cid=>X
								$namers = implode('=?,',array_keys($data));
								$sql = 'UPDATE '.$mod->FeeTable.' SET '.$namers.'=? WHERE fee_id=?';
								$args = array_values($data);
								$args[] = $cid;
								if ($utils->SafeExec($sql,$args)) {
									$icount++;
									$done = TRUE;
								}
							}
						}
						if (!$done) {
							$namers = implode(',',array_keys($data));
							$fillers = str_repeat('?,',count($data)-1);
							$sql = 'INSERT INTO '.$mod->FeeTable.' (fee_id,signature,'.$namers.',condorder,active) VALUES (?,?,'.$fillers.'?,?,1)';
							$args = array_values($data);
							$cid = $mod->dbHandle->GenID($mod->FeeTable.'_seq');
							$sig = $funcs->GetFeeSignature($data);
							array_unshift($args,$cid,$sig);
							$args[] = -1; //TODO useful order
							if ($utils->SafeExec($sql,$args)) {
								$icount++;
							} else {
								return [FALSE,'err_system'];
							}
						}
					}
				}
			}
			fclose($fh);
			if ($icount) {
				return [TRUE,$icount];
			}
			return [FALSE,'none'];
		}
		return [FALSE,'err_system'];
	}

	/*
	Store some registered-booker data in Booker and Auther user-table(s)
	Doesn't record property - active
	@mod: reference to Booker-module class object
	@utils: reference to Utils-class object
	@ufuncs: Userops-class object
	@data: array of parameters
	@password: plaintext password for the user, or FALSE
	@passhash: H-unpacked (sorta raw) previous password for the user, or FALSE
	@update: boolean whether to process data as a update (as opposed to an addition)
	Returns: for an update - boolean, for an addition bookerid or FALSE
	 */
	protected function RecordRegisteredBooker(&$mod, &$utils, $ufuncs, $data, $password, $passhash, $update)
	{
		$name = $data['name'];
		$address = $data['address'];
 		$phone = $data['phone'];
		$login = $data['publicid'];
		if ($passhash) {
			//generate a validation-ready (sufficiently-tough) interim password
			$t = str_shuffle(uniqid(self::DEFAULTPASS, TRUE));
			$password = substr($t,0,32);
		}
		if ($update) {
			$bookerid = $data['bookerid'];
			$oldname = funcTODO($login,$name,$bookerid); //$bookerid -> lookup current 'publicid' -> get $oldname from Auther
			$oldlogin = funcTODO($login,$name,$bookerid); //$bookerid -> lookup current 'publicid' == $oldlogin
//			$utils->GetUserProperties($mod, ['publicid'=>$oldlogin,'name'=>????]);
			$ret = $ufuncs->ChangeUser($mod,$bookerid,$name,$address,$phone,FALSE,$oldlogin,$login,$password); //$ret = boolean
		} else {
			$bookerid = $ufuncs->AddUser($mod,$name,$address,$phone,1,$login,$password); //$bookerid = enum or FALSE
			$ret = $bookerid;
		}
		if ($ret && $passhash) {
			//no Auther-API for changing raw passhash ...
			$cid = $mod->GetPreference('authcontext',0); //OR $afuncs->GetContext();
			$pref = \cms_db_prefix();
			$mod->dbHandle->Execute('UPDATE '.$pref.'module_auth_users SET privhash=? WHERE publicid=? AND context_id=?',
				[pack('H*',$passhash),$login,$cid]);
		}
		if ($ret) {
			//possible non-Auther properties to be recorded locally
			$namers = [];
			foreach (['phone','type','displayclass'] as $key) {
				if (isset($data[$key]) && $data[$key] != NULL) {
					$namers[$key]= $data[$key];
				}
			}
			if ($namers) {
				$utils->SetUserProperties($mod,$bookerid,$namers);
				//TODO OR just $sql & execute
			}
		}
		return $ret;
	}

	/**
	ImportBookers:
	Import booker(s) data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = count of imports or lang key for message
	*/
	public function ImportBookers(&$mod, $id)
	{
		$filekey = $id.'csvfile';
		if (isset($_FILES) && isset($_FILES[$filekey])) {
			$file_data = $_FILES[$filekey];
			$parts = explode('.',$file_data['name']);
			$ext = end($parts);
			if ($file_data['type'] != 'text/csv'
			 || !($ext == 'csv' || $ext == 'CSV')
				 || $file_data['size'] <= 0 || $file_data['size'] > 25600 //$max*1000
				 || $file_data['error'] != 0) {
				return [FALSE,'err_file'];
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh) {
				return [FALSE,'err_perm'];
			}
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return [FALSE,'err_file'];
			}
			//file-column-name to fieldname translation
			$translates = [
			 '#Name'=>'name',
			 'Login'=>'publicid',
			 'Password'=>'password', //not a real field
			 'Passhash'=>'passhash', //ditto
			 '#Email'=>'address',
			 'Phone'=>'phone',
			 'Postpayer'=>'type1', //interpreted
			 'Recorder'=>'type2', //ditto
			 'Usertype'=>'type3', //ditto
			 'Displaytype'=>'displayclass',
			 'Update'=>'update' //not a real field
			];
			/* non-public
			 =>'booker_id'
			 =>'addwhen'
			 =>'active'
			*/
			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return [FALSE,'err_file'];
			}
			//setup for interpretation
			$offers = []; //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE) {
					$offers[$col] = $priv;
				} elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return [FALSE,'err_file'];
				}
			}
			$utils = new Utils();
			//for update checks (name is encrypted or NULL)
//			$exist = $utils->SafeGet('SELECT booker_id,publicid,name FROM '.$mod->BookerTable.' ORDER BY booker_id',FALSE);

			$afuncs = NULL;
			$cfuncs = new Crypter($mod);
			$ufuncs = new Userops($mod);
			$dt = new \DateTime('now',new \DateTimeZone('UTC'));
			$st = $dt->getTimestamp();
//			$skip = FALSE;
			$icount = 0;

			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = [];
					$password = FALSE; //if set, store via Auther module;
					$passhash = FALSE; //if set, store unpack('H*',$passhash) via Auther module
					$save = FALSE;
					$update = FALSE;
					foreach ($imports as $i=>$one) {
						$k = $offers[$i];
						if ($one) {
							switch ($k) {
							 case 'name':
								$data[$k] = $ufuncs->SanitizeName($one);
								$save = TRUE;
								break;
							 case 'publicid':
								$data[$k] = trim($one);
								$save = TRUE;
								break;
							 case 'passhash':
							 case 'password':
								$$k = trim($one); //park, pending store in Auther
								$save = TRUE;
								break;
							 case 'address':
 								$t = trim($one);
								if (!preg_match(\Booker::PATNADDRESS,$t)) {
									return [FALSE,'err_file'];
								}
								$data[$k] = $t; //encrypt later, if relevant
								$save = TRUE;
								break;
							 case 'phone':
 								$t = str_replace(' ','',$one);
						 		if (!preg_match(\Booker::PATNPHONE,$t)) {
									return [FALSE,'err_file'];
								}
								$data[$k] = $t; //encrypt later, if relevant
								$save = TRUE;
								break;
							 case 'type1':
							 	$t = ($one == 'no' || $one == 'NO') ? 0:10; //permission-flag
								$k = 'type';
								if (isset($data[$k])) {
									$data[$k] += $t;
								} else {
									$data[$k] = $t;
								}
								$save = TRUE;
								break;
							 case 'type2':
								$t = ($one == 'no' || $one == 'NO') ? 0:20; //ditto
								$k = 'type';
								if (isset($data[$k])) {
									$data[$k] += $t;
								} else {
									$data[$k] = $t;
								}
								$save = TRUE;
								break;
							 case 'type3':
								if (!is_numeric($one)) {
									return [FALSE,'err_file'];
								}
								$t = (int)$one;
								if ($t < 0 || $t > 9) { //base-types 0..9
									$t = 0;
								}
								$k = 'type';
								if (isset($data[$k])) {
									$data[$k] += $t;
								} else {
									$data[$k] = $t;
								}
								$save = TRUE;
								break;
							 case 'displayclass':
								if (!is_numeric($one)) {
									return [FALSE,'err_file'];
								}
								$t = (int)$one;
								if ($t < 1 || $t > \Booker::USERSTYLES) {
									$t = 1;
								}
								$data[$k] = $t;
								$save = TRUE;
								break;
							 case 'update':
							 	if (is_numeric($one)) {
									$update = (int)$one;
								} else {
									$update = !($one == 'no' || $one == 'NO');
								}
								break;
							default:
								return [FALSE,'err_file'];
							}
						} else {
							switch ($k) {
							 case 'displayclass':
								$data[$k] = 1;
								//no break here
							 case 'passhash':
								//no break here
							 case 'password':
								$save = TRUE;
							 case 'type1': //ignore these
							 case 'type2':
							 case 'type3':
							 case 'update':
								break;
							 default:
 								$data[$k] = NULL;
								break;
							}
						}
					}
					if ($save) {
						$done = FALSE;
						if (!($password || $passhash)) {
							if (!$afuncs) {
								$amod = \cms_utils::get_module('Auther');
								if ($amod) {
									$afuncs = new \Auther\Auth($amod, $mod->GetPreference('authcontext', 0));
									unset($amod);
								}
							}
							if ($afuncs) {
								$password = $afuncs->GetConfig('default_password');
							}
							if (!$password) {
								$password = self::DEFAULTPASS;
							}
						}
						if ($update) { //TODO robust UPSERT
							if (is_numeric($update)) {
								$sql = 'SELECT booker_id FROM '.$mod->BookerTable.' WHERE booker_id=?';
								$bookerid = $utils->SafeGet($sql,[$update],'one');
							} else {
								$bookerid = FALSE;
							}
							if (!$bookerid) {
								$sql = 'SELECT booker_id FROM '.$mod->BookerTable;
								if ($data['publicid']) {
									$sql .= ' WHERE publicid=?';
									$args = [$data['publicid']];
									$bookerid = $utils->SafeGet($sql,$args,'one');
								} elseif ($data['name']) {
									$sql .= ' WHERE name=?';
									$args = [$data['name']];
									$bookerid = $utils->SafeGet($sql,$args,'one');
								} else {
									$bookerid = FALSE;
								}
							}
							if ($bookerid) {
								if ($data['publicid']) {
									$data['bookerid'] = $bookerid;
									if ($this->RecordRegisteredBooker($mod,$utils,$ufuncs,$data,$password,$passhash,TRUE)) {
										$icount++;
										$done = TRUE;
									}
								} else {
									if($data['address']) {
										$data['address'] = $cfuncs->encrypt_value($data['address']);
									}
									if($data['phone']) {
										$data['phone'] = $cfuncs->encrypt_value($data['phone']);
									}
									$namers = implode('=?,',array_keys($data));
									$sql = 'UPDATE '.$mod->BookerTable.' SET '.$namers.'=? WHERE booker_id=?';
									$args = array_values($data);
									$args[] = $bookerid;
									if ($utils->SafeExec($sql,$args)) {
										$icount++;
										$done = TRUE;
									}
								}
							}
						}
						if (!$done) {
							if ($data['publicid']) {
								if ($this->RecordRegisteredBooker($mod,$utils,$ufuncs,$data,$password,$passhash,FALSE)) {
									$icount++;
								}
							} else {
								if($data['address']) {
									$data['address'] = $cfuncs->encrypt_value($data['address']);
								}
								if($data['phone']) {
									$data['phone'] = $cfuncs->encrypt_value($data['phone']);
								}
								$namers = implode(',',array_keys($data));
								$fillers = str_repeat('?,',count($data)-1);
								$sql = 'INSERT INTO '.$mod->BookerTable.' (booker_id,'.$namers.',addwhen) VALUES (?,'.$fillers.'?,?)';
								$args = array_values($data);
								$bookerid = $mod->dbHandle->GenID($mod->BookerTable.'_seq');
								array_unshift($args,$bookerid);
								$args[] = $st;
								if ($utils->SafeExec($sql,$args)) {
									$icount++;
								} else {
									return [FALSE,'err_system'];
								}
							}
						}
//					} else {
//						$skip = TRUE;
					}
//TODO if ($done) cache $bookerid=>$data['name'].$data['publicid'] to ignore repetition ?
				}
			}
			fclose($fh);
//			if ($skip)
//				return array(FALSE,'warn_duplicate');
//			else
			if ($icount) {
				return [TRUE,$icount];
			}
			return [FALSE,'none'];
		}
		return [FALSE,'err_system'];
	}

	/**
	ImportBookings:
	Import booking(s) data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	@item_id: optional resource|group id which must be matched
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = count of imports or lang key for message
	*/
	public function ImportBookings(&$mod, $id, $item_id=FALSE)
	{
		$filekey = $id.'csvfile';
		if (isset($_FILES) && isset($_FILES[$filekey])) {
			$file_data = $_FILES[$filekey];
			$parts = explode('.',$file_data['name']);
			$ext = end($parts);
			if ($file_data['type'] != 'text/csv'
			 || !($ext == 'csv' || $ext == 'CSV')
				 || $file_data['size'] <= 0 || $file_data['size'] > 25600 //$max*1000
				 || $file_data['error'] != 0) {
				return [FALSE,'err_file'];
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh) {
				return [FALSE,'err_perm'];
			}
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return [FALSE,'err_file'];
			}
			//file-column-name to fieldname translation
			$translates = [
			 '#ID'=>'item_id', //intepreted
			 'Count'=>'subgrpcount',
			 'Lodged'=>'lodged', //ditto
			 'Approved'=>'approved', //ditto
			 'Removed'=>'removed', //ditto
			 '#Start'=>'slotstart', //ditto
			 'End'=>'slotend', //ditto
			 'Bookingstatus'=>'status',
			 '#User'=>'booker_id', //ditto
			 'Usercomment'=>'comment',
			 'Feedue'=>'fee',
		 	 'Feepaid'=>'feepaid',
			 'Feestatus'=>'statpay',
			 'Active'=>'active',
			 'Transaction'=>'gatetransaction',
			 'Update'=>'update' //not a real field
			];
			/* non-public fields
			=>'bkg_id' maybe value of update field
			*/
			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return [FALSE,'err_file'];
			}
			//setup for interpretation
			$offers = []; //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE) {
					$offers[$col] = $priv;
				} elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return [FALSE,'err_file'];
				}
			}

			$afuncs = NULL;
			$bfuncs = NULL;
			$sfuncs = NULL;
			$utils = new Utils();
			$dts = new \DateTime('@0',NULL);
			$dte = clone $dts;
			$propstore = [];
			$bookers = [];
			$regusers = NULL;
			$skip = FALSE;
			$icount = 0;
			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = [];
					$save = FALSE;
					$update = FALSE;
					$dts->setTimestamp(0);
					$dte->setTimestamp(0);
					foreach ($imports as $i=>$one) {
						$k = $offers[$i];
						$one = trim($one);
						if ($one) {
							switch ($k) {
							 case 'item_id':
								$t = $utils->GetItemID($mod,$one); //TODO cache result & lookup there first
								if ($t === FALSE) {
									return [FALSE,'err_file'];
								}
								if ($item_id && $t !== $item_id)
									continue;
								$data[$k] = $t;
								$save = TRUE;
								if (!array_key_exists($t,$propstore)) {
									$propstore[$t] = $utils->GetItemProperties($mod,$t,['slottype','slotcount'],TRUE);
									if (!$propstore[$t])
										return [FALSE,'err_system'];
								}
								break;
							 case 'lodged':
							 case 'approved':
							 case 'removed':
							 case 'slotstart':
								$lvl = error_reporting(0);
								$t = $dts->modify($one);
								error_reporting($lvl);
								if ($t) {
									$data[$k] = $dts->getTimestamp(); //store UTC timestamp
								} else {
									switch ($k) {
									 case 'lodged':
										$k = 'err_TODO'; //c.f. invalid_type
										break;
									 case 'approved':
										$k = 'err_TODO';
										break;
									 case 'removed':
										$k = 'err_TODO';
										break;
									 case 'slotstart':
										$k = 'err_badstart';
										break;
									}
									return [FALSE,$k];
								}
								break;
							 case 'slotend': //proxy for #End
								$lvl = error_reporting(0);
								$t = $dte->modify($one);
								error_reporting($lvl);
								if ($t) {
									$data[$k] = $dte->getTimestamp(); //store UTC timestamp
								} else {
									return [FALSE,'err_badend'];
								}
								break;
							 case 'booker_id':
								if (array_key_exists($one,$bookers)) {
									$data[$k] = $bookers[$one];
								} else {
									$sql = 'SELECT booker_id FROM '.$mod->BookerTable.' WHERE name=? OR publicid=?';
									$t = $mod->dbHandle->GetOne($sql,[$one,$one]);
									if ($t) {
										$t = (int)$t;
										$bookers[$one] = $t;
										$data[$k] = $t;
									} else {
										//check for registered user named $one
										if (!$afuncs) {
											$amod = \cms_utils::get_module('Auther');
											if ($amod) {
												$afuncs = new \Auther\Auth($amod,$mod->GetPreference('authcontext',0));
												unset($amod);
											}
										}
										if ($afuncs && !$regusers) {
											$regusers = $afuncs->GetPublicUsers(FALSE);
										}
										if ($regusers) {
											foreach ($regusers as $row) {
												if ($row['name'] == $one) {
													$t = $mod->dbHandle->GetOne($sql,[$one,$row['publicid']]);
													if ($t) {
														$bookers[$one] = (int)$t;
														$data[$k] = (int)$t;
														$save = TRUE;
														break 2;
													}
												}
											}
										}
										return [FALSE,'err_baduser'];
									}
								}
								$save = TRUE;
								break;
							 case 'subgrpcount':
							 case 'statpay':
							 case 'status':
								$data[$k] = (int)$one;
								$save = TRUE;
								break;
							 case 'fee':
						 	 case 'feepaid':
								$data[$k] = (float)$one;
								$save = TRUE;
								break;
							 case 'active':
								$data[$k] = ($one == 'no' || $one == 'NO') ? 0:1;
								$save = TRUE;
								break;
							 case 'comment':
							 case 'gatetransaction':
								$save = TRUE;
								break;
							 case 'update':
							 	if (is_numeric($one)) {
									$update = (int)$one;
								} else {
									$update = !($one == 'no' || $one == 'NO');
								}
								break;
							default:
								return [FALSE,'err_file'];
							}
						} else {
							switch ($k) {
							 case 'slotend':
							 case 'status':
							 case 'statpay':
								$data[$k] = -1; //update-needed flag
								break;
							 case 'subgrpcount':
							 case 'active':
								$data[$k] = 1;
								break;
							 case 'fee':
						 	 case 'feepaid':
								$data[$k] = 0.00;
								break;
							 case 'update':
								break; //no NULL
							 default:
								$data[$k] = NULL;
							}
						}
					}

					if (empty($data['slotstart']) || empty($data['slotend'])) {
						return [FALSE,'err_badtime'];
					}

					if ($save) {
						if ($data['slotend'] == -1) {
							$t = $utils->GetInterval($mod,$data['item_id'],'slot');
							$data['slotend'] = $data['slotstart'] + $t - 1;
						}
						if ($data['statpay'] == -1) {
							if ($data['fee'] == 0) {
								$data['statpay'] = \Booker::STATFREE;
							} elseif ($data['feepaid'] >= $data['fee']) {
								$data['statpay'] = \Booker::STATPAID;
							} elseif ($data['feepaid'] > 0) {
								$data['statpay'] = \Booker::STATPARTPAID;
							} else {
								$data['statpay'] = \Booker::STATPAYABLE;
							}
						}
						if ($data['status'] == -1) {
							if ($data['statpay'] == \Booker::STATFREE || $data['statpay'] == \Booker::STATPAID) {
								$data['status'] = \Booker::STATOK;
							} else {
								$data['status'] = \Booker::STATNFEE;
							}
						}

						$done = FALSE;
						if ($update) { //TODO robust UPSERT
							if (is_numeric($update)) {
								$sql = 'SELECT bkg_id FROM '.$mod->OnceTable.' WHERE bkg_id=?';
								$bkgid = $utils->SafeGet($sql,[$update],'one');
							} else {
								$bkgid = FALSE;
							}

							if ($bkgid) {
								//TODO cache $bkgid=>X
								$namers = implode('=?,',array_keys($data));
								$sql = 'UPDATE '.$mod->OnceTable.' SET '.$namers.'=? WHERE bkg_id=?';
								$args = array_values($data) + ['a'=>$bkgid];
								if ($utils->SafeExec($sql,$args)) {
									if ($data['status'] > \Booker::STATMAXREQ && $data['status'] <= \Booker::STATMAXOK) {
										if ($bfuncs == NULL) {
											$bfuncs = new Bookingops();
										}
										$save = $data;
										if ($bfuncs->ModifyBkg($mod,$utils,$data['item_id'],$data)) {
											if ($data != $save) {
												$args = array_values($data) + ['a'=>$bkgid];
												$utils->SafeExec($sql,$args);
											}
											$icount++;
											$done = TRUE;
										} else {
											return [FALSE,'err_TODO'];
										}
									} else {
										$icount++;
										$done = TRUE;
									}
								}
							}
						}

						if (!$done) {
							$namers = implode(',',array_keys($data));
							$fillers = str_repeat('?,',count($data)-1);
							$sql = 'INSERT INTO '.$mod->OnceTable.' (bkg_id,'.$namers.') VALUES (?,'.$fillers.'?)';
							$bkgid = $mod->dbHandle->GenID($mod->OnceTable.'_seq');
							$args = ['a'=>$bkgid] + array_values($data);
							if ($utils->SafeExec($sql,$args)) {
								if ($data['status'] > \Booker::STATMAXREQ && $data['status'] <= \Booker::STATMAXOK) {
									if ($sfuncs == NULL) {
										$sfuncs = new Schedule();
									}
									$save = $data;
									$t = $data['item_id'];
									if ($t < \Booker::MINGRPID) {
										$res = $sfuncs->ScheduleResource($mod,$utils,$t,$data);
									} else {
										$res = $sfuncs->ScheduleGroup($mod,$utils,$t,$data);
									}
									if ($res) {
										if ($data != $save) {
											$namers = implode('=?,',array_keys($data));
											$sql = 'UPDATE '.$mod->OnceTable.' SET '.$namers.'=? WHERE bkg_id=?';
											$args = array_values($data) + ['a'=>$bkgid];
										}
										$icount++;
									} else {
										return [FALSE,'err_TODO'];
									}
								} else {
									$icount++;
								}
							} else {
								return [FALSE,'err_system'];
							}
						}
					} else {
						$skip = TRUE;
					}
				}
			}
			fclose($fh);
			if ($skip) {
				return [FALSE,'warn_duplicate'];
			} elseif ($icount) {
				return [TRUE,$icount];
			}
			return [FALSE,'none'];
		}
		return [FALSE,'err_system'];
	}
}
