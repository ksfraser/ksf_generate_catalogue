<?php

require_once( 'class.labels_file.php' ); 

/*******************************************************//**
 * Generate an import CSV for SquareUp
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
		set_time_limit(300);
		
		$this->filename = "pricebook.csv";
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
			$this->email_file();
		return $rowcount++;
	}


	function email_file( $subject = "Price Book File" )
	{
		parent::email_file( $subject );
	}

}

?>

