<?php
#-----------------------------------------------------------------------
# Namespaced class-file autoloader for CMS Made Simple modules (C) 2016-2017 Tom Phane
#-----------------------------------------------------------------------
# CMS Made Simple (C) 2004-2017 Ted Kulp (wishy@cmsmadesimple.org)
# Its homepage is: http://www.cmsmadesimple.org
#-----------------------------------------------------------------------
# This file is free software; you can redistribute it and/or modify it
# under the terms of the GNU Affero General Public License as published
# by the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.
# Read it online at http://www.gnu.org/licenses/licenses.html#AGPL
#-----------------------------------------------------------------------

function cmsms_spacedload($classname)
{
	$sp = __DIR__; //this file must be in the 'lib' subdir for a module
	$segs = explode(DIRECTORY_SEPARATOR,$sp);
	$prefix = $segs[count($segs)-2].'\\'; //module-dir = file's namespace-prefix 
	// ignore if $classname doesn't have that
	$p = strpos($classname,$prefix);
	if (!($p === 0 || ($p === 1 && $classname[0] == '\\')))
		return;
	// get the relative class name
	$len = strlen($prefix);
	if ($classname[0] == '\\') {
		$len++;
	}
	$relative_class = trim(substr($classname,$len),'\\');
	if (($p = strrpos($relative_class,'\\',-1)) !== FALSE) {
		$relative_dir = str_replace('\\',DIRECTORY_SEPARATOR,$relative_class);
		$base = substr($relative_dir,$p+1);
		$relative_dir = substr($relative_dir,0,$p).DIRECTORY_SEPARATOR;
	} else {
		$base = $relative_class;
		$relative_dir = '';
	}
	// directory for the namespace
	$bp = $sp.DIRECTORY_SEPARATOR.$relative_dir;
	$fp = $bp.$base.'.php';
	if (file_exists($fp)) {
		include $fp;
		return;
	}
	$fp = $bp.'class.'.$base.'.php';
	if (file_exists($fp)) {
		include $fp;
	}
}
