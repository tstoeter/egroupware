<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

  $d1 = strtolower(substr($phpgw_info["server"]["api_inc"],0,3));
  $d2 = strtolower(substr($phpgw_info["server"]["server_root"],0,3));
  $d3 = strtolower(substr($phpgw_info["server"]["app_inc"],0,3));
  if($d1 == "htt" || $d1 == "ftp" || $d2 == "htt" || $d2 == "ftp" || $d3 == "htt" || $d3 == "ftp") {
    echo "Failed attempt to break in via an old Security Hole!<br>\n";
    exit;
  } unset($d1);unset($d2);unset($d3);
  
  // Since LDAP will return system accounts, there are a few we don't want to login.
  $phpgw_info["server"]["global_denied_users"] = array('root'     => True,
                                                       'bin'      => True,
                                                       'daemon'   => True,
                                                       'adm'      => True,
                                                       'lp'       => True,
                                                       'sync'     => True,
                                                       'shutdown' => True,
                                                       'halt'     => True,
                                                       'mail'     => True,
                                                       'news'     => True,
                                                       'uucp'     => True,
                                                       'operator' => True,
                                                       'games'    => True,
                                                       'gopher'   => True,
                                                       'nobody'   => True,
                                                       'xfs'      => True,
                                                       'pgsql'    => True,
                                                       'mysql'    => True,
                                                       'postgres' => True,
                                                       'ftp'      => True,
                                                       'gdm'      => True,
                                                       'named'    => True);


  // I had to create this has a wrapper, becuase the phpgw.inc.php files needs it before the classes
  // are finished loading (jengo)
  function filesystem_separator()
  {
     if (PHP_OS == "Windows" || PHP_OS == "OS/2") {
        return "\\";
     } else {
        return "/";
     }
  }

  class common
  {
    var $phpgw;
    var $iv = "";
    var $key = "";
    var $crypto;

    // return a array of installed languages
    function getInstalledLanguages()
    {
    	global $phpgw;
    	
    	$phpgw->db->query("select distinct lang from lang");
    	while (@$phpgw->db->next_record()) 
    	{
    		$installedLanguages[$phpgw->db->f("lang")] = $phpgw->db->f("lang");
    	}   
    	
    	return $installedLanguages;
    }

    // return the preferred language of the users
    // it's using HTTP_ACCEPT_LANGUAGE (send from the users browser)
    // and ...(to find out which languages are installed)
    function getPreferredLanguage()
    {
        global $HTTP_ACCEPT_LANGUAGE;
        
        // create a array of languages the user is accepting
        $userLanguages = explode(",",$HTTP_ACCEPT_LANGUAGE);
        $supportedLanguages = $this->getInstalledLanguages();

	// find usersupported language
        while (list($key,$value) = each($userLanguages))
        {
        	// remove everything behind "-" example: de-de
                $value = trim($value);
                $pieces = explode("-", $value);
                $value = $pieces[0];
                # print "current lang $value<br>";
                if ($supportedLanguages[$value])
                {
                        $retValue=$value;
                        break;
                }
        }

	// no usersupported language found -> return english
        if (empty($retValue))
        {
                $retValue="en";
        }
        
        return $retValue;
    }      

    // connect to the ldap server and return a handle
    function ldapConnect($host = "", $dn = "", $passwd = "")
    {
   	global $phpgw_info;
   	
   	if (! $host) {
   	   $host = $phpgw_info["server"]["ldap_host"];
   	}

   	if (! $dn) {
   	   $dn = $phpgw_info["server"]["ldap_root_dn"];
   	}

   	if (! $passwd) {
   	   $passwd = $phpgw_info["server"]["ldap_root_passwd"];
   	}

	
   	// connect to ldap server
   	if (! $ds = ldap_connect($host)) {
  		printf("<b>Error: Can't connect to LDAP server %s!</b><br>",$host);
		  return False;
   	}

   	// bind as admin, we not to able to do everything
   	if (! ldap_bind($ds,$dn,$passwd)) {
  		printf("<b>Error: Can't bind to LDAP server: %s!</b><br>",$dn);
		  return False;
   	}

   	return $ds;
    }

    // This function is used if the developer wants to stop a running app in the middle of execution
    // We may need to do some clean up before hand
    function phpgw_exit($call_footer = False)
    {
       global $phpgw;

       if ($call_footer) {
          $this->phpgw_footer();
       }  
       $phpgw->db->disconnect();
       exit;
    }

    function randomstring($size)
    {
      $s = "";
      srand((double)microtime()*1000000);
      $random_char = array("0","1","2","3","4","5","6","7","8","9","a","b","c","d","e","f",
                           "g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v",
                           "w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L",
                           "M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z");

      for ($i=0; $i<$size; $i++) {
         $s .= $random_char[rand(1,61)];
      }
      return $s;    
    }

    // Look at the note towards the top of this file (jengo)
    function filesystem_separator()
    {
       return filesystem_separator();
    }

    function error_list($error)
    {
       $html_error = '<table border="0" width="50%"><tr><td align="right"><b>' . lang("error") . '</b>: </td><td align="left">' . $error[0] . '</td></tr>';

       for ($i=1; $i<count($error); $i++) {
          $html_error .= '<tr><td>&nbsp;</td><td align="left">' . $error[$i] . '</td></tr>';
       }
       return $html_error . '</table>';
    }

    function check_owner($record,$link,$label,$extravars = "")
    {
       global $phpgw, $phpgw_info;

       $s = '<a href="' . $phpgw->link($link,$extravars) . '"> ' . lang($label) . ' </a>';
       if (ereg("^[0-9]+$",$record)) {
          if ($record != $phpgw_info["user"]["account_id"]) {
             $s = "&nbsp;";
          }
       } else {
          if ($record != $phpgw_info["user"]["userid"]) {
             $s = "&nbsp";
          }
      }

      return $s;
    }    

    function display_fullname($lid = "", $firstname = "", $lastname = "")
    {
      if (! $lid && ! $firstname && ! $lastname) {
         global $phpgw_info;
         $lid       = $phpgw_info["user"]["account_lid"];
         $firstname = $phpgw_info["user"]["firstname"];
         $lastname  = $phpgw_info["user"]["lastname"];
      }
    
      if (! $firstname && ! $lastname) {
         $s = $lid;
      }
      if (! $firstname && $lastname) {
         $s = $lastname;
      }
      if ($firstname && ! $lastname) {
         $s = $firstname;
      }
      if ($firstname && $lastname) {
         $s = "$lastname, $firstname";
      }
      return $s;
    }

    function grab_owner_name($id)
    {
      global $phpgw;

      $db = $phpgw->db;
      $db->query("select account_lid,account_firstname,account_lastname from accounts where account_id=".$id,__LINE__,__FILE__);
      $db->next_record();

      return $phpgw->common->display_fullname($db->f("account_lid"),$db->f("account_firstname"),$db->f("account_lastname"));
    }  

    function create_tabs($tabs, $selected, $fontsize = "")
    {
       global $phpgw_info;
       $output_text = '<table border="0" cellspacing="0" cellpadding="0"><tr>';
       $ir = $phpgw_info["server"]["images_dir"];

       if ($fontsize) {
          $fs  = '<font size="' . $fontsize . '">';
          $fse = '</font>';
       }

       $i = 1;
       while ($tab = each($tabs)) {
         if ($tab[0] == $selected) {
            if ($i == 1) {
               $output_text .= '<td align="right"><img src="' . $ir . '/tabs-start1.gif"></td>';
            }
     
            $output_text .= '<td align="left" background="' . $ir . '/tabs-bg1.gif">&nbsp;<b><a href="'
                          . $tab[1]["link"] . '" class="tablink">' . $fs . $tab[1]["label"]
                          . $fse . '</a></b>&nbsp;</td>';
            if ($i == count($tabs)) {
               $output_text .= '<td align="left"><img src="' . $ir . '/tabs-end1.gif"></td>';
            } else {
               $output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepr.gif"></td>';
            }
         } else {
            if ($i == 1) {
               $output_text .= '<td align="right"><img src="' . $ir . '/tabs-start0.gif"></td>';
            }
            $output_text .= '<td align="left" background="' . $ir . '/tabs-bg0.gif">&nbsp;<b><a href="'
                          . $tab[1]["link"] . '" class="tablink">' . $fs . $tab[1]["label"] . $fse
                          . '</a></b>&nbsp;</td>';
            if (($i + 1) == $selected) {
               $output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepl.gif"></td>';
            } else if ($i == $selected || $i != count($tabs)) {
               $output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepm.gif"></td>';
            } else if ($i == count($tabs)) {
               if ($i == $selected) {
                  $output_text .= '<td align="left"><img src="' . $ir . '/tabs-end1.gif"></td>';          
               } else {
                  $output_text .= '<td align="left"><img src="' . $ir . '/tabs-end0.gif"></td>';
               }
            } else {
               if ($i != count($tabs)) {
                  $output_text .= '<td align="left"><img src="' . $ir . '/tabs-sepr.gif"></td>';
               }
            }
         }
         $i++;
         $output_text .= "\n";
       }
       $output_text .= "</table>\n";
       return $output_text;
    }

    function get_app_dir($appname = ""){
      global $phpgw_info;
      if ($appname == ""){$appname = $phpgw_info["flags"]["currentapp"];}
      if ($appname == "home" || $appname == "logout" || $appname == "login"){$appname = "phpgwapi";}

      $appdir = $phpgw_info["server"]["include_root"]."/".$appname;
      $appdir_default = $phpgw_info["server"]["server_root"]."/".$appname;

      if (is_dir ($appdir)){
        return $appdir;
      }elseif (is_dir ($appdir_default)){
        return $appdir_default;
      }else{
        return False;
      }      
    }

    function get_inc_dir($appname = ""){
      global $phpgw_info;
      if ($appname == ""){$appname = $phpgw_info["flags"]["currentapp"];}
      if ($appname == "home" || $appname == "logout" || $appname == "login"){$appname = "phpgwapi";}

      $incdir = $phpgw_info["server"]["include_root"]."/".$appname."/inc";
      $incdir_default = $phpgw_info["server"]["server_root"]."/".$appname."/inc";

      if (is_dir ($incdir)){
        return $incdir;
      }elseif (is_dir ($incdir_default)){
        return $incdir_default;
      }else{
        return False;
      }      
    }

    function list_templates(){
      global $phpgw_info;
      $d = dir($phpgw_info["server"]["server_root"]."/phpgwapi/templates");
      while($entry=$d->read()) {
        if ($entry != "CVS" && $entry != "." && $entry != ".."){
          $list[$entry]["name"] = $entry;
          $f = $phpgw_info["server"]["server_root"]."/phpgwapi/templates/".$entry."/details.inc.php";
          if (file_exists ($f)){
            include($f);
            $list[$entry]["title"] = "Use ".$phpgw_info["template"][$entry]["title"]."interface";
          }else{
            $list[$entry]["title"] = $entry;
          }
        }
      }
      $d->close();
      reset ($list);
      return $list;
    }

    function get_tpl_dir($appname = ""){
      global $phpgw_info;
      if ($appname == ""){$appname = $phpgw_info["flags"]["currentapp"];}
      if ($appname == "home" || $appname == "logout" || $appname == "login"){$appname = "phpgwapi";}

      if ($phpgw_info["server"]["template_set"] == "user_choice" && isset($phpgw_info["user"]["preferences"]["template_set"])){
        $phpgw_info["server"]["template_set"] = $phpgw_info["user"]["preferences"]["template_set"];
      }elseif ($phpgw_info["server"]["template_set"] == "user_choice" || !isset($phpgw_info["server"]["template_set"])){
        $phpgw_info["server"]["template_set"] = "default";
      }

      $tpldir = $phpgw_info["server"]["server_root"]."/".$appname."/templates/".$phpgw_info["server"]["template_set"];
      $tpldir_default = $phpgw_info["server"]["server_root"]."/".$appname."/templates/default";

      if (is_dir ($tpldir)){
        return $tpldir;
      }elseif (is_dir ($tpldir_default)){
        return $tpldir_default;
      }else{
        return False;
      }      
    }

    function get_image_dir($appname = ""){
      global $phpgw_info;
      if ($appname == ""){$appname = $phpgw_info["flags"]["currentapp"];}
      if (empty($phpgw_info["server"]["template_set"])){$phpgw_info["server"]["template_set"] = "default";}

      $imagedir = $phpgw_info["server"]["server_root"]."/".$appname."/templates/".$phpgw_info["server"]["template_set"]."/images";
      $imagedir_default = $phpgw_info["server"]["server_root"]."/".$appname."/templates/default/images";
      $imagedir_olddefault = $phpgw_info["server"]["server_root"]."/".$appname."/images";

      if (is_dir ($imagedir)){
        return $imagedir;
      }elseif (is_dir ($imagedir_default)){
        return $imagedir_default;
      }elseif (is_dir ($imagedir_olddefault)){
        return $imagedir_olddefault;
      }else{
        return False;
      }      
    }

    function get_image_path($appname = ""){
      global $phpgw_info;
      if ($appname == ""){$appname = $phpgw_info["flags"]["currentapp"];}
      if (empty($phpgw_info["server"]["template_set"])){$phpgw_info["server"]["template_set"] = "default";}

      $imagedir = $phpgw_info["server"]["server_root"]."/".$appname."/templates/".$phpgw_info["server"]["template_set"]."/images";
      $imagedir_default = $phpgw_info["server"]["server_root"]."/".$appname."/templates/default/images";
      $imagedir_olddefault = $phpgw_info["server"]["server_root"]."/".$appname."/images";

      if (is_dir ($imagedir)){
        return $phpgw_info["server"]["webserver_url"]."/".$appname."/templates/".$phpgw_info["server"]["template_set"]."/images";
      }elseif (is_dir ($imagedir_default)){
        return $phpgw_info["server"]["webserver_url"]."/".$appname."/templates/default/images";
      }elseif (is_dir ($imagedir_olddefault)){
        return $phpgw_info["server"]["webserver_url"]."/".$appname."/images";
      }else{
        return False;
      }      
    }

    function navbar($called_directly = True)
    {
       // This is only temp, until will change everything to use the new method
       if ($called_directly) {
          echo '<center><b>Warning: You can no longer call navbar() directly, use echo parse_navbar() from now on.</b></center>';
       }
    
       global $phpgw_info, $phpgw;

       $phpgw_info["navbar"]["home"]["title"] = lang("Home");
       $phpgw_info["navbar"]["home"]["url"]   = $phpgw->link($phpgw_info["server"]["webserver_url"] . "/index.php");
       $phpgw_info["navbar"]["home"]["icon"]  = $phpgw_info["server"]["webserver_url"] . "/phpgwapi/templates/"
                                              . $phpgw_info["server"]["template_set"] . "/images/home.gif";
       while ($permission = each($phpgw_info["user"]["apps"])) {
          if ($phpgw_info["apps"][$permission[0]]["status"] != 2) {
             $phpgw_info["navbar"][$permission[0]]["title"] = lang($phpgw_info["apps"][$permission[0]]["title"]);
             $phpgw_info["navbar"][$permission[0]]["url"]   = $phpgw->link($phpgw_info["server"]["webserver_url"]
                                                            . "/" . $permission[0] . "/index.php");
            $icon_file = $phpgw_info["server"]["server_root"]."/".$permission[0] . "/templates/". $phpgw_info["server"]["template_set"]. "/images/navbar.gif";
            if (file_exists($icon_file)){
              $phpgw_info["navbar"][$permission[0]]["icon"]  = $phpgw_info["server"]["webserver_url"] . "/"
                . $permission[0] . "/templates/" . $phpgw_info["server"]["template_set"] . "/images/navbar.gif";
            }else{
              $phpgw_info["navbar"][$permission[0]]["icon"]  = $phpgw_info["server"]["webserver_url"] . "/"
                . $permission[0] . "/templates/default/images/navbar.gif";
            }
          }
       }
       $phpgw_info["navbar"]["preferences"]["title"] = lang("preferences");
       $phpgw_info["navbar"]["preferences"]["url"]   = $phpgw->link($phpgw_info["server"]["webserver_url"]
                                                     . "/preferences/index.php");
       $phpgw_info["navbar"]["preferences"]["icon"]  = $phpgw_info["server"]["webserver_url"] . "/preferences/templates/"
                                                     . $phpgw_info["server"]["template_set"] . "/images/navbar.gif";

       if ($phpgw_info["flags"]["currentapp"] == "home" || $phpgw_info["flags"]["currentapp"] == "preferences" || $phpgw_info["flags"]["currentapp"] == "about") {
          $app = "phpGroupWare";
       } else {
          $app = $phpgw_info["flags"]["currentapp"];
       }
       $phpgw_info["navbar"]["about"]["title"] = lang("About x",$about);
       $phpgw_info["navbar"]["about"]["url"]   = $phpgw->link($phpgw_info["server"]["webserver_url"]
                                               . "/about.php");
       $phpgw_info["navbar"]["about"]["icon"]  = $phpgw_info["server"]["webserver_url"] . "/phpgwapi/templates/"
                                               . $phpgw_info["server"]["template_set"] . "/images/about.gif";

       $phpgw_info["navbar"]["logout"]["title"] = lang("Logout");  // Add app name
       $phpgw_info["navbar"]["logout"]["url"]   = $phpgw->link($phpgw_info["server"]["webserver_url"]
                                                . "/logout.php");
       $phpgw_info["navbar"]["logout"]["icon"]  = $phpgw_info["server"]["webserver_url"] . "/phpgwapi/templates/"
                                                . $phpgw_info["server"]["template_set"] . "/images/logout.gif";
    }


    function app_header() {
      if (file_exists ($phpgw_info["server"]["app_inc"]."/header.inc.php")) {
        include($phpgw_info["server"]["app_inc"]."/header.inc.php");
      }
    }

    function phpgw_header() {
      global $phpgw, $phpgw_info;

      include($phpgw_info["server"]["include_root"] . "/phpgwapi/templates/"
            . $phpgw_info["server"]["template_set"] . "/head.inc.php");
      $this->navbar(False);
      include($phpgw_info["server"]["include_root"] . "/phpgwapi/templates/"
            . $phpgw_info["server"]["template_set"] . "/navbar.inc.php");
      if ((! isset($phpgw_info["flags"]["nonavbar"]) || ! $phpgw_info["flags"]["nonavbar"]) && ! $phpgw_info["flags"]["navbar_target"]) {
         echo parse_navbar();
      }
    }

    function phpgw_footer()
    {
       global $phpgw, $phpgw_info, $HTMLCOMPLAINT;
       include($phpgw_info["server"]["api_inc"] . "/footer.inc.php");
 
       // Clean up mcrypt
       if (is_object($this->crypto)) {
          $this->crypto->cleanup();
          unset($this->crypto);
       }
    }

    function hex2bin($data)
    {
       $len = strlen($data);
       return pack("H" . $len, $data);
    }

    function encrypt($data) {
      global $phpgw_info, $phpgw;

      $data = serialize($data);
      return $phpgw->crypto->encrypt($data);
    }

    function decrypt($data) {
      global $phpgw_info, $phpgw;

      $data = $phpgw->crypto->decrypt($data);
      return unserialize($data);
    }

  function des_cryptpasswd($userpass, $random)
  {
    $lcrypt = "{crypt}";
    $password = crypt($userpass);
    $ldappassword = sprintf("%s%s", $lcrypt, $password);
 
    return $ldappassword;
  }

  function md5_cryptpasswd($userpass, $random)
  {
    $bsalt = "$1$";
    $esalt = "$";						// patch
    $lcrypt = "{crypt}";
//    $modsalt = sprintf("%s%s", $bsalt, $random);
    $modsalt = sprintf("%s%s%s", $bsalt, $random, $esalt);	// patch
    $password = crypt($userpass, $modsalt);
    $ldappassword = sprintf("%s%s", $lcrypt, $password);
  
    return $ldappassword;
  }    

  function encrypt_password($password)
  {
     global $phpgw, $phpgw_info;
     
     if ($phpgw_info["server"]["ldap_encryption_type"] == "DES") {
         $salt       = $this->randomstring(2);
         $e_password = $this->des_cryptpasswd($password, $salt);
      }
      if ($phpgw_info["server"]["ldap_encryption_type"] == "MD5") {
//         $salt       = $this->randomstring(9);
         $salt       = $this->randomstring(8);			// patch
         $e_password = $this->md5_cryptpasswd($password, $salt);
      }
      return $e_password;
  }

  function hook($location = "", $order = ""){
    global $phpgw, $phpgw_info;
    if ($order == ""){$order[] = $phpgw_info["flags"]["currentapp"];}
    /* First include the ordered apps hook file */
    reset ($order);
    while (list (, $appname) = each ($order)){
      $f = $phpgw_info["server"]["server_root"] . "/" . $appname . "/inc/hook_".$phpgw_info["flags"]["currentapp"];
    	if ($location != ""){$f .= "_".$location.".inc.php";}else{$f .= ".inc.php"; }
  	  if (file_exists($f)) {include($f);}
      $completed_hooks[$appname] = True;
    }
    /* Then add the rest */
    reset ($phpgw_info["user"]["app_perms"]);
    while (list (, $appname) = each ($phpgw_info["user"]["app_perms"])){
      if ($appname != "" && $completed_hooks[$appname] != True){
        $f = $phpgw_info["server"]["server_root"] . "/" . $appname . "/inc/hook_".$phpgw_info["flags"]["currentapp"];
      	if ($location != ""){$f .= "_".$location.".inc.php";}else{$f .= ".inc.php"; }
    	  if (file_exists($f)) {include($f);}
      }
    }
  }

  function hook_single($location = "", $appname = "")
  {
     global $phpgw, $phpgw_info;
     if (! $appname) {
        $appname = $phpgw_info["flags"]["currentapp"];
     }
     $s = $phpgw->common->filesystem_separator();
     /* First include the ordered apps hook file */
     $f = $phpgw_info["server"]["server_root"] . $s . $appname . $s . "inc" . $s . "hook_".$appname;
     if ($location != "") {
        $f .= "_".$location.".inc.php";
     } else {
        $f .= ".inc.php";
     }
     if (file_exists($f)) {
        include($f);
        return True;
     } else {
        return False;
     }
  }

  function hook_count($location = ""){
    global $phpgw, $phpgw_info;
    reset ($phpgw_info["user"]["app_perms"]);
    $count = 0;
    while (list (, $appname) = each ($phpgw_info["user"]["app_perms"])){
      $f = $phpgw_info["server"]["server_root"] . "/" . $appname . "/inc/hook_".$phpgw_info["flags"]["currentapp"];
    	if ($location != ""){$f .= "_".$location.".inc.php";}else{$f .= ".inc.php"; }
  	  if (file_exists($f)) {++$count;}
    }
    return $count;
  }


  function appsession($data = "##NOTHING##") {
      global $phpgw_info, $phpgw;

      if ($data == "##NOTHING##") {  /* This allows the user to put "" as the value. */
	      $phpgw->db->query("select content from phpgw_app_sessions where sessionid = '"
		      .$phpgw_info["user"]["sessionid"]."' and loginid = '"
		      .$phpgw_info["user"]["userid"]."' and app = '"
		      .$phpgw_info["flags"]["currentapp"] . "'",__LINE__,__FILE__);
	      if($phpgw->db->num_rows()) {
	        $phpgw->db->next_record();
	        $data = $phpgw->db->f("content");
	        $data = $this->decrypt($data);
	        return $data;
	      }
      } else {
	      $data = $this->encrypt($data);
	      $phpgw->db->query("select * from phpgw_app_sessions where sessionid = '"
		      . $phpgw_info["user"]["sessionid"] . "' and app = '"
		      . $phpgw_info["flags"]["currentapp"] . "'",__LINE__,__FILE__);
	      if ($phpgw->db->num_rows()==0) {
	        $phpgw->db->query("INSERT INTO phpgw_app_sessions (sessionid,loginid,app,content)"
		        ." VALUES ('".$phpgw_info["user"]["sessionid"]."','"
		        .$phpgw_info["user"]["userid"]
		        ."','".$phpgw_info["flags"]["currentapp"]."','".$data."');",__LINE__,__FILE__);
	      } else {
	        $phpgw->db->query("update phpgw_app_sessions set content = '$data' where sessionid = '"
		        .$phpgw_info["user"]["sessionid"]."' and loginid = '"
		        .$phpgw_info["user"]["userid"]."'",__LINE__,__FILE__);
	      }
	      $data = $this->decrypt($data);
        return $data;
      }
    }

    function show_date($t = "", $format = "")
    {
      global $phpgw_info;

      if (! $t)
         $t = time();

      $t = $t + ((60*60) * $phpgw_info["user"]["preferences"]["common"]["tz_offset"]);

      if (! $format) {
         $format = $phpgw_info["user"]["preferences"]["common"]["dateformat"] . " - ";
         if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
            $format .= "h:i:s a";
         } else {
            $format .= "H:i:s";
         }
      }
      return date($format,$t);
    }

    function dateformatorder($yearstr,$monthstr,$daystr,$add_seperator = False)
    {
      global $phpgw_info;
      $dateformat = strtolower($phpgw_info["user"]["preferences"]["common"]["dateformat"]);
      $sep = substr($phpgw_info["user"]["preferences"]["common"]["dateformat"],1,1);

      $dlarr[strpos($dateformat,'y')] = $yearstr;
      $dlarr[strpos($dateformat,'m')] = $monthstr;
      $dlarr[strpos($dateformat,'d')] = $daystr;
      ksort($dlarr);

      if ($add_seperator) {
         return (implode($sep,$dlarr));
      } else {
         return (implode(" ",$dlarr));      
      }
    } 

    function formattime($hour,$min,$sec="")
    {
      global $phpgw_info;

      $h12 = $hour;
      if ($phpgw_info["user"]["preferences"]["common"]["timeformat"] == "12") {
         if ($hour > 12) 
            $ampm = " pm";
         else
            $ampm = " am";
         $h12 %= 12;
         if ($h12 == 0 && $hour)
            $h12 = 12;
         if ($h12 == 0 && ! $hour)
            $h12 = 0;
      } else 
         $h12 = $hour;

       if ($sec)
          $sec = ":$sec";

       return "$h12:$min$sec$ampm";
    }

    // This will be moved into the applications area.
    function check_code($code)
    {
      $s = "<br>";
      switch ($code)
      {
        case 13:	$s .= lang("Your message has been sent");break;
        case 14:	$s .= lang("New entry added sucessfully");break;
        case 15:	$s .= lang("Entry updated sucessfully");	break;
        case 16:	$s .= lang("Entry has been deleted sucessfully"); break;
        case 18:	$s .= lang("Password has been updated");	break;
        case 38:	$s .= lang("Password could not be changed");	break;
        case 19:	$s .= lang("Session has been killed");	break;
        case 27:	$s .= lang("Account has been updated");	break;
        case 28:	$s .= lang("Account has been created");	break;
        case 29:	$s .= lang("Account has been deleted");	break;
        case 30:	$s .= lang("Your settings have been updated"); break;
        case 31:	$s .= lang("Group has been added");	break;
        case 32:	$s .= lang("Group has been deleted");	break;
        case 33:	$s .= lang("Group has been updated");	break;
        case 34:    $s .= lang("Account has been deleted") . "<p>"
		             .  lang("Error deleting x x directory",lang("users")," ".lang("private")." ") 
 		             .  ",<br>" . lang("Please x by hand",lang("delete")) . "<br><br>"
		             .  lang("To correct this error for the future you will need to properly set the")
		             .  "<br>" . lang("permissions to the files/users directory")
	          	   .  "<br>" . lang("On *nix systems please type: x","chmod 707 "
			        . $phpgw_info["server"]["files_dir"] . "/users/"); 
       		break;
      case 35:	$s .= lang("Account has been updated") . "<p>"
		   .  lang("Error renaming x x directory",lang("users"),
		     " ".lang("private")." ") 
		   .  ",<br>" . lang("Please x by hand",
		      lang("rename")) . "<br><br>"
		   .  lang("To correct this error for the future you will need to properly set the")
		   .  "<br>" . lang("permissions to the files/users directory")
		   .  "<br>" . lang("On *nix systems please type: x","chmod 707 "
			. $phpgw_info["server"]["files_dir"] . "/users/"); 
		break;
      case 36:	$s .= lang("Account has been created") . "<p>"
		   .  lang("Error creating x x directory",lang("users"),
		     " ".lang("private")." ") 
		   .  ",<br>" . lang("Please x by hand",
		      lang("create")) . "<br><br>"
		   .  lang("To correct this error for the future you will need to properly set the")
		   .  "<br>" . lang("permissions to the files/users directory")
		   .  "<br>" . lang("On *nix systems please type: x","chmod 707 "
			. $phpgw_info["server"]["files_dir"] . "/users/"); 
		break;
      case 37:	$s .= lang("Group has been added") . "<p>"
		   .  lang("Error creating x x directory",lang("groups")," ")
		   .  ",<br>" . lang("Please x by hand",
		      lang("create")) . "<br><br>"
		   .  lang("To correct this error for the future you will need to properly set the")
		   .  "<br>" . lang("permissions to the files/users directory")
		   .  "<br>" . lang("On *nix systems please type: x","chmod 707 "
			. $phpgw_info["server"]["files_dir"] . "/groups/"); 
		break;
      case 38:	$s .= lang("Group has been deleted") . "<p>"
		   .  lang("Error deleting x x directory",lang("groups")," ")
		   .  ",<br>" . lang("Please x by hand",
		      lang("delete")) . "<br><br>"
		   .  lang("To correct this error for the future you will need to properly set the")
		   .  "<br>" . lang("permissions to the files/users directory")
		   .  "<br>" . lang("On *nix systems please type: x","chmod 707 "
			. $phpgw_info["server"]["files_dir"] . "/groups/"); 
		break;
      case 39:	$s .= lang("Group has been updated") . "<p>"
		   .  lang("Error renaming x x directory",lang("groups")," ")
		   .  ",<br>" . lang("Please x by hand",
		      lang("rename")) . "<br><br>"
		   .  lang("To correct this error for the future you will need to properly set the")
		   .  "<br>" . lang("permissions to the files/users directory")
		   .  "<br>" . lang("On *nix systems please type: x","chmod 707 "
			. $phpgw_info["server"]["files_dir"] . "/groups/"); 
		break;
      case 40: $s .= lang("You have not entered a\nBrief Description").".";
		break;
      case 41: $s .= lang("You have not entered a\nvalid time of day.");
		break;
      case 42: $s .= lang("You have not entered a\nvalid date.");
		break;
      default:	return "";
    }
    return $s;
  }
    
    function phpgw_error($error,$line = "", $file = "") 
    {
       echo "<p><b>phpGroupWare internal error:</b><p>$error";
       if ($line) {
          echo "Line: $line";
       }
       if ($file) {
          echo "File: $file";
       }
       echo "<p>Your session has been halted.";
       exit;
    }


    function create_phpcode_from_array($array)
    {
       while (list($key, $val) = each($array)) {
          if (is_array($val)) {
             while (list($key2, $val2) = each($val)) {
                if (is_array($val2)) {
                   while (list($key3, $val3) = each ($val2)) {
                      if (is_array($val3)) {
                         while (list($key4, $val4) = each ($val3)) {
                            $s .= '$phpgw_info["' . $key . '"]["' . $key2 . '"]["' . $key3 . '"]["' .$key4 . '"]="' . $val4 . '";';
                            $s .= "\n";
                         }
                      } else {
                         $s .= '$phpgw_info["' . $key . '"]["' . $key2 . '"]["' . $key3 . '"]="' . $val3 . '";';
                         $s .= "\n";
                      }
                   }
                } else {
                   $s .= '$phpgw_info["' . $key .'"]["' . $key2 . '"]="' . $val2 . '";';
                   $s .= "\n";
                }
             }
          } else {
             $s .= '$phpgw_info["' . $key . '"]="' . $val . '";';
             $s .= "\n";
          }
       }
       return $s;
    }   


    // This will return the full phpgw_info array, used for debugging    
    function debug_phpgw_info()
    {
       global $phpgw_info;

       while (list($key, $val) = each($phpgw_info)) {
          if (is_array($val)) {
             while (list($key2, $val2) = each($val)) {
                if (is_array($val2)) {
                   while (list($key3, $val3) = each ($val2)) {
                      if (is_array($val3)) {
                         while (list($key4, $val4) = each ($val3)) {
                            echo "phpgw_info[$key][$key2][$key3][$key4]=$val4<br>";
                         }
                      } else {
                         echo "phpgw_info[$key][$key2][$key3]=$val3<br>";
                      }
                  }
                } else {
                  echo "phpgw_info[$key][$key2]=$val2<br>";
                }
             }
          } else {
             echo "phpgw_info[$key]=$val<br>";
          }
       }
    }
    
    // This will return a list of functions in the API
    function debug_list_core_functions()
    {
       global $phpgw_info;

       echo "<br><b>core functions</b><br>";
       echo "<pre>";
       chdir($phpgw_info["server"]["include_root"]."/phpgwapi");
       system("grep -r '^[ \t]*function' *");
       echo "</pre>";
    }    

    function common_()
    { 
      global $phpgw, $phpgw_info;
      $phpgw_info["server"]["dir_separator"] = $this->filesystem_separator();
    }    

  }


  class hooks
  {
     function read()
     {
        global $phpgw;
        $db = $phpgw->db;

        $db->query("select * from phpgw_hooks");
        while ($db->next_record()) {
           $return_array[$db->f("hook_id")]["app"]      = $db->f("hook_appname");
           $return_array[$db->f("hook_id")]["location"] = $db->f("hook_location");
           $return_array[$db->f("hook_id")]["filename"] = $db->f("hook_filename");
        }
        return $return_array;
     }
   
     function proccess($type,$where = "")
     {
        global $phpgw_info, $phpgw;

        $currentapp = $phpgw_info["flags"]["currentapp"];
        $type = strtolower($type);

        if ($type != "location" && $type != "app") {
           return False;
        }

        // Add a check to see if that location/app has a hook
        // This way it doesn't have to loop everytime

        while ($hook = each($phpgw_info["hooks"])) {
           if ($type == "app") {
              if ($hook[1]["app"] == $currentapp) {
                 $include_file = $phpgw_info["server"]["server_root"] . "/"
                               . $currentapp . "/hooks/"
                               . $hook[1]["app"] . $hook[1]["filename"];
                 include($include_file);
              }

           } else if ($type == "location") {
              if ($hook[1]["location"] == $where) {
                 $include_file = $phpgw_info["server"]["server_root"] . "/"
                               . $hook[1]["app"] . "/hooks/"
                               . $hook[1]["filename"];
                 if (! is_file($include_file)) {
                    $phpgw->common->phpgw_error("Failed to include hook: $include_file");
                 } else {
                    include($include_file);
                 }
              }
           }
       }
    }
  }
