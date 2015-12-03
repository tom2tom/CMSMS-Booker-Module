<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Method: install
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------

$taboptarray = array('mysql' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci',
 'mysqli' => 'ENGINE MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci');
$dict = NewDataDictionary($db);
/*
 items (i.e. groups and resources) table schema:
 NOTE (almost) no NOTNULL/default values, so inheritance can be determined
 NOTE: changes here must be reflected in action.open.php, bkrcsv::ImportItems
	item_id:
	alias:
	name:
	description:
	keywords: comma-separated descriptors/tags for similarity scans
	membersname: for a group - generic plural member-descriptor e.g. 'members'
	image: filename or ,-separated series of names, uploaded file(s)
	available: interval-descriptor string or empty for always-available
	slottype: enumerator for interval of a single booking per TimeIntervals()
	slotcount: count of slottype intervals which, together with slottype, defines length of a bookings
	bookcount: max. slots per booking, 0 = no limit, 1 = no need for booking end choice
	leadtype: enumerator for interval used for max. lead-time for bookings
	leadcount: count of leadtype intervals which, together with leadtype, defines max. lead-time for (no-repeat) bookings
	rationcount: max. pending bookings for any specific booker
	keeptype: enumerator for interval used for max. retention-time for past bookings
	keepcount: count of keeptype intervals which, together with keeptype, defines max. retention-time for bookings history
	fee1: rate or amount which applies to periods satisfying fee1condition
	fee1condition: interval-descriptor string
	fee2: rate or amount which applies to periods satisfying fee2condition
	fee2condition: interval-descriptor string
	paymentiface: for future use e.g. module name, gateway name, properties to use
	latitude: for sun-related calcs, accurate to ~1km
	longitude: ditto
	timezone: for date/time offsets & calcs
	dateformat: how to display dates
	timeformat: how to display times
	listformat: Booker::LIST* enum representing default layout for list-style bookings display
	stylesfile: specific (uploaded) css file
	approver: identifier of whoever handles booking requests for this item
	approvercontact: contact info for the approver
	smsprefix: country-code to be prepended to sms messages when needed
	smspattern: regex for determining whether approvercontact is a phone suitable for sms messages (no whitespace, before country-prefix)
	formiface: for future use e.g. module name, properties to use, for custom booking-request form
	feugroup: id of group of whose members are authorised to make 'direct' (un-mediated) bookings
	owner: uid of the contact-person for the item, or 0 if there's no such person
	cleargroup: boolean whether to clear data for item when (sole) parent-group data are cleared
	subgrpalloc: Booker::ALLOC* enum for sub-group allocation protocol
	repeatsuntil: internal use, datestamp for last (full) day for which repeat-bookings have been evaluated
	subgrpdata: internal use, for working with subgrpalloc
	active: enum: 0 never; 1 always; 2 inherit (see also: available); -1 deletion pending while historical data needed
*/
$fields = "
	item_id I(4) KEY,
	alias C(24),
	name C(64),
	description X,
	keywords C(256),
	membersname C(32),
	image C(128),
	available C(128),
	slottype I(1),
	slotcount I(1),
	bookcount I(1),
	leadtype I(1),
	leadcount I(1),
	rationcount I(1),
	keeptype I(1),
	keepcount I(1),
	fee1 N(7.2),
	fee1condition C(128),
	fee2 N(7.2),
	fee2condition C(128),
	paymentiface C(48),
	latitude N(8.3),
	longitude N(8.3),
	timezone C(48),
	dateformat C(12),
	timeformat C(12),
	listformat I(1),
	stylesfile C(36),
	approver C(64),
	approvercontact C(128),
	smsprefix C(8),
	smspattern C(32),
	formiface C(48),
	feugroup I4,
	owner I4,
	cleargroup I(1),
	subgrpalloc I(1),
	repeatsuntil I DEFAULT 0,
	subgrpdata I(1) DEFAULT 0,
	active I(1) NOTNULL DEFAULT 1
";
$sqlarray = $dict->CreateTableSQL($this->ItemTable, $fields, $taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray, FALSE);
if ($res != 2)
	return FALSE;

// sequences, for resource id's (1+) and group id's (MINGRPID+) in same table
$db->CreateSequence($this->ItemTable.'_seq'); //resource id's 1..MINGRPID-1
$db->CreateSequence($this->ItemTable.'_gseq',Booker::MINGRPID); //group id's start higher

// default group usable by all resources
$bid = $db->GenID($this->ItemTable.'_gseq');
$sql = 'INSERT INTO '.$this->ItemTable.' (item_id,name) VALUES (?,?)'; //TODO more fields
$db->Execute($sql,array($bid,$this->Lang('groupdefault')));

/*
 group-relationships table schema:
 gid: unique identifier
 child: id of resource or group, indexed, not unique
 parent: id of group of which child is a member
 likeorder: order of children 'likeness' in parent
 proximity: order of multi-parents in all of which child is a member
*/
$fields = "
	gid I AUTO KEY,
	child I(2) NOTNULL DEFAULT 0,
	parent I(2) NOTNULL DEFAULT 0,
	likeorder I(2) NOTNULL DEFAULT 1,
	proximity I(2) NOTNULL DEFAULT 1
";
$sqlarray = $dict->CreateTableSQL($this->GroupTable, $fields, $taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray, FALSE);
if ($res != 2)
	return FALSE;
// index
$sqlarray = $dict->CreateIndexSQL('idx_'.$this->GroupTable, $this->GroupTable, 'child');
$dict->ExecuteSQLArray($sqlarray);

/*
non-repeated bookings-data table schema:
 bkg_id: maybe from repeat booking, so not necessarily unique
 item_id: resource or group id
 slotstart: UTC timestamp
 slotlen: seconds booked, NOT seconds-per-slot
 user: identifier
 contact:
 userclass: 0 - 5
 status: one of the Booker::STAT* values
 paid: boolean
bkrcsv::ImportBookings must conform to this
*/
$fields = "
	bkg_id I(4),
	item_id I(4),
	slotstart I,
	slotlen I,
	user C(64),
	contact C(128),
	userclass I(1) NOTNULL DEFAULT 0,
	status I(1) NOTNULL DEFAULT ".Booker::STATNONE.",
	paid I(1) NOTNULL DEFAULT 0
";
$sqlarray = $dict->CreateTableSQL($this->DataTable, $fields, $taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray, FALSE);
if ($res != 2)
	return FALSE;
//index
$sqlarray = $dict->CreateIndexSQL('idx_'.$this->DataTable, $this->DataTable, 'bkg_id');
$dict->ExecuteSQLArray($sqlarray);
// sequence
$db->CreateSequence($this->DataTable.'_seq');

/*
repeated bookings-data table schema:
 bkg_id:
 item_id: resource or group id
 formula: interval-descriptor string
 user: identifier
 contact:
 userclass: 0 - 5
 subgrpcount: no. of in-group resources to be processed per subgrpalloc
 paid: boolean
 active: boolean TRUE unless booking has been deleted but historic data remain
*/
$fields = "
	bkg_id I(4) KEY,
	item_id I(4),
	formula C(256),
	user C(64),
	contact C(128),
	userclass I(1) DEFAULT 0,
	subgrpcount I(1) DEFAULT 0,
	paid I(1) DEFAULT 0,
	active I(1) DEFAULT 1
";
$sqlarray = $dict->CreateTableSQL($this->RepeatTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray, FALSE);
if ($res != 2)
	return FALSE;

if(DBGBKG)
{
// same sequence used for repeats and non-repeats
	$bid = $db->GenID($this->DataTable.'_seq');
	$item = 8;
	$sql = 'INSERT INTO '.$this->RepeatTable.' (bkg_id,item_id,formula,user,userclass) VALUES (?,?,?,?,?)';
	$dummy = array($bid,$item,'Mon..Fri@20:00..21:00','Repeater',5);
	$db->Execute($sql,$dummy);
}

/*
NOTE action.requestbooking.php must be conformed to any change here
submitted booking requests table schema
 req_id: unique identifier
 item_id: resource or group id
 slotstart: UTC timestamp
 slotlen: seconds booked, NOT seconds-per-slot
 sender: identifier, assumed to be the booker i.e. not a 3rd-party
 contact:
 comment:
 subgrpcount: no. of requested items in a group, irrelevant for non-groups
 status: one of the Booker::STAT* values
 paid: boolean
 lodged: UTC timestamp
 approved: UTC timestamp
 */
$fields = "
	req_id I(4) KEY,
	item_id I(4),
	slotstart I,
	slotlen I,
	sender C(64),
	contact C(128),
	comment C(256),
	subgrpcount I(1) DEFAULT 1,
	status I(1) NOTNULL DEFAULT ".Booker::STATNONE.",
	paid I(1) DEFAULT 0,
	lodged I,
	approved I
";
$sqlarray = $dict->CreateTableSQL($this->RequestTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray,FALSE);
if ($res != 2)
	return FALSE;
$db->CreateSequence($this->RequestTable.'_seq');
/*
Data cache
*/
$fields = "
	cache_id I(2) AUTO KEY,
	keyword C(48),
	value B,
	save_time ".CMS_ADODB_DT;
$pre = cms_db_prefix();
$sqlarray = $dict->CreateTableSQL($pre.'module_bkr_cache',$fields,$taboptarray);
$dict->ExecuteSQLArray($sqlarray);
//this is not for table-data content
$db->CreateSequence($pre.'module_bkrcache_seq');

// permissions
$this->CreatePermission($this->PermStructName, $this->Lang('perm_module')); //Booker Module Admin
// bookings only
$this->CreatePermission($this->PermAdminName,$this->Lang('perm_admin')); //Booker Admin
$this->CreatePermission($this->PermEditName, $this->Lang('perm_edit')); //Booker Modify
$this->CreatePermission($this->PermSeeName, $this->Lang('perm_view')); //Booker View
//resources/groups
$this->CreatePermission($this->PermAddName, $this->Lang('perm_add'));
$this->CreatePermission($this->PermDelName, $this->Lang('perm_delete'));
$this->CreatePermission($this->PermModName, $this->Lang('perm_modify'));

// create preferences NOTE all named like corresponding table-column-name with 'pref_' prefix
$this->SetPreference('pref_approver','');
$this->SetPreference('pref_approvercontact','');
$this->SetPreference('pref_available',''); //always available
$this->SetPreference('pref_bookcount',0); //book any no. of slots
$this->SetPreference('pref_cleargroup',0);	//delete items in group when group is deleted (admin)
$this->SetPreference('pref_exportencoding','UTF-8'); //preference-only, not an items-table field
$this->SetPreference('pref_exportfile',0); //preference-only, not an items-table field
$this->SetPreference('pref_fee1',0.0);
$this->SetPreference('pref_fee1condition',''); //empty = always used
$this->SetPreference('pref_fee2',0.0);
$this->SetPreference('pref_fee2condition',''); //empty = never used
$this->SetPreference('pref_feugroup',0);
$this->SetPreference('pref_formiface',''); //data for custom request-form
$this->SetPreference('pref_keepcount',0);
$this->SetPreference('pref_keeptype',8); //year-index per TimeIntervals()
$this->SetPreference('pref_latitude',0.0);
$this->SetPreference('pref_longitude',0.0);
$this->SetPreference('pref_leadcount',0);
$this->SetPreference('pref_leadtype',3); //week-index per TimeIntervals()
$this->SetPreference('pref_listformat',Booker::LISTSU);
$this->SetPreference('pref_membersname',$this->Lang('members'));
$this->SetPreference('pref_owner',0);	//each resource/group may have a specific owner/contact
$this->SetPreference('pref_pagerows',10); //page-length of admin bookings-data view
$this->SetPreference('pref_paymentiface',''); //data for payment-processing
$this->SetPreference('pref_rationcount',0);
$this->SetPreference('pref_showrange',1); //week-index per DisplayIntervals(), default bookings-display-period
$this->SetPreference('pref_slotcount',1);
$this->SetPreference('pref_slottype',1); //hour-index per TimeIntervals()
$this->SetPreference('pref_smspattern','^\d{6,15}$');
$this->SetPreference('pref_smsprefix',''); //TODO func(timezone)
$this->SetPreference('pref_stripexport',0);
$this->SetPreference('pref_stylesfile','');
$this->SetPreference('pref_subgrpalloc',Booker::ALLOCNONE);
$this->SetPreference('pref_timeformat','G:i');	//default date/time format string
//for email address checking by mailcheck.js
$this->SetPreference('pref_domains',''); //for initial check
$this->SetPreference('pref_subdomains',''); //for secondary check
$this->SetPreference('pref_topdomains','biz,co,com,edu,gov,info,mil,name,net,org'); //for final check

$format = get_site_preference('defaultdateformat');
if ($format)
{
	$strftokens = array(
	// Day - no strf eq : S
	'a' => 'D', 'A' => 'l', 'd' => 'd', 'e' => 'j', 'j' => 'z', 'u' => 'N', 'w' => 'w',
	// Week - no date eq : %U, %W
	'V' => 'W',
	// Month - no strf eq : n, t
	'b' => 'M', 'B' => 'F', 'm' => 'm',
	// Year - no strf eq : L; no date eq : %C, %g
	'G' => 'o', 'y' => 'y', 'Y' => 'Y',
	// Full Date / Time - no strf eq : c, r; no date eq : %c
	's' => 'U', 'D' => 'j/n/y', 'F' => 'Y-m-d', 'x' => 'j F Y'
 	);
	$format = str_replace('%','',$format);
	$parts = explode(' ',$format);
	foreach ($parts as $i => $fmt)
	{
		if(array_key_exists($fmt, $strftokens))
			$parts[$i] = $strftokens[$fmt];
		else
			unset($parts[$i]);
	}
	$format = implode(' ', $parts);
}
else
	$format = 'd F y';

$this->SetPreference('pref_dateformat',$format); //default date/time format string

if(date_default_timezone_get())
	$zone = date_default_timezone_get();
elseif(!empty($config['timezone']))
	$zone = $config['timezone'];
else
{
	$zone = ini_get('date.timezone');
	if($zone == FALSE)
		$zone = 'Europe/London';//default to GMT
}
$this->SetPreference('pref_timezone',$zone);	//default zone for time calcs

$ud = $this->GetName();
if($ud)
{
	$fp = $config['uploads_path'];
	if($fp && is_dir($fp))
	{
		$fp = cms_join_path($fp,$ud);
		if($fp && !is_dir($fp))
		{
			if(!mkdir($fp,0770,TRUE))
				$ud = '';
		}
	}
}
$this->SetPreference('pref_uploadsdir',$ud); //place for file uploads, preference-only, not an items-table field

// enable FormBuilder-module custom processing
$ob = ModuleOperations::get_instance()->get_module_instance('FormBuilder');
if(is_object($ob))
{
	$fp = $config['root_path'];
	if($fp && is_dir($fp))
	{
		//this->GetModulePath() N/A prior to installation
		$src = cms_join_path($fp,'modules','Booker','lib','DispositionBookingRequest.class.php');
		if(is_file($src))
		{
			$dest = cms_join_path($ob->GetModulePath,'classes');
			if(copy($src,$dest))
			{
//TODO remember
			}
			else
			{
//TODO handle error - NO FRONTEND BOOKINGS
			}
		}
		else
		{
			echo "File path error";
//TODO handle error - NO FRONTEND BOOKINGS
		}
	}
	unset($ob);
}

// put mention into the admin log
$this->Audit(0, $this->Lang('fullname'), $this->Lang('audit_installed',$this->GetVersion()));

?>
