<?php
  /**************************************************************************\
  * eGroupWare                                                               *
  * http://www.egroupware.org                                                *
  * Written by Joseph Engo <jengo@phpgroupware.org>                          *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */
{
	// Only Modify the $file and $title variables.....
	$title = $appname;
	$file = array();
	$file['Preferences']		= $GLOBALS['phpgw']->link('/index.php','menuaction=addressbook.uiaddressbook.preferences');
	if(!$GLOBALS['phpgw_info']['server']['deny_user_grants_access'])
		$file['Grant Access']	= $GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname);
	$file['Edit Categories']	= $GLOBALS['phpgw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app='.$appname . '&cats_level=True&global_cats=True');

	//Do not modify below this line
	display_section($appname,$title,$file);
}
?>
