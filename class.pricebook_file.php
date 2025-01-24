<?php

require_once( 'class.labels_file.php' ); 

/*******************************************************//**
 * Generate a Price List
 *
 * This class is only for data extraction and file creation.
 * There shouldn't be any UI.
 *
 * ********************************************************/
class pricebook_file extends labels_file
{
	function __construct( $pref_tablename )
	{
		parent::__construct( null, null, null, null, $pref_tablename );
		//set_time_limit(300);
		
		$this->filename = "pricebook.csv";
		$this->query = "select s.stock_id as stock_id, s.description as description, q.instock as instock, c.description as category, p.price as price from " . TB_PREF . "stock_master s, " . TB_PREF . "ksf_qoh q, " . TB_PREF . "stock_category c, " . TB_PREF . "prices p where s.inactive=0 and s.stock_id=q.stock_id and s.category_id = c.category_id and s.stock_id=p.stock_id and p.curr_abrev='CAD' and p.sales_type_id=1 order by c.description, s.description";
		//$this->set_var( 'vendor', "ksf_generate_catalogue" );
		//$this->set_var( 'include_header', TRUE );
	}
	//INHERIT function prep_write_file()
	//CALLED by ksf_generate_catalogue::create_price_book()
	/*@int@*/function create_file()
	{
		$this->prep_write_file();
		$this->write_file->write_line( $this->hline );
		//require_once( '../ksf_qoh/class.ksf_qoh.php' ); 
		$result = db_query( $this->query, "Couldn't grab inventory to export pricebook" );
		$rowcount=0;
		while ($row = db_fetch($result)) 
		{
			$this->write_sku_labels_line( $row['stock_id'], $row['category'], $row['description'], $row['price'] );
			$rowcount++;
		}
		$this->write_file->close();
		if( $rowcount > 0 )
			$this->email_file( "Price Book File" );
		return $rowcount++;
	}
}

?>

