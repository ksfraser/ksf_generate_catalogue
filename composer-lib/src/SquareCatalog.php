<?php

namespace Ksfraser\Frontaccounting\GenCat;

use Exception;

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
        // Updated header format to match Square's new requirements (August 2025)
        // Double quotes are REQUIRED - they ensure proper CSV parsing when field names contain spaces, 
        // special characters, or when importing into Square's system
        $this->hline = '"Token","Item Name","Variation Name","SKU","Description","Categories","Reporting Category","SEO Title","SEO Description","Permalink","GTIN","Square Online Item Visibility","Item Type","Weight (kg)","Social Media Link Title","Social Media Link Description","Shipping Enabled","Self-serve Ordering Enabled","Delivery Enabled","Pickup Enabled","Price","Online Sale Price","Archived","Sellable","Contains Alcohol","Stockable","Skip Detail Screen in POS","Option Name 1","Option Value 1","Enabled Fraser Highland Shoppe","Current Quantity Fraser Highland Shoppe","New Quantity Fraser Highland Shoppe","Stock Alert Enabled Fraser Highland Shoppe","Stock Alert Count Fraser Highland Shoppe","Price Fraser Highland Shoppe","Enabled KSF","Current Quantity KSF","New Quantity KSF","Stock Alert Enabled KSF","Stock Alert Count KSF","Price KSF","Tax - Future (5%)","Tax - GST (5%)"';
    }

    /**
     * Get a preference value from the database
     * 
     * @param string $pref_name Preference name
     * @param mixed $default Default value if preference not found
     * @return mixed Preference value or default
     */
    protected function getPreference($pref_name, $default = null)
    {
        $database = $this->getDatabase();

        $tb_pref = $database->getTablePrefix();
        $query = "SELECT pref_value FROM {$tb_pref}{$this->prefs_tablename} WHERE pref_name = '" . $pref_name . "'";
        
        try {
            $result = $database->query($query, "Could not retrieve preference: " . $pref_name);
            $row = $database->fetch($result);
            return $row ? $row['pref_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Get configuration options for this generator
     * 
     * @return array Configuration options
     */
    public function getConfiguration()
    {
        return [
            'online_sale_pricebook_id' => [
                'label' => 'Online Sale Pricebook',
                'type' => 'pricebook_selector',
                'description' => 'Select the pricebook to use for online sale prices'
            ],
            'use_sale_prices' => [
                'label' => 'Use Sale Prices',
                'type' => 'yes_no',
                'description' => 'Include sale prices in the export'
            ],
            'square_export_text' => [
                'label' => 'Additional Export Text',
                'type' => 'text',
                'description' => 'Additional text to include in Square export'
            ]
        ];
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
     * Set the SQL query for retrieving Square catalog data with enhanced fields
     */
    public function setQuery()
    {
        // Get online sale price book ID from preferences
        $online_pricebook_id = $this->getPreference('online_sale_pricebook_id', 2);
        
        $this->query = "SELECT 
                    IFNULL(t.square_token, '') as token,
                    " . TB_PREF . "stock_master.stock_id, 
                    a.description as item_name, 
                    a.long_description as description, 
                    a.category as categories, 
                    IFNULL(p_prefs.default_reporting_category, 'General') as reporting_category,
                    '' as seo_title,
                    '' as seo_description, 
                    '' as permalink,
                    '' as gtin,
                    IF(a.inactive, 'N', 'Y') as square_online_item_visibility,
                    'Physical' as item_type,
                    '0.00' as weight_kg,
                    IFNULL(sm.social_media_title, '') as social_media_link_title,
                    IFNULL(sm.social_media_description, '') as social_media_link_description,
                    'Y' as shipping_enabled,
                    'Y' as self_serve_ordering_enabled,
                    'Y' as delivery_enabled,
                    'Y' as pickup_enabled,
                    IFNULL(p.price, 0.00) as price,
                    IFNULL(online_p.price, IFNULL(p.price, 0.00)) as online_sale_price,
                    IF(a.inactive, 'Y', 'N') as archived,
                    IF(a.inactive, 'N', 'Y') as sellable,
                    IFNULL(alc.contains_alcohol, 0) as contains_alcohol,
                    'Y' as stockable,
                    'N' as skip_detail_screen_in_pos,
                    '' as option_name_1,
                    '' as option_value_1,
                    a.lowstock,  
                    IFNULL(c.c_qty, 0) as hg_qty, 	
                    IFNULL(b.b_qty, 0) as hold_qty,
                    IF(a.inactive, 'N', 'Y') as enabled_hg,
                    q.price as registered
                FROM " . TB_PREF . "stock_master 
                
                -- Main stock information
                LEFT JOIN ( 
                    SELECT 
                        s.stock_id, 
                        s.description,	 
                        s.long_description, 
                        s.inactive,
                        c.description as category, 	
                        r.reorder_level as lowstock
                    FROM " . TB_PREF . "stock_master s
                    LEFT JOIN " . TB_PREF . "stock_category c ON s.category_id = c.category_id
                    LEFT JOIN " . TB_PREF . "loc_stock r ON r.stock_id = s.stock_id AND r.loc_code = '" . $this->PRIMARY_LOC . "'
                ) as a ON a.stock_id = " . TB_PREF . "stock_master.stock_id 
                
                -- Regular price (sales_type_id = 1)
                LEFT JOIN ( 
                    SELECT p.stock_id, p.price 
                    FROM " . TB_PREF . "prices p 
                    WHERE p.sales_type_id = 1 AND p.curr_abrev = 'CAD' 
                ) as p ON " . TB_PREF . "stock_master.stock_id = p.stock_id
                
                -- Online sale price (configurable sales_type_id)
                LEFT JOIN ( 
                    SELECT p.stock_id, p.price 
                    FROM " . TB_PREF . "prices p 
                    WHERE p.sales_type_id = " . (int)$online_pricebook_id . " AND p.curr_abrev = 'CAD' 
                ) as online_p ON " . TB_PREF . "stock_master.stock_id = online_p.stock_id
                
                -- Square tokens
                LEFT JOIN ( 
                    SELECT t.stock_id, t.square_token
                    FROM " . TB_PREF . "square_tokens t 
                ) as t ON " . TB_PREF . "stock_master.stock_id = t.stock_id
                
                -- Registered price (sales_type_id = 3)
                LEFT JOIN ( 
                    SELECT q.stock_id, q.price 
                    FROM " . TB_PREF . "prices q 
                    WHERE q.sales_type_id = 3 AND q.curr_abrev = 'CAD' 
                ) as q ON " . TB_PREF . "stock_master.stock_id = q.stock_id
                
                -- Stock quantities - Primary location
                LEFT JOIN ( 
                    SELECT c.stock_id, c.loc_code as c_loc_code, SUM(c.qty) as c_qty 
                    FROM " . TB_PREF . "stock_moves c 
                    WHERE c.loc_code = '" . $this->PRIMARY_LOC . "' 
                    GROUP BY c.stock_id 
                ) as c ON " . TB_PREF . "stock_master.stock_id = c.stock_id
                
                -- Stock quantities - Secondary location
                LEFT JOIN ( 
                    SELECT b.stock_id, b.loc_code as b_loc_code, SUM(b.qty) as b_qty 
                    FROM " . TB_PREF . "stock_moves b 
                    WHERE b.loc_code = '" . $this->SECONDARY_LOC . "' 
                    GROUP BY b.stock_id 
                ) as b ON " . TB_PREF . "stock_master.stock_id = b.stock_id
                
                -- Social media fields (new table)
                LEFT JOIN " . TB_PREF . "social_media_fields sm ON sm.stock_id = " . TB_PREF . "stock_master.stock_id
                
                -- Contains alcohol flag (new table)  
                LEFT JOIN " . TB_PREF . "contains_alcohol alc ON alc.stock_id = " . TB_PREF . "stock_master.stock_id
                
                -- Preferences for default values
                LEFT JOIN (
                    SELECT 
                        'General' as default_reporting_category
                ) as p_prefs ON 1=1
                
                ORDER BY a.category, a.description";
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

        $result = $this->getDatabase()->query($this->query, "Couldn't grab inventory to export to Square");

        $rowcount = 0;
        while ($row = $this->getDatabase()->fetch($result)) {
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
        
        $row['item_name'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['item_name'] ?? ''));
        $row['description'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['description'] ?? ''));
        $row['categories'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['categories'] ?? ''));

        // Set default values if empty
        if (empty($row['description'])) {
            $row['description'] = $row['item_name'];
        }
        
        // Format prices
        $row['price'] = !empty($row['price']) ? number_format($row['price'], 2, '.', '') : '0.00';
        $row['online_sale_price'] = !empty($row['online_sale_price']) ? number_format($row['online_sale_price'], 2, '.', '') : $row['price'];
        
        // Format boolean values
        $row['contains_alcohol'] = ($row['contains_alcohol'] == 1) ? 'Y' : 'N';
        
        // Set default quantities if null
        $row['hg_qty'] = $row['hg_qty'] ?? '0';
        $row['hold_qty'] = $row['hold_qty'] ?? '0';
    }

    /**
     * Write a Square catalog row to the CSV file using new format
     * 
     * @param array $row Product row data
     */
    protected function writeSquareRow($row)
    {
        $this->write_file->write_array_to_csv([
            $row['token'] ?? '',                                    // Token
            $row['item_name'] ?? '',                               // Item Name
            'Regular',                                             // Variation Name
            $row['stock_id'] ?? '',                                // SKU
            $row['description'] ?? '',                             // Description
            $row['categories'] ?? '',                              // Categories
            $row['reporting_category'] ?? 'General',               // Reporting Category
            $row['seo_title'] ?? '',                               // SEO Title
            $row['seo_description'] ?? '',                         // SEO Description
            $row['permalink'] ?? '',                               // Permalink
            $row['gtin'] ?? '',                                    // GTIN
            $row['square_online_item_visibility'] ?? 'Y',          // Square Online Item Visibility
            $row['item_type'] ?? 'Physical',                       // Item Type
            $row['weight_kg'] ?? '0.00',                           // Weight (kg)
            $row['social_media_link_title'] ?? '',                 // Social Media Link Title
            $row['social_media_link_description'] ?? '',           // Social Media Link Description
            $row['shipping_enabled'] ?? 'Y',                       // Shipping Enabled
            $row['self_serve_ordering_enabled'] ?? 'Y',            // Self-serve Ordering Enabled
            $row['delivery_enabled'] ?? 'Y',                       // Delivery Enabled
            $row['pickup_enabled'] ?? 'Y',                         // Pickup Enabled
            $row['price'] ?? '0.00',                               // Price
            $row['online_sale_price'] ?? ($row['price'] ?? '0.00'), // Online Sale Price
            $row['archived'] ?? 'N',                               // Archived
            $row['sellable'] ?? 'Y',                               // Sellable
            $row['contains_alcohol'],                              // Contains Alcohol
            $row['stockable'] ?? 'Y',                              // Stockable
            $row['skip_detail_screen_in_pos'] ?? 'N',              // Skip Detail Screen in POS
            $row['option_name_1'] ?? '',                           // Option Name 1 (future use)
            $row['option_value_1'] ?? '',                          // Option Value 1 (future use)
            $row['enabled_hg'] ?? 'Y',                             // Enabled Fraser Highland Shoppe
            $row['hg_qty'] ?? '0',                                 // Current Quantity Fraser Highland Shoppe
            $row['hg_qty'] ?? '0',                                 // New Quantity Fraser Highland Shoppe
            'Y',                                                   // Stock Alert Enabled Fraser Highland Shoppe
            '1',                                                   // Stock Alert Count Fraser Highland Shoppe
            $row['price'] ?? '0.00',                               // Price Fraser Highland Shoppe
            'N',                                                   // Enabled KSF (dev environment)
            $row['hold_qty'] ?? '',                                // Current Quantity KSF
            $row['hold_qty'] ?? '',                                // New Quantity KSF
            '',                                                    // Stock Alert Enabled KSF
            '',                                                    // Stock Alert Count KSF
            '',                                                    // Price KSF
            'N',                                                   // Tax - Future (5%)
            'Y'                                                    // Tax - GST (5%)
        ]);
    }
}
