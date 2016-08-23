<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: CSV - functions for import/export of module data
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
# needs Multibyte String extension
namespace Booker;

class CSV
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
		 case 'history':
			$name = 'TODO';
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
	ExportFees:
	Export fee(s) properties
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@fee_id: enumerator of the item to process, or array of such, or '*'
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportFees(&$mod, $fee_id, $sep=',')
	{
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
		return array(FALSE,'err_data');
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

			// TODO $mod->FeeTable data for items

			$sql =<<<EOS
SELECT DISTINCT I.name FROM {$mod->ItemTable} I LEFT JOIN {$mod->GroupTable} G
ON I.item_id=G.parent
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
			/* non-public
			'repeatsuntil'
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
							 case 'slotcount':
							 case 'bookcount':
							 case 'leadtype':
							 case 'leadcount':
							 case 'rationcount':
							 case 'keeptype':
							 case 'keepcount':
							 case 'item_id':
								$fv = (int)$fv;
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
	ExportBookers:
	Export booker(s) properties
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@booker_id: enumerator of the booker to process, or array of such, or '*'
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportBookers(&$mod, $booker_id, $sep=',')
	{
		if (!$booker_id)
			return array(FALSE,'err_system');

		$sql = 'SELECT * FROM '.$mod->BookerTable;
		if (is_array($booker_id)) {
			$fillers = str_repeat('?,',count($booker_id)-1);
			$sql .= ' WHERE booker_id IN('.$fillers.'?) ORDER BY name';
			$args = $booker_id;
		} elseif ($booker_id == '*') {
			$sql .= ' ORDER BY name';
			$args = array();
		} else {
			$sql .= ' WHERE booker_id=?';
			$args = array($booker_id);
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
			 =>'addwhen'
			 =>'active'
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
			$detail = self::NameDetail($mod,$utils,$booker_id,'booker');
			$fname = self::FullName($mod,$detail);
			return self::ExportContent($mod,$fname,$outstr);
		} //$all
		return array(FALSE,'err_data');
	}

	private function ExtraSQL($bkg_id, $bkr_id, $xtra=TRUE)
	{
		$sql = '';
		$joiner = ($xtra) ? 'AND':'WHERE';
		$args = array();
		if (is_array($bkg_id)) {
			$fillers = str_repeat('?,',count($bkg_id)-1);
			$sql .= ' '.$joiner.' bkg_id IN('.$fillers.'?)';
			$args = array_merge($args,$bgk_id);
			$joiner = 'AND';
		}elseif ($bkg_id && $bkg_id != '*') {
			$sql .= ' '.$joiner.' bkg_id=?';
			$args[] = $bkg_id;
			$joiner = 'AND';
		}
		if (is_array($bkr_id)) {
			$fillers = str_repeat('?,',count($bkr_id)-1);
			$sql .= ' '.$joiner.' booker_id IN('.$fillers.'?)';
			$args = array_merge($args,$bkr_id);
		} elseif ($bkr_id && $bkr_id != '*') {
			$sql .= ' '.$joiner.' booker_id=?';
			$args[] = $bkr_id;
		}
		return array($sql,$args);
	}

	/**
	ExportBookings:
	Export booking(s) data
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	At least one of @item_id, @bkg_id, @bkr_id must be provided
	@mod: reference to current Booker module object
	@item_id: optional item enumerator, or array of such, or '*' default FALSE,
	 must be provided if neither @bkg_id or @bkr_id is provided
	@bkg_id: optional booking enumerator, or array of such, or '*' default FALSE
	@bkr_id: optional booker enumerator, or array of such, or '*' default FALSE
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportBookings(&$mod, $item_id=FALSE, $bkg_id=FALSE, $bkr_id=FALSE, $sep=',')
	{
		if (!($item_id || $bkg_id || $bkr_id))
			return array(FALSE,'err_system');
		$all = FALSE;
		$sql = 'SELECT bkg_id FROM '.$mod->DataTable;
 		if ($item_id) {
			if (is_array($item_id)) {
				$fillers = str_repeat('?,',count($item_id)-1);
				$sql .= ' WHERE item_id IN ('.$fillers.'?)';
				$args = $item_id;
				if ($bkg_id || $bkr_id) {
					list($xql,$xarg) = self::ExtraSQL($bkg_id,$bkr_id);
					$sql .= $xql;
					$args = array_merge($args,$xarg);
				}
				$sql .= ' ORDER BY item_id,slotstart';
			} elseif ($item_id == '*') {
				if ($bkg_id || $bkr_id) {
					list($xql,$args) = self::ExtraSQL($bkg_id,$bkr_id,FALSE);
					$sql .= $xql;
				} else {
					$args = array();
				}
				$sql .= ' ORDER BY item_id,slotstart';
			} else {
				$sql .= ' WHERE item_id=?';
				$args = array($item_id);
				if ($bkg_id || $bkr_id) {
					list($xql,$xarg) = self::ExtraSQL($bkg_id,$bkr_id);
					$sql .= $xql;
					$args = array_merge($args,$xarg);
				}
				$sql .= ' ORDER BY slotstart';
			}
		}
		if (!$item_id && $bkg_id) {
			if (is_array($bkg_id)) {
				if ($bkr_id) {
					list($xql,$xarg) = self::ExtraSQL(FALSE,$bkr_id,FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
					$args = array_merge($bkg_id,$xarg);
				} else
					$all = $bkg_id;
			} elseif ($bkg_id == '*') {
				if ($bkr_id) {
					list($xql,$args) = self::ExtraSQL(FALSE,$bkr_id,FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
				} else {
					$args = array();
					$sql .= ' ORDER BY slotstart';
				}
			} else {
				if ($bkr_id) {
					list($xql,$xarg) = self::ExtraSQL(FALSE,$bkr_id,FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
					$args = array_merge($args,$xarg);
				} else
					$all = array($bkg_id);
			}
		}
		if (!$item_id && $bkr_id) {
			if (is_array($bkr_id)) {
				$fillers = str_repeat('?,',count($bkr_id)-1);
				$sql .= ' WHERE booker_id IN ('.$fillers.'?)';
				$args = $bkr_id;
				$sql .= ' ORDER BY booker_id,slotstart';
			} elseif ($bkr_id == '*') {
				$args = array();
				$sql .= ' ORDER BY booker_id,slotstart';
			} else {
				$sql .= ' WHERE booker_id=?';
				$args = array($bkr_id);
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
				if($bkr_id)
					$detail .= '_'.self::NameDetail($mod,$utils,$bkr_id,'booker');
				if($bkg_id)
					$detail .= '_'.self::NameDetail($mod,$utils,$bkg_id,'booking');
			}
			elseif($bkr_id)
				$detail = self::NameDetail($mod,$utils,$bkr_id,'booker');
			elseif($bkg_id)
				$detail = self::NameDetail($mod,$utils,$bkg_id,'booking');
			$fname = self::FullName($mod,$detail);
			return self::ExportContent($mod,$fname,$outstr);
		}
		return array(FALSE,'err_data');
	}

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
	ImportFees:
	Import fees data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: module identifier
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
	*/
	public function ImportFees(&$mod, $id)
	{
			return array(FALSE,'none');
	}

	/**
	ImportHistory:
	Import history data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: module identifier
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
	*/
	public function ImportHistory(&$mod, $id)
	{
			return array(FALSE,'none');
	}

	/**
	ImportItems:
	Import resource(s) and/or group(s) data from uploaded CSV file. Can handle
	re-ordered columns.
	@mod: reference to current Booker module object
	@id: module identifier
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
 			 'Update'=>'update' //not a real field
			);
			/* non-public
			'item_id'
			'repeatsuntil'
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
			$periods = $utils->TimeIntervals();
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
								$data[$k] = array_search(trim($one),$periods);
								$save = TRUE;
								break;
							 case 'slotcount':
							 case 'bookcount':
							 case 'leadcount':
							 case 'rationcount':
							 case 'keepcount':
							 case 'listformat':
							 case 'subgrpalloc':
								$data[$k] = (int)$one;
								$save = TRUE;
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
							 case 'listformat':
								$data[$k] = ($is_group) ? \Booker::LISTSR:\Booker::LISTSU;
								break;
							 case 'cleargroup':
								$data[$k] = 0; //no clear group
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
								if ($t !== FALSE) {
									$sql[] = $sqlg;
									$args[] = array($item_id,$t,-1,$i+1); //likeorder unknowable in this context, proximity assumes blank canvas!
								} else {
									return array(FALSE,'err_file');
								}
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
	ImportBookers:
	Import booker(s) data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: module identifier
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
			$exist = $utils->SafeGet('SELECT booker_id,name,publicid FROM '.$mod->BookerTable.' ORDER BY bkr_id',FALSE);

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
								$bid = $utils->SafeGet($sql,array($update))
							} else {
								$bid = FALSE;
							}
							if (!$bid) {
								$sql = 'SELECT booker_id FROM '.$mod->BookerTable;
								if ($data['publicid']) {
									$sql .= ' WHERE publicid=?';
									$args = array($data['publicid']);
									$bid = $utils->SafeGet($sql,$args,'one');
								} elseif ($data['name']) {
									$sql .= ' WHERE name=?';
									$args = array($data['name']);
									$bid = $utils->SafeGet($sql,$args,'one');
								} else {
									$bid = FALSE;
								}
							}
							if ($bid) {
								$namers = implode('=?,',array_keys($data));
								$sql = 'UPDATE '.$mod->BookerTable.' SET '.$namers.'=? WHERE booker_id=?';
								$args = array_values($data);
								$args[] = $bid;
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
							$bid = $mod->dbHandle->GenID($mod->BookerTable.'_seq');
							array_unshift($args,$bid);
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
	@id: module identifier
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
			 'End'=>'slotlen', //ditto
			 '#User'=>'booker_id', //ditto
			 'Status'=>'status',
			 'Paid'=>'paid',
			 'Update'=>'update' //not a real field
			);
			/* non-public
			=>'bkg_id'
			=>'status'
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
			$dts = new \DateTime('1900-1-1',new \DateTimeZone('UTC'));
			$dte = clone $dts;
			$item_lens = array();
			$bookers = array();
			$sql = 'SELECT booker_id FROM '.$mod->BookerTable.' WHERE name=? OR publicid=?';
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
								$t = $utils->GetItemID($mod,$one);
								if ($t === FALSE) {
									return array(FALSE,'err_file');
								}
								if ($item_id && $t !== $item_id)
									continue;
								$data[$k] = $t;
								$save = TRUE;
								if (!array_key_exists($t,$item_lens)) {
									$item_lens[$t] = $utils->GetInterval($mod,$t,'slot');
									if (!$item_lens[$t])
										return array(FALSE,'err_system');
								}
								break;
							 case 'slotstart':
								if (empty($data['item_id'])) {
									return array(FALSE,'err_file');
								}
								try {
									$dts->modify($one);
								} catch (Exception $e) {
									return array(FALSE,'err_badstart');
								}
								$data['slotstart'] = $dts->getTimestamp(); //store UTC timestamp
								break;
							 case 'slotlen': //proxy for #End
								if (empty($data['item_id'])) {
									return array(FALSE,'err_file');
								}
								try {
									$dte->modify($one);
								} catch (Exception $e) {
									return array(FALSE,'err_badend');
								}
								if (isset($data['slotstart']))
									$data['slotlen'] = $dte->getTimestamp() - $data['slotstart'];
								else
									$data['slotlen'] = $dte->getTimestamp(); //interim value cached
								break;
							 case 'booker_id':
								if (array_key_exists($one,$bookers)) {
									$data[$k] = $bookers[$one];
								} else {
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
							 case 'slotstart':
							 case 'slotlen':
							 case 'paid':
								$data[$k] = 0;
							 case 'update':
								break;
							 default:
								$data[$k] = NULL;
							}
						}
					}

					if ($dts->getTimestamp() > 0) {
						$slen = $item_lens[$data['item_id']];
						$utils->TrimRange($dts,$dte,$slen);
						$data['slotstart'] = $dts->getTimestamp();
						$data['slotlen'] = $dte->getTimestamp() - $data['slotstart'];
						$funcs2 = new Schedule();
						$save = !$funcs2->ItemBooked($mod,$data['item_id'],$dts,$dte)
							&& $funcs2->ItemAvailable($mod,$utils,$data['item_id'],$dts,$dte);
					} else {
						return array(FALSE,'err_badstart');
					}
					if ($save) {
						$done = FALSE;
						if ($update) { //TODO robust UPSERT
							if (is_numeric($update)) {
								$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE bkg_id=?';
								$bid = $utils->SafeGet($sql,array($update),'one');
							} else {
								$bid = FALSE;
							}
/*						if (!$bid) {
								$sql = $TODO; //get relevant bkg_id
								$args = $TODO;
								$bid = $utils->SafeGet($sql,$args,'one');
							}
*/
							if ($bid) {
								$namers = implode('=?,',array_keys($data));
								$sql = 'UPDATE '.$mod->DataTable.' SET '.$namers.'=? WHERE bkg_id=?';
								$args = array_values($data);
								$args[] = $bid;
								if ($utils->SafeExec($sql,$args)) {
									$icount++;
									$done = TRUE;
								}
							}
						}
						if (!$done) {
							$namers = implode(',',array_keys($data));
							$fillers = str_repeat('?,',count($data)-1);
//TODO HistoryTable too
							$sql = 'INSERT INTO '.$mod->DataTable.' (bkg_id,'.$namers.') VALUES (?,'.$fillers.'?)';
							$args = array_values($data);
							$bid = $mod->dbHandle->GenID($mod->DataTable.'_seq');
							array_unshift($args,$bid);
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

}
