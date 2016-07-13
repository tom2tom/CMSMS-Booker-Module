<?php
#------------------------------------------------------------------------
# Module: Booker - a resource booking module for CMS Made Simple
# Mostly copyright (C) 2015 Tom Phane <@>
# This project's forge-page is: http://dev.cmsmadesimple.org/projects/booker
#
# This module is free software; you can redistribute it and/or modify it under
# the terms of the GNU Affero General Public License as published by the Free
# Software Foundation; either version 3 of the License, or (at your option)
# any later version.
#
# This module is distributed in the hope that it will be useful, but
# WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License (www.gnu.org/licenses/licenses.html#AGPL)
# for more details
#-----------------------------------------------------------------------

define('DBGBKG', TRUE);

class Booker extends CMSModule
{
	const MINGRPID = 10000; //lowest id in items-table for a group

	//sub-group allocation protocol
	const ALLOCNONE = 0;
	const ALLOCFIRST = 1;
	const ALLOCRAND = 2;
	const ALLOCROTE = 3;
	const ALLOCCHOOSE = 4;
	/*list-display formats
	LISTSU group by slotstart, show each interval, [resource,] user
	LISTSR group by slotstart, show each user [,resource,] interval i.e. ~same as LISTSU unless is group
	[LISTRS group by resource, show each interval, user] i.e. only for groups
	LISTUS group by user, show each interval [,resource]
	*/
	const LISTSU = 1;
	const LISTSR = 2; //default for imported groups
	const LISTRS = 3;
	const LISTUS = 4;
	//request status codes
	const STATNONE = 0;//unknown/normal
	const STATTEMP = 99;//new and already recorded, pending confirmation
	const STATNEW = 1;//new pending
	const STATCHG = 2;//change pending
	const STATDEL = 3;//delete pending
	const STATTELL = 4;//further information submitted
	const STATASK = 5;//queried (waiting for asker)
	const STATDEFER = 6;//booking request not yet processed cuz' too far ahead
	const STATNOPAY = 14;//unpaid
	const STATBIG = 15;//too many slots requested
	const STATNA = 16; //resouce N/A at requested time, cannot accept
	const STATDUP = 17;//duplicate request, cannot accept
	const STATOK = 18;//done/processed i.e. request not yet deleted
	const STATCANCEL = 19;//abandoned
	const STATGONE = 20;//deletion pending, while its historical data needed
	const STATERR = 29;//system error while processing
	//bookings-table-column durations
	const SEGDAY = 0;
	const SEGWEEK = 1;
	const SEGMTH = 2;
//	const SEGYR = 3;
	//booking-display-periods
	const RANGEDAY = 0;
	const RANGEWEEK = 1;
	const RANGEMTH = 2;
	const RANGEYR = 3;

	public $dbHandle; //cached connection to adodb
	public $DataTable; //non-repeated bookings-data
	public $GroupTable; //group-relationships
	public $ItemTable; //resources and resource-groups
	public $RepeatTable; //repeated bookings-data
	public $RequestTable; //submitted booking requests
	public $PayTable; //payment rates and associated conditions
//	public $CacheTable; //cached bookings-data
	public $UserTable; //admin users (who may 'own' resource/group)
	public $before20;
	public $havemcrypt;

	protected $PermStructName = 'Booker Module Admin';
	protected $PermAdminName = 'Booker Admin';
	protected $PermSeeName = 'Booker View';
	protected $PermEditName = 'Booker Modify';
	protected $PermAddName = 'Booker Resource Add';
	protected $PermModName = 'Booker Resource Modify';
	protected $PermDelName = 'Booker Resource Delete';

	public function __construct()
	{
		parent::__construct();

		$this->RegisterModulePlugin(TRUE);

		$this->dbHandle = cmsms()->GetDb();
		$pre = cms_db_prefix();
		$this->DataTable = $pre.'module_bkr_data';
		$this->GroupTable = $pre.'module_bkr_group';
		$this->ItemTable = $pre.'module_bkr_item';
		$this->RepeatTable = $pre.'module_bkr_repeats';
		$this->RequestTable = $pre.'module_bkr_requests';
		$this->PayTable = $pre.'module_bkr_pay';
//		$this->CacheTable = $pre.'module_bkr_cache';
		$this->UserTable = $pre.'users';
		global $CMS_VERSION;
		$this->before20 = (version_compare($CMS_VERSION,'2.0') < 0);
		$this->havemcrypt = function_exists('mcrypt_encrypt');

		spl_autoload_register(array($this,'cmsms_spacedload'));
	}

	public function __destruct()
	{
		spl_autoload_unregister(array($this,'cmsms_spacedload'));
		parent::__destruct();
	}

	/* namespace autoloader - CMSMS default autoloader doesn't do spacing */
	private function cmsms_spacedload($class)
	{
		$prefix = get_class().'\\'; //our namespace prefix
		// ignore if $class doesn't have that
		if (($p = strpos($class,$prefix)) === FALSE)
			return;
		if (!($p === 0 || ($p === 1 && $class[0] == '\\')))
			return;
		// get the relative class name
		$len = strlen($prefix);
		if ($class[0] == '\\') {
			$len++;
		}
		$relative_class = trim(substr($class,$len),'\\');
		if (($p = strrpos($relative_class,'\\',-1)) !== FALSE) {
			$relative_dir = str_replace('\\',DIRECTORY_SEPARATOR,$relative_class);
			$base = substr($relative_dir,$p+1);
			$relative_dir = substr($relative_dir,0,$p).DIRECTORY_SEPARATOR;
		} else {
			$base = $relative_class;
			$relative_dir = '';
		}
		// base directory for the namespace prefix
		$fp = __DIR__.DIRECTORY_SEPARATOR.'lib'
		.DIRECTORY_SEPARATOR.$relative_dir.'class.'.$base.'.php';
		if (file_exists($fp))
			include $fp;
	}

	public function AllowAutoInstall()
	{
		return FALSE;
	}

	public function AllowAutoUpgrade()
	{
		return FALSE;
	}

	//for 1.11+
	public function AllowSmartyCaching()
	{
		return FALSE;
	}

	public function GetName()
	{
		return 'Booker';
	}

	public function GetFriendlyName()
	{
		return $this->Lang('friendlyname');
	}

	public function GetHelp()
	{
		$fn = cms_join_path(__DIR__,'css','public.css');
		$cont = @file_get_contents($fn);
		if ($cont) {
			$example = preg_replace(array('~\s?/\*(.*)?\*/~Usm','~\s?//.*$~m'),array('',''),$cont);
			$example = str_replace(array("\n\n","\n","\t"),array('<br />','<br />',' '),trim($example));
		} else
			$example = $this->Lang('missing');
		return $this->Lang('help',$example);
	}

	public function GetVersion()
	{
		return '0.1';
	}

	public function GetAuthor()
	{
		return 'tomphantoo';
	}

	public function GetAuthorEmail()
	{
		return 'tpgww@onepost.net';
	}

	public function GetChangeLog()
	{
		$fn = cms_join_path(__DIR__,'include','changelog.inc');
		return @file_get_contents($fn);
	}

	public function IsPluginModule()
	{
		return TRUE;
	}

	public function HasAdmin()
	{
		return TRUE;
	}

	/*
	LazyLoadAdmin() for 1.10 and later
	*/
	public function LazyLoadAdmin()
	{
		return FALSE;
	}

	public function GetAdminSection()
	{
		return 'content';
	}

	public function GetAdminDescription()
	{
		return $this->Lang('moddescription');
	}

	public function VisibleToAdminUser()
	{
		return $this->_CheckAccess();
	}

/*public function AdminStyle()
	{
	}
*/
	public function GetHeaderHTML()
	{
		return '<link rel="stylesheet" type="text/css" id="adminstyler" href="'.$this->GetModuleURLPath().'/css/admin.css" />';
	}

	public function SuppressAdminOutput(&$request)
	{
		//prevent output of general admin content when doing an export,
		//and when processing an ajax call
		if (isset($request['mact'])) {
			if (strpos($request['mact'],'exportbooking',6)) return TRUE;
			if (strpos($request['mact'],'multibooking',6)
				&& isset($request['m1_export'])) return TRUE;
			if (strpos($request['mact'],'sortlike',6)) return TRUE;
		}
		return FALSE;
	}

	public function GetDependencies()
	{
		return array();
	}

	/*
	LazyLoadFrontend() for 1.10 and later
	*/
	public function LazyLoadFrontend()
	{
		return TRUE;
	}

	public function MinimumCMSVersion()
	{
		return '1.10'; //CHECKME
	}

/*public function MaximumCMSVersion()
	{
		return '1.12.99';
	}
*/
	public function InstallPostMessage()
	{
		return $this->Lang('postinstall');
	}

	public function UninstallPreMessage()
	{
		return $this->Lang('really_uninstall');
	}

	public function UninstallPostMessage()
	{
		return $this->Lang('postuninstall');
	}

	/*
	SetParameters() for pre-1.10
	*/
	public function SetParameters()
	{
		$this->InitializeAdmin();
		$this->InitializeFrontend();
	}

	/*
	InitializeFrontend() partial setup for 1.10
	*/
	public function InitializeFrontend()
	{
		$this->RestrictUnknownParams();
		//TODO parameter types
		$this->SetParameterType('apply',CLEAN_STRING); //change view enum
		$this->SetParameterType('bookat',CLEAN_INT);
		$this->SetParameterType('subgrpcount',CLEAN_INT);
		$this->SetParameterType('calendarinput',CLEAN_STRING);
		$this->SetParameterType('cancel',CLEAN_NONE);
		$this->SetParameterType('captcha',CLEAN_STRING);
		$this->SetParameterType('chooser',CLEAN_INT);
		$this->SetParameterType('clickat',CLEAN_STRING);
		$this->SetParameterType('comment',CLEAN_STRING); //booking-request parameters
		$this->SetParameterType('contact',CLEAN_STRING);
		$this->SetParameterType('find',CLEAN_NONE);
		$this->SetParameterType('item',CLEAN_STRING); //id or alias
		$this->SetParameterType('item_id',CLEAN_INT); //for zooms
		$this->SetParameterType('listformat',CLEAN_INT); //list-format enum
		$this->SetParameterType('message',CLEAN_STRING);
		$this->SetParameterType('newlist',CLEAN_INT); //list-format change boolean
		$this->SetParameterType('nosend',CLEAN_NONE);
		$this->SetParameterType('origreturnid',CLEAN_INT); //something for captcha module?
		$this->SetParameterType('range',CLEAN_STRING); //enum or period-name
		$this->SetParameterType('ranger',CLEAN_INT); //change view-range enum
		$this->SetParameterType('request',CLEAN_NONE);
		$this->SetParameterType('requesttype',CLEAN_INT);
		$this->SetParameterType('send',CLEAN_NONE);
		$this->SetParameterType('slide',CLEAN_INT); //value matches button label
		$this->SetParameterType('slotid',CLEAN_STRING);
		$this->SetParameterType('startat',CLEAN_STRING);
		$this->SetParameterType('toggle',CLEAN_NONE);
		$this->SetParameterType('until',CLEAN_STRING);
		$this->SetParameterType('user',CLEAN_STRING);
		$this->SetParameterType('view',CLEAN_STRING); //table or list
		$this->SetParameterType('when',CLEAN_STRING);
		$this->SetParameterType('zoomin',CLEAN_NONE);
		$this->SetParameterType('zoomout',CLEAN_NONE);
		/* register 'routes' to use for pretty url parsing
		these regexes translate url-parameter(s) to $param[](s) be supplied
		to the specified actions (default calls ->DisplayModuleOutput())
		so the routes need to conform to parameter-usage in handler-func(s).
		See also: Booker\Utils->GetLink() which needs to conform to this.
		*/
		// for showing the contents of a specific group
		$this->RegisterRoute('/[Bb]ookings?\/group(?P<group>.*?)\/(?P<returnid>[0-9]+)$/',array('action' => 'default'));
		// for showing all the details for a specific item
		$this->RegisterRoute('/[Bb]ookings?\/item(?P<item>.*?)\/(?P<returnid>[0-9]+)$/',array('action' => 'default'));
		// for doing nothing i.e. ignored links
		$this->RegisterRoute('/[Bb]ookings?\/(?P<returnid>[0-9]+)$/',array('action' => 'default'));
	}

	/*
	InitializeAdmin() partial setup for 1.10
	*/
	public function InitializeAdmin()
	{
		$this->CreateParameter('item','',$this->Lang('help_item'));
		$this->CreateParameter('startat','',$this->Lang('help_startat'));
		$this->CreateParameter('range',$this->Lang('week'),$this->Lang('help_range'));
		$this->CreateParameter('view','table',$this->Lang('help_view'));
	}

	/*
	get_tasks:
	Specify the tasks that this module uses
	Returns: CmsRegularTask-compliant object, or array of them
	*/
	public function get_tasks()
	{
		return array(
			new Booker\Cleanold_task(),
			new Booker\Clearcache_task()
		);
	}

	/**
	DoAction:
	@action:
	@id:
	@params:
	@returnid:
	No permission-checks are done here or in related action files, as capabilities
	are governed by which actionable widgets are displayed
	- and those are permission-checked before creation
	*/
	public function DoAction($action, $id, $params, $returnid=-1)
	{
		switch ($action) {
		 case 'add':
		 case 'copy':
		 case 'edit':
		 case 'see':
		 case 'update': //apply/submit item/group changes
 			if (isset($params['modfee'])) //in-page edit-fees button clicked
				$action = 'openfees';
			elseif (isset($params['sortlike']))
				$action = 'sortlike';
			else
				$action = 'openitem';
			break;
		 case 'inspect':
			$action = 'administer';
			break;
		 case 'toggle': //[de]activate
			$this->_ActivateItem($id, $params, $returnid); //trivial func, don't bother with separate action file
			$action = 'defaultadmin';
			break;
		 case 'administer':
		 case 'default':
		 case 'defaultadmin':
		 case 'delete':
		 case 'delbooking':
		 case 'exportbooking':
		 case 'findbooking':
		 case 'import':
		 case 'notifybooker':
		 case 'openitem':
		 case 'openbooking':
		 case 'openrequest':
		 case 'requestbooking':
		 case 'openfees':
		 case 'setprefs':
		 case 'swapgroups':
		 case 'sortlike':
			break;
		 case 'adminbooking':
			if (isset($params['importbkg']))
				$action = 'import';
			elseif (isset($params['find']))
				$action = 'findbooking'; //TODO admin, not frontend
			else
				$action = 'processrequest';
			break;
		 case 'process': //multiple/selected/?export?/delete etc
			if (isset($params['setfees']))
				$action = 'openfees';
			elseif (isset($params['importitm']))
				$action = 'import';
			break;
		 case 'multibooking':
			if (isset($params['importbkg']))
				$action = 'import';
			break;
		 case 'approve':
		 case 'reject':
		//TODO others
		 case 'rapprove':
		 case 'rreject':
		 case 'rdelete':
		 case 'redit':
		 case 'rnotify':
		 case 'rsee':
			$action = 'processrequest';
			break;
		 case 'addfee':
		 case 'delfee':
		 case 'modfee':
			$action = 'openfees';
			break;
		 default:
			if (isset($params['active_tab'])) //TODO if backend
				$action = 'defaultadmin';
			else
				return;
		}
		parent::DoAction($action,$id,$params,$returnid);
	}

	/**
	_CheckAccess($permission='',$warn=FALSE)
		NOT PART OF THE MODULE API
	*/
	public function _CheckAccess($permission='', $warn=FALSE)
	{
		switch ($permission) {
		 case '': //anything relevant
			$name = '';
			$ok = $this->CheckPermission($this->PermAdminName);
			if (!$ok) $ok = $this->CheckPermission($this->PermSeeName);
			if (!$ok) $ok = $this->CheckPermission($this->PermEditName);
			if (!$ok) $ok = $this->CheckPermission($this->PermAddName);
			if (!$ok) $ok = $this->CheckPermission($this->PermDelName);
			if (!$ok) $ok = $this->CheckPermission($this->PermModName);
			if (!$ok) $ok = $this->CheckPermission($this->PermStructName);
			break;
			//bookings
		 case 'view':
			$name = $this->PermSeeName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'book':
			$name = $this->PermEditName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'admin':
			$name = $this->PermAdminName;
			$ok = $this->CheckPermission($name);
			break;
		//resources
		 case 'add':
			$name = $this->PermAddName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'modify':
			$name = $this->PermModName;
			$ok = $this->CheckPermission($name);
			break;
		 case 'delete':
			$name = $this->PermDelName;
			$ok = $this->CheckPermission($name);
			break;
		//module
		 case 'module':
			$name = $this->PermStructName;
			$ok = $this->CheckPermission($name);
			break;
		 default:
			$name = '';
			$ok = FALSE;
		}
		if (!$ok && $warn) {
			if ($name == '') $name = $this->Lang('perm_some');
			echo '<p class="error">'.$this->Lang('accessdenied',$name).'</p>';
		}
		return $ok;
	}

	/**
	_BuildNav:
	@id:
	@$params:
	@returnid:
	@tplvars: reference to array of template variables
	*/
	public function _BuildNav($id, &$params, $returnid, &$tplvars)
	{
		$navstr = $this->CreateLink($id,'defaultadmin',$returnid,
			'&#171; '.$this->Lang('back_module'));
		if (isset($params['bkg_id'])) {
			$navstr .= ' '.$this->CreateLink($id,$params['resume'],$returnid,
				'&#171; '.$this->Lang('title_bookings'),array(
				'item_id' => $params['item_id']));
		}
		$tplvars['back_nav'] = $navstr;
	}

	/* *
	_GetActiveTab(&$params)
	Get name of active tab (in a multi-tab page)
	*/
/*public function _GetActiveTab(&$params)
	{
		if (empty($params['active_tab']))
			return 'data';
		else
			return $params['active_tab'];
	}
*/
	/**
	_PrettyMessage:
	@text: text to display, or if @key = TRUE, a lang-key for the text to display
	@success: optional default TRUE whether to style message as positive
	@key: optional default TRUE whether @text is a lang key or raw
	*/
	public function _PrettyMessage($text, $success=TRUE, $key=TRUE)
	{
		$base = ($key) ? $this->Lang($text) : $text;
		if ($success)
			return $this->ShowMessage($base);
		else {
			$msg = $this->ShowErrors($base);
//			if ($faillink == FALSE) {
				//strip the link
				$pos = strpos($msg,'<a href=');
				$part1 = ($pos !== FALSE) ? substr($msg,0,$pos) : '';
				$pos = strpos($msg,'</a>',$pos);
				$part2 = ($pos !== FALSE) ? substr($msg,$pos+4) : $msg;
				$msg = $part1.$part2;
//			}
			return $msg;
		}
	}

	/**
	_CreateInputLinks:
	Generate xhtml for image and/or submit input(s) which can be styled like an icon
	and/or link, using class "fakeicon" for an image, "fakelink" for a standard link.
	Such object(s) is(are) needed where the handler/action requires all form data,
	instead of just the data for the oject itself (which happens for a normal link)

	@id: system-id to be passed to the module
	@name: name of action to be performed when (either of) the object(s) is clicked
	@iconfile: optional name of theme icon, or module-relative or absolute URL
	  of some other icon for image input, default FALSE i.e. no image
	@link: optional whether to (also) create a submit input, default FALSE
	@text: optional title and tip for an image, or mandatory displayed text for a link, default ''
	@extra: optional additional text that should be added into the object, default ''
	*/
	public function _CreateInputLinks($id, $name, $iconfile=FALSE, $link=FALSE, $text='', $extra='')
	{
		if ($iconfile) {
			$p = strpos($iconfile,'/');
			if ($p === FALSE) {
				$theme = ($this->before20) ? cmsms()->get_variable('admintheme'):
					cms_utils::get_theme_object();
				$imgstr = $theme->DisplayImage('icons/system/'.$iconfile,$text,'','','fakeicon systemicon');
				//trim string like <img src="..." class="fakeicon systemicon" alt="$text" title="$text" />
				$imgstr = str_replace(array('<img','/>'),array('',''),$imgstr);
			} elseif ($p == 0) {
				$imgstr = $this->GetModuleURLPath().$iconfile; } elseif (strpos($iconfile,'://',$p-1) === $p-1) {
				$imgstr = $iconfile; } else {
				$imgstr = $this->GetModuleURLPath().'/'.$iconfile; }
			$ret = '<input type="image" '.$imgstr.' name="'.$id.$name.'"'; //conservative assumption about spaces
			if ($extra)
				$ret .= ' '.$extra;
			$ret .= ' />';
		} else
			$ret = '';
		if ($link && $text) {
			if ($ret)
				$ret .=' ';
			$ret .='<input type="submit" value="'.$text.'" name="'.$id.$name.'" class="fakelink"';
			if ($extra)
				$ret .= ' '.$extra;
			$ret .= ' />';
		}
		return $ret;
	}

	/**
	_ActivateItem:
	@id:
	@params:
	@returnid:
	[de]activate the item passed in @params
	See also: bkritemops::ToggleItemActive()
	*/
	public function _ActivateItem($id, &$params, $returnid)
	{
		if (isset($params['item_id'])) {
			$qdata = array();
			if (isset($params['active'])) {
				if ($params['active'])
					$qdata[] = 0;
				else
					$qdata[] = 1;
			} else
				$qdata[] = 0;
			$qdata[] = $params['item_id'];

			$sql = 'UPDATE '.$this->ItemTable.' SET active=? WHERE item_id=?';
	    	$this->dbHandle->Execute($sql,$qdata);

			if ($params['item_id'] < Booker::MINGRPID)
				$params = array('active_tab' => 'items');
			else
				$params = array('active_tab' => 'groups');
		} else
			$params = array('active_tab' => 'items');
	}
}
