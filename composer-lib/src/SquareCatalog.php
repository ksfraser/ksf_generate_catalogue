<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Square Catalog Generator
 * 
 * Generates CSV files for importing products into Square
 */
class SquareCatalog extends PricebookFile
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = "square_catalog.csv";
        $this->hline = '"Token", "Item Name", "Description", "Category", "SKU", "Variation Name", "Price", "Enabled Fraser Highland Shoppe", "Current Quantity Fraser Highland Shoppe", "New Quantity Fraser Highland Shoppe", "Stock Alert Enabled Fraser Highland Shoppe", "Stock Alert Count Fraser Highland Shoppe", "Price Fraser Highland Shoppe", "Enabled KSF", "Current Quantity KSF", "New Quantity KSF", "Stock Alert Enabled KSF", "Stock Alert Count KSF", "Price KSF", "Tax - GST (5%)", "Square Online Item Visibility", "Weight (kg)", "Sale Start Date", "Sale End Date", "Sale Price", "Item Type", "Sync With Square", "Delivery Enabled", "Shipping Enabled", "Pickup Enabled", "SEO Title", "SEO Description", "Permalink"';
    }

    /**
     * Get generator metadata for dynamic discovery
     * 
     * @return array Generator metadata
     */
    public static function getGeneratorMetadata()
    {
        return [
            'name' => 'square',
            'title' => 'Square Catalog',
            'class' => 'SquareCatalog',
            'description' => 'Generate Square catalog import CSV file for POS integration',
            'method' => 'createSquareCatalog',
            'category' => 'pos',
            'version' => '1.0.0',
            'author' => 'KS Fraser'
        ];
    }

    /**
     * Get the priority/order for this generator
     * 
     * @return int Priority order (20 = medium priority)
     */
    public static function getGeneratorPriority()
    {
        return 25; // Medium priority
    }

    /**
     * Set the SQL query for retrieving Square catalog data
     */
    public function setQuery()
    {
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
                order by a.category, a.description";
    }

    /**
     * Create the Square catalog CSV file
     * 
     * @return int Number of rows processed
     */
    public function createFile()
    {
        $this->setQuery();
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $result = db_query($this->query, "Couldn't grab inventory to export to Square");

        $rowcount = 0;
        while ($row = db_fetch($result)) {
            $this->processSquareRow($row);
            $this->writeSquareRow($row);
            $rowcount++;
        }
        
        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile("Square Catalog");
        }
        return $rowcount;
    }

    /**
     * Process a Square catalog row
     * 
     * @param array $row Product row data
     */
    protected function processSquareRow(&$row)
    {
        // Clean up HTML entities
        $bad_decode = ["&#039;", ";", "&#150;", "&quot;", "?"];
        $good_decode = ["'", ".", ".", "'", "-"];
        $row['description'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['description']));
        $row['long_description'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['long_description']));
        $row['category'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['category']));

        // Set default values if empty
        if (empty($row['long_description'])) {
            $row['long_description'] = $row['description'];
        }
        
        // Format price
        if (!empty($row['price'])) {
            $row['price'] = number_format($row['price'], 2, '.', '');
        }
        if (!empty($row['sale_price'])) {
            $row['sale_price'] = number_format($row['sale_price'], 2, '.', '');
        }
    }

    /**
     * Write a Square catalog row to the CSV file
     * 
     * @param array $row Product row data
     */
    protected function writeSquareRow($row)
    {
        $this->write_file->write_array_to_csv([
            $row['token'] ?? '',
            $row['description'] ?? '',
            $row['long_description'] ?? '',
            $row['category'] ?? '',
            $row['stock_id'] ?? '',
            'Regular', // Variation Name
            $row['price'] ?? '0.00',
            $row['enabled_hg'] ?? 'Y',
            $row['hg_qty'] ?? '0',
            $row['hg_qty'] ?? '0', // New Quantity same as current
            'Y', // Stock Alert Enabled Fraser Highland Shoppe
            '1', // Stock Alert Count Fraser Highland Shoppe
            $row['price'] ?? '0.00', // Price Fraser Highland Shoppe
            'N', // Enabled KSF (dev environment)
            '', // Current Quantity KSF
            '', // New Quantity KSF
            '', // Stock Alert Enabled KSF
            '', // Stock Alert Count KSF
            '', // Price KSF
            'Y', // Tax - GST (5%)
            $row['Square_Online_Item_Visibility'] ?? 'Y',
            $row['weight'] ?? '0.00',
            $row['sale_start_date'] ?? '',
            $row['sale_end_date'] ?? '',
            $row['sale_price'] ?? '',
            $row['Item_Type'] ?? 'Physical',
            $row['sync_with_square'] ?? 'Y',
            'Y', // Delivery Enabled
            'Y', // Shipping Enabled
            'Y', // Pickup Enabled
            '', // SEO Title
            '', // SEO Description
            '' // Permalink
        ]);
    }
}
