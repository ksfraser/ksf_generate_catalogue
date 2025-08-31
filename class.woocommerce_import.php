<?php

require_once( 'class.pricebook_file.php' ); 
global $path_to_root;

/*******************************************************
* 20240219 This started as the Square catalogue file
*	Extending to generate Woo
*
********************************************************/

/*******************************************************//**
 * Generate an import CSV for WooCommerce
 *
 * This class is only for data extraction and file creation.
 * There shouldn't be any UI.
 *
 * ********************************************************/
class woocommerce_import extends pricebook_file
{
	//WOOCOMMERCE Import Fields:
	//	ID
	//	Type
	//	SKU
	//	Name
	//	Published
	//	Is Featured
	//	Visibility in Catalogue
	//	Short Description
	//	Description
	//	Regular Price
	//	Sales Price
	//	Sales Start DAte
	//	Sales End Date
	//	Tax Status
	//	Tax Class
	//	In Stock
	//	Stock
	//	Backorders
	//	Low Stock Amount
	//	Sold Individually
	//	Weight
	//	Length
	//	Height
	//	Width
	//	Categories
	//	Tags
	//	Shipping Class
	//	Images
	//	Parent
	//	Upsells
	//	Cross Sells
	//	Group Products
	//	External URL
	//	Button Text
	//	Download ID
	//	Download Name
	//	Download URL
	//	Download Limit
	//	Download Expiry Days
	//	Attribute Name
	//	Attribute Values
	//	Is global
	//	Attribute visibility
	//	Default Attribute
	//	Allow Customer Reviews
	//	Purchase Note
	//	Import as Meta Data
	//	Position
	//	Sync with Square

//Inherited!
	//var $RETAIL_type;	//!<string
	//var $SALEPRICE_type;	//!<string
	//var $SALE_END_DATE;	//!<string YYYY-MM-DD

	function __construct( $pref_tablename )
	{
		parent::__construct( null, null, null, null, $pref_tablename );
		$this->file_count=0;
		$this->file_base =  "woo_catalog";
		$this->file_ext = "csv";
		$this->setFileName();


		//$this->hline  = '"ID", "Type", "SKU", "Name", "Published", "Is featured?", "Visibility in catalog", "Short Description", "Description", "Regular Price", "Sales Price", "Sales Start Date", "Sales End Date", "Tax Status", "Tax Class", "In Stock?", "Stock", "Backorders", "Low Stock Amount", "Sold Individually?", "Weight", "Length", "Height", "Width", "Categories", "Tags", "Shipping Class", "Images", "Parent", "Upsells", "Cross Sells", "Group Products", "External URL", "Button Text", "Download ID", "Download Name", "Download URL", "Download Limit", "Download Expiry Days", "Attribute Name", "Attribute Values", "Is global", "Attribute visibility", "Default Attribute", "Allow Customer Reviews", "Purchase Note", "Import as Meta Data", "Position", "Sync with Square"';
		$this->hline  = '"ID", "SKU", "Name", "Published", "Is Featured?", "Visibility in catalog", "Short Description", "Description", "Regular price", "Sale price", "Date sale price starts", "Date sale price ends", "Tax status", "Tax class", "In Stock?", "Stock", "Backorders", "Low stock amount", "Sold individually?", "Categories", "Tags", "Shipping class", "Allow customer reviews?", "Sync with Square", "Last Price Change", "Last Detail Change", "Sale Price Last Updated"';

display_notification( __FILE__ . "::" . __LINE__ );
	}

	//INHERIT function setFileName()

	function setQuery()
	{

/*************************************
 *	https://woocommerce.com/document/product-csv-importer-exporter/#product-csv-import-schema
 *	https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/sample-data/sample_products.csv
 *	https://woocommerce.com/document/product-csv-importer-exporter/
 *		See Converting existing Simple Product to Variable Product
 */
		$this->query = "select 
					t.woocommerce_id as ID, ";
/* Type can be simple, variation, virual*/
/*
			$this->query .= "'Simple' as Type,"; 
*/
			$this->query .= "" . TB_PREF . "stock_master.stock_id as SKU, 
					a.description as Name, 
					if( a.inactive, '0', '1') as published,
					'0' as featured,
					if( a.inactive, 'hidden', 'visible') as Visible, "; 	//Was N. Y
			$this->query .=	" a.description, 
					a.long_description, 
					a.last_updated as stock_last_updated,
					p.price as price, 
					p.last_updated as price_last_updated, 
					q.price as sale_price,
					q.last_updated as sale_last_updated,  ";
			$this->query .= " CURDATE() - INTERVAL 56 DAY as sale_start_date, ";
			$this->query .= " CURDATE() - INTERVAL 28 DAY as sale_end_date, ";
			$this->query .= " 'taxable' as TaxStatus,";				//was Y
			$this->query .= " 'Standard' as TaxClass,";				//was Y
			$this->query .= " '1' as instock,"; 				//was Y
			$this->query .= " ifnull( c.c_qty, 0 ) as hg_qty, 	
					'1' as backorder,
					a.lowstock,  
					'0' as sold_individually, ";
/*
			$this->query .= "'0.00' as weight, ";
			$this->query .= "'0.00' as length, ";
			$this->query .= "'0.00' as width, ";
			$this->query .= "'0.00' as height, ";
*/
			/*Example csv has category being -clothing > hoodies-*/
			$this->query .= "a.category, 
					'' as tags,
					'parcel' as shipping_class,";
/*
			$this->query .= "'' as images,";
			$this->query .= "'' as parent, ";			//This is for variation products - sku of the parent.
			$this->query .= "'' as upsells, ";			//id:100, SKU-1
			$this->query .= "'' as cross_sells, ";		//id:100 or SKU-1
			$this->query .= "'' as group_products, ";		//SKU1, SKU2 or id:100, ...
			$this->query .= "'' as external_url,
					'' as button_text,
					'' as download_id,
					'' as download_url,
					'' as download_limit,
					'' as download_expiry_days,
					'' as attribute_name, ";			//e.g. COLOR
			$this->query .= "'' as attribute_value, ";			//e.g. Blue, Red, Green
			$this->query .= "'' as attribute_is_global,
					'' as attribute_visibility,
					'' as default_attribute, ";
*/
			$this->query .= "'1' as allow_customer_reviews, ";
/*
			$this->query .= "'' as purchase_note, ";
			$this->query .= "'' as import_as_meta, ";
			$this->query .= "'' as position, ";
*/
			$this->query .= "if( a.inactive, 'no', 'yes')  as sync_with_square ";

/****
	SQUARE fields
					ifnull( c.c_qty, 0 ) as hg_qty, 	
					ifnull( b.b_qty, 0 ) as hold_qty, 	
					if( a.inactive, 'N', 'Y') as Square_Online_Item_Visibility,
					if( a.inactive, 'N', 'Y') as enabled_hg,
					q.price as sale_price,
***/
			$this->query .= "from 
					" . TB_PREF . "stock_master 
				left join 
					( select 
						p.stock_id, 
						p.last_updated, 
						p.price 
					from 
						" . TB_PREF . "prices p 
					where 
						p.sales_type_id=(select id from " . TB_PREF . "sales_types where sales_type in (  '" . $this->RETAIL_type . "' ) ) and 
						p.curr_abrev='CAD' 
					) as p
				on " . TB_PREF . "stock_master.stock_id = p.stock_id
				left join 
					( select 
						t.stock_id, 
						t.woocommerce_id
					from 
						" . TB_PREF . "woocommerce_tokens t 
					) as t
				on " . TB_PREF . "stock_master.stock_id = t.stock_id
				left join 
					( select 
						q.stock_id, 
						q.price,
						q.last_updated  
					from 
						" . TB_PREF . "prices q 
					where 
						q.sales_type_id=(select id from " . TB_PREF . "sales_types where sales_type in (  '" . $this->SALEPRICE_type . "' ) ) and 	
						q.curr_abrev='CAD' 
					) as q
				on " . TB_PREF . "stock_master.stock_id = q.stock_id
				left join 
					( select 
						c.stock_id, 
						c.loc_code as c_loc_code,  
						sum( c.qty ) as c_qty 
					from 
						" . TB_PREF . "stock_moves c 
					where 
						c.loc_code='" . $this->PRIMARY_LOC . "' 
					group by c.stock_id 
					) as c
				on " . TB_PREF . "stock_master.stock_id=c.stock_id
				left join 
					( select 
						b.stock_id, 
						b.loc_code as b_loc_code,  
						sum( b.qty ) as b_qty 
					from 
						" . TB_PREF . "stock_moves b 
					where 
						b.loc_code='" . $this->SECONDARY_LOC . "' 
					group by b.stock_id 
					) as b
				on " . TB_PREF . "stock_master.stock_id=b.stock_id
				LEFT JOIN 
					( select 
						s.stock_id, 
						s.description,	 
						s.last_updated, 
						s.long_description, 
						s.inactive,
						c.description as category, 	
						r.reorder_level as lowstock
            				from    
						" . TB_PREF . "stock_master s, 
						" . TB_PREF . "stock_category c, 
						" . TB_PREF . "loc_stock r
            				where   
						s.category_id = c.category_id and 
						r.loc_code='" . $this->PRIMARY_LOC . "' and 
						r.stock_id=s.stock_id 
					) as a
				ON a.stock_id = " . TB_PREF . "stock_master.stock_id ";
		if( 0 == strncasecmp( $this->sort_by, "stock", 5 ) )
		{
			//sort by Stock Details change then price
		 	$this->query .= "	order by a.last_updated DESC, p.last_updated DESC ";
		}
		else
		{
			//sort by last price change then item details
		 	$this->query .= "	order by p.last_updated DESC, a.last_updated DESC ";
		}
		 $this->query .= "";
/*
				where 
					" . TB_PREF . "stock_master.loc_code='" . $this->PRIMARY_LOC . "' 
				group by 
					" . TB_PREF . "stock_master.stock_id ";
*/
	}
	
	/*************************************//**
	 * Get the details for labels from stock_master, stock_category, prices
	 *
	 * @return db_result
	 * ****************************************/
	function get_catalog_details_all()
	{
		require_once( '../ksf_modules_common/class.fa_stock_master.php' );
		$sm = new fa_stock_master( $this->pref_tablename );
		$sm->getAll( true );	//->stock_array
		return $sm->stock_array; 
	}
	//INHERIT function prep_write_file()	
	/******************************************************//**
	 * This function creates the csv file for upload to WooCommerce
	 *
	 * Due to the query used in this class, we might not get all
	 * of the items from inventory if the REORDER level isn't set.
	 * This can be fixed with a query like
	 * 	insert ignore into 1_loc_stock( loc_code, reorder_level, stock_id ) select 'HG', 0, stock_id from 1_stock_master;
	 *
	 * @param internal hline
	 * @param internal query
	 * @param internal write_file (object)
	 * @param internal email_file (object)
	 * *******************************************************/
	/*@int@*/function create_file()
	{

display_notification( __FILE__ . "::" . __LINE__ );
		//INHERIT function prep_write_file()	
		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );
display_notification( __FILE__ . "::" . __LINE__ . print_r( $this->query, true ) );

		$result = db_query( $this->query, "Couldn't grab inventory to export to square" );

display_notification( __FILE__ . "::" . __LINE__ );
		$rowcount=0;
		while( $row = db_fetch( $result ) )
		{
/*
			$price = number_format( $row['price'], 2, ".", "" ); 
			if( $price < 10000 )
			{
*/

			$bad_decode = array ( "&#039;", ";", "&#150;", "&quot;", "?" );
			$good_decode = array ( "'", ".", ".", "'", "-" );
			$row['Name'] = str_replace( $bad_decode, $good_decode, html_entity_decode( $row['Name'] ) );
			$row['description'] = str_replace( $bad_decode, $good_decode, html_entity_decode( $row['description'] ) );
			$row['long_description'] = str_replace( $bad_decode, $good_decode, html_entity_decode( $row['long_description'] ) );
			$row['category'] = str_replace( $bad_decode, $good_decode, html_entity_decode( $row['category'] ) );

				if( strlen( $row['description'] ) > 2 )
				{
 					//if( strncmp( $row['description'], '~', 1 ) == 0 )
 					if( strncmp( $row['description'], $this->DISCONTINUED_PREFIX, 1 ) == 0 )
                        		{
                                		$row['long_description'] .= " --" . $this->DISCONTINUED_LABEL;
                                		$row['description'] = substr( $row['description'], 1 ) . " --" . $this->DISCONTINUED_LABEL;
                                		$row['Name'] = substr( $row['Name'], 1 );
                                		//$row['description'] .= " --" . $this->DISCONTINUED_LABEL;
                                		//$row['description'] .= " --DISCONTINUED";
						$row["sale_start_date"] = $this->DISCONTINUED_SALE_START_DATE; //was hardcoded "2024-12-31"
						$row["sale_end_date"] = $this->DISCONTINUED_SALE_END_DATE; //was hardcoded "2024-12-31"
						$row['backorder'] = 0;
						$row['featured'] = 1;
						$row['allow_customer_reviews'] = 0;
						if( $row['hg_qty'] == 0 && $row['published'] == 1)
						{
							//don't publish if we don't have any stock.
							//This item should be inactive in FA
							$row['published'] = 0;
							$row['instock'] = 0;
                                			display_notification( "Product discontinued and ZERO inventory but is Active ::" . print_r( $row, true ) );
						}
						$row['category'] .= ", " . $this->DISCONTINUED_CATEGORIES;
						//$row['category'] .= ", discontinued, clearance";
                                		//display_notification( "Product discontinued ::" . print_r( $row, true ) );
                        		} else
 					//if( strncmp( $row['description'], '*', 1 ) == 0 )
 					if( strncmp( $row['description'], $this->SPECIAL_ORDER_PREFIX, 1 ) == 0 )
                        		{
                                		$row['long_description'] .= " --SPECIAL ORDER";
                                		$row['description'] =  substr( $row['description'], 1 ) . " --" . $this->SPECIAL_ORDER_LABEL;
                                		$row['Name'] = substr( $row['Name'], 1 );
                                		//$row['long_description'] .= " --" . $this->SPECIAL_ORDER_LABEL;
                                		//$row['description'] .= " --" . $this->SPECIAL_ORDER_LABEL;
                                		//display_notification( "Product Special Order ::" . print_r( $row, true ) );
						$row['category'] .= ", " . $this->SPECIAL_ORDER_CATEGORIES;
                        		} else
                        		{
                        		}
 					//if( strncmp( $row['description'], '^', 1 ) == 0 )
 					if( strncmp( $row['description'], $this->CLEARANCE_PREFIX, 1 ) == 0 )
                        		{
                                		$row['description'] = substr( $row['description'], 1 ) . " --" . $this->CLEARANCE_LABEL;
                                		$row['long_description'] .= " --" . $this->CLEARANCE_LABEL;
                                		$row['Name'] = substr( $row['Name'], 1 );
                                		//$row['description'] .= " --" . $this->CLEARANCE_LABEL;
						$row["sale_start_date"] = $this->CLEARANCE_SALE_START_DATE; //was hardcoded "2024-12-31"
						$row["sale_end_date"] = $this->CLEARANCE_SALE_END_DATE; //was hardcoded "2024-12-31"
						$row['backorder'] = 0;
						$row['featured'] = 1;
						$row['allow_customer_reviews'] = 0;
						if( $row['hg_qty'] == 0 && $row['published'] == 1)
						{
							//don't publish if we don't have any stock.
							//This item should be inactive in FA
							$row['published'] = 0;
							$row['instock'] = 0;
                                			display_notification( "Product clearance and ZERO inventory but is Active ::" . print_r( $row, true ) );
						$row['category'] = $this->CLEARANCE_CATEGORIES . ", " . $row['category'];
						//$row['category'] .= ", clearance, overstock";
						}
                                		//display_notification( "Product Clearance ::" . print_r( $row, true ) );
                        		} else
                        		{
                        		}
 					//if( strncmp( $row['description'], '#', 1 ) == 0 )
 					if( strncmp( $row['description'], $this->CUSTOM_PREFIX, 1 ) == 0 )
                        		{
                                		$row['long_description'] .= " --" . $this->CUSTOM_LABEL;
                                		$row['Name'] = substr( $row['Name'], 1 );
                                		$row['description'] = substr( $row['description'], 1 ) . " --" . $this->CUSTOM_LABEL;
						$row['category'] .= ", " . $this->CUSTOM_CATEGORIES;
                                		//display_notification( "Product Custom ::" . print_r( $row, true ) );
                        		} else
                        		{
                        		}
					//if( ( $row['hg_qty'] > 0 OR $row['hold_qty'] > 0 ) && $row['published'] == 0 )
					if( ( $row['hg_qty'] > 0  ) && $row['published'] == 0 )
					{
                                		display_notification( "Product is inactive but has quantity::" . $row['SKU'] );
					}
					$bad_char = array( "&quot;" );
					$replace_char = array( "'" );


					$this->write_file->write_array_to_csv( array( 
						$row["ID"],
						$row["SKU"],
						$row['Name'],  
						$row['published'],
						$row['featured'],
						$row['Visible'],
						$row['description'],
						$row['long_description'],
						$row["price"],
						$row["sale_price"],
						$row["sale_start_date"],
						$row["sale_end_date"],
						$row["TaxStatus"],
						$row["TaxClass"],
						$row["instock"],
						$row['hg_qty'],	//current
						$row["backorder"],
						$row["lowstock"],
						$row["sold_individually"],
						$row['category'],	//Category
						$row['tags'],	//Category
						$row['shipping_class'],	//Category
						$row['allow_customer_reviews'],	//Category
						$row["sync_with_square"],
						$row["price_last_updated"],
						$row["stock_last_updated"],
						$row["sale_last_updated"],
						)
					);
					$rowcount++;
					if( $rowcount >= $this->max_rows_file )
					{
						//Time to start a new file
						$this->write_file->close();
						$this->file_count++;
						$this->setFileName();
						$this->prep_write_file();
						$this->write_file->write_line( $this->hline );
						$rowcount = 0;
						display_notification( "Another file " . $this->filename );
					}
				}	//MAntis 2458
/*
			}
*/
		}
		$this->write_file->close();
		//Calculate the NEW rowcount
		$rc = $this->file_count * $this->max_rows_file + $rowcount;
		if( $rc > 0 )
			$this->email_file( "Woo Catalog" );
		return $rc;
	}
	/******************************************************//**
	 * This function creates the csv file for upload to Square
	 *
	 * Due to the query used in this class, we might not get all
	 * of the items from inventory if the REORDER level isn't set.
	 * This can be fixed with a query like
	 * 	insert ignore into 1_loc_stock( loc_code, reorder_level, stock_id ) select 'HG', 0, stock_id from 1_stock_master;
	 *
	 * @param internal hline
	 * @param internal query
	 * @param internal write_file (object)
	 * @param internal email_file (object)
	 * *******************************************************/
	/*@int@*/function create_file_2()
	{
		/*
		require_once( '../ksf_modules_common/class.fa_stock_category.php' ); 
		$sc = new fa_stock_category( $this );
		require_once( '../ksf_modules_common/class.fa_prices.php' ); 
		$sp = new fa_prices( $this );
		require_once( '../ksf_qoh/class.ksf_qoh.php' ); 
		$qoh = new ksf_qoh( $this->pref_tablename );
		require_once( $path_to_root . '/includes/db/inventory_db.inc' );	//get_qoh_on_date($stock_id, $location=null, $date_=null) 
		 */

display_notification( __FILE__ . "::" . __LINE__ );
		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );

display_notification( __FILE__ . "::" . __LINE__ );
		$result = $this->get_catalog_details_all();	//Pass this func name in as param allows a generic version...

display_notification( __FILE__ . "::" . __LINE__ );
		$rowcount=0;
		foreach( $result as $row ) 
		{
display_notification( __FILE__ . "::" . __LINE__ );
			$sp->set( 'stock_id', $row['stock_id'] );
			$sp->set( 'sales_type_id', "1" );	//Retail]
			$sp->set( 'curr_abrev', "CAD" );
			$price_array = $sp->get_stock_price();
			$sc->set( 'category_id', $row['category'] );
			$cat = $sc->get_category_name();
			$qoh = get_qoh_on_date( $row['stock_id'] );
			$this->write_sku_catalogs_line( "",	//Token
				$row['description'],
				$row['long_description'],
				$cat['description'],	//Category
				$row['stock_id'],
				'Regular', //Variation Name
				$price_array['price'],
				'Y', 
				$qoh,	//current
				$qoh,	//new
				'Y', //Stock Alert Enabled Fraser Highland Shoppe
				'1', //Stock Alert Count Fraser Highland Shoppe
				'', //Price Fraser Highland Shoppe
				'N', //Enabled DEVEL
				'', //$row[''],Current Quantity DEVEL 
				'', //$row[''],New Quantity DEVEL 
				'', //$row[''],Stock Alert Enabled DEVEL 
				'', //$row[''],Stock Alert Count DEVEL 
				'', //$row[''],Price DEVEL 
				'Y' //Tax - GST (5%)
			);
		}
display_notification( __FILE__ . "::" . __LINE__ );
		$this->write_file->close();
		if( $rowcount > 0 )
			$this->email_file( "Woo Catalog" );
		return $rowcount++;
	}
}

?>

