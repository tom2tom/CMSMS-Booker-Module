<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: CSV - functions for import/export of module data
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
# needs Multibyte String extension

class CSV
{
	/**
	ExportName:
	Construct filename based on name of [first] item and current date/time
	@mod: reference to current Booker module object
	@item_id: enumerator of the item to process, or array of such, or FALSE if @bkg_id is provided
	@bkg_id: enumerator of the booking to process, or array of such, or FALSE if @item_id is provided
	Returns: string
	*/
	public function ExportName(&$mod,$item_id=FALSE,$bkg_id=FALSE)
	{
		$funcs = new Booker\Shared();
		$multi = FALSE;
		if($item_id)
		{
			if(is_array($item_id))
			{
				$item_id = reset($item_id);
				$multi = TRUE;
			}
		}
		else
		{
			if(is_array($bkg_id))
				$bkg_id = reset($bkg_id);
			$item_id = $funcs->GetBookingItemID($mod,$bkg_id);
		}
		$iname = $funcs->GetItemNameForID($mod,$item_id);
		$sname = preg_replace('/\W/','_',$iname);
		if($multi)
			$sname .= '_plus';
		$datestr = date('Y-m-d-H-i'); //server time
		return $mod->GetName().$mod->Lang('export').'-'.$sname.'-'.$datestr.'.csv';
	}

	/*
	BookingCSV:
	Constructs a CSV string for all booking-records belonging to @item_id or @bkg_id,
	and returns the string or writes it progressively to the file associated with @fh
	(which must be opened and closed upstream)
	To avoid field-corruption, existing separators in headings or data are converted
	to something else, generally like &#...;
	(except when the separator is '&', '#' or ';', those become %...%)
	@mod: reference to current Booker module object
	@item_id: index of the item to process, or array of such indices,
		or FALSE if @bkg_id is provided
	@bkg_id: index of a single reponse to process, or array of such indices,
		or FALSE to process @item_id, default=FALSE
	@fh: handle of open file, if writing data to disk, or FALSE if constructing in memory, default = FALSE
	@$sep: field-separator in output data, assumed single-byte ASCII, default = ','
	Returns: TRUE/string, or FALSE on error
	*/
	private function BookingCSV(&$mod,$item_id=FALSE,$bkg_id=FALSE,$fh=FALSE,$sep=',')
	{
		$funcs = new Booker\Shared();
		if($item_id)
		{
			if(is_array($item_id))
			{
				$fillers = str_repeat('?,',count($item_id)-1);
				$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE item_id IN ('.$fillers.'?) ORDER BY item_id,slotstart';
				$all = $funcs->SafeGet($sql,$item_id,'col');
			}
			else
			{
				$sql = 'SELECT bkg_id FROM '.$mod->DataTable.' WHERE item_id=? ORDER BY slotstart';
				$all = $funcs->SafeGet($sql,array($item_id),'col');
			}
		}
		elseif($bkg_id)
		{
			if(is_array($bkg_id))
				$all = $bkg_id;
			else
				$all = array($bkg_id);
		}

		if($all)
		{
			if($fh && ini_get('mbstring.internal_encoding') !== FALSE) //send to file, and conversion is possible
			{
				$config = cmsms()->GetConfig();
				if(!empty($config['default_encoding']))
					$defchars = trim($config['default_encoding']);
				else
					$defchars = 'UTF-8';
				$expchars = $mod->GetPreference('pref_exportencoding','UTF-8');
				$convert = (strcasecmp($expchars,$defchars) != 0);
			}
			else
				$convert = FALSE;

			$sep2 = ($sep != ' ')?' ':',';
			switch ($sep)
			{
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
			 '#User'=>'user',
			 'Class'=>'userclass',
			 'Contact'=>'contact',
			 'Paid'=>'paid'
			);
			/* non-public fields
			=>'bkg_id'
			=>'status'
			*/
			$dts = new DateTime('1900-1-1',new DateTimeZone('UTC'));
			//header line
			$outstr = implode($sep,array_keys($translates));
			$outstr .= "\n";

			$sql = 'SELECT * FROM '.$mod->DataTable.' WHERE bkg_id=?';
			//data lines(s)
			foreach($all as $one)
			{
				$data = $funcs->SafeGet($sql,array($one),'row');
				foreach($translates as &$one)
				{
					$fv = $data[$one];
					switch($one)
					{
					 case 'item_id':
					  $fv = $funcs->GetItemNameForID($mod,$fv);
						if($strip)
							$fv = strip_tags($fv);
						$fv = str_replace($sep,$r,$fv);
					 	break;
					 case 'slotstart':
						$dts->setTimestamp($fv);
					  $fv = $dts->format('Y-n-j G:i');
					 	break;
					 case 'slotlen':
						$dts->setTimestamp($fv+$one['slotstart']);
					  $fv = $dts->format('Y-n-j G:i');
					 	break;
					 case 'userclass':
						$fv = (int)$fv;
					 	break;
					 case 'user':
					 case 'contact':
						if($strip)
							$fv = strip_tags($fv);
						$fv = str_replace($sep,$r,$fv);
					 	break;
					 case 'paid':
					 	$fv = ($fv) ? 'YES':'NO'; //no translation
					 	break;
					}
					$outstr .= $sep.preg_replace('/[\n\t\r]/',$sep2,$fv);
				}
				unset($one);
				$outstr .= "\n";
				if($fh)
				{
					if($convert)
					{
						$conv = mb_convert_encoding($outstr, $expchars, $defchars);
						fwrite($fh, $conv);
						unset($conv);
					}
					else
					{
						fwrite($fh, $outstr);
					}
					$outstr = '';
				}
			}
			if($fh)
				return TRUE;
			else
				return $outstr; //encoding conversion upstream
		}
		return FALSE;
	}

	/* *
	ExportItems:
	Export items properties
	@mod: reference to current Booker module object
	@item_id: item_id, or array of such id's
	@sep: optional field-separator for exported content default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
/*	public function ExportItems(&$mod,$item_id,$sep=',')
	{
		$fname = self::ExportName($mod,$item_id,FALSE);

		return array(FALSE,'error');
	}
*/
	/**
	ExportBookings:
	Export bookings data
	At least one of @item_id, @bkg_id must be provided
	@mod: reference to current Booker module object
	@item_id: optional item identifier, default FALSE
	@bkg_id: optional bkg_id, or array of such id's, default FALSE
	@sep: optional field-separator for exported content default ','
	Returns: 2-member array, 1st is T/F indicating success, 2nd '' or lang key for message
	*/
	public function ExportBookings(&$mod,$item_id=FALSE,$bkg_id=FALSE,$sep=',')
	{
		if (!($item_id || $bkg_id))
			return array(FALSE,'err_system');
		$fname = self::ExportName($mod,$item_id,$bkg_id);

		if($mod->GetPreference('pref_exportfile'))
		{
			$funcs = new Booker\Shared();
			$updir = $funcs->GetUploadsPath($mod);
			if($updir)
			{
				$filepath = $updir.DIRECTORY_SEPARATOR.$fname;
				$fh = fopen($filepath,'w');
				if($fh)
				{
					$success = self::BookingCSV($mod,$item_id,$bkg_id,$fh,$sep);
					fclose($fh);
					if($success)
					{
						$url = $funcs->GetUploadURL($mod,$fname,FALSE); //must succeed, in this context
						@ob_clean();
						@ob_clean();
						header('Location: '.$url);
						return array(TRUE,'');
					}
					else
						return array(FALSE,'error'); //TODO
				}
				else
					return array(FALSE,'err_perm');
			}
			else
				return array(FALSE,'err_system');
		}
		else
		{
			$csv = self::BookingCSV($mod,$item_id,$bkg_id,FALSE,$sep);
			if($csv)
			{
				$config = cmsms()->GetConfig();
				if(!empty($config['default_encoding']))
					$defchars = trim($config['default_encoding']);
				else
					$defchars = 'UTF-8';

				if(ini_get('mbstring.internal_encoding') !== FALSE) //conversion is possible
				{
					$expchars = $mod->GetPreference('pref_exportencoding','UTF-8');
					$convert = (strcasecmp ($expchars,$defchars) != 0);
				}
				else
				{
					$expchars = $defchars;
					$convert = FALSE;
				}

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
				if($convert)
					echo mb_convert_encoding($csv,$expchars,$defchars);
				else
					echo $csv;
				return array(TRUE,'');
			}
		}
		return array(FALSE,'err_export');
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
		do
		{
			$fields = fgetcsv($fh,4096);
			if(is_null($fields) || $fields == FALSE)
				return FALSE;
		} while(!isset($fields[1]) && is_null($fields[0])); //blank line
		$some = FALSE;
		//convert any separator supported by exporter
		foreach ($fields as &$one)
		{
			if($one)
			{
				$some = TRUE;
				$one = trim(preg_replace_callback(
					array('/&#\d\d;/','/%\d\d%/'),array($this,'ToChr'),$one));
			}
		}
		unset($one);
		if($some)
			return $fields;
		return FALSE; //ignore lines with all fields empty
	}

	/**
	ImportBookings:
	Import booking(s) data from uploaded CSV file. Can handle re-ordered columns.
	@mod: reference to current Booker module object
	@id: module identifier
	@item_id: optional resource|group id which must be matched
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
	*/
	public function ImportBookings(&$mod,$id,$item_id=FALSE)
	{
		$filekey = $id.'csvfile';
		if(isset($_FILES) && isset($_FILES[$filekey]))
		{
			$file_data = $_FILES[$filekey];
			$parts = explode('.',$file_data['name']);
			$ext = end($parts);
			if($file_data['type'] != 'text/csv'
			 || !($ext == 'csv' || $ext == 'CSV')
				 || $file_data['size'] <= 0 || $file_data['size'] > 25600 //$max*1000
				 || $file_data['error'] != 0)
			{
				return array(FALSE,'err_file');
			}
			$handle = fopen($file_data['tmp_name'],'r');
			if(!$handle)
				return array(FALSE,'err_perm');
			//basic validation of file-content
			$firstline = self::GetSplitLine($handle);
			if($firstline == FALSE)
			{
				return array(FALSE,'err_file');
			}
			//file-column-name to fieldname translation
			$translates = array(
			 '#ID'=>'item_id', //intepreted
			 '#Start'=>'slotstart', //ditto
			 'End'=>'slotlen', //ditto
			 '#User'=>'user',
			 'Class'=>'userclass',
			 'Contact'=>'contact',
			 'Paid'=>'paid'
			);
			/* non-public
			=>'bkg_id'
			=>'status'
			*/
			$t = count($firstline);
			if($t < 1 || $t > count($translates))
			{
				return array(FALSE,'err_file');
			}
			//setup for interpretation
			$offers = array(); //column-index to fieldname translator
			foreach($translates as $pub=>$priv)
			{
				$col = array_search($pub,$firstline);
				if($col !== FALSE)
					$offers[$col] = $priv;
				elseif($pub[0] == '#')
				{
					//name of compulsory fields has '#' prefix
					return array(FALSE,'err_file');
				}
			}
			$funcs = new Booker\Shared();
			$tzone = new DateTimeZone('UTC');
			$item_lens = array();
			$skip = FALSE;
			$icount = 0;
			$db = $mod->dbHandle;
			while(!feof($handle))
			{
				$imports = self::GetSplitLine($handle);
				if($imports)
				{
					$data = array();
					$save = FALSE;
					$dts = FALSE;
					$dte = FALSE;
					foreach($imports as $i=>$one)
					{
						$k = $offers[$i];
						if($one)
						{
							switch($k)
							{
							 case 'item_id':
								$t = $funcs->GetItemID($mod,$one);
								if($t === FALSE)
								{
									return array(FALSE,'err_file');
								}
								if($item_id && $t !== $item_id)
									continue;
								$data[$k] = $t;
								$save = TRUE;
								if(!array_key_exists($t,$item_lens))
								{
									$item_lens[$t] = $funcs->GetInterval($mod,$t,'slot');
									if(!$item_lens[$t])
										return array(FALSE,'err_system');
								}
								break;
							 case 'slotstart':
								if(empty($data['item_id']))
								{
									return array(FALSE,'err_file');
								}
								try {
									$dts = new DateTime($one,$tzone);
								} catch (Exception $e) {
									return array(FALSE,'err_badstart');
								}
								$data['slotstart'] = $dts->getTimestamp(); //store UTC timestamp
								break;
							 case 'slotlen': //proxy for #End
								if(empty($data['item_id']))
								{
									return array(FALSE,'err_file');
								}
								try {
									$dte = new DateTime($one,$tzone);
								} catch (Exception $e) {
									return array(FALSE,'err_badend');
								}
								if(isset($data['slotstart']))
									$data['slotlen'] = $dte->getTimestamp() - $data['slotstart'];
								else
									$data['slotlen'] = $dte->getTimestamp(); //interim value cached
								break;
							 case 'user':
							 case 'contact': //TODO block injection
								$data[$k] = trim($one);
								$save = TRUE;
								break;
							 case 'userclass':
								if(!is_numeric($one))
								{
									return array(FALSE,'err_file');
								}
								$data[$k] = (int)$one;
								$save = TRUE;
								break;
							 case 'paid':
								if(!($one == 'no' || $one == 'NO'))
									$data[$k] = 1;
								$save = TRUE;
								break;
							default:
								return array(FALSE,'err_file');
							}
						}
						else
						{
							switch($k)
							{
							 case 'slotstart':
							 case 'slotlen':
							 case 'userclass':
							 case 'paid':
								$data[$k] = 0;
								break;
							 default:
								$data[$k] = NULL;
							}
						}
					}
					if($dts)
					{
						if(!$dte)
							$dte = clone $dts;
						$slen = $item_lens[$data['item_id']];
						$funcs->TrimRange($dts,$dte,$slen);
						$data['slotstart'] = $dts->getTimestamp();
						$data['slotlen'] = $dte->getTimestamp() - $data['slotstart'];
						$funcs2 = new Booker\Schedule();
						$save = !$funcs2->ItemBooked($mod,$data['item_id'],$dts,$dte)
							&& $funcs2->ItemAvailable($mod,$funcs,$data['item_id'],$dts,$dte);
					}
					else
					{
						return array(FALSE,'err_badstart');
					}
					if($save)
					{
						$namers = implode(',',array_keys($data));
						$fillers = str_repeat('?,',count($data)-1);
						$sql = 'INSERT INTO '.$mod->DataTable.' (bkg_id,'.$namers.') VALUES (?,'.$fillers.'?)';
						$args = array_values($data);
						$bid = $db->GenID($mod->DataTable.'_seq');
						array_unshift($args,$bid);
						if($funcs->SafeExec($sql,$args))
							$icount++;
						else
						{
							return array(FALSE,'err_system');
						}
					}
					else
					{
						$skip = TRUE;
					}
				}
			}
			fclose($handle);
			if($skip)
				return array(FALSE,'warn_duplicate');
			elseif($icount)
				return array(TRUE,$icount);
			return array(FALSE,'none');
		}
		return array(FALSE,'error');
	}

	/**
	ImportItems:
	Import resource(s) and/or group(s) data from uploaded CSV file. Can handle
	re-ordered columns.
	@mod: reference to current Booker module object
	@id: module identifier
	Returns: 2-member array, 1st is T/F indicating success, 2nd is count of imports or lang key for message
	*/
	public function ImportItems(&$mod,$id)
	{
		$filekey = $id.'csvfile';
		if(isset($_FILES) && isset($_FILES[$filekey]))
		{
			$file_data = $_FILES[$filekey];
			$parts = explode('.',$file_data['name']);
			$ext = end($parts);
			if($file_data['type'] != 'text/csv'
			 || !($ext == 'csv' || $ext == 'CSV')
				 || $file_data['size'] <= 0 || $file_data['size'] > 25600 //$max*1000
				 || $file_data['error'] != 0)
			{
				return array(FALSE,'err_file');
			}
			$handle = fopen($file_data['tmp_name'],'r');
			if(!$handle)
				return array(FALSE,'err_perm');
			//basic validation of file-content
			$firstline = self::GetSplitLine($handle);
			if($firstline == FALSE)
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
// TODO $this->PayTable stuff pairs of fee+condition
//			 'Fee1'=>'fee1',
//			 'Fee1condition'=>'fee1condition',
//			 'Fee2'=>'fee2',
//			 'Fee2condition'=>'fee2condition',
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
			 'Ingroups'=>'ingroups' //not a real field
			);
			/* non-public
			'item_id'
			'repeatsuntil'
			'subgrpdata'
			'active'
			*/
			$t = count($firstline);
			if($t < 1 || $t > count($translates))
			{
				return array(FALSE,'err_file');
			}
			//setup for interpretation
			$offers = array(); //column-index to fieldname translator
			foreach($translates as $pub=>$priv)
			{
				$col = array_search($pub,$firstline);
				if($col !== FALSE)
					$offers[$col] = $priv;
				elseif($pub[0] == '#')
				{
					//name of compulsory fields has '#' prefix
					return array(FALSE,'err_file');
				}
			}

			$funcs = new Booker\Shared();
			$periods = $funcs->TimeIntervals();
			$icount = 0;
			$db = $mod->dbHandle;
			$sqlg = 'INSERT INTO '.$mod->GroupTable.' (child,parent,likeorder,proximity) VALUES (?,?,?,?)';
			while(!feof($handle))
			{
				$imports = self::GetSplitLine($handle);
				if($imports)
				{
					$data = array();
					$save = FALSE;
					$is_group = FALSE;
					$in_grps = FALSE;
					foreach($imports as $i=>$one)
					{
						$k = $offers[$i];
						if($one)
						{
							switch($k)
							{
							 case 'isgroup':
								$is_group = !($one == 'no' || $one == 'NO');
								break;
							 case 'alias': //(no duplication check)
							 case 'name': //# ditto
							 case 'description':
							 case 'keywords':
							 case 'image':
							 case 'available': //NO sanity check
// TODO $this->PayTable stuff pairs of fee+condition
//							 case 'fee1condition':
//							 case 'fee2condition':
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
// TODO $this->PayTable stuff pairs of fee+condition
//							 case 'fee1':
//							 case 'fee2':
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
								$in_grps = explode(',',$one);
								break;
							 default:
								return array(FALSE,'err_file');
							}
						}
						else
						{
							switch($k)
							{
							 case 'listformat':
								$data[$k] = ($is_group) ? Booker::LISTSR:Booker::LISTSU;
								break;
							 case 'cleargroup':
								$data[$k] = 0; //no clear group
							 case 'isgroup': //ignore fake fields
							 case 'ingroups':
								break;
							 default:
								$data[$k] = NULL;
							}
						}
					}

					if($save) //process
					{
						$namers = implode(',',array_keys($data));
						$fillers = str_repeat('?,',count($data)-1);
						$sql = 'INSERT INTO '.$mod->ItemTable.' (item_id,'.$namers.',active) VALUES (?,'.$fillers.'?,1)';
						$args = array_values($data);
						$t = ($is_group) ? $mod->ItemTable.'_gseq':$mod->ItemTable.'_seq';
						$item_id = $db->GenID($t);
						array_unshift($args,$item_id);
						if($db->Execute($sql,$args))
							$icount++;
						else
						{
							return array(FALSE,'err_system');
						}
						if($in_grps)
						{
							foreach($in_grps as $i=>$one)
							{
								//find id of this name
								$t = $funcs->GetItemID($mod,$one);
								if($t !== FALSE)
								{
									//setup groups table
									$db->Execute($sqlg,array($item_id,$t,-1,$i+1)); //likeorder unknowable in this context, proximity assumes blank canvas!
								}
								else
								{
									return array(FALSE,'err_file');
								}
							}
						}
					}
				}
			}
			fclose($handle);
			if($icount)
				return array(TRUE,$icount);
			return array(FALSE,'none');
		}
		return array(FALSE,'error');
	}

}

?>
