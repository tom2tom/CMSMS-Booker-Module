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

}
