<?php

require_once( 'class.pricebook_file.php' ); 
global $path_to_root;

/*******************************************************
* 20230601 Mantis 998 fix Inventory Counts.
*          Mantin 2348 Extend Square columns.
* 20240601 Mantis 2813 CSV Format change - Item Type
*
********************************************************/

/*******************************************************//**
 * Generate an import CSV for SquareUp
 *
 * This class is only for data extraction and file creation.
 * There shouldn't be any UI.
 *
 * ********************************************************/
class square_catalog extends pricebook_file
{
	function __construct( $pref_tablename )
	{
		parent::__construct( null, null, null, null, $pref_tablename );
		$this->filename = "square_catalog.csv";
/**
	20230818 Square errors
		Square doesn't know location DEVEL					Mantis 2468
		Square_Online_Item_Visibility can be Visible, Invisible, Hidden		Mantis 2448
		Token is coming up invalid						Mantis 2453
		Item Name is sometimes blank						Mantis 2458
*/

//Mantis 2813 - Add Item Type
		$this->hline  = '"Token", "Item Name", "Description", "Category", "SKU", "Variation Name", "Price", "Enabled Fraser Highland Shoppe", "Current Quantity Fraser Highland Shoppe", "New Quantity Fraser Highland Shoppe", "Stock Alert Enabled Fraser Highland Shoppe", "Stock Alert Count Fraser Highland Shoppe", "Price Fraser Highland Shoppe", "Enabled KSF", "Current Quantity KSF", "New Quantity KSF", "Stock Alert Enabled KSF", "Stock Alert Count KSF", "Price KSF", "Tax - GST (5%)", "Square Online Item Visibility", "Weight (kg)", "Sale Start Date", "Sale End Date", "Sale Price", "Item Type", "Sync With Square", "Delivery Enabled", "Shipping Enabled", "Pickup Enabled", "SEO Title", "SEO Description", "Permalink"';
//Option Name 1, Option Value 1, ...6
//20231215 Options available in Square Import		MANTIS 2443
//Skip Detail Screen in POS
//Delivery Enabled
//Shipping Enabled
//Self-serve Ordering Enabled
//Pickup Enabled
//SEO Title
//SEO Description
//Permalink
//Unit and Precision


//Mantis 2813 - Add Item Type
		$this->query = "select 
					ifnull( t.square_token, '') as token,
					" . TB_PREF . "stock_master.stock_id, 
					a.description, 
					a.long_description, 
					a.category, 
					a.lowstock,  
					ifnull( c.c_qty, 0 ) as hg_qty, 	
					ifnull( b.b_qty, 0 ) as hold_qty, 	
					p.price as price, 
					q.price as registered,
					if( a.inactive, 'N', 'Y') as Square_Online_Item_Visibility,
					if( a.inactive, 'N', 'Y') as enabled_hg,
					'0.00' as weight,
					CURDATE() - INTERVAL 56 DAY as sale_start_date,
					CURDATE() - INTERVAL 28 DAY as sale_end_date,
					q.price as sale_price,
					'Physical' as Item_Type,
					'Y' as sync_with_square
				from 
					" . TB_PREF . "stock_master 
				left join 
					( select 
						p.stock_id, 
						p.price 
					from 
						" . TB_PREF . "prices p 
					where 
						p.sales_type_id=1 and 
						p.curr_abrev='CAD' 
					) as p
				on " . TB_PREF . "stock_master.stock_id = p.stock_id
				left join 
					( select 
						t.stock_id, 
						t.square_token
					from 
						" . TB_PREF . "square_tokens t 
					) as t
				on " . TB_PREF . "stock_master.stock_id = t.stock_id
				left join 
					( select 
						q.stock_id, 
						q.price 
					from 
						" . TB_PREF . "prices q 
					where 
						q.sales_type_id=3 and 	
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
				ON a.stock_id = " . TB_PREF . "stock_master.stock_id
				";
/*
				where 
					" . TB_PREF . "stock_master.loc_code='" . $this->PRIMARY_LOC . "' 
				group by 
					" . TB_PREF . "stock_master.stock_id ";
*/
	}
	
	//INHERIT function prep_write_file()	
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
	/*@int@*/function create_file()
	{

		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );

		$result = db_query( $this->query, "Couldn't grab inventory to export to square" );

		$rowcount=0;
		while( $row = db_fetch( $result ) )
		{
			$price = number_format( $row['price'], 2, ".", "" ); 
			if( $price < 10000 )
			{
/**
	20230818 Square errors
		Square doesn't know location DEVEL					Mantis 2468
		Square_Online_Item_Visibility can be Visible, Invisible, Hidden		Mantis 2448
		Token is coming up invalid						Mantis 2453
		Item Name is sometimes blank						Mantis 2458
*/

	$bad_decode = array ( "&#039;", ";", "&#150;" );
	$good_decode = array ( "'", ".", "." );
	$row['description'] = str_replace( $bad_decode, $good_decode, html_entity_decode( $row['description'] ) );
	$row['long_description'] = str_replace( $bad_decode, $good_decode, html_entity_decode( $row['long_description'] ) );
	$row['category'] = str_replace( $bad_decode, $good_decode, html_entity_decode( $row['category'] ) );

//20231215 Removing "Regular" from variation name as similar products are getting mis-flagged as variations by Square.
//	Square requires all variations to have the same description.

				if( strlen( $row['description'] ) > 2 )
				{
					if( "N" == $row["Square_Online_Item_Visibility"] )
					{
						$row["Square_Online_Item_Visibility"] = "HIDDEN";	//2448
					}
					else
					{
						$row["Square_Online_Item_Visibility"] = "VISIBLE";	//2448
					}
//Mantis 2813 - Add Item Type
					$this->write_file->write_array_to_csv( array( 
						$row["token"],	//Token
						$row['description'],
						$row['long_description'],
						$row['category'],	//Category
						$row['stock_id'],
						'', 		//Variation Name
						$price,
						$row['enabled_hg'], //'Y', 
						$row['hg_qty'],	//current
						$row['hg_qty'],	//new
						'N', //Stock Alert Enabled Fraser Highland Shoppe
						'0', //'1', //Stock Alert Count Fraser Highland Shoppe
						$price, //Price Fraser Highland Shoppe
						'N', //Enabled DEVEL
						$row['hold_qty'], //$row[''],Current Quantity DEVEL
						$row['hold_qty'], //$row[''],New QuantitY DEVEL
						'N', //$row[''],Stock Alert Enabled DEVEL
						$row['lowstock'], //$row[''],Stock Alert Count DEVEL
						number_format( $row['registered'], 2, ".", "" ), //$row[''],Price DEVEL
						'Y', //Tax - GST (5%) 
						$row["Square_Online_Item_Visibility"],
						$row["weight"],
						$row["sale_start_date"],
						$row["sale_end_date"],
						$row["sale_price"],
						$row['Item_Type'],
						$row["sync_with_square"],
									//MANTIS 2443
						$row['enabled_hg'], //'Y', 	delivery
						$row['enabled_hg'], //'Y', 	Shipping
						$row['enabled_hg'], //'Y', 	Pickup
						$row['description'], //		SEO Title
						$row['long_description'], //	SEO Description
						'' //Permalink	Permalinks can only contain letters, numbers, and the following characters [ / _ - .] and cannot contain spaces. Example: my-permalink.
						)
					);
					$rowcount++;
				}	//MAntis 2458
			}
		}
		$this->write_file->close();
		if( $rowcount > 0 )
			$this->email_file( "Square Catalog" );
		return $rowcount++;
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

		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );

		$result = $this->get_catalog_details_all();	//Pass this func name in as param allows a generic version...

		$rowcount=0;
		foreach( $result as $row ) 
		{
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
				'', //$row[''],Current Quantity DEVEL 
				'', //$row[''],New Quantity DEVEL 
				'', //$row[''],Stock Alert Enabled DEVEL 
				'', //$row[''],Stock Alert Count DEVEL 
				'', //$row[''],Price DEVEL 
				'Y' //Tax - GST (5%)
			);
		}
		$this->write_file->close();
		if( $rowcount > 0 )
			$this->email_file( "Square Catalog" );
		return $rowcount++;
	}
}

?>

