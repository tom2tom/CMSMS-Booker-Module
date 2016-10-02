<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: Export - functions for export of module data
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
# needs Multibyte String extension
namespace Booker;

class Export
{
	/*
	NameDetail:
	@mod: reference to current Booker module object
	@utils: reference to Utils-class object
	@id: enumerator of the thing being exported, or array of such, or '*'
	@mode: one of: 'item','booker','booking','fee','history'
	Returns: string
	*/
	private function NameDetail(&$mod, &$utils, $id, $mode)
	{
		$multi = FALSE;
		if (is_array($id)) {
			$id = reset($id);
			$xtra = '_plus';
		} elseif ($id == '*') {
			$id = '';
			$xtra = '_all';
		}
		switch ($mode) {
		 case 'item':
		 	if ($id)
				$name = $utils->GetItemNameForID($mod,$id);
			else
				$name = $mod->Lang('title_items');
			break;
		 case 'booker':
		 	if ($id)
				$name = 'Booker'.$id; //TODO
			else
				$name = $mod->Lang('title_bookers');
			break;
		 case 'booking':
		 	if ($id) {
				if (is_numeric($id))
					$name = trim($mod->Lang('title_booksfor',$utils->GetItemNameForID($mod,$id),''));
				else
					$name = $id;
			} else
				$name = $mod->Lang('title_bookings');
			break;
		 case 'fee':
		 	if ($id)
				$name = $mod->Lang('title_fee').$id;
			else
				$name = $mod->Lang('title_fees');
			break;
		 case 'history':
		 	if ($id)
				$name = $mod->Lang('TODO').$id;
			else
				$name = $mod->Lang('TODO');
			break;
		}
		return $name.$extra;
	}

	/*
	FullName:
	Construct filename based on @detail and current (server) date/time
	@mod: reference to current Booker module object
	@detail: specific descriptor
	Returns: filename string
	*/
	private function FullName(&$mod, $detail)
	{
		$name = $mod->GetName().$mod->Lang('export');
		$detail = preg_replace('/\W/','_',$detail);
		$when = date('Y-m-d-H-i');
		return $name.'-'.$detail.'-'.$when.'.csv';
	}

	/*
	ExportContent:
	@mod: reference to current Booker module object
	@fname: filename string
	@csv: content to be saved string
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	private function ExportContent(&$mod, $fname, $csv)
	{
		$config = cmsms()->GetConfig();
		if (!empty($config['default_encoding']))
			$defchars = trim($config['default_encoding']);
		else
			$defchars = 'UTF-8';

		if (ini_get('mbstring.internal_encoding') !== FALSE) { //conversion is possible
			$expchars = $mod->GetPreference('pref_exportencoding','UTF-8');
			$convert = (strcasecmp ($expchars,$defchars) != 0);
		} else {
			$expchars = $defchars;
			$convert = FALSE;
		}

		if ($mod->GetPreference('pref_exportfile')) {
			$utils = new Utils();
			$updir = $utils->GetUploadsPath($mod);
			if ($updir) {
				$filepath = $updir.DIRECTORY_SEPARATOR.$fname;
				$fh = fopen($filepath,'w');
				if ($fh) {
					if ($convert)
						$csv = mb_convert_encoding($csv,$expchars,$defchars);
					$res = fwrite($fh,$csv);
					fclose($fh);
					if ($res) {
						return array(TRUE,'');
					} else
						return array(FALSE,'err_system');
				} else
					return array(FALSE,'err_perm');
			} else
				return array(FALSE,'err_system');
		} else {
			@ob_clean();
			@ob_clean();
			header('Pragma: public');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Cache-Control: private',FALSE);
			header('Content-Description: File Transfer');
			//note: some older HTTP/1.0 clients did not deal properly with an explicit charset parameter
			header('Content-Type: text/csv; charset='.$expchars);
			header('Content-Length: '.strlen($csv));
			header('Content-Disposition: attachment; filename='.$fname);
			if ($convert)
				echo mb_convert_encoding($csv,$expchars,$defchars);
			else
				echo $csv;
			return array(TRUE,'');
		}
	}

	/**
	ExportItems:
	Export item(s) properties
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@item_id: enumerator of the item to process, or array of such, or '*'
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportItems(&$mod, $item_id, $sep=',')
	{
		if (!$item_id)
			return array(FALSE,'err_system');

		$sql = 'SELECT * FROM '.$mod->ItemTable;
		if (is_array($item_id)) {
			$fillers = str_repeat('?,',count($item_id)-1);
			$sql .= ' WHERE item_id IN('.$fillers.'?) ORDER BY name';
			$args = $item_id;
		} elseif ($item_id == '*') {
			$sql .= ' ORDER BY name';
			$args = array();
		} else {
			$sql .= ' WHERE item_id=?';
			$args = array($item_id);
		}
		$utils = new Utils();
		$all = $utils->SafeGet($sql,$args);
		if ($all) {
			$sep2 = ($sep != ' ')?' ':',';
			switch ($sep) {
			 case '&':
				$r = '%38%';
				break;
			 case '#':
				$r = '%35%';
				break;
			 case ';':
				$r = '%59%';
				break;
			 default:
				$r = '&#'.ord($sep).';';
				break;
			}

			$periods = $utils->TimeIntervals();

			$sql =<<<EOS
SELECT DISTINCT I.name FROM $mod->ItemTable I
LEFT JOIN $mod->GroupTable G ON I.item_id=G.parent
WHERE G.child=? ORDER BY G.proximity,G.likeorder
EOS;
			$strip = $mod->GetPreference('pref_stripexport');
			//file-column-name to fieldname translation
			$translates = array(
			 '#Isgroup'=>'isgroup', //not a real field
			 'Alias'=>'alias',
			 '#Name'=>'name',
			 'Description'=>'description',
			 'Keywords'=>'keywords',
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
			 'Membersnamed'=>'membersname',
			 'Ingroups'=>'ingroups', //not a real field
			 'Update'=>'item_id' //not a real field
			);
			/* non-public fields
			'subgrpdata'
			'active'
			*/
			//header line
			$outstr = implode($sep,array_keys($translates));
			$outstr .= "\n";
			//data lines(s)
			foreach ($all as $data) {
				//accumulator
				$stores = array();
				foreach ($translates as $one) {
					if (isset($data[$one])) {
						$fv = $data[$one];
						if ($fv || is_numeric($fv)) {
							switch ($one) {
							 case 'name':
							 case 'description':
								if ($strip)
									$fv = strip_tags($fv);
								$fv = preg_replace('/[\n\t\r]/',$sep2,$fv);
							 case 'alias':
							 case 'keywords':
							 case 'available':
							 case 'dateformat':
							 case 'timeformat':
							 case 'approver':
							 case 'approvercontact':
							 case 'smspattern':
								$fv = str_replace($sep,$r,$fv);
								break;
							 case 'slottype':
							 case 'keeptype':
							 case 'leadtype':
							 	$fv = $periods[$fv];
							 	break;
							 case 'item_id':
							 case 'slotcount':
							 case 'keepcount':
							 case 'leadcount':
							 case 'bookcount':
							 case 'rationcount':
							 case 'grossfees':
								$fv = (int)$fv;
								break;
							 case 'taxrate':
								$fv = (float)$fv;
								break;
							}
						} else {
							$fv = '';
						}
					} else {
						switch ($one) {
						 case 'isgroup':
							$fv = ($data['item_id'] >= \Booker::MINGRPID) ? 'YES':'';
							break;
						 case 'ingroups':
							$parents = $utils->SafeGet($sql,array($data['item_id']),'col');
							if ($parents) {
								$fv = implode('||',$parents); //$r-separator N/A in import-func
							} else {
								$fv = '';
							}
							break;
						 default:
							$fv = '';
						}
					}
					$stores[] = $fv;
				} //foreach $translates
				//TODO fees and fee-conditions to be appended
				$outstr .= implode($sep,$stores)."\n";
			} //foreach $all
			$detail = self::NameDetail($mod,$utils,$item_id,'item');
			$fname = self::FullName($mod,$detail);
			return self::ExportContent($mod,$fname,$outstr);
		} //$all
		return array(FALSE,'err_data');
	}

	/**
	ExportFees:
	Export fee(s) properties
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@condition_id: enumerator of the item to process, or array of such, or '*'
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportFees(&$mod, $condition_id, $sep=',')
	{
		if (!$condition_id)
			return array(FALSE,'err_system');

		$sql =<<<EOS
SELECT F.*,I.name FROM $mod->FeeTable F
JOIN $mod->ItemTable I ON F.item_id=I.item_id
EOS;
		if (is_array($condition_id)) {
			$fillers = str_repeat('?,',count($condition_id)-1);
			$sql .= ' WHERE F.condition_id IN('.$fillers.'?) ORDER BY F.item_id,F.condorder';
			$args = $condition_id;
		} elseif ($condition_id == '*') {
			$sql .= ' ORDER BY F.item_id,F.condorder';
			$args = array();
		} else {
			$sql .= ' WHERE F.condition_id=?';
			$args = array($condition_id);
		}
		$utils = new Utils();
		$all = $utils->SafeGet($sql,$args);
		if ($all) {
			$sep2 = ($sep != ' ')?' ':',';
			switch ($sep) {
			 case '&':
				$r = '%38%';
				break;
			 case '#':
				$r = '%35%';
				break;
			 case ';':
				$r = '%59%';
				break;
			 default:
				$r = '&#'.ord($sep).';';
				break;
			}

			$strip = $mod->GetPreference('pref_stripexport');
			//file-column-name to fieldname translation
			$translates = array(
			 '#ID'=>'name',
			 'Description'=>'description',
			 'Duration'=>'slottype', //interpreted
			 'Count'=>'slotcount',
			 '#Fee'=>'fee',
			 'Condition'=>'feecondition',
			 'Type'=>'usercondition',
			 'Update'=>'condition_id' //not real
			);
			/* non-public fields
			'item_id'
			'signature'
			'condorder'
			'active'
			*/
			$periods = $utils->TimeIntervals();
			//header line
			$outstr = implode($sep,array_keys($translates));
			$outstr .= "\n";
			//data lines(s)
			foreach ($all as $data) {
				//accumulator
				$stores = array();
				foreach ($translates as $one) {
					$fv = $data[$one];
					switch ($one) {
					 case 'name':
					 case 'description':
						$fv = preg_replace('/[\n\t\r]/',$sep2,$fv);
						//no break here
					 case 'feecondition':
					 case 'usercondition':
						$fv = str_replace($sep,$r,$fv);
						break;
					 case 'fee':
						$fv = (float)$fv;
						break;
					 case 'condition_id':
					 case 'slotcount':
						$fv = int($fv);
						break;
					 case 'slottype':
						$fv = $periods[$fv];
						break;
					}
					$stores[] = $fv;
				} //foreach $translates
				$outstr .= implode($sep,$stores)."\n";
			} //foreach $all
			$detail = self::NameDetail($mod,$utils,$condition_id,'fee');
			$fname = self::FullName($mod,$detail);
			return self::ExportContent($mod,$fname,$outstr);
		} //$all
		return array(FALSE,'err_data');
	}

	/**
	ExportBookers:
	Export booker(s) properties
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@bookerid: enumerator of the booker to process, or array of such, or '*'
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportBookers(&$mod, $bookerid, $sep=',')
	{
		if (!$bookerid)
			return array(FALSE,'err_system');

		$sql = 'SELECT * FROM '.$mod->BookerTable;
		if (is_array($bookerid)) {
			$fillers = str_repeat('?,',count($bookerid)-1);
			$sql .= ' WHERE booker_id IN('.$fillers.'?) ORDER BY name';
			$args = $bookerid;
		} elseif ($bookerid == '*') {
			$sql .= ' ORDER BY name';
			$args = array();
		} else {
			$sql .= ' WHERE booker_id=?';
			$args = array($bookerid);
		}
		$utils = new Utils();
		$all = $utils->SafeGet($sql,$args);
		if ($all) {
			$sep2 = ($sep != ' ')?' ':',';
			switch ($sep) {
			 case '&':
				$r = '%38%';
				break;
			 case '#':
				$r = '%35%';
				break;
			 case ';':
				$r = '%59%';
				break;
			 default:
				$r = '&#'.ord($sep).';';
				break;
			}

			$strip = $mod->GetPreference('pref_stripexport');
			//file-column-name to fieldname translation
			$translates = array(
			 '#Name'=>'name',
			 'Login'=>'publicid',
			 'Password'=>'fake', //not real field
			 'Passhash'=>'passhash',
			 '#Email'=>'address',
			 'Phone'=>'phone',
			 'Usertype'=>'type',
			 'Postpayer'=>'poster', //not real
			 'Recorder'=>'recorder', //not real
			 'Displaytype'=>'displayclass',
			 'Update'=>'booker_id' //not real
			);
			/* non-public fields
			 'addwhen'
			 'active'
			 */
			//header line
			$outstr = implode($sep,array_keys($translates));
			$outstr .= "\n";
			//data lines(s)
			foreach ($all as $data) {
				//accumulator
				$stores = array();
				foreach ($translates as $one) {
					if (isset($data[$one])) {
						$fv = $data[$one];
						if ($fv || $fv === '0') {
							switch ($one) {
							 case 'name':
							 case 'address':
								$fv = preg_replace('/[\n\t\r]/',$sep2,$fv);
							 case 'publicid':
								$fv = str_replace($sep,$r,$fv);
								break;
							 case 'type':
								$fv	= $fv % 10; //base type
								break;
							 case 'displayclass':
							 case 'booker_id':
								$fv = (int)$fv;
								break;
							}
						} elseif ($fv !== 0) {
							$fv = '';
						}
					} else {
						switch ($one) {
						 case 'poster':
							$flags = (int)($data['type'] / 10);
							$fv = ($flags & 0x1) ? 'YES':''; //post-payment allowed
							break;
						 case 'recorder':
							$flags = (int)($data['type'] / 10);
							$fv = ($flags & 0x2) ? 'YES':''; //record-booking allowed
							break;
						 default:
							$fv = '';
						}
					}
					$stores[] = $fv;
				} //foreach $translates
				$outstr .= implode($sep,$stores)."\n";
			} //foreach $all
			$detail = self::NameDetail($mod,$utils,$bookerid,'booker');
			$fname = self::FullName($mod,$detail);
			return self::ExportContent($mod,$fname,$outstr);
		} //$all
		return array(FALSE,'err_data');
	}

	private function ExtraSQL($bkgid, $bookerid, $xtra=TRUE)
	{
		$sql = '';
		$joiner = ($xtra) ? 'AND':'WHERE';
		$args = array();
		if (is_array($bkgid)) {
			$fillers = str_repeat('?,',count($bkgid)-1);
			$sql .= ' '.$joiner.' bkg_id IN('.$fillers.'?)';
			$args = array_merge($args,$bgk_id);
			$joiner = 'AND';
		}elseif ($bkgid && $bkgid != '*') {
			$sql .= ' '.$joiner.' bkg_id=?';
			$args[] = $bkgid;
			$joiner = 'AND';
		}
		if (is_array($bookerid)) {
			$fillers = str_repeat('?,',count($bookerid)-1);
			$sql .= ' '.$joiner.' booker_id IN('.$fillers.'?)';
			$args = array_merge($args,$bookerid);
		} elseif ($bookerid && $bookerid != '*') {
			$sql .= ' '.$joiner.' booker_id=?';
			$args[] = $bookerid;
		}
		return array($sql,$args);
	}

	/**
	ExportBookings:
	Export booking(s) data
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	At least one of @item_id, @bkgid, @bookerid must be provided
	@mod: reference to current Booker module object
	@item_id: optional item enumerator, or array of such, or '*' default FALSE,
	 must be provided if neither @bkgid or @bookerid is provided
	@bkgid: optional booking enumerator, or array of such, or '*' default FALSE
	@bookerid: optional booker enumerator, or array of such, or '*' default FALSE
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportBookings(&$mod, $item_id=FALSE, $bkgid=FALSE, $bookerid=FALSE, $sep=',')
	{
		if (!($item_id || $bkgid || $bookerid))
			return array(FALSE,'err_system');
		$all = FALSE;
		$sql = 'SELECT bkg_id FROM '.$mod->DataTable;
 		if ($item_id) {
			if (is_array($item_id)) {
				$fillers = str_repeat('?,',count($item_id)-1);
				$sql .= ' WHERE item_id IN ('.$fillers.'?)';
				$args = $item_id;
				if ($bkgid || $bookerid) {
					list($xql,$xarg) = self::ExtraSQL($bkgid,$bookerid);
					$sql .= $xql;
					$args = array_merge($args,$xarg);
				}
				$sql .= ' ORDER BY item_id,slotstart';
			} elseif ($item_id == '*') {
				if ($bkgid || $bookerid) {
					list($xql,$args) = self::ExtraSQL($bkgid,$bookerid,FALSE);
					$sql .= $xql;
				} else {
					$args = array();
				}
				$sql .= ' ORDER BY item_id,slotstart';
			} else {
				$sql .= ' WHERE item_id=?';
				$args = array($item_id);
				if ($bkgid || $bookerid) {
					list($xql,$xarg) = self::ExtraSQL($bkgid,$bookerid);
					$sql .= $xql;
					$args = array_merge($args,$xarg);
				}
				$sql .= ' ORDER BY slotstart';
			}
		}
		if (!$item_id && $bkgid) {
			if (is_array($bkgid)) {
				if ($bookerid) {
					list($xql,$xarg) = self::ExtraSQL(FALSE,$bookerid,FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
					$args = array_merge($bkgid,$xarg);
				} else
					$all = $bkgid;
			} elseif ($bkgid == '*') {
				if ($bookerid) {
					list($xql,$args) = self::ExtraSQL(FALSE,$bookerid,FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
				} else {
					$args = array();
					$sql .= ' ORDER BY slotstart';
				}
			} else {
				if ($bookerid) {
					list($xql,$xarg) = self::ExtraSQL(FALSE,$bookerid,FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
					$args = array_merge($args,$xarg);
				} else
					$all = array($bkgid);
			}
		}
		if (!$item_id && $bookerid) {
			if (is_array($bookerid)) {
				$fillers = str_repeat('?,',count($bookerid)-1);
				$sql .= ' WHERE booker_id IN ('.$fillers.'?)';
				$args = $bookerid;
				$sql .= ' ORDER BY booker_id,slotstart';
			} elseif ($bookerid == '*') {
				$args = array();
				$sql .= ' ORDER BY booker_id,slotstart';
			} else {
				$sql .= ' WHERE booker_id=?';
				$args = array($bookerid);
				$sql .= ' ORDER BY slotstart';
			}
		}

		$utils = new Utils();
		if (!$all) {
			$all = $utils->SafeGet($sql,$args,'col');
		}

		if ($all) {

			$sep2 = ($sep != ' ')?' ':',';
			switch ($sep) {
			 case '&':
				$r = '%38%';
				break;
			 case '#':
				$r = '%35%';
				break;
			 case ';':
				$r = '%59%';
				break;
			 default:
				$r = '&#'.ord($sep).';';
				break;
			}

			$strip = $mod->GetPreference('pref_stripexport');
			//file-column-name to fieldname translation
			$translates = array(
			 '#ID'=>'item_id', //intepreted
			 '#Start'=>'slotstart', //ditto
			 'End'=>'slotlen', //ditto
			 '#User'=>'name',
			 'Status'=>'status',
			 'Paid'=>'paid',
			 'Update'=>'bkg_id' //not real
			);
			/* non-public fields
			*/
			$dt = new \DateTime('@0',NULL);
			//header line
			$outstr = implode($sep,array_keys($translates));
			$outstr .= $sep."\n";
			$sql = <<<EOS
SELECT D.*,B.name,B.publicid
FROM {$mod->DataTable} D
JOIN {$mod->BookerTable} B ON D.booker_id=B.booker_id
WHERE D.bkg_id=?
EOS;
			//data lines(s)
			foreach ($all as $one) {
				$data = $utils->SafeGet($sql,array($one),'row');
				$stores = array();
				foreach ($translates as $one) {
					$fv = $data[$one];
					switch ($one) {
					 case 'item_id':
					  $fv = $utils->GetItemNameForID($mod,$fv);
						if ($strip)
							$fv = strip_tags($fv);
						$fv = str_replace($sep,$r,$fv);
					 	break;
					 case 'slotstart':
						$dt->setTimestamp($fv);
						$fv = $dt->format('Y-n-j G:i');
					 	break;
					 case 'slotlen':
						$dt->setTimestamp($fv+$data['slotstart']);
						$fv = $dt->format('Y-n-j G:i');
					 	break;
					 case 'name':
					 	if ($data['publicid']) {
							$fv = $data['publicid']; //prefer account identifier
						}
						$fv = str_replace($sep,$r,$fv);
					 	break;
					 case 'status':
					 case 'bkg_id':
					 	$fv = (int)$fv;
					 	break;
					 case 'paid':
					 	$fv = ($fv) ? 'YES':'';//no translation
					 	break;
					}
					$stores[] = preg_replace('/[\n\t\r]/',$sep2,$fv);
				}
				$outstr .= implode($sep,$stores)."\n";
			}

			if($item_id) {
				$detail = self::NameDetail($mod,$utils,$item_id,'item');
				if($bookerid)
					$detail .= '_'.self::NameDetail($mod,$utils,$bookerid,'booker');
				if($bkgid)
					$detail .= '_'.self::NameDetail($mod,$utils,$bkgid,'booking');
			}
			elseif($bookerid)
				$detail = self::NameDetail($mod,$utils,$bookerid,'booker');
			elseif($bkgid)
				$detail = self::NameDetail($mod,$utils,$bkgid,'booking');
			$fname = self::FullName($mod,$detail);
			return self::ExportContent($mod,$fname,$outstr);
		}
		return array(FALSE,'err_data');
	}

	/**
	ExportHistory:
	Export history data
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@history_id: enumerator of the item to process, or array of such, or '*'
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportHistory(&$mod, $history_id, $sep=',')
	{
		if (!$history_id)
			return array(FALSE,'err_system');

		$sql =<<<EOS
SELECT H.*,B.name,I.name AS what FROM $mod->HistoryTable H
JOIN $mod->BookerTable B ON H.booker_id=B.booker_id
JOIN $mod->ItemTable I ON H.item_id=I.item_id
EOS;
		if (is_array($history_id)) {
			$fillers = str_repeat('?,',count($history_id)-1);
			$sql .= ' WHERE history_id IN('.$fillers.'?) ORDER BY H.item_id,H.slotstart';
			$args = $history_id;
		} elseif ($history_id == '*') {
			$sql .= ' ORDER BY H.item_id,H.slotstart';
			$args = array();
		} else {
			$sql .= ' WHERE H.history_id=?';
			$args = array($history_id);
		}
		$utils = new Utils();
		$all = $utils->SafeGet($sql,$args);
		if ($all) {
			$sep2 = ($sep != ' ')?' ':',';
			switch ($sep) {
			 case '&':
				$r = '%38%';
				break;
			 case '#':
				$r = '%35%';
				break;
			 case ';':
				$r = '%59%';
				break;
			 default:
				$r = '&#'.ord($sep).';';
				break;
			}

			$strip = $mod->GetPreference('pref_stripexport');
			//file-column-name to fieldname translation
			$translates = array(
			 '#ID'=>'what',
			 'Count'=>'subgrpcount',
			 '#User'=>'name',
			 'Lodged'=>'lodged',
			 'Approved'=>'approved',
			 '#Start'=>'slotstart',
			 'End'=>'slotlen',
			 'Comment'=>'comment',
			 'FeeDue'=>'fee',
			 'Feepaid'=>'netfee',
			 'Status'=>'status',
			 'Feestatus'=>'payment',
			 'Transaction'=>'gatetransaction',
			 'Update'=>'history_id'
			);
			/* non-public fields
			'item_id'
			'booker_id'
			'gatedata'
			*/
			$dtw = new \DateTime('@0',NULL);
			//header line
			$outstr = implode($sep,array_keys($translates));
			$outstr .= "\n";
			//data lines(s)
			foreach ($all as $data) {
				//accumulator
				$stores = array();
				foreach ($translates as $one) {
					$fv = $data[$one];
					switch ($one) {
					case 'what':
					case 'name':
					case 'comment':
					case 'gatetransaction':
						$fv = preg_replace('/[\n\t\r]/',$sep2,$fv);
						$fv = str_replace($sep,$r,$fv);
						break;
						break;
					case 'lodged':
					case 'approved':
					case 'slotstart':
						if ($fv) {
							$dtw->setTimestamp($fv);
							$fv = $dtw->format('Y-m-d G:i');
						} else {
							$fv = '';
						}
						break;
					case 'slotlen':
						if ($fv && $data['slotstart']) {
							$dtw->setTimestamp($fv+$data['slotstart']);
							$fv = $dtw->format('Y-m-d G:i');
						} else {
							$fv = '';
						}
						break;
					case 'fee':
					case 'netfee':
						$fv = (float)$fv;
						break;
					case 'subgrpcount':
					case 'status':
					case 'payment':
					case 'history_id':
						$fv = (int)$fv;
						break;
					}
					$stores[] = $fv;
				} //foreach $translates
				$outstr .= implode($sep,$stores)."\n";
			} //foreach $all
			$detail = self::NameDetail($mod,$utils,$history_id,'history');
			$fname = self::FullName($mod,$detail);
			return self::ExportContent($mod,$fname,$outstr);
		} //$all
		return array(FALSE,'err_data');
	}
}
