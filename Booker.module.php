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
//	public $CacheTable; //cached bookings-data
	public $UserTable; //admin users (who may 'own' resource/group)
	public $before20;

	protected $PermStructName = 'Booker Module Admin';
	protected $PermAdminName = 'Booker Admin';
	protected $PermSeeName = 'Booker View';
	protected $PermEditName = 'Booker Modify';
	protected $PermAddName = 'Booker Resource Add';
	protected $PermModName = 'Booker Resource Modify';
	protected $PermDelName = 'Booker Resource Delete';

	function __construct()
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
//		$this->CacheTable = $pre.'module_bkr_cache';
		$this->UserTable = $pre.'users';
		global $CMS_VERSION;
		$this->before20 = (version_compare($CMS_VERSION,'2.0') < 0);
	}

	function GetName()
	{
		return 'Booker';
	}

	function GetFriendlyName()
	{
		return $this->Lang('friendlyname');
	}

	function GetVersion()
	{
		return '0.1';
	}

	function MinimumCMSVersion()
	{
		return '1.9'; //CHECKME
	}

	function MaximumCMSVersion()
	{
		return '1.19.99';
	}

	function GetHelp()
	{
		$fn = cms_join_path(dirname(__FILE__),'css','public.css');
		$cont = @file_get_contents($fn);
		if ($cont)
		{
			$example = preg_replace(array('~\s?/\*(.*)?\*/~Usm','~\s?//.*$~m'),array('',''),$cont);
			$example = str_replace(array("\n\n","\n","\t"),array('<br />','<br />',' '),trim($example));
		}
		else
			$example = $this->Lang('missing');
		return $this->Lang('help',$example);
	}

	function GetAuthor()
	{
		return 'tomphantoo';
	}

	function GetAuthorEmail()
	{
		return 'tpgww@onepost.net';
	}

	function GetChangeLog()
	{
		$fn = cms_join_path(dirname(__FILE__),'include','changelog.inc');
		return @file_get_contents($fn);
	}

	function IsPluginModule()
	{
		return true;
	}

	function HasAdmin()
	{
		return true;
	}

	/*
	LazyLoadAdmin() for 1.10 and later
	*/
	function LazyLoadAdmin()
	{
		return false;
	}

	function GetAdminSection()
	{
		return 'content';
	}

	function GetAdminDescription()
	{
		return $this->Lang('moddescription');
	}

	function VisibleToAdminUser()
	{
		return $this->_CheckAccess();
	}

/*	function AdminStyle()
	{
	}
*/
	function GetHeaderHTML()
	{
		return '<link rel="stylesheet" type="text/css" id="adminstyler" href="'.$this->GetModuleURLPath().'/css/admin.css" />';
	}

	function SuppressAdminOutput(&$request)
	{
		//prevent output of general admin content when doing an export,
		//and when processing an ajax call
		if (isset($request['mact']))
		{
			if(strpos($request['mact'],'exportbooking',6)) return true;
			if(strpos($request['mact'],'multibooking',6)
				&& isset($request['m1_export'])) return true;
			if(strpos($request['mact'],'sortlike',6)) return true;
		}
		return false;
	}

	function GetDependencies()
	{
		return array();
	}

	/*
	LazyLoadFrontend() for 1.10 and later
	*/
	function LazyLoadFrontend()
	{
		return true;
	}

	function InstallPostMessage()
	{
		return $this->Lang('postinstall');
	}

	function UninstallPreMessage()
	{
		return $this->Lang('really_uninstall');
	}

	function UninstallPostMessage()
	{
		return $this->Lang('postuninstall');
	}

	/*
	SetParameters() for pre-1.10
	*/
	function SetParameters()
	{
		$this->InitializeAdmin();
		$this->InitializeFrontend();
	}

	/*
	InitializeFrontend() partial setup for 1.10
	*/
	function InitializeFrontend()
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
		See also: bkrshared::GetLink() which needs to conform to this.
		*/
		// for showing the contents of a specific group
		$this->RegisterRoute('/[Bb]ookings?\/group(?P<group>.*?)\/(?P<returnid>[0-9]+)$/',array('action'=>'default'));
		// for showing all the details for a specific item
		$this->RegisterRoute('/[Bb]ookings?\/item(?P<item>.*?)\/(?P<returnid>[0-9]+)$/',array('action'=>'default'));
		// for doing nothing i.e. ignored links
		$this->RegisterRoute('/[Bb]ookings?\/(?P<returnid>[0-9]+)$/',array('action'=>'default'));
	}

	/*
	InitializeAdmin() partial setup for 1.10
	*/
	function InitializeAdmin()
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
	function get_tasks()
	{
		return array(
			new bkrcleanold_task(),
			new bkrclearcache_task()
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
	function DoAction($action,$id,$params,$returnid=-1)
	{
		switch ($action)
		{
		 case 'add':
		 case 'copy':
		 case 'edit':
		 case 'see':
		 case 'update': //apply/submit item/group changes
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
		 case 'openbooking':
		 case 'openrequest':
		 case 'requestbooking':
		 case 'setprefs':
		 case 'sortlike':
			break;
		 case 'adminbooking':
			if(isset($params['importbkg']))
				$action = 'import';
			elseif(isset($params['find']))
				$action = 'findbooking'; //TODO admin, not frontend
			else
				$action = 'processrequest';
			break;
		 case 'process': //multiple/selected/?export?/delete etc
			if(isset($params['price']))
				$action = 'price';
			elseif(isset($params['importitm']))
				$action = 'import';
			break;
		 case 'multibooking':
			if(isset($params['importbkg']))
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
		 default:
			if(isset($params['active_tab'])) //TODO if backend
				$action = 'defaultadmin';
			else
				return;
		}
		parent::DoAction($action,$id,$params,$returnid);
	}

	/**
	_CheckAccess($permission='',$warn=false)
		NOT PART OF THE MODULE API
	*/
	function _CheckAccess($permission='',$warn=false)
	{
		switch ($permission)
		{
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
			$ok = false;
		}
		if (!$ok && $warn)
		{
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
	*/
	function _BuildNav($id,&$params,$returnid)
	{
		$navstr = $this->CreateLink($id,'defaultadmin',$returnid,
			'&#171; '.$this->Lang('back_module'));
		if(isset($params['bkg_id']))
		{
			$navstr .= ' '.$this->CreateLink($id,$params['resume'],$returnid,
				'&#171; '.$this->Lang('title_bookings'),array(
				'item_id'=>$params['item_id']));
		}
		$smarty = cmsms()->GetSmarty();
		$smarty->assign('back_nav',$navstr);
	}

	/* *
	_GetActiveTab(&$params)
	Get name of active tab (in a multi-tab page)
	*/
/*	function _GetActiveTab(&$params)
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
	function _PrettyMessage($text,$success=TRUE,$key=TRUE)
	{
		$base = ($key) ? $this->Lang($text) : $text;
		if ($success)
			return $this->ShowMessage($base);
		else
		{
			$msg = $this->ShowErrors($base);
//			if ($faillink == FALSE)
//			{
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
	_ActivateItem:
	@id:
	@params:
	@returnid:
	[de]activate the item passed in @params
	See also: bkritemops::ToggleItemActive()
	*/
	function _ActivateItem($id,&$params,$returnid)
	{
		if(isset($params['item_id']))
		{
			$qdata = array();
			if(isset($params['active']))
			{
				if($params['active'])
					$qdata[] = 0;
				else
					$qdata[] = 1;
			}
			else
				$qdata[] = 0;
			$qdata[] = $params['item_id'];

			$sql = 'UPDATE '.$this->ItemTable.' SET active=? WHERE item_id=?';
	    	$this->dbHandle->Execute($sql,$qdata);

			if($params['item_id'] < Booker::MINGRPID)
				$params = array('active_tab'=>'items');
			else
				$params = array('active_tab'=>'groups');
		}
		else
			$params = array('active_tab'=>'items');
	}
}

?>
