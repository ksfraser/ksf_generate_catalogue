<?php

require_once( 'class.labels_file.php' ); 

/*******************************************************//**
 * Generate an import CSV for SquareUp
 *
 * This class is only for data extraction and file creation.
 * There shouldn't be any UI.
 *
 * ********************************************************/
class WooPOS_Count_file extends labels_file
{
	function __construct( $pref_tablename )
	{
		parent::__construct( null, null, null, null, $pref_tablename );
		//set_time_limit(300);
		
		$this->filename = "SkuList.txt";
		$this->query = "select s.stock_id as stock_id, s.description as description, q.instock as instock, p.price as price from " . TB_PREF . "stock_master s, " . TB_PREF . "ksf_qoh q, " . TB_PREF . "prices p where s.inactive=0 and s.stock_id=q.stock_id and s.stock_id=p.stock_id and p.curr_abrev='CAD' and p.sales_type_id=1 order by s.description";
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
			$this->write_woo_pos_count_line( $row['stock_id'], $row['description'] . " QOH:" . $row['instock'], $row['price'] );
			$rowcount++;
		}
		$this->write_file->close();
		if( $rowcount > 0 )
			$this->email_file( "WooPOS Count File" );
		return $rowcount++;
	}
     	/******************************************************************************//**
        * Generate a line in a CSV to be used to do a Stock Count
        *
        *       This function is being use to create a CSV for WooPOS Count
        *
        * @param string stock_id
        * @param string description
        * @param float price
        *
        * @returns null
        **************************************************************************************/
        function write_woo_pos_count_line( $stock_id, $description, $price )
        {
        	$line  = '"' . $stock_id . '",';
                $line .= '"' . $description . '",';
                $line .= '"' . $price . '"';
                $this->write_file->write_line( $line );
                return null;
        }

}

?>

