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
			if (is_null($fields) || $fields == FALSE)
				return FALSE;
		} while (!isset($fields[1]) && is_null($fields[0])); //blank line
		$some = FALSE;
		//convert any separator supported by exporter
		foreach ($fields as &$one) {
			if ($one) {
				$some = TRUE;
				$one = trim(preg_replace_callback(
					array('/&#\d\d;/','/%\d\d%/'),array($this,'ToChr'),$one));
			}
		}
		unset($one);
		if ($some)
			return $fields;
		return FALSE; //ignore lines with all fields empty
	}

	/**
	ImportItems:
	Import resource(s) and/or group(s) data from uploaded CSV file. Can handle
	re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
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
				return array(FALSE,'err_file');
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh)
				return array(FALSE,'err_perm');
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE)
				return array(FALSE,'err_file');
			//file-column-name to fieldname translation
			$translates = array(
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
			 'Ingroups'=>'ingroups', //not a real field
 			 'Update'=>'update' //not a real field
			);
			/* non-public
			'item_id'
			'subgrpdata'
			'active'
			*/

			// TODO $mod->FeeTable data for items

			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return array(FALSE,'err_file');
			}
			//setup for interpretation
			$offers = array(); //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE)
					$offers[$col] = $priv;
				elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return array(FALSE,'err_file');
				}
			}

			$utils = new Utils();
			$periods = array(-3=>'any',-2=>'all',-1=>'fixed') + $utils->TimeIntervals();
			$deftypes = array('slottype'=>1,'leadtype'=>2,'keeptype'=>5);
			$icount = 0;
			$db = $mod->dbHandle;
			$sqlg = 'INSERT INTO '.$mod->GroupTable.' (child,parent,likeorder,proximity) VALUES (?,?,?,?)';
			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = array();
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
								return array(FALSE,'err_file');
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
								$item_id = $utils->SafeGet($sql,array($update),'one');
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
								return array(FALSE,'err_system');
							}
						}
						if ($in_grps) {
							//setup groups table
							$sql = array();
							$args = array();
							$data = explode('||',$in_grps); //TODO actual separator
							foreach ($data as $i=>$one) {
								//find id of this name
								$t = $utils->GetItemID($mod,$one);
								if ($t === FALSE) {
									return array(FALSE,'err_file');
								}
								$sql[] = $sqlg;
								$args[] = array($item_id,$t,-1,$i+1); //likeorder unknowable in this context, proximity assumes blank canvas!
							}
							$utils->SafeExec($sql,$args);
						}
					}
				}
			}
			fclose($fh);
			if ($icount)
				return array(TRUE,$icount);
			return array(FALSE,'none');
		}
		return array(FALSE,'error');
	}

	/**
	ImportFees:
	Import fee-rules data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
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
				return array(FALSE,'err_file');
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh)
				return array(FALSE,'err_perm');
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return array(FALSE,'err_file');
			}
			//file-column-name to fieldname translation
			$translates = array(
			 '#ID'=>'item_id', //interpreted
			 'Description'=>'description',
			 'Duration'=>'slottype', //interpreted
			 'Count'=>'slotcount',
			 '#Fee'=>'fee',
			 'Condition'=>'feecondition',
			 'Type'=>'usercondition',
			 'Update'=>'update' //not a real field
			);
			/* non-public
			 =>'signature'
			 =>'condorder,
			 =>'active'
			*/
			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return array(FALSE,'err_file');
			}
			//setup for interpretation
			$offers = array(); //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE)
					$offers[$col] = $priv;
				elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return array(FALSE,'err_file');
				}
			}

			$funcs = new Payment();
			$utils = new Utils();
			$periods = array(-3=>'any',-2=>'all',-1=>'fixed') + $utils->TimeIntervals();
			$icount = 0;

			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = array();
					$save = FALSE;
					$update = FALSE;
					foreach ($imports as $i=>$one) {
						$k = $offers[$i];
						if ($one) {
							switch ($k) {
							 case 'item_id':
								$t = $utils->GetItemID($mod,trim($one));
								if ($t === FALSE) {
 									return array(FALSE,'err_file');
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
								if ($t < 1.0) //TODO support selectable min. payment
									$t = 0.0;
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
								return array(FALSE,'err_file');
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
								$sql = 'SELECT condition_id FROM '.$mod->FeeTable.' WHERE condition_id=?';
								$cid = $utils->SafeGet($sql,array($update),'one');
							} else {
								$cid = FALSE;
							}
/*							if (!$cid) {
								$sql = 'SELECT condition_id FROM '.$mod->FeeTable.' WHERE TODO';
								$args = $TODO;
								$cid = $utils->SafeGet($sql,$args,'one');
							}
*/
							if ($cid) {
								//TODO cache $cid=>X
								$namers = implode('=?,',array_keys($data));
								$sql = 'UPDATE '.$mod->FeeTable.' SET '.$namers.'=? WHERE condition_id=?';
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
							$sql = 'INSERT INTO '.$mod->FeeTable.' (condition_id,signature,'.$namers.',condorder,active) VALUES (?,?,'.$fillers.'?,?,1)';
							$args = array_values($data);
							$cid = $mod->dbHandle->GenID($mod->FeeTable.'_seq');
							$sig = $funcs->GetFeeSignature($data);
							array_unshift($args,$cid,$sig);
							$args[] = -1; //TODO useful order
							if ($utils->SafeExec($sql,$args)) {
								$icount++;
							} else {
								return array(FALSE,'err_system');
							}
						}
					}
				}
			}
			fclose($fh);
			if ($icount)
				return array(TRUE,$icount);
			return array(FALSE,'none');
		}
		return array(FALSE,'err_system');
	}

	/**
	ImportBookers:
	Import booker(s) data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
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
				return array(FALSE,'err_file');
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh)
				return array(FALSE,'err_perm');
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return array(FALSE,'err_file');
			}
			//file-column-name to fieldname translation
			$translates = array(
			 '#Name'=>'name',
			 'Login'=>'publicid',
			 'Password'=>'passhash', //interpreted
			 'Passhash'=>'passhash',
			 '#Email'=>'address',
			 'Phone'=>'phone',
			 'Postpayer'=>'type', //interpreted
			 'Recorder'=>'type',
			 'Usertype'=>'type',
			 'Displaytype'=>'displayclass',
			 'Update'=>'update' //not a real field
			);
			/* non-public
			 =>'booker_id'
			 =>'addwhen'
			 =>'active'
			*/
			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return array(FALSE,'err_file');
			}
			//setup for interpretation
			$offers = array(); //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE)
					$offers[$col] = $priv;
				elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return array(FALSE,'err_file');
				}
			}
			$utils = new Utils();
			//for update checks
			$exist = $utils->SafeGet('SELECT booker_id,name,publicid FROM '.$mod->BookerTable.' ORDER BY booker_id',FALSE);

			$funcs = new Userops();
			$dt = new \DateTime('now',new \DateTimeZone('UTC'));
			$st = $dt->getTimestamp();
//			$skip = FALSE;
			$icount = 0;

			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = array();
					$save = FALSE;
					$update = FALSE;
					foreach ($imports as $i=>$one) {
						$k = $offers[$i];
						if ($one) {
							switch ($k) {
							 case 'name':
							 case 'publicid':
								$data[$k] = trim($one);
								$save = TRUE;
								break;
							 case 'passhash':
 								$t = trim($one);
								if ($translates[$i] == 'Password') {
									$data[$k] = $funcs->HashPassword($t);
									$save = TRUE;
								} elseif (empty($data[$k])) { //Passhash but no prior Password
									$data[$k] = ($t) ? $t : $funcs->HashPassword($t);
									$save = TRUE;
								}
								break;
							 case 'address':
 								$t = trim($one);
								if (!preg_match('/\w+@\w+\.\w+/',$t)) {
									return array(FALSE,'err_file');
								}
								$data[$k] = $t;
								$save = TRUE;
								break;
							 case 'phone':
 								$t = trim($one);
						 		if (!preg_match('/^(\+\d{1,4} *)?[\d ]{5,15}$/',$t)) {
									return array(FALSE,'err_file');
								}
								$data[$k] = $t;
								$save = TRUE;
								break;
							 case 'type':
								switch ($translates[$i]) {
							 	 case 'Postpayer':
								 	$t = ($one == 'no' || $one == 'NO') ? 0:10; //permission-flag
									break;
								 case 'Recorder':
								 	$t = ($one == 'no' || $one == 'NO') ? 0:20; //ditto
									break;
								 case 'Usertype':
									if (!is_numeric($one)) {
										return array(FALSE,'err_file');
									}
									$t = (int)$one;
									if ($t < 0 || $t > 9) //base-types 0..9
										$t = 0;
									break;
								}
								if (isset($data[$k]))
									$data[$k] += $t;
								else
									$data[$k] = $t;
								$save = TRUE;
								break;
							 case 'displayclass':
								if (!is_numeric($one)) {
									return array(FALSE,'err_file');
								}
								$t = (int)$one;
								if ($t < 1 || $t > \Booker::USERSTYLES)
									$t = 1;
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
								return array(FALSE,'err_file');
							}
						} else {
							switch ($k) {
							 case 'type':
								if (!isset($data[$k])) {
									if ($translates[$i] == 'Usertype')
										$data[$k] = 0;
								}
								break;
							 case 'displayclass':
								$data[$k] = 1;
							 case 'update': //ignore this
								break;
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
								$sql = 'SELECT booker_id FROM '.$mod->BookerTable.' WHERE booker_id=?';
								$bookerid = $utils->SafeGet($sql,array($update),'one');
							} else {
								$bookerid = FALSE;
							}
							if (!$bookerid) {
								$sql = 'SELECT booker_id FROM '.$mod->BookerTable;
								if ($data['publicid']) {
									$sql .= ' WHERE publicid=?';
									$args = array($data['publicid']);
									$bookerid = $utils->SafeGet($sql,$args,'one');
								} elseif ($data['name']) {
									$sql .= ' WHERE name=?';
									$args = array($data['name']);
									$bookerid = $utils->SafeGet($sql,$args,'one');
								} else {
									$bookerid = FALSE;
								}
							}
							if ($bookerid) {
								//TODO cache $bookerid=>$data['name'].$data['publicid']
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
						if (!$done) {
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
								return array(FALSE,'err_system');
							}
						}
//					} else {
//						$skip = TRUE;
					}
				}
			}
			fclose($fh);
//			if ($skip)
//				return array(FALSE,'warn_duplicate');
//			else
			if ($icount)
				return array(TRUE,$icount);
			return array(FALSE,'none');
		}
		return array(FALSE,'err_system');
	}

	/**
	ImportBookings:
	Import booking(s) data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	@item_id: optional resource|group id which must be matched
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
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
				return array(FALSE,'err_file');
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh)
				return array(FALSE,'err_perm');
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return array(FALSE,'err_file');
			}
			//file-column-name to fieldname translation
			$translates = array(
			 '#ID'=>'item_id', //intepreted
			 '#Start'=>'slotstart', //ditto
			 'End'=>'slotend', //ditto
			 '#User'=>'booker_id', //ditto
			 'Status'=>'status',
			 'Paid'=>'paid',
			 'Update'=>'update' //not a real field
			);
			/* non-public
			=>'bkg_id'
			=>'bulk_id'
			=>'active'
			*/
			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return array(FALSE,'err_file');
			}
			//setup for interpretation
			$offers = array(); //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE)
					$offers[$col] = $priv;
				elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return array(FALSE,'err_file');
				}
			}

			$utils = new Utils();
			$dts = new \DateTime('@0',NULL);
			$dte = clone $dts;
			$propstore = array();
			$bookers = array();
			$skip = FALSE;
			$icount = 0;
			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = array();
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
									return array(FALSE,'err_file');
								}
								if ($item_id && $t !== $item_id)
									continue;
								$data[$k] = $t;
								$save = TRUE;
								if (!array_key_exists($t,$propstore)) {
									$propstore[$t] = $utils->GetItemProperty($mod,$t,array('slottype','slotcount'),TRUE);
									if (!$propstore[$t])
										return array(FALSE,'err_system');
								}
								break;
							 case 'slotstart':
								$lvl = error_reporting(0);
								$t = $dts->modify($one);
								error_reporting($lvl);
								if ($t) {
									$data[$k] = $dts->getTimestamp(); //store UTC timestamp
								} else {
									return array(FALSE,'err_badstart');
								}
								break;
							 case 'slotend': //proxy for #End
								$lvl = error_reporting(0);
								$t = $dte->modify($one);
								error_reporting($lvl);
								if ($t) {
									$data[$k] = $dte->getTimestamp(); //store UTC timestamp
								} else {
									return array(FALSE,'err_badend');
								}
								break;
							 case 'booker_id':
								if (array_key_exists($one,$bookers)) {
									$data[$k] = $bookers[$one];
								} else {
									$sql = 'SELECT booker_id FROM '.$mod->BookerTable.' WHERE name=? OR publicid=?';
									$t = $mod->dbHandle->GetOne($sql,array($one,$one));
									if ($t) {
										$t = (int)$t;
										$bookers[$one] = $t;
										$data[$k] = $t;
									} else {
										return array(FALSE,'err_baduser');
									}
								}
								$save = TRUE;
								break;
							 case 'paid':
								$data[$k] = ($one == 'no' || $one == 'NO') ? 0:1;
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
								return array(FALSE,'err_file');
							}
						} else {
							switch ($k) {
//compusory					 case 'slotstart':
							 case 'slotend':
								$data[$k] = $data['slotstart'] + 3599; //TODO BAD if out-of-order!
								break;
							 case 'status':
							 case 'paid':
								$data[$k] = 0;
							 case 'update':
								break;
							 default:
								$data[$k] = NULL;
							}
						}
					}

					if (!(empty($data['slotstart']) || empty($data['slotend']))) {
						$item_id = $data['item_id'];
						list($bs,$be) = $utils->TuneBlock(
							$propstore[$item_id]['slottype'],$propstore[$item_id]['slotcount'],
							$data['slotstart'],$data['slotend']);
						$data['slotstart'] = $bs;
						unset($data['slotend']);
						$data['slotlen'] = $be-$bs;
						$funcs2 = new Schedule();
						//any booker
						$save = $funcs2->ItemVacantCount($mod,$item_id,$bs,$be)
							&& $funcs2->ItemAvailable($mod,$utils,$item_id,0,$bs,$be);
					} else {
						return array(FALSE,'err_badtime');
					}
					if ($save) {
						$done = FALSE;
						//TODO define once
						$histfields = array(
						 'history_id',
						 'booker_id',
						 'item_id',
						 'subgrpcount',
						 'lodged',
						 'approved',
						 'slotstart',
						 'slotlen',
						 'status',
						 'payment');
						if ($update) { //TODO robust UPSERT
							if (is_numeric($update)) {
								$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE bkg_id=?';
								$bkgid = $utils->SafeGet($sql,array($update),'one');
							} else {
								$bkgid = FALSE;
							}
/*							if (!$bkgid) {
								$sql = $TODO; //get relevant bkg_id
								$args = $TODO;
								$bkgid = $utils->SafeGet($sql,$args,'one');
							}
*/
							if ($bkgid) {
								//TODO cache $bkgid=>X
								$sql = array();
								$args = array();
								$namers = implode('=?,',array_keys($data));
								$sql[] = 'UPDATE '.$mod->DataTable.' SET '.$namers.'=? WHERE bkg_id=?';
								$args[] = array_values($data) + array(-1=>$bkgid);
//TODO update HistoryTable too
//								$sql[] = 'UPDATE '.$mod->HistoryTable.' SET WHERE =?'
//								$args[] =

								if ($utils->SafeExec($sql,$args)) {
									$icount++;
									$done = TRUE;
								}
							}
						}
						if (!$done) {
							$sql = array();
							$args = array();
							$namers = implode(',',array_keys($data));
							$fillers = str_repeat('?,',count($data)-1);
							$sql[] = 'INSERT INTO '.$mod->DataTable.' (bkg_id,'.$namers.') VALUES (?,'.$fillers.'?)';
							$bkgid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
							$args[] = array(-1=>$bkgid) + array_values($data);

							$namers = implode(',',$histfields);
							$fillers = str_repeat('?,',count($histfields)-1);
							$sql[] = 'INSERT INTO '.$mod->HistoryTable.' ('.$namers.') VALUES ('.$fillers.'?)';
							$hid = $mod->dbHandle->GenID($mod->HistoryTable.'_seq');
							$dts->modify('now');
							$st = $dts->getTimestamp();
//TODO useful status, payment codes
							$status = \Booker::STATOK;
							$payment = ($data['paid']) ? \Booker::STATPAID : \Booker::STATFREE;
							$args[] = array($hid,$data['booker_id'],$data['item_id'],1,$st,$st,$data['slotstart'],$data['slotlen'],$status,$payment);

							if ($utils->SafeExec($sql,$args)) {
								$icount++;
							} else {
								return array(FALSE,'err_system');
							}
						}
					} else {
						$skip = TRUE;
					}
				}
			}
			fclose($fh);
			if ($skip)
				return array(FALSE,'warn_duplicate');
			elseif ($icount)
				return array(TRUE,$icount);
			return array(FALSE,'none');
		}
		return array(FALSE,'err_system');
	}

	/**
	ImportHistory:
	Import history data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: session identifier
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
	*/
	public function ImportHistory(&$mod, $id)
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
				return array(FALSE,'err_file');
			}
			$fh = fopen($file_data['tmp_name'],'r');
			if (!$fh)
				return array(FALSE,'err_perm');
			//basic validation of file-content
			$firstline = self::GetSplitLine($fh);
			if ($firstline == FALSE) {
				return array(FALSE,'err_file');
			}
			//file-column-name to fieldname translation
			$translates = array(
			 '#ID'=>'item_id', //interpreted
			 'Count'=>'subgrpcount',
			 '#User'=>'booker_id', //interpreted
			 'Lodged'=>'lodged',
			 'Approved'=>'approved',
			 '#Start'=>'slotstart',
			 'End'=>'slotlen', //interpreted
			 'Comment'=>'comment',
			 'FeeDue'=>'fee',
			 'Feepaid'=>'netfee',
			 'Status'=>'status',
			 'Feestatus'=>'payment',
			 'Transaction'=>'gatetransaction',
			 'Update'=>'update'
			);
			/* non-public fields
			'item_id'
			'booker_id'
			'gatedata'
			*/
			$t = count($firstline);
			if ($t < 1 || $t > count($translates)) {
				return array(FALSE,'err_file');
			}
			//setup for interpretation
			$offers = array(); //column-index to fieldname translator
			foreach ($translates as $pub=>$priv) {
				$col = array_search($pub,$firstline);
				if ($col !== FALSE)
					$offers[$col] = $priv;
				elseif ($pub[0] == '#') {
					//name of compulsory fields has '#' prefix
					return array(FALSE,'err_file');
				}
			}
			$utils = new Utils();
			//for update checks
//			$exist = $utils->SafeGet('SELECT booker_id,name,publicid FROM '.$mod->BookerTable.' ORDER BY booker_id',FALSE);

			$dtw = new \DateTime('@0',NULL);
			$icwount = 0;

			while (!feof($fh)) {
				$imports = self::GetSplitLine($fh);
				if ($imports) {
					$data = array();
					$save = FALSE;
					$update = FALSE;
					foreach ($imports as $i=>$one) {
						$k = $offers[$i];
						$one = trim($one);
						if ($one || is_numeric($one)) {
							switch ($k) {
							 case 'item_id': //interpret name
								$t = $utils->GetItemID($mod,$one);
								if ($t === FALSE) {
									return array(FALSE,'err_file');
								}
								$data[$k] = $t;
								$save = TRUE;
								break;
 							 case 'comment':
 								$data[$k] = $one;
								$save = TRUE;
								break;
							 case 'subgrpcount':
							 case 'status':
							 case 'payment':
 								$data[$k] = (int)$one;
								$save = TRUE;
								break;
							 case 'booker_id': //interpreted
								break;
							 case 'lodged':
							 case 'approved':
							 case 'slotstart':
								try {
									$dtw->modify($one);
								} catch (Exception $e) {
									return array(FALSE,'err_badstart');
								}
								$data[$k] = $dtw->getTimestamp();
								if ($k == 'slotstart' && isset($data['slotlen'])) {
									$data['slotlen'] -= $data[$k]; //TODO extend to last-second of slot
								}
								$save = TRUE;
								break;
							 case 'slotlen': //interpret end-time
								try {
									$dtw->modify($one);
								} catch (Exception $e) {
									return array(FALSE,'err_badend');
								}
								if (isset($data['slotstart'])) {
									$data[$k] = $dtw->getTimestamp() - $data['slotstart']; //TODO extend to last-second of slot
								} else {
									$data[$k] = $dtw->getTimestamp(); //cache the end
								}
								$save = TRUE;
 								break;
							 case 'fee':
							 case 'netfee':
							 	$t = (float)number_format((float)$one,2,'.','');
								if ($t < 1.0) //TODO support selectable min. payment
									$t = 0.0;
								$data[$k] = $t;
								$save = TRUE;
 								break;
							 case 'gatetransaction':
								$data[$k] = $one;
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
								return array(FALSE,'err_file');
							}
						} else { //non-numeric FALSE
							switch ($k) {
							 case 'subgrpcount':
								$data[$k] = 1;
								break;
							 case 'lodged':
							 case 'approved':
								$dtw->modify('now');
								$data[$k] = $dtw->getTimestamp();
								break;
							 case 'slotlen': //interpreted
								$data[$k] = 3599; //TODO something contect-related
							 	break;
							 case 'fee':
							 case 'netfee':
								$data[$k] = 0.0;
							 	break;
							 case 'status':
							 case 'payment':
								$data[$k] = 0;
								break;
							 case 'update': //ignore this
								break;
//compulsory				 case 'item_id':
//compulsory				 case 'booker_id':
//compulsory				 case 'slotstart':
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
								$sql = 'SELECT history_id FROM '.$mod->HistoryTable.' WHERE history_id=?';
								$hid = $utils->SafeGet($sql,array($update),'one');
								//TODO cache this
							} else {
								$hid = FALSE;
							}
/* TODO						if (!$hid) {
								$sql = $TODO;
								$args = $TODO;
								$hid = $utils->SafeGet($sql,$args,'one');
							}
*/
							if ($hid) {
								//TODO cache $hid=>X
								$namers = implode('=?,',array_keys($data));
								$sql = 'UPDATE '.$mod->HistoryTable.' SET '.$namers.'=? WHERE condition_id=?';
								$args = array_values($data);
								$args[] = $hid;
								if ($utils->SafeExec($sql,$args)) {
									$icount++;
									$done = TRUE;
								}
							}
						}
						if (!$done) {
							$namers = implode(',',array_keys($data));
							$fillers = str_repeat('?,',count($data)-1);
							$sql = 'INSERT INTO '.$mod->HistoryTable.' (condition_id,'.$namers.') VALUES (?,'.$fillers.'?)';
							$args = array_values($data);
							$hid = $mod->dbHandle->GenID($mod->HistoryTable.'_seq');
							array_unshift($args,$hid);
							if ($utils->SafeExec($sql,$args)) {
								$icount++;
							} else {
								return array(FALSE,'err_system');
							}
						}
					}
				}
			}
			fclose($fh);
			if ($icount)
				return array(TRUE,$icount);
			return array(FALSE,'none');
		}
		return array(FALSE,'err_system');
	}
}
