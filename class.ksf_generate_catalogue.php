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
{
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
	var $write_file;	//!< class write_file for writing files
	var $tmp_dir;		//!< @var string temp directory to store pricebook
	var $filename;		//!< @var string pricebook filename.
	var $dolabels;
	var $delivery_no;		//!< @var int order number to export labels for
	var $smtp_server;
	var $smtp_port;
	var $smtp_user;
	var $smtp_pass;
	var $b_email;
	protected $SALEPRICE_type;	//!<string the Sale Price "sale type"
	protected $RETAIL_type;		//!<string the Regular Price "sale type"
	var $SALE_START_DATE;     //!<string YYYY-MM-DD
	var $SALE_END_DATE;     //!<string YYYY-MM-DD
	var $CLEARANCE_SALE_START_DATE;     //!<string YYYY-MM-DD
	var $CLEARANCE_SALE_END_DATE;     //!<string YYYY-MM-DD
	var $CLEARANCE_LABEL;     //!<string 
	var $CLEARANCE_PREFIX;    //!<string 
	var $CLEARANCE_CATEGORIES;    //!<string 
	var $DISCONTINUED_SALE_START_DATE;     //!<string YYYY-MM-DD
	var $DISCONTINUED_SALE_END_DATE;     //!<string YYYY-MM-DD
	var $DISCONTINUED_LABEL;     //!<string 
	var $DISCONTINUED_PREFIX;    //!<string 
	var $DISCONTINUED_CATEGORIES;    //!<string 
	var $SPECIAL_ORDER_LABEL;     //!<string 
	var $SPECIAL_ORDER_PREFIX;    //!<string 
	var $SPECIAL_ORDER_CATEGORIES;    //!<string 
	var $CUSTOM_LABEL;     //!<string 
	var $CUSTOM_PREFIX;    //!<string 
	var $CUSTOM_CATEGORIES;    //!<string 
	var $PRIMARY_LOC;
	var $SECONDARY_LOC;
	var $max_rows_file;		//!<int maximum rows per output file
	var $file_count;
	var $file_base;		//!<string base of output filename.  Will be $file_base_$file_count.$file_ext
	var $file_ext;		//!<string extension of file
	var $sort_by;		//!<string sort by price/details
	protected $stock_id;	//!<string the stock_id we want to create a single CSV for.  MANTIS 3228
	var $thermal_printer;	//!<bool Are we using a thermal printer or Avery Labels with 3of9     	MANTIS 3228
//20241102 I haven't designed how I am going to use this field yet!
	var $use_price_change_for_sales;	//!<char should we use price change dates for sales dates?
	protected $TEST_DATE;

	function __construct( $prefs_tablename )
	{
		simple_page_mode(true);
		global $db;
		$this->db = $db;
		//echo "ksf_generate_catalogue constructor";
		parent::__construct( null, null, null, null, $prefs_tablename );
		
		$this->tmp_dir = "../../tmp";
		$this->filename = "pricebook.csv";
		//$this->set_var( 'vendor', "ksf_generate_catalogue" );
		$this->set_var( 'include_header', TRUE );
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
		$this->config_values[] = array( 'pref_name' => 'include_header', 'label' => 'Include Headers' );
		$this->config_values[] = array( 'pref_name' => 'maxrowsallowed', 'label' => 'Maximum Rows Allowed in file' );
		$this->config_values[] = array( 'pref_name' => 'mailto', 'label' => 'Mail CSV to email address' );
		$this->config_values[] = array( 'pref_name' => 'mailfrom', 'label' => 'Mail from email address' );
		$this->config_values[] = array( 'pref_name' => 'environment', 'label' => 'Environment (devel/accept/prod)' );
		$this->config_values[] = array( 'pref_name' => 'dolabels', 'label' => 'Print Labels (0/1)' );
		$this->config_values[] = array( 'pref_name' => 'smtp_server', 'label' => 'Mail Server' );
		$this->config_values[] = array( 'pref_name' => 'smtp_port', 'label' => 'Mail Server Port (25/993)' );
		$this->config_values[] = array( 'pref_name' => 'smtp_user', 'label' => 'Mail Server User' );
		$this->config_values[] = array( 'pref_name' => 'smtp_passs', 'label' => 'Mail Server Password' );
		$this->config_values[] = array( 'pref_name' => 'b_email', 'label' => 'Send file by email', 'type' => 'yesno_list' );
		$this->config_values[] = array( 'pref_name' => 'debug', 'label' => 'Debug (0,1+)' );
		$this->config_values[] = array( 'pref_name' => 'PRIMARY_LOC', 'label' => 'Primary Retail Location for Inventory string (default HG)', 'type' => 'location' );
		$this->config_values[] = array( 'pref_name' => 'SECONDARY_LOC', 'label' => 'Secondary Retail Location for Inventory string (default HOLD)', 'type' => 'location' );
		$this->config_values[] = array( 'pref_name' => 'RETAIL_type', 'label' => 'Retail Pricebook (sales_type_id) string' );
		$this->config_values[] = array( 'pref_name' => 'SALEPRICE_type', 'label' => 'Sales Pricing Pricebook (sales_type_id) string' );
		$this->config_values[] = array( 'pref_name' => 'SALE_START_DATE', 'label' => 'Sale Start Date YYYY-MM-DD' );
		$this->config_values[] = array( 'pref_name' => 'SALE_END_DATE', 'label' => 'Sale End Date YYYY-MM-DD' );
	//	$this->config_values[] = array( 'pref_name' => 'TEST_DATE', 'label' => 'TEST Date YYYY-MM-DD', 'type' => 'dateselector', 'lead_days' => 0, 'lead_months' = 0, 'lead_years' => 0 );
		$this->config_values[] = array( 'pref_name' => 'DISCONTINUED_SALE_START_DATE', 'label' => 'Discontinued Sale Start Date YYYY-MM-DD' );
		$this->config_values[] = array( 'pref_name' => 'DISCONTINUED_SALE_END_DATE', 'label' => 'Discontinued Sale End Date YYYY-MM-DD' );
		$this->config_values[] = array( 'pref_name' => 'DISCONTINUED_LABEL', 'label' => 'Discontinued Label on products e.g. --DISCONTINUED' );
		$this->config_values[] = array( 'pref_name' => 'DISCONTINUED_PREFIX', 'label' => 'Discontinued Prefix Character e.g. ~' );
		$this->config_values[] = array( 'pref_name' => 'DISCONTINUED_CATEGORIES', 'label' => 'Discontinued Categories e.g. discontinued' );
		$this->config_values[] = array( 'pref_name' => 'CLEARANCE_SALE_START_DATE', 'label' => 'Clearance Sale Start Date YYYY-MM-DD' );
		$this->config_values[] = array( 'pref_name' => 'CLEARANCE_SALE_END_DATE', 'label' => 'Clearance Sale End Date YYYY-MM-DD' );
		$this->config_values[] = array( 'pref_name' => 'CLEARANCE_LABEL', 'label' => 'Clearance Label on products e.g. --CLEARANCE' );
		$this->config_values[] = array( 'pref_name' => 'CLEARANCE_PREFIX', 'label' => 'Clearance Prefix Character e.g. ^' );
		$this->config_values[] = array( 'pref_name' => 'CLEARANCE_CATEGORIES', 'label' => 'Clearance Categories e.g. clearance' );
		$this->config_values[] = array( 'pref_name' => 'SPECIAL_ORDER_LABEL', 'label' => 'Special Order Label on products e.g. --SPECIAL_ORDER' );
		$this->config_values[] = array( 'pref_name' => 'SPECIAL_ORDER_PREFIX', 'label' => 'Special Order Prefix Character e.g. *' );
		$this->config_values[] = array( 'pref_name' => 'SPECIAL_ORDER_CATEGORIES', 'label' => 'Special Order Categories e.g. specialorder' );
		$this->config_values[] = array( 'pref_name' => 'CUSTOM_LABEL', 'label' => 'Custom Order Label on products e.g. --CUSTOM' );
		$this->config_values[] = array( 'pref_name' => 'CUSTOM_PREFIX', 'label' => 'Custom Order Prefix Character e.g. #' );
		$this->config_values[] = array( 'pref_name' => 'CUSTOM_CATEGORIES', 'label' => 'Special Order Categories e.g. customorder' );
		$this->config_values[] = array( 'pref_name' => 'max_rows_file', 'label' => 'Maximum rows per file' );
		/** Mantis 3228 generate SKU without '*' for thermal printer - add ->thermal_printer var **/
		$this->config_values[] = array( 'pref_name' => 'thermal_printer', 'label' => ' Are we using a thermal printer (T) or Avery Labels with 3of9(F) (bool)' );
//20241102 I haven't designed how I am going to use this field yet!
		//$this->config_values[] = array( 'pref_name' => 'use_price_change_for_sales', 'label' => 'Use the pricing last changed date for sales date? (T/F)' );
		$this->dolabels = 0;
		$this->file_count = 0;
		$this->file_ext = "csv";
		
		//The forms/actions for this module
		//Hidden tabs are just action handlers, without accompying GUI elements.
		//$this->tabs[] = array( 'title' => '', 'action' => '', 'form' => '', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Install Module', 'action' => 'create', 'form' => 'install', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Export File', 'action' => 'exportfile', 'form' => 'write_file_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Generate Catalogue', 'action' => 'gencat', 'form' => 'form_pricebook', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Lables for a Purchase Order', 'action' => 'polabelsfile', 'form' => 'polabelsfile_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Labels Generated', 'action' => 'label_export_by_PO_Delivery', 'form' => 'label_export_by_PO_Delivery', 'hidden' => TRUE );
		$this->tabs[] = array( 'title' => 'Lables for a Stock_id (SKU)', 'action' => 'skulabelsfile', 'form' => 'skulabelsfile_form', 'hidden' => FALSE );
		$this->tabs[] = array( 'title' => 'Lable for a Stock_id (SKU)', 'action' => 'skulabelsfile_done', 'form' => 'skulabelsfile_done', 'hidden' => TRUE );
		//We could be looking for plugins here, adding menu's to the items.
		$this->add_submodules();
	/*	
	 */
	}
        /**//*************************************************************
        * Set the filename for the export file.  Take file_count into consideration
        *
        * @params none
        * @returns none sets internal
        ***************************************************************************/
        function setFileName()
        {
                $this->filename = $this->file_base . "_" . $this->file_count . "." . $this->file_ext;
        }

	//CALLED by child classes
	/**//**************************************
	* Prepare an output file.
	*
	* @param none
	* @return none
	******************************************/
	function prep_write_file()
	{	
		if( ! isset( $this->tmp_dir ) OR strlen( $this->tmp_dir ) < 3 )
			throw new Exception( "Tmp dir not set" );
		if( ! isset( $this->filename ) OR strlen( $this->filename ) < 3 )
			throw new Exception( "Output filename not set" );
		try 
		{
			//Inheriting classes need to change the filename to incorporate the file_count
			require_once( '../ksf_modules_common/class.write_file.php' ); 
			$this->write_file = new write_file( $this->tmp_dir, $this->filename );
		}
		catch( Exception $e )
		{
			throw $e;
		}
	}
	/*******************************************************************//**
	*
	*
	************************************************************************/
	//CALLED by form_pricebook
	/*@int@*/function create_price_book()
	{
		if( include_once( 'class.pricebook_file.php' ) )
		{
			$pb = new pricebook_file( $this->prefs_tablename );
			foreach( $this->config_values as $arr )
			{
				$value = $arr["pref_name"];
				$pb->$value = $this->$value;
			}
			$rowcount = $pb->create_file();
		}
		if( include_once( 'class.square_catalog.php' ) )
		{
			$sc = new square_catalog( $this->prefs_tablename );
			foreach( $this->config_values as $arr )
			{
				$value = $arr["pref_name"];
				$sc->$value = $this->$value;
			}
			$rowcount = $sc->create_file();
		}
		if( include_once( 'class.woocommerce_import.php' ) )
		{
			$sc = new woocommerce_import( $this->prefs_tablename );
			//$sc->set( "RETAIL_type", $this->get( "RETAIL_type" ) );
			//$sc->set( "SALEPRICE_type", $this->get( "SALEPRICE_type" ) );
			foreach( $this->config_values as $arr )
			{
				$value = $arr["pref_name"];
				$sc->$value = $this->$value;
			}
			$sc->setQuery();
			$rowcount = $sc->create_file();
		}
		if( include_once( 'class.WooPOS_Count.php' ) )
		{
			$woopos = new WooPOS_Count_file( $this->prefs_tablename );
			foreach( $this->config_values as $arr )
			{
				$value = $arr["pref_name"];
				$woopos->$value = $this->$value;
			}
			$woopos->create_file();
			return $rowcount;
		}
	}
	/*******************************************************************//**
	*
	*
	************************************************************************/
	//Called by form_pricebook
	/*@int@*/function create_sku_labels()
	{
		if( include_once( 'class.labels_file.php' ) )
		{
			$lf = new labels_file( $this->prefs_tablename );
			foreach( $this->config_values as $arr )
			{
				$value = $arr["pref_name"];
				$lf->$value = $this->$value;
			}
			if( isset( $this->stock_id ) )
			{
				$lf->set( "stock_id", $this->stock_id );
			}
			$rowcount = $lf->create_file();
			$lf->email_file();
			return $rowcount;
		}
		else
			return -1;
	}
 
	
	/*******************************************************************//**
	*
	*
	************************************************************************/
	function email_file( $email_subject = 'Pricebook file' )
	{
		if( isset( $this->mailto ) )
		{
			require_once( '../ksf_modules_common/class.email_file.php' ); 
			if( $this->b_email )
			{
				try {
					$mail_file = new email_file( $this->mailfrom, $this->mailto, $this->tmp_dir, $this->filename, $this->smtp_user, $this->smtp_pass, $this->smtp_server, $this->smtp_port );
					//$mail_file = new email_file( $this->mailfrom, $this->mailto, $this->tmp_dir, $this->filename, "kevin@ksfraser.com", "letmein", "musicone.ksfraser.com", "25" );	//Error about HELO name
					//$mail_file = new email_file( $this->mailfrom, $this->mailto, $this->tmp_dir, $this->filename, "sales@fraserhighlandshoppe.ca", "HiGhLaNd12@", "p3plcpnl0185.prod.phx3.secureserver.net", "993" );
					$mail_file->email_file( $email_subject );
					display_notification("email sent to $this->mailto.");
				}
				catch( Exception $e )
				{
				}
				return TRUE;
			}
		}
		return FALSE;
	}
	/*******************************************************************//**
	*
	*
	************************************************************************/
	function form_pricebook()
	{
		$this->create_price_book();
		$this->email_file();
		if( $this->dolabels )
		{
			
			$this->create_sku_labels();
		}
		$this->call_table( '', "OK" );
	}
	/*******************************************************************//**
	*
	*
	************************************************************************/
	function write_file_form()
	{
		if( $this->dolabels)
			$this->call_table( 'gencat', "Create Catalogue File and Labels" );
		else
			$this->call_table( 'gencat', "Create Catalogue File" );
	}
	/*******************************************************************//**
	* Form to request the stock_id to generate a label
	*
	* @since 20250227
	*
	* @param none
	* @return none
	************************************************************************/
	function skulabelsfile_form()
	{
	 	$selected_id = 1;
                $none_option = "";
                $submit_on_change = FALSE;
                $all = FALSE;
                $all_items = TRUE;
                $mode = 1;
                $spec_option = "";
                start_form(true);
                start_table(TABLESTYLE2, "width=40%");
                table_section_title("Labels by Stock_ID");
                text_row("SKU to generate the label", 'stock_id', "", STOCK_ID_LENGTH, STOCK_ID_LENGTH);
                end_table(1);
                hidden('action', 'skulabelsfile_done');
                submit_center('skulabelsfile', "Generate Label for SKU");
                end_form();
	}
	/******************************************************************************//**
	* Given a PO number create the labels for the items in that PO
	*
	*
	* @returns bool
	*********************************************************************************/
	/*@bool@*/function skulabelsfile_done()
	{
		if( !isset( $this->stock_id ) )
		{
			if( isset( $_POST['stock_id'] ) )
				$this->stock_id = $_POST['stock_id'];
		}
		
		if( include_once( 'class.labels_file.php' ) )
		{
			$lf = new labels_file( $this->prefs_tablename );
			$lf->set( "stock_id", $this->stock_id );
			foreach( $this->config_values as $arr )
			{
				if( isset( $arr['title'] ) )
				{
					foreach( $arr['title'] as $value )
					{
						$lf->$value = $this->$value;
					}
				}
				else
				{
					//20230903 This was printing the PREFS always!
					if( $this->debug > 1 )
					{
						echo "<br />";
						var_dump( $arr );
						echo "<br />";
					}
				}
			}
			$count = $lf->create_sku_labels_from_sku();
		}
		return TRUE;
	}

	/*******************************************************************//**
	*
	*
	************************************************************************/
	function polabelsfile_form()
	{
	 	$selected_id = 1;
                $none_option = "";
                $submit_on_change = FALSE;
                $all = FALSE;
                $all_items = TRUE;
                $mode = 1;
                $spec_option = "";
                start_form(true);
                start_table(TABLESTYLE2, "width=40%");
                table_section_title("Labels for Purchase Order");
/*
                $company_record = get_company_prefs();
                $this->get_id_range();
                $sql = "SELECT supp_name, delivery_no FROM " . $this->company_prefix . "purch_orders o, " . $this->company_prefix . "suppliers s where s.supplier_id = o.supplier_id";
                //echo combo_input("SupplierPO", $selected_id, $sql, 'supplier_id', 'supp_name',
*/

                 text_row("Export Purchase Order -- <b>Delivery ID</b> (First):", 'delivery_no', $this->lastoid+1, 10, 10);
		/**
		 *      Mantis 2483 */
                 text_row("Export Purchase Order -- <b>Delivery ID</b> (Last):", 'last_delivery_no', $this->lastoid+1, 10, 10);

                 end_table(1);

                 hidden('action', 'label_export_by_PO_Delivery');
                 submit_center('label_export_by_PO_Delivery', "Export PO Delivery");

                 end_form();

	//	$this->call_table( 'polabels', "Create Labels" );
	}
	/*******************************************************************//**
	*
	*
	************************************************************************/
	function label_export()
	{
			$this->filename = "delivery_" . $this->delivery_no . "_labels.csv";
			$this->create_sku_labels();
			$this->email_file();
	}
	/******************************************************************************//**
	* Given a PO number create the labels for the items in that PO
	*
	*
	* @returns bool
	*********************************************************************************/
	/*@bool@*/function label_export_by_PO_Delivery()
	{
		if( !isset( $this->delivery_no ) )
		{
			if( isset( $_POST['delivery_no'] ) )
				$this->delivery_no = $_POST['delivery_no'];
		}
		
		if( include_once( 'class.labels_file.php' ) )
		{
			$lf = new labels_file( $this->prefs_tablename );
			$lf->set( "delivery_no", $this->delivery_no );
			/**
			 *      Mantis 2483 */
			if( isset(  $_POST['last_delivery_no'] ) )
			{
				$lf->set( "last_delivery_no", $_POST['last_delivery_no'] );
			}
			else
			{
				$lf->set( "last_delivery_no", $this->delivery_no );
			}
			/** !	Mantis 2483 */
			foreach( $this->config_values as $arr )
			{
				if( isset( $arr['title'] ) )
				{
					foreach( $arr['title'] as $value )
					{
						$lf->$value = $this->$value;
					}
				}
				else
				{
					//20230903 This was printing the PREFS always!
					if( $this->debug > 1 )
					{
						echo "<br />";
						var_dump( $arr );
						echo "<br />";
					}
				}
			}
			$count = $lf->create_sku_labels_from_PO( $this->delivery_no );
			if( 0 < $count )
			{
			/** Mantis 2973
			 * lastoid was being set to delivery_no rather than last_delivery_no */
				if( isset( $this->last_delivery_no ) )
				{
					$this->set_pref( 'lastoid', $this->last_delivery_no );
				}
				else
				{
					$this->set_pref( 'lastoid', $this->delivery_no );
				}
			/** ! Mantis 2973 */
			}
		}
		return TRUE;
	}
	

}

?>

