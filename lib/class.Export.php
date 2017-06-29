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
	@mode: one of: 'item','booker','booking','fee'
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
			if ($id) {
				$name = $utils->GetItemNameForID($mod, $id);
			} else {
				$name = $mod->Lang('title_items');
			}
			break;
		 case 'booker':
			if ($id) {
				$name = 'Booker'.$id; //TODO
			}
			else {
				$name = $mod->Lang('title_bookers');
			}
			break;
		 case 'booking':
			if ($id) {
				if (is_numeric($id)) {
					$name = trim($mod->Lang('title_booksfor', $utils->GetItemNameForID($mod, $id), ''));
				} else {
					$name = $id;
				}
			} else {
				$name = $mod->Lang('title_bookings');
			}
			break;
		 case 'fee':
			if ($id) {
				$name = $mod->Lang('title_fee').$id;
			} else {
				$name = $mod->Lang('title_fees');
			}
			break;
		 default:
			$name = '';
		}
		return $name.$xtra;
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
		$detail = preg_replace('/\W/', '_', $detail);
		$when = date('Y-m-d-H-i');
		return $name.'-'.$detail.'-'.$when.'.csv';
	}

	/*
	ExportContent:
	@mod: reference to current Booker module object
	@fname: filename string
	@csv: content to be saved string
	Returns: 2-member array, [0]=T/F indicating success, [1]='' or lang key for message
	*/
	private function ExportContent(&$mod, $fname, $csv)
	{
		//TODO use fputcsv ($fh, $fields ,$sep);
		$config = \cmsms()->GetConfig();
		if (!empty($config['default_encoding'])) {
			$defchars = trim($config['default_encoding']);
		} else {
			$defchars = 'UTF-8';
		}

		if (ini_get('mbstring.internal_encoding') !== FALSE) { //conversion is possible
			$expchars = $mod->GetPreference('exportencoding', 'UTF-8');
			$convert = (strcasecmp ($expchars, $defchars) != 0);
		} else {
			$expchars = $defchars;
			$convert = FALSE;
		}

		if ($mod->GetPreference('exportfile')) {
			$utils = new Utils();
			$updir = $utils->GetUploadsPath($mod);
			if ($updir) {
				$filepath = $updir.DIRECTORY_SEPARATOR.$fname;
				$fh = fopen($filepath, 'w');
				if ($fh) {
					if ($convert) {
						$csv = mb_convert_encoding($csv, $expchars, $defchars);
					}
					$res = fwrite($fh, $csv);
					fclose($fh);
					if ($res) {
						return [TRUE,''];
					} else {
						return [FALSE,'err_system'];
					}
				} else {
					return [FALSE,'err_perm'];
				}
			} else {
				return [FALSE,'err_system'];
			}
		} else {
			@ob_clean();
			@ob_clean();
			header('Pragma: public');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Cache-Control: private', FALSE);
			header('Content-Description: File Transfer');
			//note: some older HTTP/1.0 clients did not deal properly with an explicit charset parameter
			header('Content-Type: text/csv; charset='.$expchars);
			header('Content-Length: '.strlen($csv));
			header('Content-Disposition: attachment; filename='.$fname);
			if ($convert) {
				echo mb_convert_encoding($csv, $expchars, $defchars);
			} else {
				echo $csv;
			}
			return [TRUE,''];
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
	Returns: 2-member array, [0]=T/F indicating success, [1]='' or lang key for message
	*/
	public function ExportItems(&$mod, $item_id, $sep = ',')
	{
		if (!$item_id) {
			return [FALSE,'err_system'];
		}

		$sql = 'SELECT * FROM '.$mod->ItemTable;
		if (is_array($item_id)) {
			$fillers = str_repeat('?,', count($item_id) - 1);
			$sql .= ' WHERE item_id IN('.$fillers.'?) ORDER BY name';
			$args = $item_id;
		} elseif ($item_id == '*') {
			$sql .= ' ORDER BY name';
			$args = [];
		} else {
			$sql .= ' WHERE item_id=?';
			$args = [$item_id];
		}
		$utils = new Utils();
		$all = $utils->SafeGet($sql, $args);
		if ($all) {
			$sep2 = ($sep != ' ') ? ' ' : ',';
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

			$sql = <<<EOS
SELECT DISTINCT I.name FROM $mod->ItemTable I
LEFT JOIN $mod->GroupTable G ON I.item_id=G.parent
WHERE G.child=? ORDER BY G.proximity,G.likeorder
EOS;
			$strip = $mod->GetPreference('stripexport');
			//file-column-name to fieldname translation
			$translates = [
			 '#Isgroup' => 'isgroup', //not a real field
			 'Alias' => 'alias',
			 '#Name' => 'name',
			 'Description' => 'description',
			 'Keywords' => 'keywords',
			 'Membersnamed' => 'membersname',
			 'Choosername' => 'pickname',
			 'Inchooser' => 'pickthis',
			 'Choosemembers' => 'pickmembers',
			 'Image' => 'image',
			 'Available' => 'available',
			 'Slottype' => 'slottype',
			 'Slotcount' => 'slotcount',
			 'BookingSlots' => 'bookcount',
			 'Leadtype' => 'leadtype',
			 'Leadcount' => 'leadcount',
			 'Rationcount' => 'rationcount',
			 'Keeptype' => 'keeptype',
			 'Keepcount' => 'keepcount',
			 'Grossfees' => 'grossfees',
			 'Taxrate' => 'taxrate',
			 'PayInterface' => 'paymentiface',
			 'Latitude' => 'latitude',
			 'Longitude' => 'longitude',
			 'Timezone' => 'timezone',
			 'Dateformat' => 'dateformat',
			 'Timeformat' => 'timeformat',
			 'Listformat' => 'listformat',
			 'Stylesfile' => 'stylesfile',
			 'Approver' => 'approver',
			 'Approvercontact' => 'approvercontact',
			 'SMSprefix' => 'smsprefix',
			 'SMSpattern' => 'smspattern',
			 'FormInterface' => 'formiface',
			 'Feugroup' => 'feugroup',
			 'Owner' => 'owner',
			 'Cleargroup' => 'cleargroup',
			 'Allocategroup' => 'subgrpalloc',
			 'Ingroups' => 'ingroups', //not a real field
			 'Update' => 'item_id' //not a real field
			];
			/* non-public fields
			'subgrpdata'
			'active'
			*/
			//header line
			$outstr = implode($sep, array_keys($translates));
			$outstr .= "\n";
			//data lines(s)
			foreach ($all as $data) {
				//accumulator
				$stores = [];
				foreach ($translates as $one) {
					if (isset($data[$one])) {
						$fv = $data[$one];
						if ($fv || is_numeric($fv)) {
							switch ($one) {
							 case 'name':
							 case 'description':
							 case 'pickname':
								if ($strip) {
									$fv = strip_tags($fv);
								}
								$fv = preg_replace('/[\n\t\r]/', $sep2, $fv);
							 case 'alias':
							 case 'keywords':
							 case 'available':
							 case 'dateformat':
							 case 'timeformat':
							 case 'approver':
							 case 'approvercontact':
							 case 'smspattern':
								$fv = str_replace($sep, $r, $fv);
								break;
							 case 'slottype':
							 case 'keeptype':
							 case 'leadtype':
								$fv = $periods[$fv];
								break;
							 case 'item_id':
							 case 'pickthis':
							 case 'pickmembers':
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
							$fv = ($data['item_id'] >= \Booker::MINGRPID) ? 'YES' : '';
							break;
						 case 'ingroups':
							$parents = $utils->SafeGet($sql, [$data['item_id']], 'col');
							if ($parents) {
								$fv = implode('||', $parents); //$r-separator N/A in import-func
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
				$outstr .= implode($sep, $stores)."\n";
			} //foreach $all
			$detail = self::NameDetail($mod, $utils, $item_id, 'item');
			$fname = self::FullName($mod, $detail);
			return self::ExportContent($mod, $fname, $outstr);
		} //$all
		return [FALSE,'err_data'];
	}

	/**
	ExportFees:
	Export fee(s) properties
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@feeid: enumerator of the item to process, or array of such, or '*'
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array, [0]=T/F indicating success, [1]='' or lang key for message
	*/
	public function ExportFees(&$mod, $feeid, $sep = ',')
	{
		if (!$feeid) {
			return [FALSE,'err_system'];
		}
		$sql = <<<EOS
SELECT F.*,I.name FROM $mod->FeeTable F
JOIN $mod->ItemTable I ON F.item_id=I.item_id
EOS;
		if (is_array($feeid)) {
			$fillers = str_repeat('?,', count($feeid) - 1);
			$sql .= ' WHERE F.fee_id IN('.$fillers.'?) ORDER BY F.item_id,F.condorder';
			$args = $feeid;
		} elseif ($feeid == '*') {
			$sql .= ' ORDER BY F.item_id,F.condorder';
			$args = [];
		} else {
			$sql .= ' WHERE F.fee_id=?';
			$args = [$feeid];
		}
		$utils = new Utils();
		$all = $utils->SafeGet($sql, $args);
		if ($all) {
			$sep2 = ($sep != ' ') ? ' ' : ',';
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

			$strip = $mod->GetPreference('stripexport');
			//file-column-name to fieldname translation
			$translates = [
			 '#ID' => 'name',
			 'Description' => 'description',
			 'Duration' => 'slottype', //interpreted
			 'Count' => 'slotcount',
			 '#Fee' => 'fee',
			 'Condition' => 'feecondition',
			 'Type' => 'usercondition',
			 'Update' => 'fee_id' //not real
			];
			/* non-public fields
			'item_id'
			'signature'
			'condorder'
			'active'
			*/
			$periods = $utils->TimeIntervals();
			//header line
			$outstr = implode($sep, array_keys($translates));
			$outstr .= "\n";
			//data lines(s)
			foreach ($all as $data) {
				//accumulator
				$stores = [];
				foreach ($translates as $one) {
					$fv = $data[$one];
					switch ($one) {
					 case 'name':
					 case 'description':
						$fv = preg_replace('/[\n\t\r]/', $sep2, $fv);
						//no break here
					 case 'feecondition':
					 case 'usercondition':
						$fv = str_replace($sep, $r, $fv);
						break;
					 case 'fee':
						$fv = (float)$fv;
						break;
					 case 'fee_id':
					 case 'slotcount':
						$fv = int($fv);
						break;
					 case 'slottype':
						$fv = $periods[$fv];
						break;
					}
					$stores[] = $fv;
				} //foreach $translates
				$outstr .= implode($sep, $stores)."\n";
			} //foreach $all
			$detail = self::NameDetail($mod, $utils, $feeid, 'fee');
			$fname = self::FullName($mod, $detail);
			return self::ExportContent($mod, $fname, $outstr);
		} //$all
		return [FALSE,'err_data'];
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
	Returns: 2-member array, [0]=T/F indicating success, [1]='' or lang key for message
	*/
	public function ExportBookers(&$mod, $bookerid, $sep = ',')
	{
		if (!$bookerid) {
			return [FALSE,'err_system'];
		}
		//get B.* except name,address
		$sql = <<<EOS
SELECT B.booker_id,B.publicid,B.phone,B.addwhen,B.type,B.displayclass,B.active,
COALESCE(A.name,B.name,'') AS name,COALESCE(A.address,B.address,'') AS address,A.passhash
FROM $mod->BookerTable B
LEFT JOIN $mod->AuthTable A ON B.publicid=A.publicid
EOS;
		if (is_array($bookerid)) {
			$fillers = str_repeat('?,', count($bookerid) - 1);
			$sql .= ' WHERE booker_id IN('.$fillers.'?) ORDER BY name';
			$args = $bookerid;
		} elseif ($bookerid == '*') {
			$sql .= ' ORDER BY name';
			$args = [];
		} else {
			$sql .= ' WHERE booker_id=?';
			$args = [$bookerid];
		}
		$utils = new Utils();
		$all = $utils->SafeGet($sql, $args);
		if ($all) {
			$utils->GetUserProperties($mod, $all);
			$sep2 = ($sep != ' ') ? ' ' : ',';
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

			$strip = $mod->GetPreference('stripexport');
			//file-column-name to fieldname translation
			$translates = [
			 '#Name' => 'name',
			 '#Email' => 'address',
			 'Phone' => 'phone',
			 'Login' => 'publicid',
			 'Password' => 'password', //not real field
			 'Passhash' => 'passhash',
			 'Usertype' => 'type',
			 'Postpayer' => 'poster', //not real
			 'Recorder' => 'recorder', //not real
			 'Displaytype' => 'displayclass',
			 'Update' => 'booker_id' //not real
			];
			/* non-public fields
			 'addwhen'
			 'active'
			 */
			//header line
			$outstr = implode($sep, array_keys($translates));
			$outstr .= "\n";
			//data lines(s)
			foreach ($all as $data) {
				//accumulator
				$stores = [];
				foreach ($translates as $one) {
					if (isset($data[$one])) {
						$fv = $data[$one];
						if ($fv || $fv === '0') {
							switch ($one) {
							 case 'name':
							 case 'address':
								$fv = preg_replace('/[\n\t\r]/', $sep2, $fv);
							 case 'publicid':
								$fv = str_replace($sep, $r, $fv);
								break;
							 case 'passhash':
								$fv = unpack('H*', $fv);
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
							$fv = ($flags & 0x1) ? 'YES' : ''; //post-payment allowed
							break;
						 case 'recorder':
							$flags = (int)($data['type'] / 10);
							$fv = ($flags & 0x2) ? 'YES' : ''; //record-booking allowed
							break;
						 default:
							$fv = '';
						}
					}
					$stores[] = $fv;
				} //foreach $translates
				$outstr .= implode($sep, $stores)."\n";
			} //foreach $all
			$detail = self::NameDetail($mod, $utils, $bookerid, 'booker');
			$fname = self::FullName($mod, $detail);
			return self::ExportContent($mod, $fname, $outstr);
		} //$all
		return [FALSE,'err_data'];
	}

	private function ExtraSQL($bkgid, $bookerid, $xtra = TRUE)
	{
		$sql = '';
		$joiner = ($xtra) ? 'AND' : 'WHERE';
		$args = [];
		if (is_array($bkgid)) {
			$fillers = str_repeat('?,', count($bkgid) - 1);
			$sql .= ' '.$joiner.' bkg_id IN('.$fillers.'?)';
			$args = array_merge($args, $bgk_id);
			$joiner = 'AND';
		} elseif ($bkgid && $bkgid != '*') {
			$sql .= ' '.$joiner.' bkg_id=?';
			$args[] = $bkgid;
			$joiner = 'AND';
		}
		if (is_array($bookerid)) {
			$fillers = str_repeat('?,', count($bookerid) - 1);
			$sql .= ' '.$joiner.' booker_id IN('.$fillers.'?)';
			$args = array_merge($args, $bookerid);
		} elseif ($bookerid && $bookerid != '*') {
			$sql .= ' '.$joiner.' booker_id=?';
			$args[] = $bookerid;
		}
		return [$sql,$args];
	}

	/**
	ExportBookings:
	Export OnceTable data: onetime-booking(s) and/or request(s)
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
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = '' or lang key for message
	*/
	public function ExportBookings(&$mod, $item_id = FALSE, $bkgid = FALSE, $bookerid = FALSE, $sep = ',')
	{
		if (!($item_id || $bkgid || $bookerid)) {
			return [FALSE,'err_system'];
		}
		$all = FALSE;
		$sql = 'SELECT bkg_id FROM '.$mod->OnceTable;
		if ($item_id) {
			if (is_array($item_id)) {
				$fillers = str_repeat('?,', count($item_id) - 1);
				$sql .= ' WHERE item_id IN ('.$fillers.'?)';
				$args = $item_id;
				if ($bkgid || $bookerid) {
					list($xql, $xarg) = self::ExtraSQL($bkgid, $bookerid);
					$sql .= $xql;
					$args = array_merge($args, $xarg);
				}
				$sql .= ' ORDER BY item_id,slotstart';
			} elseif ($item_id == '*') {
				if ($bkgid || $bookerid) {
					list($xql, $args) = self::ExtraSQL($bkgid, $bookerid, FALSE);
					$sql .= $xql;
				} else {
					$args = [];
				}
				$sql .= ' ORDER BY item_id,slotstart';
			} else {
				$sql .= ' WHERE item_id=?';
				$args = [$item_id];
				if ($bkgid || $bookerid) {
					list($xql, $xarg) = self::ExtraSQL($bkgid, $bookerid);
					$sql .= $xql;
					$args = array_merge($args, $xarg);
				}
				$sql .= ' ORDER BY slotstart';
			}
		}
		if (!$item_id && $bkgid) {
			if (is_array($bkgid)) {
				if ($bookerid) {
					list($xql, $xarg) = self::ExtraSQL(FALSE, $bookerid, FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
					$args = array_merge($bkgid, $xarg);
				} else {
					$all = $bkgid;
				}
			} elseif ($bkgid == '*') {
				if ($bookerid) {
					list($xql, $args) = self::ExtraSQL(FALSE, $bookerid, FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
				} else {
					$args = [];
					$sql .= ' ORDER BY slotstart';
				}
			} else {
				if ($bookerid) {
					list($xql, $xarg) = self::ExtraSQL(FALSE, $bookerid, FALSE);
					$sql .= $xql.' ORDER BY booker_id,slotstart';
					$args = array_merge($args, $xarg);
				} else {
					$all = [$bkgid];
				}
			}
		}
		if (!$item_id && $bookerid) {
			if (is_array($bookerid)) {
				$fillers = str_repeat('?,', count($bookerid) - 1);
				$sql .= ' WHERE booker_id IN ('.$fillers.'?)';
				$args = $bookerid;
				$sql .= ' ORDER BY booker_id,slotstart';
			} elseif ($bookerid == '*') {
				$args = [];
				$sql .= ' ORDER BY booker_id,slotstart';
			} else {
				$sql .= ' WHERE booker_id=?';
				$args = [$bookerid];
				$sql .= ' ORDER BY slotstart';
			}
		}

		$utils = new Utils();
		if (!$all) {
			$all = $utils->SafeGet($sql, $args, 'col');
		}

		if ($all) {
			$sep2 = ($sep != ' ') ? ' ' : ',';
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

			$strip = $mod->GetPreference('stripexport');
			//file-column-name to fieldname translation
			$translates = [
			 '#ID' => 'item_id', //intepreted
			 'Count' => 'subgrpcount',
			 'Lodged' => 'lodged', //ditto
			 'Approved' => 'approved', //ditto
			 'Removed' => 'removed', //ditto
			 '#Start' => 'slotstart', //ditto
			 'End' => 'slotlen', //ditto
			 'Bookingstatus' => 'status',
			 '#User' => 'name',
			 'Usercomment' => 'comment',
			 'Feedue' => 'fee',
			 'Feepaid' => 'feepaid',
			 'Feestatus' => 'statpay',
			 'Active' => 'active',
			 'Transaction' => 'gatetransaction',
			 'Update' => 'bkg_id' //not real
			];
			/* non-public fields
			*/
			$dt = new \DateTime('@0', NULL);
			//header line
			$outstr = implode($sep, array_keys($translates));
			$outstr .= $sep."\n";
			$sql = <<<EOS
SELECT O.*,COALESCE(A.name,B.name,'') AS name,B.publicid
FROM $mod->OnceTable O
JOIN $mod->BookerTable B ON O.booker_id=B.booker_id
LEFT JOIN $mod->AuthTable A ON B.publicid=A.publicid
WHERE O.bkg_id=?
EOS;
			//data line(s)
			foreach ($all as $one) {
				$data = $utils->SafeGet($sql, [$one], 'row');
				$utils->GetUserProperties($mod, $data);
				$stores = [];
				foreach ($translates as $one) {
					$fv = $data[$one];
					switch ($one) {
					 case 'item_id':
					  $fv = $utils->GetItemNameForID($mod, $fv);
						if ($strip) {
							$fv = strip_tags($fv);
						}
						$fv = str_replace($sep, $r, $fv);
						break;
					 case 'lodged':
					 case 'approved':
					 case 'removed':
						if (!$fv) {
							$fv = '';
							break;
						}
						//no break here
					 case 'slotstart':
						$dt->setTimestamp($fv);
						$fv = $dt->format('Y-n-j G:i');
						break;
					 case 'slotlen':
						$dt->setTimestamp($fv + $data['slotstart']);
						$fv = $dt->format('Y-n-j G:i');
						break;
					 case 'name':
						if ($data['publicid']) {
							$fv = $data['publicid']; //prefer login identifier
						}
						$fv = str_replace($sep, $r, $fv);
						break;
					 case 'fee':
					 case 'feepaid':
						$fv = (float)$fv;
						break;
					 case 'subgrpcount':
						if ($data['item_id'] < \Booker::MINGRPID) {
							$fv = '';
							break;
						}
						//no break here
					 case 'status':
					 case 'statpay':
					 case 'bkg_id':
						$fv = (int)$fv;
						break;
					 case 'active':
						$fv = ($fv) ? 'YES' : '';//no translation
						break;
					}
					$stores[] = preg_replace('/[\n\t\r]/', $sep2, $fv);
				}
				$outstr .= implode($sep, $stores)."\n";
			}

			if ($item_id) {
				$detail = self::NameDetail($mod, $utils, $item_id, 'item');
				if ($bookerid) {
					$detail .= '_'.self::NameDetail($mod, $utils, $bookerid, 'booker');
				}
				if ($bkgid) {
					$detail .= '_'.self::NameDetail($mod, $utils, $bkgid, 'booking');
				}
			} elseif ($bookerid) {
				$detail = self::NameDetail($mod, $utils, $bookerid, 'booker');
			} elseif ($bkgid) {
				$detail = self::NameDetail($mod, $utils, $bkgid, 'booking');
			}
			$fname = self::FullName($mod, $detail);
			return self::ExportContent($mod, $fname, $outstr);
		}
		return [FALSE,'err_data'];
	}

	/**
	ExportReport:
	@mod: reference to current Booker module object
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	@title: displayable title for the report as used in UI
	@all: array of value-arrays, 1st member has titles
	@sep: optional field-separator for exported content, assumed single-byte ASCII, default ','
	Returns: 2-member array,
	 [0] = boolean indicating success
	 [1] = '' or lang key for message
	*/
	public function ExportReport(&$mod, $title, $all, $sep = ',')
	{
		if ($all) {
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
			$sep2 = ($sep != ' ') ? ' ' : ',';
			$strip = $mod->GetPreference('stripexport');

			$outstr = '';

			foreach ($all as &$one) {
				foreach ($one as &$fv) {
					if ($strip) {
						$fv = strip_tags($fv);
					}
					$fv = str_replace($sep, $r, $fv);
					preg_replace('/[\n\t\r]/', $sep2, $fv);
				}
				$outstr .= implode($sep, $one)."\n";
			}
			unset($one);
			unset($fv);

			$fname = self::FullName($mod, $title);
			return self::ExportContent($mod, $fname, $outstr);
		}
		return [FALSE, 'err_data'];
	}
}
