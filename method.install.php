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
	paymentiface: for future use e.g. module name, gateway name, properties to use
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
	paymentiface C(48),
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
 contact: phone, email etc
 userclass: enum 0..5
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
 contact: phone, email etc
 userclass: enum 0..5
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

/*
NOTE action.requestbooking.php must be conformed to any change here
submitted booking requests table schema
 req_id: unique identifier
 item_id: resource or group id
 slotstart: UTC timestamp
 slotlen: seconds booked, NOT seconds-per-slot
 sender: identifier, assumed to be the booker i.e. not a 3rd-party
 contact: phone, email etc
 comment:
 userclass: enum 0..5
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
	userclass I(1) DEFAULT 0,
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

/* Fees & related conditions
 condition_id:
 item_id: group/item to which the condition applies
 description: public info/help about the condition
 slottype: enumerator for interval covered by the payment, 0..5 per TimeIntervals() or -1 for fixed amount
 slotcount: count of slottype intervals which, together with slottype, defines length to which fee applies
 fee: rate or amount which applies when feecondition is satisfied
 feecondition: interval-descriptor or user-decriptor
 condtype: enum 0 = interval or 1 = user
 condorder: enum for display-order and fee-application-order
 NOTE changes to this field-structure must be replicated in the add-fee mechanism
 in action.fees.php
*/
$fields = "
 condition_id I(4) KEY,
 item_id I(4),
 description C(64),
 slottype I(1),
 slotcount I(1),
 fee N(7.2),
 feecondition C(128),
 condtype I(1) DEFAULT 0,
 condorder I(1) DEFAULT -1,
 active I(1) DEFAULT 1
";
$sqlarray = $dict->CreateTableSQL($this->PayTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray,FALSE);
if ($res != 2)
	return FALSE;
$db->CreateSequence($this->PayTable.'_seq');

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
$db->CreateSequence($pre.'module_bkr_cache_seq');

if(DBGBKG)
{
	$data = array(
array(10001,'allcourts','All courts',NULL,NULL,'courts','6:00..21:00',1,1,3,1,5,1,'-37.814','144.963','Australia/Melbourne','j M Y','G:i',2,'P Cook','tpgww@onepost.net','61','^04\\d{8}$',NULL),
array(10002,'ontocar','Entoutcas courts',NULL,'front','courts',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,3),
array(10003,'carpet','Synthetic courts','Tigerturf court','back,carpet,tigerturf','modclay courts',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,1),
array(10004,'lit','Lit courts','Suitable for playing at night.<br />Fee: $10 per hour per court when lights are used.','light,lights','night courts',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,1),
array(1,'court1','Court 1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'P Cook','tpgww@onepost.net',NULL,NULL,NULL),
array(2,'court2','Court 2',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'P Cook','tpgww@onepost.net',NULL,NULL,NULL),
array(3,'court3','Court 3',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL),
array(4,'court4','Court 4',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL),
array(5,'court5','Court 5',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL),
array(6,'court6','Court 6',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL),
array(7,'court7','Court 7',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL),
array(8,'court8','Court 8',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL),
array(9,'court9','Court 9',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL),
array(10,'court10','Court 10',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL)
);
		$sql = 'INSERT INTO '.$this->ItemTable.
' (item_id,alias,name,description,keywords,membersname,available,slottype,slotcount,leadtype,leadcount,keeptype,keepcount,latitude,longitude,timezone,dateformat,timeformat,listformat,approver,approvercontact,smsprefix,smspattern,subgrpalloc)
VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
	foreach($data as $dummy)
	{
		$db->Execute($sql,$dummy);
	}

	$sql = 'UPDATE '.$this->ItemTable.'_seq SET id=10';
	$db->Execute($sql);
	$sql = 'UPDATE '.$this->ItemTable.'_gseq SET id=10004';
	$db->Execute($sql);

	$data = array(
array(10002,10001,-1,1),
array(10003,10001,-1,1),
array(10004,10002,-1,1),
array(10004,10001,-1,2),
array(1,10002,-1,1),
array(1,10004,-1,2),
array(1,10001,-1,3),
array(2,10002,-1,1),
array(2,10004,-1,2),
array(2,10001,-1,3),
array(3,10002,-1,1),
array(3,10004,-1,2),
array(3,10001,-1,3),
array(4,10002,-1,1),
array(4,10004,-1,2),
array(4,10001,-1,3),
array(5,10003,-1,1),
array(5,10001,-1,2),
array(5,10004,-1,3),
array(6,10003,-1,1),
array(6,10001,-1,2),
array(6,10004,-1,3),
array(7,10003,-1,1),
array(7,10001,-1,2),
array(7,10004,-1,3),
array(8,10003,-1,1),
array(8,10001,-1,2),
array(8,10004,-1,3),
array(9,10002,-1,1),
array(9,10001,-1,2),
array(10,10002,-1,1),
array(10,10001,-1,2)
);
	$sql = 'INSERT INTO '.$this->GroupTable.
' (child,parent,likeorder,proximity) VALUES (?,?,?,?)';
	foreach($data as $dummy)
	{
		$db->Execute($sql,$dummy);
	}

	$dt = new DateTime('now',new DateTimeZone('UTC'));
	$i = $dt->format('w');
	if($i > 0)
		$dt->modify('-'.$i.' days'); //to current-week Sunday
	$daygroup = 5;

	$data = array(
array(2,11,1,'Coaching','0444 555 666',5,0,1),
array(2,12,1,'Mary','@myfirm',1,0,0),
array(2,13,2,'Comp1',NULL,2,0,1),
array(2,15,1,'User2',NULL,0,0,0),
array(2,9,1,'Fred','me@here.com',1,0,0),

array(2,11,1,'Coaching','0444 555 666',5,0,1),
array(2,12,1,'Mary','@myfirm',1,0,0),
array(2,13,2,'Comp1',NULL,2,0,1),
array(2,15,1,'User2',NULL,0,0,0),
array(2,9,1,'Fred','me@here.com',1,0,0),

array(2,11,1,'Coaching','0444 555 666',5,0,1),
array(2,12,1,'Mary','@myfirm',1,0,0),
array(2,13,2,'Comp2',NULL,2,0,1),
array(2,15,1,'User2',NULL,1,0,0),
array(2,9,1,'Fred','me@here.com',1,0,0),

array(2,11,1,'Coaching','0444 555 666',5,0,1),
array(2,12,1,'Jane','@myfirm',1,0,0),
array(2,13,2,'Comp2',NULL,2,0,1),
array(2,15,1,'User2',NULL,0,0,0),
array(2,9,1,'Fred','me@here.com',1,0,0),

array(2,11,1,'Coaching','0444 555 666',5,0,1),
array(2,12,1,'Jane','@myfirm',1,0,0),
array(2,13,2,'Comp1',NULL,2,0,1),
array(2,15,1,'User2',NULL,1,0,0),
array(2,9,1,'Fred','me@here.com',1,0,0)
);
	$sql = 'INSERT INTO '.$this->DataTable.
' (bkg_id,item_id,slotstart,slotlen,user,contact,userclass,status,paid) VALUES (?,?,?,?,?,?,?,?,?)';
	$i = $daygroup;
	foreach($data as $dummy)
	{
		if($i == $daygroup)
		{
			$dt->modify('+1 day');
			$i = 0;
		}
		$bid = $db->GenID($this->DataTable.'_seq');
		$dt->setTime(0,0,0);
		$dt->modify('+'.(int)$dummy[1].' hours');
		$dummy[1] = $dt->getTimestamp();
        $dummy[2] = $dummy[2]*3600 - 1;
		array_unshift($dummy,$bid);
		$db->Execute($sql,$dummy);
		$i++;
	}
	$sql = 'UPDATE '.$this->DataTable.'_seq SET id='.(count($data)-1);
	$db->Execute($sql);

// same sequence used for repeats and non-repeats
	$bid = $db->GenID($this->DataTable.'_seq');
	$item = 8;
	$sql = 'INSERT INTO '.$this->RepeatTable.' (bkg_id,item_id,formula,user,userclass) VALUES (?,?,?,?,?)';
	$dummy = array($bid,$item,'Mon..Fri@20:00..21:00','Repeater',5);
	$db->Execute($sql,$dummy);

// 1 description 2 slottype 3 slotcount 4 fee 5 feecondition
	$data = array(
array(1,'Fixed test',-1,NULL,'28.00','sunrise..sunset'),
array(2,'Variable test',1,1,'15.00',NULL),
array(2,'Test2',1,1,'25.00','12:00'),
array(2,'Test3',1,1,'5.00','13:00..15:30'),
array(10003,'	Non-members hire',1,1,'28.00',NULL),
array(10004,'Nightplay fee',1,1,'10.00','0..sunrise,sunset..23:59'),
	);
	$sql = 'INSERT INTO '.$this->PayTable.
' (condition_id,item_id,description,slottype,slotcount,fee,feecondition,condorder) VALUES (?,?,?,?,?,?,?,?)';
	$i = 0;
	foreach($data as $dummy)
	{
		$bid = $db->GenID($this->PayTable.'_seq');
		$args = array($bid,$dummy[0],$dummy[1],$dummy[2],$dummy[3],$dummy[4],$dummy[5],$i);
		$db->Execute($sql,$args);
		$i++;
	}
}

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
$this->SetPreference('pref_fee',0.0);
$this->SetPreference('pref_feecondition',''); //empty = always used
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
