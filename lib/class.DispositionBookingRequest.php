<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Custom class for FormBuilder module, to enable front-end booking submission
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
#
# FormBuilder. Copyright (C) 2005-2012 Samuel Goldstein <sjg@cmsmodules.com>
# More info at http://dev.cmsmadesimple.org/projects/formbuilder

class DispositionBookingRequest extends FieldBase {

	function __construct(&$form_ptr, &$params)
	{
		parent::__construct($form_ptr, $params);
//		$mod = $form_ptr->module_ptr;
		$this->Type = 'DispositionBookingRequest';
		$this->IsDisposition = TRUE;
		$this->NonRequirableField = TRUE;
		$this->DisplayInForm = TRUE;
		$this->DisplayInSubmission = TRUE;
		$this->HideLabel = TRUE;
		$this->NeedsDiv = FALSE;
		$this->sortable = FALSE;
	}

	function DisposeForm($returnid)
	{
	}

}

?>
