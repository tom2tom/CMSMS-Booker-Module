<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
//NOTE Any ' inside these strings which is js-processed must be double-escaped
$lang['accessdenied'] = 'Access denied. You don\'t have %s permission.';
$lang['accessdenied3'] = 'You don\'t have permission.';
$lang['account'] = 'account/login';
$lang['activate'] = 'Activate';
$lang['activatesel'] = 'toggle activation of selected %s';
$lang['addbooker'] = 'Add new booker';
$lang['addbooking2'] = 'Add new repeat-booking';
$lang['addbooking'] = 'Add new booking';
$lang['addfee']	= 'Add new fee';
$lang['addgroup'] = 'Add new group';
$lang['additem'] = 'Add new resource';
$lang['advanced'] = 'Advanced';
$lang['all'] = 'All';
$lang['allgroups'] = 'All of these';
$lang['allsaved']='Is everything saved that needs to be?';
$lang['allusers'] = 'Everyone Authorised';
$lang['always'] = 'Always';
$lang['anytime'] = 'anything';
$lang['application'] = 'Applies when';
$lang['apply'] = 'Apply';
$lang['approve'] = 'Approve';
$lang['approved'] = 'Approved';
$lang['approver'] = 'Person who approves booking requests';
$lang['approvercontact'] = 'Contact details for approver';
$lang['approvertell'] = 'Send message to approver upon saved booking or notice from booker';
$lang['assignchoose'] = 'Choose assignment';
$lang['assignfirst'] = 'First available';
$lang['assignnone'] = 'Not applicable';
$lang['assignrandom'] = 'Random assignment';
$lang['assignrotate'] = 'Rotate assignment';
$lang['ask'] = 'Ask';
$lang['audit_installed'] = 'version %s installed';
$lang['audit_uninstalled'] = 'uninstalled';
$lang['audit_upgraded'] = 'upgraded to version %s';
$lang['back_module'] = 'Module Main Page';
$lang['basic'] = 'Basic';
$lang['book'] = 'Book';
$lang['booker'] = 'booker';
$lang['booker_multi'] = 'booker(s)';
$lang['bookertell'] = 'Send confirmation message to booker after request or saved booking';
$lang['booking'] = 'booking';
$lang['booking_feedback'] = 'Booking request lodged';
$lang['booking_feedback2'] = 'Booking recorded';
$lang['booking_multi'] = 'booking(s)';
$lang['bookings_deleted'] = '%d bookings deleted'; //count
$lang['calendar'] = 'Calendar';
$lang['cancel'] = 'Cancel';
$lang['cart'] = 'Cart';
$lang['change'] = 'Change';
$lang['close'] = 'Close';
$lang['compulsory_items'] = 'Properties marked with a <strong>*</strong> are compulsory.' ;
//$lang['confirm']='Are you sure?';
$lang['confirm_delete_record']='Are you sure you want to delete this booking?';
$lang['confirm_delete_sel']='Are you sure you want to delete the selected bookings?';
$lang['confirm_delete_type'] = 'Are you sure you want to delete %s \\\'%s\\\' ?';
//$lang['confirm_uninstall']='Are you sure you want to uninstall Boooker?';
$lang['contact'] = 'contact'; //see also title...
$lang['continue'] = 'Continue';
$lang['copy_type'] = 'Copy of %s';
$lang['countof'] = '%d of %d';
$lang['countof2'] = '%d of %s';
$lang['currentdesc'] = 'There already is a booking at the selected time, in the name of <strong>%s</strong>.';
$lang['currentdesc2'] = 'There already is a booking in the name of <strong>%s</strong>, starting <strong>%s</strong> and ending <strong>%s</strong>.';
$lang['currentdesc3'] = 'There already are booking(s) at the selected time';
$lang['date'] = 'date';
$lang['day'] = 'day';
$lang['delete'] = 'Delete';
$lang['delete_upload'] = 'Delete uploaded file(s) <strong>%s</strong>';
$lang['del_confirm'] = 'Are you sure you want to delete %s?';
$lang['delsel_confirm'] = 'Are you sure you want to delete selected %s?';
$lang['description'] = 'Description';
//$lang['editbooking'] = 'change bookings';
$lang['each'] = 'each';
$lang['edit'] = 'Edit';
$lang['end'] = 'End';

$lang['err_account'] = 'Unrecognised user';
$lang['err_badend'] = 'Cannot use the end-time';
$lang['err_badstart'] = 'Missing or unusable start-time';
$lang['err_badtime'] = 'Something is wrong with booking time(s)';
$lang['err_baduser'] = 'Missing or unrecognised user';
$lang['err_captcha'] = 'The entered captcha text was wrong';
$lang['err_data'] = 'No data';
$lang['err_dup'] = 'Nominated time already booked';
$lang['err_file'] = 'Inappropriate file specified';
$lang['err_na'] = 'Resource not available at specified time';
$lang['err_late'] = 'Backdating not allowed';
$lang['err_nocaptcha'] = 'You must provide the captcha text';
$lang['err_nocontact'] = 'Without a valid email or phone contact, the booking cannot be confirmed';
$lang['err_nocontact2'] = 'You must provide a valid email and/or phone contact';
$lang['err_nosender'] = 'You must provide a name';
$lang['err_perm'] = 'No permission';
$lang['err_parm'] = 'Parameter error';
$lang['err_server'] = 'Server error';
$lang['err_system'] = 'System error';
//$lang['err_text'] = 'TODO';
$lang['err_upload'] = 'Failed to upload styling file: %s';
$lang['error'] = 'Error!';

$lang['every'] = 'every';
$lang['except'] = 'except';
$lang['export'] = 'Export';
$lang['exportbook'] = 'Export Bookings';
$lang['export_filename'] = '%s-Export-%s.csv';
$lang['false'] = 'no';
$lang['fee_multi'] = 'fee(s)';
$lang['feeintro'] = 'Introduction'; //TODO >> 'intro'
$lang['feesel'] = '%s fees for use of selected %s';
$lang['fetch'] = 'Fetch';
$lang['file'] = 'file'; //for type-specific errors
$lang['find'] = 'Find';
$lang['first'] = 'First';
$lang['fixed'] = 'Fixed';
$lang['formats'] = 'Formats';
$lang['friendlyname'] = 'Bookings Manager';
$lang['fullname'] = 'Bookings Manager module';
$lang['future'] = 'Future';
$lang['groupdefault'] = 'All resources';
$lang['group'] = 'group';
$lang['group_multi'] = 'group(s)';
$lang['groupsame'] = 'Same as group';
/*
$lang['help_alias'] = 'This may be used to identify this %s on a page or template, instead of the corresponding ID number.<br />Any supplied string must be unique.';
$lang['help_count'] = 'Leave blank or enter 0 for no limit.';
$lang['help_order_group'] = 'Displayed groups are ordered by this number.<br />You can enter -1 here to place this group first, leave blank to place it last.';
$lang['help_order_item'] = 'The resources in each group are ordered by this number.<br />You can enter -1 here to place this resource first, leave blank to place it last.';
$lang['help_styles'] = '.css file containing style-parameters.';
*/
$lang['help_address'] = 'Email address for general messaging and/or billing';
$lang['help_alias'] = 'For use in web-page smarty-tags that display this %s. If left blank, a default will be applied, or else the entered string must be unique.';
$lang['help_authcontext'] = 'Authenticator-module context used for account verification';
//$lang['help_bulletin'] = '';
$lang['help_book_contact'] = 'An email address or phone number for providing information to the user';
$lang['help_book_end'] = 'A numeric timestamp formatted as for the start, or if left blank, the end will be assumed to be 1-hour after the start';
$lang['help_book_start'] = 'A numeric timestamp like YYYY-[M]M-[D]D [H]H:[M]M (where [] is optional)';
$lang['help_bookcount'] = '0 means no limit, 1 implies no need for specifying booking-end';
$lang['help_bookertype'] = 'For discriminating among bookers e.g. for fees';
$lang['help_cart'] = 'Bookings may be accumulated in the cart and later submitted as one batch.';
$lang['help_cascade'] = 'Properties marked with a <strong>&#8225;</strong> are inherited, the applied value will be taken from nearest ancestor group, or module setting, if not specified here.';
$lang['help_conformcontact'] = 'If the contact is changed here, the change will be replicated across all the user\'s bookings';
$lang['help_conformstyle'] = 'If the category is changed here, the change will be replicated across all the user\'s bookings';
$lang['help_conformuser'] = 'If the user is changed here, the change will be replicated across all bookings';
$lang['help_date'] = 'A string including format characters recognised by PHP\'s date() function. For reference, please check the <a href="http://www.php.net/manual/function.date.php">php manual</a>.<br />Remember to escape any characters you don\'t want interpreted as format codes!';
$lang['help_exportfile']='Progressively create each .csv file in the general or specific <em>uploads</em> directory, instead of processing the export in memory. This may be wise if there is a lot of data to export. The downside is that someone needs to get that file and (usually) then delete it.';
$lang['help_feerecorded'] = 'Fee for the booking as recorded: <b>%s</b>';
$lang['help_keywords'] = 'Comma-separated words or word-groups, used (along with titles) to determine similarity when looking for comparable %s.';
$lang['help_stripexport']='Remove all HTML tags from records when exported to .csv';
$lang['help_subgrpalloc'] = 'When booking less than a whole group, the specific resources will be determined according to this, when possible';
$lang['help_subgrpcount'] = 'If a number > 0 is provided here, this booking will apply to that number of resources in the group, instead of to the whole group';
$lang['help_cssfile'] = 'File in uploads directory. See module help for details about included styles';
$lang['help_dnd'] = 'You can change the order by dragging any row, or double-click on any number of rows prior to dragging them all.';
$lang['help_displayclass'] = 'Tabular displays of bookings data are styled using this parameter';
$lang['help_email_domains'] = 'Comma-separated series of email domains, e.g. \'msn.com,gmail.com\' to use instead of the default values used by the mailcheck script for initial address-validation';
$lang['help_email_subdomains'] = 'Comma-separated series of partial domains, e.g. \'yahoo,hotmail\' to use instead of the default values used by the mailcheck script for secondary address-validation';
$lang['help_email_topdomains'] = 'Comma-separated series of top domains, e.g. \'com,com.tw,de,net,net.au\' to use instead of the default values used by the mailcheck script for final address-validation';
$lang['help_fee'] = 'Fixed cost, or rate per defined-interval, applied when specified condition(s) met.';
$lang['help_fees'] = <<<'EOS'
Entered fees may be absolute (like 1.23 or 4.5 or 6) or relative (indicated by leading +/-, maybe trailing %/percent, like -2.50 or +4% or -3.2% or +12.5percent).<br />
Fees are per period as entered, or fixed if the corresponding period is 'anything'.
EOS;
$lang['help_feecondition'] = 'See advice for available days and times. If blank, applies <strong>always</strong>';
$lang['help_feugroup'] = 'Front-end users group whose members may commit and change bookings directly, instead of via request.';
$lang['help_focus'] = '<br />click/tap any timeslot, then one of the buttons +/- or Book<br />double-click/tap any timeslot to initiate a booking request/change for that<br />hover the pointer over a booked timeslot, to see more detail';
$lang['help_formiface'] = 'TODO';
$lang['help_groups'] = <<<'EOS'
This %s may be in one or more groups, or none.<br />
<strong>NOTE</strong> Any missing property of this %s will, if possible, be inherited from a group of which this %s is a member.<br />
If in more than one group, their order matters! Groups are checked sequentially, from last-displayed to first-displayed (as if - nearest to furthest ancestor).
EOS;
$lang['help_groupbooking'] = 'Any booking marked with &Dagger; represents a resource-group booking';
$lang['help_image'] = 'Supply one (or more, in which case comma-separated) name(s) of uploaded image files';
$lang['help_importbooking'] = <<<'EOS'
<h3>File format</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one booking.</p>
<h4>Header line</h4>
<p>The first line of the file names the fields in the file, as follows. Names prefixed by a '#' represent compulsory values.<br />
<code>#ID,#Start,End,#User,Status,Paid,Update</code></p>
<h4>Other lines</h4>
<p>The data in each line must conform to the header columns, of course. Any non-compulsory field, or entire line, may be empty.<br />
The #ID field must provide the relevant name, alias or identifying number.<br />
#Start and End fields must be numeric timestamps like YYYY-[M]M-[D]D [H]H:[M]M ([] is optional). When an End is not provided, it is assumed to be 1-hour after the corresponding #Start.<br />
The #User field must provide the name or login of a recorded user.<br />
The Paid and Update fields will be treated as TRUE if they contain something other than 0 or 'no' or 'NO' (no quotes, untranslated).</p>
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the first line field names are are not as expected</li>
<li>a compulsory-field value is not provided</li>
<li>a specified resource is not recognised</li>
<li>a start or end field is malformed</li>
</ul></p>
EOS;
$lang['help_importbooker'] = <<<'EOS'
<h3>File format</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one booker.</p>
<h4>Header line</h4>
<p>The first line of the file names the fields in the file, as follows.
The supplied names may be in any order. Those prefixed by a '#' represent compulsory values.<br />
<code>#Name,Login,Password,Passhash,#Email,Phone,Usertype,Postpayer,Recorder,Displaytype,Update</code></p>
<h4>Other lines</h4>
<p>The data in each line must conform to the header columns, of course. Any non-compulsory field, or entire line, may be empty.<br />
If a Login value is provided, a Password or (previously-exported) Passhash value should also be provided.<br />
The Postpayer, Recorder and Update fields will be treated as TRUE if they contain something other than 0 or 'no' or 'NO' (no quotes, untranslated).<br />
The Usertype field discriminates among user-types 0..9.<br />
The Displaytype field controls display styling for the user, and if provided should be a number, 1&nbsp;to&nbsp;5.<br />
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the first line field names are are not as expected</li>
<li>a compulsory-field value is not provided</li>
<li>an email address or mobile/cell phone number is malformed</li>
</ul></p>
EOS;
$lang['help_importfee'] = <<<'EOS'
<h3>File format</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one fee.</p>
<h4>Header line</h4>
<p>The first line of the file names the fields in the file, as follows.
The supplied names may be in any order. Those prefixed by a '#' represent compulsory values.<br />
<code>#ID,Description,Duration,Count,#Fee,Condition,Type,Update</code></p>
<h4>Other lines</h4>
<p>The data in each line must conform to the header columns, of course. Any non-compulsory field, or entire line, may be empty.<br />
TODO explain fields</p>
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the first line field names are are not as expected</li>
<li>a compulsory-field value is not provided</li>
<li>a resource is not recognised</li>
</ul></p>
EOS;
$lang['help_importhistory'] = <<<'EOS'
<h3>File format</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one record.</p>
<h4>Header line</h4>
<p>The first line of the file names the fields in the file, as follows.
The supplied names may be in any order. Those prefixed by a '#' represent compulsory values.<br />
<code>#ID,Count,#User,Lodged,Approved,#Start,End,Comment,FeeDue,Feepaid,Status,Feestatus,Transaction,Update</code></p>
<h4>Other lines</h4>
<p>The data in each line must conform to the header columns, of course. Any non-compulsory field, or entire line, may be empty.<br />
TODO explain fields</p>
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the first line field names are are not as expected</li>
<li>a compulsory-field value is not provided</li>
<li>a resource or user is not recognised</li>
<li>a date-time value is malformed</li>
</ul></p>
EOS;
$lang['help_importitem'] = <<<'EOS'
<h3>File format</h3>
<p>The input file must be in ASCII format with data fields separated by commas.
Any actual comma in a field should be represented by '&amp;#44;'.
Each line in the file (except the header line, discussed below) represents one resource or group.</p>
<h4>Header line</h4>
<p>The first line of the file names the fields in the file, as follows.
The supplied names may be in any order. Those prefixed by a '#' represent compulsory values.<br />
<code>#Isgroup,#Name,Alias,Description,Keywords,Image,Notice,Membersnamed,<br />
Choosername,Inchooser,Choosemembers,Available,Slottype,Slotcount,BookingSlots,<br />
Leadtype,Leadcount,Rationcount,Keeptype,Keepcount,Grossfees,Taxrate,PayInterface,<br />
SMSprefix,SMSpattern,Latitude,Longitude,Timezone,Dateformat,Timeformat,Listformat,Stylesfile,<br />
Approver,Approvercontact,FormInterface,Feugroup,Owner,Cleargroup,Allocategroup,Ingroups,Update</code></p>
<h4>Other lines</h4>
<p>The data in each line must conform to the header columns, of course. Any non-compulsory field, or entire line, may be empty.<br />
The #Isgroup field will be treated as TRUE if it contains something other than 0 or 'no' or 'NO' (no quotes, untranslated).<br />
TODO explain</p>
<h3>Problems</h3>
<p>The import process will fail if:<ul>
<li>the first line field names are are not as expected</li>
<li>the #Name field is not the first or second field</li>
<li>a compulsory-field value is not provided</li>
</ul></p>
EOS;
$lang['help_item'] = 'Display bookings for this resource or group (name or alias or id-number)';
$lang['help_keep'] = 'How long to preserve booking-data after the booking time. The number may be integer or decimal, or if 0 or empty, there is no retention.';
$lang['help_latitude'] = 'Needed only for sunrise/sunset calculations for available times. Accuracy up to 3 decimal places.';
$lang['help_longitude'] = 'See advice for latitude.';
$lang['help_lodger'] = 'We will assume that the lodger will be the user, and so the booking will be recorded under this name. In any case, the user can be changed later';
$lang['help_lead'] = 'How far ahead of the current time that a booking may be initiated. The number may be integer or decimal, or if 0 or empty, there is no limit.';
$lang['help_members'] = '<strong>DO NOT</strong> select any member which is also a selected parent-group (below).';
$lang['help_members2'] = 'The displayed order defines the \'similarity\' of group members, used for clustering multiple-bookings and selecting alternatives.';
$lang['help_membersname'] = 'Used in messages to/from resource users';
$lang['help_memcount'] = 'There are %d %s in the group.';
$lang['help_passnew'] = 'Replace existing password';
$lang['help_passwd'] = 'A password must be provided for a registered booker';
$lang['help_paymentiface'] = 'CMSMS module to be used for making online payments';
$lang['help_phone'] = 'Phone number for messaging.<br />For a non-registered booker, address and/or phone must must be provided.';
$lang['help_pickname'] = 'Label for this item in dropdown item-selectors';
$lang['help_postpay'] = 'No need to pre-pay';
$lang['help_publicid'] = 'Must be provided for a registered booker';
$lang['help_range'] = 'Scope of bookings-data to display (string day|week|month|year)';
$lang['help_ration'] = 'If 0 or empty, no limit applies.';
$lang['help_record'] = 'Save data about bookings directly, without intervention by approver';
$lang['help_showfrom'] = 'Display bookings starting from this date (as YYYY-[M]M-[M]M, default current day)';
//$lang['help_slotlength'] = 'Together with the available-hours setting, these determine when bookings may start. The number may be integer or decimal.';
$lang['help_sitepage'] = 'Page alias or numeric id, essential for URL routing';
$lang['help_smspattern']='Regular-expression - see <a href="http://www.regexlib.net/Search.aspx?k=phone">this documentation</a>, for example';
$lang['help_smsprefix']='One or more numbers e.g. 1 for USA. <a href="http://countrycode.org">Search</a>';
$lang['help_taxrate'] = 'A value less than 1 will be treated as a proportion, >= 1 as a percentage';
$lang['help_time'] = 'See advice for date format.';
//$lang['help_upload'] = '%s or %s doesn\'t upload a selected file, so it must be done independently, via the button.';
$lang['help_uploadsdir'] = <<<'EOS'
Filesystem path relative to website-host uploads directory.
No leading or trailing path-separator, and any intermediate path-separator must be host-system-specific e.g. '\' on Windows.
If left blank, the default will be used.
Uploads could be .css files for displayed bookings, among others.
EOS;
$lang['help_use_smarty'] = 'Plugins and smarty variables are valid in this field.';
$lang['help_view'] = 'Type of bookings-data display (table or list)';
$lang['help_zone'] = 'A string like \'Europe/London\', used for local time calculations. For reference, please check the <a href="http://www.php.net/manual/timezones.php">php manual</a>.';

/*
Values which include month and/or day must be formatted in accord with local settings.<br />
Month-names may be abbreviated.
Day-names may be abbreviated.
If multiple conditions are specified, it will be sufficient for any of them to be satisfied.
*/
$lang['help_intervals'] = <<<EOS
One or more (in which case, comma-separated) conditions like 'P@T', where<br />
&#8226; (optional) 'P' represents a period-descriptor<br />
&#8226; (optional) 'T' represents a time-descriptor for period P<br />
&#8226; separator '@' is needed only if both P and T are present<br />
If P is not present for a T, the times apply to all days.<br />
If T is not present for a P, all times are available for the days which match P.<br />
Any P or T can be<br />
&#8226; a single value<br />
&#8226; a bracket-enclosed and comma-separated sequence of values (in any order)<br />
&#8226; a '..' separated range of sequential values<br />
For dates, month and day, or just day, are optional. Times are 24-hour, minutes are optional,
the minute-separator must be ':'. Non-ranged times each represent one hour. Other numeric values may be < 0, meaning count backwards.<br />
Any P may be prefaced by 'not' or 'except' (any case) to specify a period to be excluded from the days otherwise covered by other specified period-descriptors.<br />
Any P may be qualifed by 'each' or 'every' N, to represent fixed-interval repetition such as 'every 2nd Wednesday'.
Examples:<br />
&#8226; 2000 or 2000..2005 or 2000-6 or 2000-10..2001-3 or 2000-9-1 or 2000-10-1..2000-12-31<br />
&#8226; January or November..December<br />
&#8226; for week(s)-of-any-month (some of which may not be 7-days): 2(week) or -1(week) or 2..3(week)<br />
&#8226; for week(s)-of-named-month: 2(week(March)) or or 1..3(week(July,August)) or (-2,-1)(week(April..July))<br />
&#8226; for day(s)-of-month: 1 or -2 or or 1..10 or 2..-1 or -3..-1 or each 2 day<br />
&#8226; for day(s)-of-month: 1(Sunday) or -1(Wednesday..Friday) or 1..3(Friday,Saturday) or each or each 2 Tuesday or 2 week<br />
&#8226; for days(s)-of-named-month: 2(March) or or 1..3(July,August) or (-2,-1)(April..July) or 2(Sunday(July..September))<br />
&#8226; for day(s)-of-week: Monday or Wednesday..Friday<br />
&#8226; for times: 9 or 12..23 or 6:30..15:30 or sunrise..16 or 9..sunset-3:30<br />
{$lang['help_use_smarty']}
EOS;
$lang['help_feeconditions'] = <<<EOS
Rules for determining application of a fee may include:<br />
{$lang['help_intervals']}<br />
and/or<br />
One or more (in which case, comma-separated) user-classes, like TODO.<br /><br />
{$lang['help_use_smarty']}<br />
If multiple conditions are specified, it will be sufficient for any of them to be satisfied.
If blank, fees apply always.
EOS;
$lang['history_multi'] = 'history record(s)';

$lang['import'] = 'Import';
$lang['import_fees'] = 'Import Fees';
$lang['import_result'] = '%d %s imported';
$lang['inherit'] = 'Inherit';
$lang['inspect'] = 'Inspect';
$lang['interval'] = 'Interval';
$lang['invalid_type'] = 'Invalid %s';
$lang['is'] = 'is';
$lang['islike'] = 'resembles';
$lang['item_multi'] = 'resource(s)';
$lang['itemv_multi'] = 'item(s)';
$lang['item'] = 'resource';
//$lang['label_nextitem'] = 'Next';
//$lang['label_order'] = 'Order';
//$lang['label_previtem'] = 'Previous';
$lang['last'] = 'Last';
$lang['list'] = 'List';
$lang['listformat'] = 'Format of list-style booking displays';
$lang['latitude'] = 'Latitude';
$lang['lodged'] = 'Lodged';
$lang['longitude'] = 'Longitude';
$lang['meaning_type'] = 'Did you mean %s ?';
$lang['members'] = 'members';
$lang['midday'] = 'noon';
$lang['midnight'] = 'midnight';
$lang['missing'] = 'missing';
$lang['missing_type'] = 'Missing %s';
$lang['moddescription'] = 'Create and manage bookings of resources';
$lang['month'] = 'month';
$lang['move'] = 'Move';
$lang['needjs'] = 'Please enable javascript in your browser, to allow this process to work properly.';
$lang['never'] = 'Never';
$lang['next'] = 'Next';
$lang['nil'] = 'Nil'; //for no-payment
//$lang['nocontact'] = 'missing contact-person name';
$lang['nobooker'] = 'No booker is recorded';
$lang['nocartitems'] = 'Cart is empty';
$lang['nodata'] = 'No booking is registered.';
$lang['nodata_one'] = 'No booking is registered for %s.';
$lang['nodata_range'] = 'No booking is registered from %s to %s.';
$lang['nofees'] = 'No fee is registered.';
$lang['nofinds'] = 'Nothing matches the criteria';
$lang['nogroups'] = 'No resource-group is registered.';
$lang['noitems'] = 'No resource is registered.';
$lang['nolimit'] = 'Unlimited';
$lang['noname'] = 'missing name';
$lang['none'] = 'None';
$lang['nonew'] = 'No new booking has been requested.';
$lang['no'] = 'No'; //see also ['false']
$lang['noowner'] = 'missing owner name';
$lang['nopastdesc'] = 'You cannot change a booking for a previous time';
$lang['nosel'] = 'nothing is selected';
$lang['notavail'] = 'N/A';
$lang['notify'] = 'Notify';
$lang['not'] = 'not';
$lang['notyet'] = 'NOT YET IMPLEMENTED';
//$lang['notype'] = 'No %s'; see missing_type
$lang['notypesel'] = 'No %s selected';
$lang['pageof'] = 'Page %s of %s';
$lang['pagerows'] = 'rows per page';
$lang['partbooked'] = 'part';
$lang['password'] = 'password';
$lang['pending'] = 'Approval pending';
$lang['percent'] = 'percent';
//conform these to $Perm* in module file
$lang['perm_add'] = 'Add Booking Resource';
$lang['perm_admin'] = 'Change Booking Settings';
$lang['perm_booker'] = 'Change Booker Settings';
$lang['perm_delete'] = 'Delete Booking Resource';
$lang['perm_edit'] = 'Change Bookings';
$lang['perm_modify'] = 'Change Booking Resource';
$lang['perm_module'] = 'Change Bookings Module Settings';
$lang['perm_some'] = 'some relevant';
$lang['perm_view'] = 'Inspect Bookings';

$lang['phone'] = 'telephone number';
$lang['picture_type'] = 'Picture of %s';
$lang['postinstall'] = 'Be sure to set "... bookings" permission(s) for users of this module!';
$lang['postuninstall'] = 'Bookings Manager module uninstalled';
$lang['previous'] = 'Previous';
$lang['proceed'] = 'Proceed';

$lang['really_uninstall'] = 'You\'re sure you want to uninstall Bookings Manager?';
$lang['recorded'] = 'recorded'; //adjective included in tip
$lang['recover_lost'] = 'Click here to recover lost password';
$lang['register'] = 'Register';
$lang['registered'] = 'Registered';
$lang['reject'] = 'Reject';
$lang['reminder'] = 'Remember to advise the user about this';
$lang['reports'] = 'Reports';
$lang['reregister'] = 'If your password is lost, you will need to re-register';
//request-booking radio labels
$lang['reqadd'] = 'request new booking';
$lang['reqchange'] = 'change existing booking';
$lang['reqdelete'] = 'delete existing booking';
$lang['reqnotice'] = 'supply extra information';
$lang['request'] = 'request';
$lang['request_multi'] = 'request(s)';
//$lang['reset'] = 'Reset';

$lang['scrolldown'] = 'scroll down';
$lang['scrollleft'] = 'scroll left';
$lang['scrollright'] = 'scroll right';
$lang['scrollup'] = 'scroll up';
$lang['select'] = 'Select from current';
$lang['selectall'] = 'toggle select all';
$lang['settings'] = 'Settings';
$lang['short_length'] = 'up to 64 chars'; //conform to field-length
$lang['showhelp'] = 'click to toggle display of information about this parameter';
$lang['showrange'] = '%s to %s';
$lang['sort'] = 'Sort';
$lang['start'] = 'Start';

$lang['stat_approved'] = 'approved';
$lang['stat_ask'] = 'wait for info';
$lang['stat_big'] = 'too big';
$lang['stat_cancel'] = 'cancelled';
$lang['stat_chg'] = 'change';
$lang['stat_defer'] = 'on hold';
$lang['stat_defer'] = 'too far ahead';
$lang['stat_del'] = 'abandon';
$lang['stat_dup'] = 'slot taken';
$lang['stat_err'] = 'system error';
$lang['stat_fail'] = 'generic failure';
$lang['stat_gone'] = 'tagged for delete';
$lang['stat_late'] = 'too soon';
$lang['stat_na'] = 'not available';
$lang['stat_new'] = 'new';
$lang['stat_none'] = 'unknown';
$lang['stat_nopay'] = 'unpaid';
$lang['stat_ok'] = 'all done';
$lang['stat_perm'] = 'not permitted';
$lang['stat_rec'] = 'recorded';
$lang['stat_retry'] = 'retry later';
$lang['stat_selfrec'] = 'user-recorded';
$lang['stat_tell'] = 'sent info';
$lang['stat_temp'] = 'recorded';
$lang['status'] = 'Status';
$lang['submit'] = 'Submit';
$lang['sunrise'] = 'sunrise';
$lang['sunset'] = 'sunset';

$lang['table'] = 'Table';
$lang['task_cleanold'] = 'Delete historical data which is older than the relevant retention period';
$lang['task_clearcache'] = 'Delete redundant cached bookings data';
$lang['tell_booker'] = 'You need to advise the booker(s) about this';
$lang['time'] = 'time';
$lang['tip_addbooktype'] = 'add %s booking';
$lang['tip_admintype'] = 'administer %s bookings';
$lang['tip_approve'] = 'approve request';
$lang['tip_approve_sel'] = 'approve selected request(s)';
$lang['tip_ask_selected_requests'] = 'send notice to lodger of each selected request';
$lang['tip_back1'] = 'back 1 %s';
$lang['tip_backN'] = 'back %d %s';
$lang['tip_book'] = 'initiate booking';
$lang['tip_calendar'] = 'select a date for display';
$lang['tip_cartadd'] = 'add this to bookings cart';
$lang['tip_cartempty'] = 'bookings cart is empty';
$lang['tip_cartshow'] = 'display bookings cart';
$lang['tip_change'] = 'change account information';
$lang['tip_copytype'] = 'clone %s';
$lang['tip_deletetype'] = 'delete %s';
$lang['tip_delfeetype'] = 'delete %s fee';
$lang['tip_delsel_items'] = 'delete selected items';
$lang['tip_delseltype'] = 'delete selected %s';
$lang['tip_down'] = 'move down';
//$lang['tip_editbooking'] = 'edit booking';
$lang['tip_editreq'] = 'edit booking request';
$lang['tip_edittype'] = 'edit %s';
$lang['tip_enter'] = 'like %s';
$lang['tip_export_selected_records'] = 'export data for selected items';
$lang['tip_exportbookseltype'] = 'export bookings data for selected %s';
$lang['tip_exportbooktype'] = 'export %s bookings data';
$lang['tip_exportseltype'] = 'export data for selected %s';
//$lang['tip_exporttype'] = 'export %s';
$lang['tip_find'] = 'find matching booking';
$lang['tip_findbkg'] = 'find a specific booking';
$lang['tip_finditm'] = 'find a comparable resource to use instead of this';
$lang['tip_forw1'] = 'forward 1 %s';
$lang['tip_forwN'] = 'forward %d %s';
$lang['tip_importbkr'] = 'import bookers data from file';
$lang['tip_importbkg'] = 'import bookings data from file';
$lang['tip_importfee'] = 'import item-fees data from file';
$lang['tip_importitm'] = 'import items data from file';
$lang['tip_interval'] = 'select different display-range';
$lang['tip_listtype'] = 'list style';
$lang['tip_notify_selected_records'] = 'send notice to user of each selected item';
$lang['tip_notifylodger'] = 'send notice to lodger';
$lang['tip_notifyuser'] = 'send notice to user';
$lang['tip_otherview'] = 'show other format';
$lang['tip_register'] = 'submit request to become a registered user';
$lang['tip_reject'] = 'reject request';
$lang['tip_reject_sel'] = 'reject selected request(s)';
$lang['tip_reject2'] = 'disallow booking';
$lang['tip_seetype'] = 'inspect %s bookings';
$lang['tip_seereq'] = 'inspect request';
$lang['tip_selecttype'] = 'select %s';
$lang['tip_sortchilds'] = 'sort group members by similarity (name,description and/or keywords)';
$lang['tip_sortparents'] = 'sort groups by similarity (name,description and/or keywords)';
$lang['tip_sorttype'] = 'sort %s';
$lang['tip_up'] = 'move up';
$lang['tip_upload'] = 'select file for upload';
$lang['tip_view'] = 'change view of bookings data';
$lang['tip_zoomin'] = 'show more detail';
$lang['tip_zoomout'] = 'show less detail';
//$lang['tip_viewbooking'] = 'inspect bookings';
//$lang['tip_viewbooking'] = 'inspect booking';
$lang['tip_viewtype'] = 'view %s';

$lang['title_account'] = 'Account/login name';
$lang['title_address'] = 'Contact';
$lang['title_active'] = 'Activated';
$lang['title_alias'] = 'Alias';
$lang['title_anytime'] = 'Anytime';
$lang['title_atimes'] = 'Available times';
$lang['title_bulletin'] = 'Custom message for frontend pages';
$lang['title_authcontext'] = 'Authentication context';
$lang['title_available'] = 'Available days and times';
$lang['title_bookcount'] = 'Maximum slots in a single booking';
$lang['title_booker_page'] = 'Booker details';
$lang['title_bookers'] = 'Bookers';
$lang['title_bookertype'] = 'Type';
$lang['title_bookfor'] = 'Booking for %s %s';
$lang['title_bookings'] = 'Bookings';
$lang['title_booknewfor'] = 'New booking for %s %s';
$lang['title_booksfor'] = 'Bookings for %s %s';
$lang['title_captcha'] = 'Characters in the image';
$lang['title_cart'] = 'Current cart contents';
$lang['title_change'] = 'Change your account details';
$lang['title_cleargroup'] = 'Delete all resources in a group when the group itself is deleted';
$lang['title_cleargroup2'] = 'Delete all resources in this group when the group is deleted';
$lang['title_commenced'] = 'Commenced';
$lang['title_comment'] = 'Comment';
$lang['title_conformcontact'] = 'Conform to this contact';
$lang['title_conformstyle'] = 'Conform to this category';
$lang['title_conformuser'] = 'Conform to this user';
$lang['title_contact'] = 'Contact';
$lang['title_contacthow'] = 'How we can contact you';
$lang['title_count'] = 'Resources';
$lang['title_cssfile'] = 'File with CSS styles for displayed bookings';
$lang['title_dateformat'] = 'Template for formatting displayed dates';
$lang['title_days'] = 'Days';
$lang['title_deletemarked'] = 'Flagged for deletion';
$lang['title_description'] = 'Booking descriptor';
$lang['title_displayclass'] = 'Display category';
$lang['title_email_domains'] = 'Email-address-check domains';
$lang['title_email_subdomains'] = 'Email-address-check sub-domains';
$lang['title_email_topdomains'] = 'Email-address-check top-level domains';
$lang['title_ended'] = 'and ended';
$lang['title_ending'] = 'and ending when';
$lang['title_exportencoding']='Character-encoding of exported content';
$lang['title_exportfile']='Export to host';
$lang['title_fee'] = 'Fee';
$lang['title_feecondition'] = 'Applies when';
//$lang['title_feedback'] = 'Message to lodger';
//$lang['title_feedback2'] = 'Template for message to lodger';
$lang['title_feedback3'] = 'Template for message to user';
$lang['title_feeinterval'] = 'Fee for interval';
$lang['title_feemod'] = 'Update fees for use of resource %s';
$lang['title_feemod1'] = 'Update fees for use of resource %s and other(s)';
$lang['title_feemod2'] = 'Update fees for use of resources in group %s';
$lang['title_feemod3'] = 'Update fees for use of resources in group %s and other(s)';
$lang['title_fees'] = 'Fees'; //button label
$lang['title_feesee'] = 'Fees for use of resource %s';
$lang['title_feesee2'] = 'Fees for use of resources in group %s';
$lang['title_feesum'] = 'Total fee';
$lang['title_feeusage'] = 'Fee for usage';
$lang['title_feugroup'] = 'Authorised users-group';
$lang['title_find'] = 'Find bookings';
$lang['title_formiface'] = 'Custom request-form interface';
$lang['title_gcount'] = 'Members';
$lang['title_grossfees'] = 'Fees include sales tax';
$lang['title_group'] = 'Group';
$lang['title_group_page'] = 'Resource-group details';
$lang['title_groups'] = 'Groups';
$lang['title_groups2'] = 'Groups which include this %s';
$lang['title_hours'] = 'Hours';
$lang['title_howmany'] = 'How many of the %s?';
$lang['title_id'] = 'ID';
$lang['title_image'] = 'Descriptive image(s)';
$lang['title_importbookers'] = 'Import bookers';
$lang['title_importbooks'] = 'Import bookings';
$lang['title_importfees'] = 'Import fee details';
$lang['title_importhists'] = 'Import history records';
$lang['title_importitems'] = 'Import items';
$lang['title_item'] = 'Resource';
$lang['title_items'] = 'Resources';
$lang['title_item_page'] = 'Resource details';
$lang['title_keep'] = 'Bookings data retention period';
$lang['title_keywords'] = 'Tags/keywords';
$lang['title_lead'] = 'Maximum advance-booking period';
$lang['title_lodger'] = 'Lodged by';
$lang['title_long_desc'] = 'Detailed description';
$lang['title_members'] = 'Members of this group';
$lang['title_membersname'] = 'Generic plural member-descriptor e.g. \'rooms\'';
$lang['title_minutes'] = 'Minutes';
$lang['title_months'] = 'Months';
$lang['title_masterpass']='Pass-phrase for securing sensitive data';
$lang['title_must'] = 'Information <strong>must</strong> be provided for each item below which is marked with a <strong>*</strong>';
$lang['title_name'] = 'Name';
$lang['title_noname'] = 'un-named %s %d';
$lang['title_order_number'] = 'Display order';
$lang['title_occasional'] = 'Occasional-user details';
$lang['title_owner'] = 'User responsible for resources and resource groups';
$lang['title_owner2'] = 'Person responsible';
$lang['title_pagetag'] = 'Page tag';
$lang['title_paid'] = 'Paid';
$lang['title_passnew'] = 'New password';
$lang['title_passwd'] = 'Password';
$lang['title_paymentiface'] = 'Payments interface';
$lang['title_period'] = 'Interval';
$lang['title_phone'] = 'Cell/mobile phone';
$lang['title_pickname'] = 'Resource-selector label';
$lang['title_pickmembers'] = 'Include resource-members of this group in item-selectors';
$lang['title_pickthis'] = 'Include this item in item-selectors';
$lang['title_postpay'] = 'Authorised to post-pay';
$lang['title_prompt'] = 'Replacement for square-bracketed text';
$lang['title_range'] = 'Timespan of booking displays';
$lang['title_range2'] = 'Display';
$lang['title_ration'] = 'Maximum pending bookings per user';
$lang['title_record'] = 'Authorised to record';
$lang['title_recover'] = 'Recover your password for use in the bookings system';
$lang['title_register'] = 'Register your details for use in the bookings system';
$lang['title_registered'] = 'Registered-user details';
$lang['title_repeats'] = 'Repeat bookings';
//$lang['title_repeatsfor'] = 'Repeat bookings for %s %s';
$lang['title_request'] = 'Lodge booking request';
//$lang['title_request1'] = 'You can'; //start of sentence
$lang['title_request1'] = 'If the booking is yours, you can'; //start of sentence
$lang['title_request2'] = 'make a booking for %s'; //one option for rest of sentence
//$lang['title_requeststatus'] = 'Request status';
$lang['title_sender'] = 'Your name';
$lang['title_short_desc'] = 'Brief description';
$lang['title_sitepage'] = 'Website bookings-page identifier';
$lang['title_slotlength'] = 'Interval between successive bookings';
$lang['title_smspattern']='Validator for phone numbers suitable for receiving SMS';
$lang['title_smsprefix']='Country-code to be prepended to phone numbers';
$lang['title_started'] = 'Booking started';
$lang['title_starting'] = 'Starting when';
$lang['title_stripexport']='Strip HTML tags on export';
$lang['title_styles'] = '.css file containing style-parameters for this %s';
$lang['title_subgrpalloc'] = 'Sub-group selection';
$lang['title_subgrpcount'] = 'Sub-group size';
$lang['title_taxrate'] = 'Sales tax rate';
$lang['title_timeformat'] = 'Template for formatting displayed times';
$lang['title_timezone'] = 'Timezone of resources to be booked';
$lang['title_uploadsdir'] = 'Sub-directory for module-specific file uploads';
$lang['title_user'] = 'User';
$lang['title_usercondition']= 'Applies to user type(s)';
$lang['title_vacancies'] = 'Vacancies';
$lang['title_various'] = 'Various users';
$lang['title_weeks'] = 'Weeks';
$lang['title_when'] = 'When';
$lang['title_year'] = 'Year';
$lang['title_zone'] = 'Timezone';
//$lang['title_leadcount'] = 'Maximum advance-booking period count';

$lang['to'] = 'to';
$lang['true'] = 'yes';
$lang['update'] = 'Update';
$lang['upload'] = 'Upload';
$lang['user'] = 'user'; //see also title...
$lang['useselection'] = 'Use selected item';
$lang['view'] = 'View';
$lang['warn_duplicate'] = 'Duplicate booking(s) ignored! Please check.';
$lang['week'] = 'week'; //see also ['periods']
$lang['year'] = 'year'; //see also ['periods']
$lang['yes'] = 'Yes'; //see also ['true']
$lang['zoomin'] = 'Zoom+';
$lang['zoomout'] = 'Zoom-';

//messages - inward
$lang['email_request_title'] = 'Booking request submitted';
$lang['email_request'] = 'A request to book %s has been submitted';
$lang['email_reqchange_title'] = 'Booking-change request submitted';
$lang['email_reqchange'] = 'A request to change booking of %s has been submitted';
$lang['email_store_title'] = 'Booking recorded';
$lang['email_store'] = 'A booking has been recorded for %s';
$lang['email_stochange_title'] = 'Booking changed';
$lang['email_stochange'] = 'A booking has been changed for %s';
//sms|tweet
$lang['text_request'] = 'Booking requested, %s';
$lang['text_reqchange'] = 'Booking-change requested, %s';
$lang['text_store'] = 'Booking recorded, %s';
$lang['text_stochange'] = 'Booking changed, %s';
//messages - outward NOTE '[' and ']', where used, must be retained
$lang['email_approve'] = 'The booking of %s has been approved. [optional extra]';
$lang['email_approve_title'] = 'Booking request approved';
$lang['email_approvepay'] = 'The booking of %s has been approved, subject to payment of the %s fee';
$lang['email_ask'] = 'Please lodge further information about the booking of %s - [send]';
$lang['email_ask_title'] = 'Booking request - information needed';
$lang['email_cancel'] = 'The booking of %s must be cancelled [because ...]';
$lang['email_cancelled_title'] = 'Booking cancelled';
$lang['email_change'] = 'The booking of %s must be changed [because ...]. Please re-book.';
$lang['email_changed_title'] = 'Booking changed';
$lang['email_conflict'] = 'The request to book %s must be refused, due to scheduling conflict';
$lang['email_reject'] = 'The request to book %s must be refused [because ...]';
$lang['email_reject_title'] = 'Booking request rejected';
//sms|tweet
$lang['text_approve'] = 'Booking request approved, %s';
$lang['text_approvepay'] = 'Booking request approved, %s, must pay %s fee';
$lang['text_ask'] = 'Further information needed about the booking of %s - [send]';
$lang['text_cancel'] = 'Booking of %s cancelled, [reason]';
$lang['text_change'] = 'Must change booking of %s, [reason]. Please re-book.';
$lang['text_conflict'] = 'Booking of %s cancelled, scheduling conflict';
$lang['text_reject'] = 'Booking request refused, %s, [reason]';
//templates for sprintf into message-strings %s
$lang['whatonday'] = '%s on %s at %s';
$lang['whatovrday'] = '%s on %s';

//calendar time-interval names - singular & plural
//names are expected to be all lower-case, will be capitalised on demand,
//must be ordered shortest..longest, comma-separated, no whitespace
$lang['periods'] = 'minute,hour,day,week,month,year';
$lang['multiperiods'] = 'minutes,hours,days,weeks,months,years';
$lang['meridiem'] = 'AM,PM'; //upper-case, comma-separated, ante-first

//popup calendar titles
//$lang['title_month'] = 'Month';
$lang['nextm'] = 'Next Month';
$lang['prevm'] = 'Previous Month';
//$lang['title_year'] = 'Year';
//longform daynames - must be Sunday first, comma-separated, no whitespace
$lang['longdays'] = 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday';
//shortform daynames - must be Sunday first, comma-separated, no whitespace
$lang['shortdays'] = 'Sun,Mon,Tue,Wed,Thu,Fri,Sat';
//longform monthnames - must be January first, comma-separated, no whitespace
$lang['longmonths'] = 'January,February,March,April,May,June,July,August,September,October,November,December';
//shortform monthnames - must be January first, comma-separated, no whitespace
$lang['shortmonths'] = 'Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec';
//list formats
$lang['resource+start'] = 'Group by resource,start'; //LISTRS
$lang['start+user'] = 'Group by start,user'; //LISTSU
$lang['user+resource'] = 'Group by user,resource'; //LISTUR
$lang['user+start'] = 'Group by user,start'; //LISTUS
