<?php

require_once( 'class.ksf_generate_catalogue.php' ); 
//require_once( 'purchasing/includes/db/grn_db.inc:' );	/get_grn_items

/*******************************************************//**
 * Generate an import CSV for SquareUp
 *
 * This class is only for data extraction and file creation.
 * There shouldn't be any UI.
 *
 * ********************************************************/
class labels_file extends ksf_generate_catalogue
{
	protected $hline = '"stock_id", "Title", "barcode", "category", "price"';
	protected $query;
/***	INHERIT
	protected $delivery_no;
*/
/**
 *	Mantis 2483*/
	protected $last_delivery_no;
/** Mantis 2978 
 *	Retail price set to 0 */
	protected $RETAIL_type;	//The sales_type of our retail price book.  Default is 1.
	 	
	function __construct( $pref_tablename )
	{
/** 20230903 Mantis 2323
	Wrong results were being returned.  this->delivery_no was not being set
*/	
		$this->delivery_no = 0;
/** Mantis 2978 
 *	Retail price set to 0 */
		$this->RETAIL_type = "Retail";	//Is the default install type "default"??
		$this->config_values[] = array( 'pref_name' => 'RETAIL_type', 'label' => 'Retail Pricebook (sales_type_id) string' );
		parent::__construct( null, null, null, null, $pref_tablename );
		set_time_limit(300);
		
		$this->filename = "labels.csv";
		$this->set_var( 'include_header', TRUE );
		$this->query = "select s.stock_id as stock_id, s.description as description, q.instock as instock, c.description as category, 0 as price from " . TB_PREF . "stock_master s, " . TB_PREF . "ksf_qoh q, " . TB_PREF . "stock_category c where s.inactive=0 and s.stock_id=q.stock_id and s.category_id = c.category_id order by c.description, s.description";

	}
	/**//****************************************************************
	*
	*
	*********************************************************************/
	//INHERIT function prep_write_file()
	//CALLED by ksf_generate_catalogue::create_sku_labels()
	/*@int@*/function create_file()
	{

		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );

		//require_once( '../ksf_qoh/class.ksf_qoh.php' ); 
		
		$result = db_query( $this->query, "Couldn't grab inventory to export labels" );

		$rowcount=0;
		while ($row = db_fetch($result)) 
		{
			$num = $row['instock'];
			//If we have 6 items instock, we need 6 labels to print so we can put on product
			for( $num; $num > 0; $num-- )
			{
				/** Mantis 3228 generate SKU without '*' for thermal printer - add ->thermal_printer var **/
				$this->write_sku_labels_line( $row['stock_id'], $row['category'], $row['description'], $row['price'],  $this->thermal_printer  );
				$rowcount++;
			}
		}
		$this->write_file->close();
		if( $rowcount > 0 )
			$this->email_file();
		return $rowcount;	
	}
	/**//****************************************************************
	*
	*
	*********************************************************************/
	/*@int@*/function create_sku_catalogs()
	{
			return 0;
	}
	/*************************************************************************************//**
	* Generate catalogs from a purchase order
	*
	* 
	*
	*****************************************************************************************/
	//CALLED by label_export_by_PO_Delivery()
	/*@int@*/function create_sku_labels_from_PO( $delivery_no )
	{
		//display_notification( __METHOD__ );
/** 20230903 Mantis 2323
	Wrong results were being returned.  this->delivery_no was not being set
*/	
		$this->delivery_no = $delivery_no;
		$this->filename = "delivery_" . $this->delivery_no . "_labels.csv";
		
		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );

		require_once( '../ksf_qoh/class.ksf_qoh.php' ); 

/** 20230903 Mantis 2323
	Wrong results were being returned.  this->delivery_no was not being set
*/	
		//$result = get_grn_items( $delivery_no, "", false, false, 0, "", "" );
		$rowcount=0;
/**
 *	Mantis 2483
*/
		for( $number = $this->delivery_no; $number <= $this->last_delivery_no; $number++ )
		{
			// function get_grn_items($grn_batch_id=0, $supplier_id="", $outstanding_only=false, $is_invoiced_only=false, $invoice_no=0, $begin_date="", $end_date="")
			$result = get_grn_items( $number, "", false, false, 0, "", "" );
			while ($row = db_fetch($result)) 
			{
				display_notification( __FILE__ . "::" . __LINE__ . "::" );
				//20230903 don't print the list unless debugging turned up
	                        if( $this->debug > 1 )
	                        {
					var_dump( $row );
				}
				$num = $row['qty_recd'];		
				//display_notification( __FILE__ . "::" . __LINE__ . "::" );
				$price = 0;
/** Mantis 2978 
 *	Retail price set to 0 */
      				$price_sql = "SELECT price from " . TB_PREF . "prices p1
                				WHERE p1.sales_type_id = (select id from " . TB_PREF . "sales_types where sales_type in ( '" . $this->RETAIL_type . "' ) )
							AND p1.stock_id='" . $row['item_code'] . "'";
				//display_notification( __FILE__ . "::" . __LINE__ . "::" . print_r( $price_sql, true ) );
				$pres = db_query( $price_sql, "Couldn't update table desired retail" );
				//display_notification( __FILE__ . "::" . __LINE__ . "::" );
				$prow = db_fetch_assoc( $pres );
				//display_notification( __FILE__ . "::" . __LINE__ . "::" . print_r( $prow ) );
				if( isset( $prow['price'] ) )
				{
					$price = $prow['price'];
				}
				else
				{
					display_error( __FILE__ . "::" . __LINE__ . "::" . print_r( $prow ) );
				}
/** ! Mantis 2978 */
    
				//If we have 6 items instock, we need 6 labels to print so we can put on product
				for( $num; $num > 0; $num-- )
				{
					/** Mantis 3228 generate SKU without '*' for thermal printer - add ->thermal_printer var **/
					$this->write_sku_labels_line( $row['item_code'], "", $row['description'], $price,  $this->thermal_printer );
					$rowcount++;
				}
			}
/**
 *	Mantis 2483
*/
		}
		$this->write_file->close();
		if( $rowcount > 0 )
			$this->email_file();
			//$this->email_price_book();	//email_price_book doesn't exist
		return $rowcount;
	}
	function email_file( $subject = "Labels File" )
	{
		if( parent::email_file( $subject ) )
		{
			display_notification( "Download file <a href=" . $this->tmp_dir . "/" . $this->filename . ">" . $this->filename . "</a>" );
		}
		else
		{
			echo "<br /><br />Download file <a href=" . $this->tmp_dir . "/" . $this->filename . ">" . $this->filename . "</a>";
		}
	}
	function form_pricebook()
	{
		$this->create_price_book();
		$this->call_table( '', "OK" );
	}
	function write_file_form()
	{
		$this->call_table( 'gencat', "Create Catalogue File" );
	}
	function pocatalogsfile_form()
	{
	}
	function catalog_export()
	{
	}
	/******************************************************************************//**
	* Given a PO number create the catalogs for the items in that PO
	*
	*
	* @returns bool
	*********************************************************************************/
	/*@bool@*/function catalog_export_by_PO_Delivery()
	{
		return FALSE;
	}
	/*************************************************************************************//**
	* Generate label from stock_id
	*
	* @since 20250227
	*
	* @param none uses internal
	* @returns int 1 - number of "rows" for the single stock id
	*****************************************************************************************/
	/*@int@*/function create_sku_labels_from_sku()
	{
		//display_notification( __FILE__ . "::" . __LINE__ . "::" . __METHOD__ );
		if( ! isset( $this->stock_id ) OR  strlen( $this->stock_id ) < 1 )
		{
			throw new Exception( "stock_id invalid!", KSF_FIELD_NOT_SET );
		}
		$this->filename = $this->stock_id . "_labels.csv";
		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );
		$category = ""; 
		$price = 0;

		require_once( '../ksf_modules_common/class.fa_stock_master.php' );
		$sm = new fa_stock_master( $this->prefs_tablename );
		$row = $sm->getStock_ID( $this->stock_id );
		//display_notification( __FILE__ . "::" . __LINE__ . "::" );
	        if( $this->debug > 1 )
	        {
			var_dump( $row );
		} 
		require_once( '../ksf_modules_common/class.fa_sales_types.php' );
		$st = new fa_sales_types( $this );
		$sales_type_id = $st->get_sales_type_from_name( $this->RETAIL_type );
		//display_notification( __FILE__ . "::" . __LINE__ . "::" . "Sales type id: " . print_r( $sales_type_id, true ) );

		require_once( '../ksf_modules_common/class.fa_prices.php' );
		$faprice = new fa_prices( $this );
		$faprice->set( "sales_type_id", $sales_type_id );
		$faprice->set( "stock_id", $this->stock_id );
		$faprice->set( "curr_abrev", "CAD" );		//We should probably have either a config variable, or grab the system default!
		$price = $faprice->get_stock_price();
			//display_notification( __FILE__ . "::" . __LINE__ . "::" . "price: " . print_r( $faprice, true ) );
		//display_notification( __FILE__ . "::" . __LINE__ . "::" . "price: " . print_r( $price, true ) );

		require_once( '../ksf_modules_common/class.fa_stock_category.php' );
		$sc = new fa_stock_category( $this );
		$sc->set( "category_id", $row['category_id'] );
		$res = $sc->get_category_name();
		//display_notification( __FILE__ . "::" . __LINE__ . "::" . "CAT: " . print_r( $res, true ) );
		if( isset( $res['description'] ) )
		{
			$category = $res['description'];
		}
		
		$this->write_sku_labels_line( $this->stock_id, $category, $row['description'], $price,  $this->thermal_printer );
		$this->write_file->close();
			$this->email_file();
			//$this->email_price_book();	//email_price_book doesn't exist
		return 1;
	}

}

?>

