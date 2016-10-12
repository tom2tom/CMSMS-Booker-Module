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
$pre = cms_db_prefix();
/*
items (i.e. groups and resources) table schema:
NOTE (almost) no NOTNULL/default values, so inheritance can be determined
NOTE: changes here must be reflected in action.openitem, Import::ImportItems(), Export::ExportItems()
 item_id:
 alias:
 name:
 description:
 keywords: comma-separated descriptors/tags for similarity scans
 pickname: for a group - name to display in resource-picker
 membersname: for a group - generic plural member-descriptor e.g. 'members'
 image: filename or ,-separated series of names, uploaded file(s)
 available: interval-descriptor string or empty for always-available
 pickthis: whether to display this item in resource-picker
 pickmembers: for a group - whether to display its members in resource-picker
 slottype: enumerator for interval of a single booking per TimeIntervals()
 slotcount: count of slottype intervals which, together with slottype, defines length of a bookings
 bookcount: max. slots per booking, 0 = no limit, 1 = no need for booking end choice
 bookertell: whether to (try to) send confirmation message to booker, default inherit
 leadtype: enumerator for interval used for max. lead-time for bookings
 leadcount: count of leadtype intervals which, together with leadtype, defines max. lead-time for (no-repeat) bookings
 rationcount: max. pending bookings for any specific booker
 keeptype: enumerator for interval used for max. retention-time for past bookings
 keepcount: count of keeptype intervals which, together with keeptype, defines max. retention-time for bookings history
 grossfees: whether recorded fees include sales tax, default true
 taxrate: sales tax rate, 0..1 assumed to be a proportion, >=1 assumed percent
 latitude: for sun-related calcs, accurate to ~1km
 longitude: ditto
 timezone: for date/time offsets & calcs
 dateformat: how to display dates
 timeformat: how to display times
 listformat: Booker::LIST* enum representing default layout for list-style bookings display
 stylesfile: specific (uploaded) css file
 approver: identifier of whoever handles booking requests for this item
 approvercontact: contact info for the approver
 approvertell: whether to (try to) send notice to approver about submitted/recorded booking, default inherit
 smsprefix: country-code to be prepended to sms messages when needed
 smspattern: regex for determining whether approvercontact is a phone suitable for sms messages (no whitespace, before country-prefix)
 formiface: for future use e.g. module name, properties to use, for custom booking-request form
 paymentiface: for future use e.g. module name, gateway name, properties to use
 feugroup: id of group of whose members are authorised to make 'direct' (un-mediated) bookings
 owner: uid of the contact-person for the item, or 0 if there's no such person
 cleargroup: boolean whether to clear data for item when (sole) parent-group data are cleared, default false
 subgrpalloc: Booker::ALLOC* enum for sub-group allocation protocol
 subgrpdata: internal use, for working with subgrpalloc
 active: enum: 0 never; 1 always; 2 inherit (see also: available); -1 deletion pending while historical data needed
*/
$fields = '
item_id I(4) KEY,
alias C(24),
name C(64),
description X,
keywords C(256),
pickname C(48),
membersname C(32),
image C(128),
available C(128),
pickthis I(1) DEFAULT 1,
pickmembers I(1) DEFAULT 0,
slottype I(1) DEFAULT 1,
slotcount I(1) DEFAULT 0,
leadtype I(1) DEFAULT 2,
leadcount I(1) DEFAULT 0,
keeptype I(1) DEFAULT 5,
keepcount I(1) DEFAULT 0,
bookcount I(1) DEFAULT 0,
rationcount I(1) DEFAULT 0,
grossfees I(1) DEFAULT 1,
taxrate N(8.4),
latitude N(8.3),
longitude N(8.3),
timezone C(48),
dateformat C(12),
timeformat C(12),
listformat I(1),
stylesfile C(36),
approver C(64),
approvercontact C(128),
approvertell I(1) DEFAULT 1,
bookertell I(1) DEFAULT 1,
smsprefix C(8),
smspattern C(32),
formiface C(48),
paymentiface C(48),
feugroup I(8),
owner I(8),
cleargroup I(1) DEFAULT 0,
subgrpalloc I(1),
subgrpdata I(1) DEFAULT 0,
active I(1) DEFAULT 1
';
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
$gid = $db->GenID($this->ItemTable.'_gseq');
$sql = 'INSERT INTO '.$this->ItemTable.' (item_id,name) VALUES (?,?)'; //TODO more fields
$db->Execute($sql,array($gid,$this->Lang('groupdefault')));

/*
group-relationships table schema:
 gid: unique identifier
 child: id of resource or group, indexed, not unique
 parent: id of group of which child is a member
 likeorder: order of children 'likeness' in parent
 proximity: order of multi-parents in all of which child is a member
*/
$fields = '
gid I AUTO KEY,
child I(2) NOTNULL DEFAULT 0,
parent I(2) NOTNULL DEFAULT 0,
likeorder I(2) NOTNULL DEFAULT 1,
proximity I(2) NOTNULL DEFAULT 1
';
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
 bkg_id: unique identifier
 bulk_id: bkg_id of repeat-booking, or of group-boooking, or 0 TODO check need for separate repeat & group id's
 item_id: resource or group id
 slotstart: UTC timestamp
 slotlen: seconds booked, NOT seconds-per-slot
 booker_id: identifier
 status: one of the Booker::STAT* values
 paid: boolean
 active: enum/boolean 1, or 0 if booking has been deleted but historic data remain
Booker\CSV::ImportBookings must conform to this
*/
$fields = '
bkg_id I(4) KEY,
bulk_id I(4) DEFAULT 0,
item_id I(4),
slotstart I(8),
slotlen I(4),
booker_id I(4),
status I(1) DEFAULT '.Booker::STATNONE.',
paid I(1) DEFAULT 0,
active I(1) DEFAULT 1
';
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
 bkg_id: unique identifier
 item_id: resource or group id
 formula: interval-descriptor string
 booker_id: identifier
 checkedfrom: timestamp for start of first day for which actual bookings have been recoreded
 checkedto: timestamp for start of day after last day for which actual bookings have been recoreded
 subgrpcount: no. of in-group resources to be processed per subgrpalloc
 paid: boolean
 active: enum/boolean 1, or 0 if booking has been deleted but historic data remain
*/
$fields = '
bkg_id I(4) KEY,
item_id I(4),
formula C(256),
booker_id I(4),
checkedfrom I DEFAULT 0,
checkedto I DEFAULT 0,
subgrpcount I(1) DEFAULT 1,
paid I(1) DEFAULT 0,
active I(1) DEFAULT 1
';
$sqlarray = $dict->CreateTableSQL($this->RepeatTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray, FALSE);
if ($res != 2)
	return FALSE;

/*
resource-availabilty-cache table schema:
 avl_id: key, not used
 item_id: resource, NOT a group
 slotstart: UTC timestamp
 slotlen: seconds available
 cond_id: condition identifier
*/
/*$fields = '
avl_id I(4) AUTO KEY,
item_id I(4),
slotstart I(8),
slotlen I(4),
cond_id I(4)
';
$sqlarray = $dict->CreateTableSQL($this->AvailTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray, FALSE);
if ($res != 2)
	return FALSE;
//index
$sqlarray = $dict->CreateIndexSQL('idx_'.$this->AvailTable, $this->AvailTable, 'item_id');
$dict->ExecuteSQLArray($sqlarray);
// sequence
//$db->CreateSequence($this->AvailTable.'_seq');
*/
/*
Fees for resource usage & related conditions
 condition_id:
 item_id: group/item to which the condition applies
 signature: identifier for cross-resource matching, raw crc32 hash of slottype.slotcount.fee.feecondition
 description: public info/help about the condition
 slottype: enumerator for interval covered by the payment, 0..5 per TimeIntervals() or -1 for fixed amount
 slotcount: count of slottype intervals which, together with slottype, defines length to which fee applies
 fee: rate or amount which applies when feecondition is satisfied
 feecondition: interval-descriptor or user-decriptor
 usercondition: string, numeric usergroup 0..9 or comma-seprated series of them or empty
 condorder: enum for display-order and fee-application-order
 active: boolean/enum whether to use this fee
NOTE changes to this field-structure must be replicated in the add-fee mechanism
in action.fees.php
*/
$fields = '
condition_id I(4) KEY,
item_id I(4),
signature I(4),
description C(64),
slottype I(1),
slotcount I(1),
fee N(8.2),
feecondition C(128),
usercondition C(32),
condorder I(1) DEFAULT -1,
active I(1) DEFAULT 1
';
$sqlarray = $dict->CreateTableSQL($this->FeeTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray,FALSE);
if ($res != 2)
	return FALSE;
$db->CreateSequence($this->FeeTable.'_seq');

/*
bookers table schema:
 booker_id: table key
 name: identifier for display, and identity check if publicid N/A
 publicid: login/account for 'registed' bookers
 passhash: login password, 1-way encrypted
 address: email (maybe accept a post-address...) for general messaging and billing
 phone: cell-phone number, preferred for messaging
 addwhen: UTC timestamp when this record added
 type: combination of 10 generic types and permission-flags - see class.Userops
 displayclass: display-stying enum 1..Booker::USERSTYLES
 active: whether currently enabled
*/
$fields = '
booker_id I(4) KEY,
name C(64),
publicid C(32),
passhash C(48),
address C(96),
phone C(24),
addwhen I(8),
type I(1) DEFAULT 0,
displayclass I(1) DEFAULT 1,
active I(1) DEFAULT 1
';
$sqlarray = $dict->CreateTableSQL($this->BookerTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray,FALSE);
if ($res != 2)
	return FALSE;
$db->CreateSequence($this->BookerTable.'_seq');

/*
NOTE action.requestbooking.php must be conformed to any change here
 history table schema:
 history_id: table key
 booker_id: cross-referencer (indexed)
 item_id: booked-resource identifier
 subgrpcount: no. of requested items in a group, irrelevant for non-groups
 lodged: UTC timestamp booking submitted/recorded
 approved: UTC timestamp - sometimes needed
 removed: UTC timestamp - sometimes needed for cleanups
 slotstart: UTC timestamp start of booking
 slotlen: booking length (seconds)
 comment: as supplied by booker as part of request
 fee: how much was paid
 netfee: fee less any gateway/institution cost
 status: enum per some of Booker:STAT*
	See also BookingCartItem:: constants which overlap this a bit
 payment: another enum per some of Booker:STAT*
 gatetransaction: transaction id reported by payment gateway
 gatedata: json data reported by payment gateway, encrypted
*/
$fields = '
history_id I(4) KEY,
booker_id I(4),
item_id I(4),
subgrpcount I(1) DEFAULT 1,
lodged I(8),
approved I(8),
removed I(8),
slotstart I(8),
slotlen I(4),
comment C(64),
fee N(8.2),
netfee N(8.2),
status I(1) DEFAULT '.Booker::STATNONE.',
payment I(1) DEFAULT '.Booker::STATFREE.',
gatetransaction C(48),
gatedata B
';
$sqlarray = $dict->CreateTableSQL($this->HistoryTable,$fields,$taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray,FALSE);
if ($res != 2)
	return FALSE;
// index
$sqlarray = $dict->CreateIndexSQL('idx_'.$this->HistoryTable,$this->HistoryTable,'booker_id');
$dict->ExecuteSQLArray($sqlarray);
$db->CreateSequence($this->HistoryTable.'_seq');

/*
item-pickability table schema:
 gid: unique identifier
 item_id: group id
 pick: boolean whether to always show @item_id in relevant resource-picklists
 pickfor: group identifier or NULL, if not pick, then show item_id in relevant
	resource-picklists when this field's value is an ancestor of item_id
 pickmembers: boolean whether to always show members of @item_id in relevant resource-picklists
 pickmembersfor: group identifier or NULL, if not pickmembers, then show members
	of item_id in relevant resource-picklists when this field's value is an ancestor of item_id
*/
/*$fields = '
gid I AUTO KEY,
item_id I(4) NOTNULL,
pick I(1) DEFAULT 1,
pickfor I(4),
pickmembers I(1) DEFAULT 1,
pickmembersfor I(4)
';
$sqlarray = $dict->CreateTableSQL($this->ItemTable.'pick', $fields, $taboptarray);
if ($sqlarray == FALSE)
	return FALSE;
$res = $dict->ExecuteSQLArray($sqlarray, FALSE);
if ($res != 2)
	return FALSE;
*/
/*
Data cache
 cache_id:
 keyword: key
 value: flattened value
 savetime: timestamp - UTC?
 lifetime: seconds
*/
$fields = '
cache_id I(2) AUTO KEY,
keyword C(48),
value B,
savetime I(8),
lifetime I(4)
';
$sqlarray = $dict->CreateTableSQL($pre.'module_bkr_cache',$fields,$taboptarray);
$dict->ExecuteSQLArray($sqlarray);
//this is not for table-data content
$db->CreateSequence($pre.'module_bkr_cache_seq');

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
//bookers
$this->CreatePermission($this->PermPerName, $this->Lang('perm_booker'));

// create preferences NOTE all named like corresponding table-column-name with 'pref_' prefix
$this->SetPreference('pref_approver','');
$this->SetPreference('pref_approvercontact','');
$this->SetPreference('pref_approvertell',1);
$this->SetPreference('pref_available',''); //always available
$this->SetPreference('pref_bookcount',0); //book any no. of slots
$this->SetPreference('pref_bookertell',1);
$this->SetPreference('pref_cleargroup',0);	//delete items in group when group is deleted (admin)
$this->SetPreference('pref_exportencoding','UTF-8'); //preference-only, not an items-table field
$this->SetPreference('pref_exportfile',0); //preference-only, not an items-table field
$this->SetPreference('pref_fee',0.0);
$this->SetPreference('pref_feecondition',''); //empty = always used
$this->SetPreference('pref_feugroup',0);
$this->SetPreference('pref_formiface',''); //data for custom request-form
$this->SetPreference('pref_grossfees',1);
$this->SetPreference('pref_keepcount',0);
$this->SetPreference('pref_keeptype',8); //year-index per TimeIntervals()
$this->SetPreference('pref_latitude',0.0);
$this->SetPreference('pref_longitude',0.0);
$this->SetPreference('pref_taxrate',0.0);
$this->SetPreference('pref_leadcount',0);
$this->SetPreference('pref_leadtype',3); //week-index per TimeIntervals()
$this->SetPreference('pref_listformat',Booker::LISTSU);
$this->SetPreference('pref_masterpass','OWFmNT1dGbU5FbnRlciBhdCB5b3VyIG93biByaXNrISBEYW5nZXJvdXMgZGF0YSE=');
$this->SetPreference('pref_membersname',$this->Lang('members'));
$this->SetPreference('pref_owner',0);	//each resource/group may have a specific owner/contact
$this->SetPreference('pref_pagerows',10); //page-length of admin bookings-data view
$this->SetPreference('pref_paymentiface',''); //data for payment-processing
$this->SetPreference('pref_rationcount',0);
$this->SetPreference('pref_showrange',1); //week-index per DisplayIntervals(), default bookings-display-period
$this->SetPreference('pref_slotcount',1);
$this->SetPreference('pref_slottype',1); //hour-index per TimeIntervals()
$this->SetPreference('pref_smspattern','^\d[ \d]{6,15}$');
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
if ($format) {
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
	foreach ($parts as $i => $fmt) {
		if (array_key_exists($fmt, $strftokens))
			$parts[$i] = $strftokens[$fmt];
		else
			unset($parts[$i]);
	}
	$format = implode(' ', $parts);
} else
	$format = 'd F y';

$this->SetPreference('pref_dateformat',$format); //default date/time format string

if (date_default_timezone_get())
	$zone = date_default_timezone_get();
elseif (!empty($config['timezone']))
	$zone = $config['timezone'];
else {
	$zone = ini_get('date.timezone');
	if ($zone == FALSE)
		$zone = 'Europe/London';//default to GMT
}
$this->SetPreference('pref_timezone',$zone);	//default zone for time calcs

//place for file uploads, not an inheritable item-property
$ud = $this->GetName();
if ($ud) {
	$fp = $config['uploads_path'];
	if ($fp && is_dir($fp)) {
		$fp = cms_join_path($fp,$ud);
		if ($fp && !is_dir($fp)) {
			if (!mkdir($fp,0777,TRUE)) //don't know how server is running!
				$ud = '';
		}
	}
}
$this->SetPreference('pref_uploadsdir',$ud);
//site-page alias for use in RegisterRoute, not an inheritable item-property
$this->SetPreference('pref_sitepage','booker');

// enable FormBuilder-module custom processing
$ob = cms_utils::get_module('FormBuilder');
if (is_object($ob)) {
	$fp = $config['root_path'];
	if ($fp && is_dir($fp)) {
		//this->GetModulePath() N/A prior to installation
		$src = cms_join_path($fp,'modules','Booker','lib','DispositionBookingRequest.class.php');
		if (is_file($src)) {
			$dest = cms_join_path($ob->GetModulePath,'classes');
			if (copy($src,$dest)) {
//TODO remember
			} else {
//TODO handle error - NO FRONTEND BOOKINGS
			}
		} else {
			echo 'File path error';
//TODO handle error - NO FRONTEND BOOKINGS
		}
	}
	unset($ob);
}

// put mention into the admin log
$this->Audit(0, $this->Lang('fullname'), $this->Lang('audit_installed',$this->GetVersion()));
