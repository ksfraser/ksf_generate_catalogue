<?php

require_once( '../ksf_modules_common/class.generic_fa_interface.php' ); 


/************************************************************************//**
 *
 * uses inherited call_table
 * uses class write_file
 * uses class email_file
 *
 * *************************************************************************/
class ksf_generate_catalogue extends generic_fa_interface
//class ksf_generate_catalogue 
{
	/*
	var $include_header;
	var $maxrowsallowed;
	var $lastoid;
	var $mailto;
	var $mailfrom;
	var $db;
	var $environment;
	var $maxpics;
	var $debug;
	var $fields_array;
	 */
	var $write_file;	//!< class write_file for writing files
	var $tmp_dir;		//!< @var string temp directory to store pricebook
	var $filename;		//!< @var string pricebook filename.
	function __construct( $pref_tablename )
	{
		simple_page_mode(true);
		global $db;
		$this->db = $db;
		//echo "ksf_generate_catalogue constructor";
	//	parent::__construct( null, null, null, null, $pref_tablename );
		
		$this->tmp_dir = "../../tmp";
		$this->filename = "pricebook.csv";
	//	$this->set_var( 'vendor', "ksf_generate_catalogue" );
	//	$this->set_var( 'include_header', TRUE );
		/*
		$this->fields_array = array();
		$this->fields_array[] = array( 'field' => 'category_id', 'table' => '', 'header' => '', 'join' => '0', );
		$this->fields_array[] = array( 'field' => 'sku', 'table' => 'stock_master', 'header' => 'SKU Barcode', 'join' => '0',);
		$this->fields_array[] = array( 'field' => 'sku', 'table' => 'stock_master', 'header' => 'SKU Text', 'join' => '0',);
		$this->fields_array[] = array( 'field' => 'price', 'table' => '', 'header' => 'Price', 'join' => '0',);
		$this->fields_array[] = array( 'field' => 'inactive', 'table' => 'stock_master', 'header' => '', 'join' => '0', 'where' => '=0');
		 */
		$this->config_values[] = array( 'pref_name' => 'lastoid', 'label' => 'Last Order Exported' );
		$this->tabs[] = array( 'title' => 'Config Updated', 'action' => 'update', 'form' => 'checkprefs', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Configuration', 'action' => 'config', 'form' => 'action_show_form', 'hidden' => FALSE );
	}
}
?>
