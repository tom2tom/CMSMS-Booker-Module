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
 NOTE: changes here must be reflected in action.open.php, Booker\CSV::ImportItems
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
 slottype I(1) DEFAULT 2,
 slotcount I(1) DEFAULT 1,
 bookcount I(1),
 bookertell I(1) DEFAULT -1,
 leadtype I(1),
 leadcount I(1),
 rationcount I(1),
 keeptype I(1),
 keepcount I(1),
 grossfees I(1) DEFAULT -1,
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
 approvertell I(1) DEFAULT -1,
 smsprefix C(8),
 smspattern C(32),
 formiface C(48),
 paymentiface C(48),
 feugroup I,
 owner I,
 cleargroup I(1) DEFAULT 0,
 subgrpalloc I(1),
 repeatsuntil I DEFAULT 0,
 subgrpdata I(1) DEFAULT 0,
 active I(1) DEFAULT 1
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
 bkg_id: maybe from repeat booking, so not necessarily unique, but is indexed
 item_id: resource or group id
 slotstart: UTC timestamp
 slotlen: seconds booked, NOT seconds-per-slot
 booker_id: identifier
 status: one of the Booker::STAT* values
 paid: boolean
Booker\CSV::ImportBookings must conform to this
*/
$fields = "
 bkg_id I(4),
 item_id I(4),
 slotstart I,
 slotlen I(4),
 booker_id I(4),
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
 booker_id: identifier
 subgrpcount: no. of in-group resources to be processed per subgrpalloc
 paid: boolean
 active: enum/boolean 1, or 0 if booking has been deleted but historic data remain
*/
$fields = "
 bkg_id I(4) KEY,
 item_id I(4),
 formula C(256),
 booker_id I(4),
 subgrpcount I(1) DEFAULT 1,
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
resource-availabilty-cache table schema:
 avl_id: key, not used
 item_id: resource, NOT a group
 slotstart: UTC timestamp
 slotlen: seconds available
 cond_id: condition identifier
*/
/*$fields = "
 avl_id I(4) AUTO KEY,
 item_id I(4),
 slotstart I,
 slotlen I(4),
 cond_id I(4)
";
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
 condtype: enum 0 = interval or 1 = user
 condorder: enum for display-order and fee-application-order
 active: boolean/enum whether to use this fee
 NOTE changes to this field-structure must be replicated in the add-fee mechanism
 in action.fees.php
*/
$fields = "
 condition_id I(4) KEY,
 item_id I(4),
 signature I(4),
 description C(64),
 slottype I(1),
 slotcount I(1),
 fee N(8.2),
 feecondition C(128),
 condtype I(1) DEFAULT 0,
 condorder I(1) DEFAULT -1,
 active I(1) DEFAULT 1
";
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
$fields = "
 booker_id I(4) KEY,
 name C(64),
 publicid C(32),
 passhash C(48),
 address C(96),
 phone C(24),
 addwhen I,
 type I(1) DEFAULT 0,
 displayclass I(1) DEFAULT 1,
 active I(1) DEFAULT 1
";
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
$fields = "
 history_id I(4) KEY,
 booker_id I(4),
 item_id I(4),
 subgrpcount I(1) DEFAULT 1,
 lodged I,
 approved I,
 slotstart I,
 slotlen I(4),
 comment C(64),
 fee N(8.2),
 netfee N(8.2),
 status I(1) DEFAULT ".Booker::STATNONE.",
 payment I(1) DEFAULT ".Booker::STATFREE.",
 gatetransaction C(48),
 gatedata B
";
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
Data cache
 cache_id:
 keyword: key
 value: flattened value
 savetime: timestamp - UTC?
 lifetime: seconds
*/
$fields = "
 cache_id I(2) AUTO KEY,
 keyword C(48),
 value B,
 savetime I,
 lifetime I(4)
";
$sqlarray = $dict->CreateTableSQL($pre.'module_bkr_cache',$fields,$taboptarray);
$dict->ExecuteSQLArray($sqlarray);
//this is not for table-data content
$db->CreateSequence($pre.'module_bkr_cache_seq');

if (DBGBKG) {
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
	foreach ($data as $dummy) {
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
	$sql = 'INSERT INTO '.$this->GroupTable.' (child,parent,likeorder,proximity) VALUES (?,?,?,?)';
	foreach ($data as $dummy) {
		$db->Execute($sql,$dummy);
	}

	$dt = new DateTime('now',new DateTimeZone('UTC'));
	$i = $dt->format('w');
	if ($i > 0)
		$dt->modify('-'.$i.' days'); //to current-week Sunday
	$daygroup = 5;

	$data = array(
array(2,11,1,9,0,1),
array(2,12,1,2,0,0),
array(2,13,2,4,0,1),
array(2,15,1,6,0,0),
array(2,9,1,7,0,0),

array(2,11,1,9,0,1),
array(2,12,1,2,1,0,0),
array(2,13,2,4,0,1),
array(2,15,1,6,0,0),
array(2,9,1,7,0,0),

array(2,11,1,9,0,1),
array(2,12,1,2,1,0,0),
array(2,13,2,11,0,1),
array(2,15,1,6,0,0),
array(2,9,1,7,0,0),

array(2,11,1,9,0,1),
array(2,12,1,8,0,0),
array(2,13,2,11,0,1),
array(2,15,1,6,0,0),
array(2,9,1,7,0,0),

array(2,11,1,9,0,1),
array(2,12,1,8,0,0),
array(2,13,2,4,0,1),
array(2,15,1,6,0,0),
array(2,9,1,7,0,0)
);
	$sql = 'INSERT INTO '.$this->DataTable.
' (bkg_id,item_id,slotstart,slotlen,booker_id,status,paid) VALUES (?,?,?,?,?,?,?)';
	$i = $daygroup;
	foreach ($data as $dummy) {
		if ($i == $daygroup) {
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
	$sql = 'INSERT INTO '.$this->RepeatTable.' (bkg_id,item_id,formula,booker_id) VALUES (?,?,?,?)';
	$dummy = array($bid,8,'Mon..Fri@20:00..21:00',1);
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
	$sql = 'INSERT INTO '.$this->FeeTable.
' (condition_id,item_id,signature,description,slottype,slotcount,fee,feecondition,condorder) VALUES (?,?,?,?,?,?,?,?,?)';
	$i = 0;
	foreach ($data as $dummy) {
		$sig = '';
		foreach (array(2,3,4,5) as $k) {
			$sig .= ($dummy[$k] !== NULL) ? $dummy[$k] : 'NULL';
		}
		$sig = crc32($sig);
		$bid = $db->GenID($this->FeeTable.'_seq');
		$args = array($bid,$dummy[0],$sig,$dummy[1],$dummy[2],$dummy[3],$dummy[4],$dummy[5],$i);
		$db->Execute($sql,$args);
		$i++;
	}

	$funcs = new Booker\Userops();
	//name,publicid,passhash,address,phone,addwhen,type,displayclass
	$data = array(
array('Repeater','','','tpgww@onepost.net','0417394479','2016-1-1',0,5),
array('Mary','C821D00','password','','0417394479','2016-1-1',0,1),
array('Tester 45','','','','0417394479','2016-3-4',0,4),
array('Comp1','','','tpgww@onepost.net','0417394479','2016-3-4',0,4),
array('Somebody Else','C824D','password','','0417394479','2016-4-4',0,1),
array('User2','C821D123','password','tpgww@onepost.net','0417394479','2016-5-2',1,1),
array('Fred','','','tpgww@onepost.net','','2015-1-10',10,1),
array('Jane','C821D125','longlonglonglonglong','tpgww@onepost.net','0417394479','2015-10-10',1,1),
array('Coaching','C822D123','nope','','','2016-1-1',3,5),
array('Roger Rabbit','C823D123','haha','tpgww@onepost.net','0417394479','2016-4-4',2,2),
array('Roger RAbbit','C823D123','c9218d','tpgww@onepost.net','0417394479','2016-4-3',2,2),
array('Comp2','','','tpgww@onepost.net','0417394479','2016-3-4',0,4)
);
	$sql = 'UPDATE '.$this->BookerTable.' SET address=?,phone=?,addwhen=?,type=?,displayclass=? WHERE booker_id=?';
	foreach ($data as $dummy) {
		$bid = $funcs->AddUser($this,$dummy[0],$dummy[1],$dummy[2]);
		$dt->modify($dummy[5]);
		$args = array($dummy[3],$dummy[4],$dt->getTimestamp(),$dummy[6],$dummy[7],$bid);
		$db->Execute($sql,$args);
	}

	//history_id,booker_id,item_id,subgrpcount,lodged,approved,slotstart,slotlen,comment,fee
	$dt->modify('now');
	$dt->setTime(0,0,0);
	$stoff = $dt->getTimestamp();
	$dt->modify('2016-7-25 0:0');
	$stoff -= $dt->getTimestamp();

	$data = array(
array(4,2,'2015-12-12 9:15','2015-12-12 12:00',3600,'Hi there',0,Booker::STATOK),
array(4,1,'2015-1-1 15:14', '2015-1-2 9:00',7200,'Might need to cancel',0,Booker::STATOK),
array(4,3,'2016-4-30 17:00','2016-5-1 17:00',3600,'YAY TEAM',10,Booker::STATOK),
array(4,1,'2016-6-12 17:01','2016-6-19 14:00',7200,'Won\'t pay',0.6,Booker::STATOK),
array(5,1,'2016-7-12 17:01','2016-7-25 14:00',7200,'Nowish',12.5,Booker::STATSELFREC),
array(5,3,'2016-7-12 17:02','2016-7-25 14:00',3600,'Nowish',12.5,Booker::STATADMINREC),
array(6,1,'2016-7-20 17:01','2016-7-26 14:00',7200,'Future',28.0,Booker::STATADMINREC),
array(7,1,'2016-6-20 17:01','2016-7-2 14:00',7200,'Past',28.0,Booker::STATADMINREC),
array(7,1,'2016-7-20 13:39','2016-8-3 14:00',7200,'Future',28.0,Booker::STATSELFREC),
array(7,1,'2016-7-20 14:01','2016-8-2 14:00',7200,'Future',28.0,Booker::STATNEW),
array(8,1,'2016-7-20 17:01','2016-8-9 14:00',7200,'Future',28.0,Booker::STATNEW)
);
	$sql = 'INSERT INTO '.$this->HistoryTable.' (history_id,booker_id,item_id,lodged,slotstart,slotlen,comment,fee,status) VALUES (?,?,?,?,?,?,?,?,?)';
	$utils = new Booker\Utils();
	foreach ($data as $dummy) {
		$hid = $db->GenID($this->HistoryTable.'_seq');
		$dt->modify($dummy[2]);
		$st = $dt->GetTimestamp() + $stoff;
		$dt->modify($dummy[3]);
		$args = array($hid,$dummy[0],$dummy[1],$st,$dt->getTimestamp()+$stoff,$dummy[4]-1,$dummy[5],$dummy[6],$dummy[7]);
		$db->Execute($sql,$args);
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
$this->SetPreference('pref_uploadsdir',$ud); //place for file uploads, preference-only, not an items-table field

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
			echo "File path error";
//TODO handle error - NO FRONTEND BOOKINGS
		}
	}
	unset($ob);
}

// put mention into the admin log
$this->Audit(0, $this->Lang('fullname'), $this->Lang('audit_installed',$this->GetVersion()));
