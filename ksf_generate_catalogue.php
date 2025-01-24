<?php
/**********************************************
Name: KSF Generate Catalogue 
for FrontAccounting 2.3.15 by kfraser 
Free software under GNU GPL
***********************************************/

$page_security = 'SA_ksf_generate_catalogue';
$path_to_root="../..";

include($path_to_root . "/includes/session.inc");
add_access_extensions();
set_ext_domain('modules/ksf_generate_catalogue');

include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/data_checks.inc");
//include_once($path_to_root . "/modules/ksf_modules_common/class.eventloop.php");

error_reporting(E_ALL);
ini_set("display_errors", "on");

global $db; // Allow access to the FA database connection
$debug_sql = 0;  // Change to 1 for debug messages

//display_notification( __LINE__ );
//page mode and page are needed to setup the theme, display_* Exception handler etc.
//simple_page_mode(true);
//page("test");

	include_once($path_to_root . "/modules/ksf_generate_catalogue/class.ksf_generate_catalogue.php");
	require_once( 'ksf_generate_catalogue.inc.php' ); //KSF_GENERATE_CATALOGUE_PREFS

	$coastc = new ksf_generate_catalogue( KSF_GENERATE_CATALOGUE_PREFS );
	$found = $coastc->is_installed();
	$coastc->set_var( 'found', $found );
	$coastc->set_var( 'help_context', "Generate Catalogue" );
	$coastc->set_var( 'redirect_to', "ksf_generate_catalogue.php" );
	$coastc->run();

//}

?>
