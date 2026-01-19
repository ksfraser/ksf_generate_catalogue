<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * WooCommerce Import File Generator
 * 
 * Generates CSV files for importing products into WooCommerce
 * Implements OutputHandlerInterface for plugin-based architecture
 */
class WoocommerceImport extends BaseCatalogueGenerator implements OutputHandlerInterface
{
    /**
     * Mapping table used (optionally) to describe WooCommerce variable/variation relationships.
     *
     * If present, it is expected to contain at least:
     * - parent_stock_id
     * - child_stock_id
     */
    private const WOO_RELATION_TABLE = 'woocommerce_parent_child';

    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->file_count = 0;
        $this->file_base = "woo_catalog";
        $this->file_ext = "csv";
        $this->setFileName();

        // Note: WooCommerce CSV import uses header names for mapping; ordering is not critical.
        // We include Type + Parent so variable/variation relationships can be imported.
        $this->hline = '"ID", "Type", "Parent", "SKU", "Name", "Published", "Is Featured?", "Visibility in catalog", "Short Description", "Description", "Regular price", "Sale price", "Date sale price starts", "Date sale price ends", "Tax status", "Tax class", "In Stock?", "Stock", "Backorders", "Low stock amount", "Sold individually?", "Categories", "Tags", "Shipping class", "Allow customer reviews?", "Sync with Square", "Last Price Change", "Last Detail Change", "Sale Price Last Updated"';
    }

    /**
     * Escape a string for use in a SQL literal.
     */
    protected function sqlEscape(string $value): string
    {
        if (function_exists('db_escape')) {
            return db_escape($value);
        }
        return addslashes($value);
    }

    /**
     * Check whether the Woo relationship table exists.
     */
    protected function hasWooRelationshipTable(): bool
    {
        try {
            $db = $this->getDatabase();
            $table = $db->getTablePrefix() . self::WOO_RELATION_TABLE;
            $sql = "SHOW TABLES LIKE '" . $this->sqlEscape($table) . "'";
            $res = $db->query($sql, 'Failed checking Woo relationship table');
            return $db->fetch($res) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get generator metadata for dynamic discovery
     * 
     * @return array Generator metadata
     */
    public static function getGeneratorMetadata()
    {
        return self::getOutputHandlerMetadata();
    }

    /**
     * Get output handler metadata for dynamic discovery
     * 
     * @return array Handler metadata
     */
    public static function getOutputHandlerMetadata()
    {
        return [
            'name' => 'woocommerce',
            'title' => 'WooCommerce Import',
            'class' => 'WoocommerceImport',
            'description' => 'Generate WooCommerce product import CSV file',
            'method' => 'createWoocommerceImport',
            'category' => 'ecommerce',
            'output_type' => 'csv',
            'version' => '1.0.0',
            'author' => 'KS Fraser',
            'requires_config' => false
        ];
    }

    /**
     * Get the priority/order for this generator
     * 
     * @return int Priority order (20 = medium priority)
     */
    public static function getGeneratorPriority()
    {
        return 20;
    }

    /**
     * Get the priority/order for this output handler
     * 
     * @return int Priority order (20 = medium priority)
     */
    public static function getOutputHandlerPriority()
    {
        return 20;
    }

    /**
     * Check if this output handler is available
     * 
     * @return bool True if handler is available
     */
    public static function isOutputHandlerAvailable()
    {
        return true;
    }

    /**
     * Generate the output file(s)
     * 
     * @return array Result information
     */
    public function generateOutput()
    {
        try {
            $rowcount = $this->createFile();
            
            return [
                'success' => true,
                'rows' => $rowcount,
                'files' => [$this->getFullFilename()],
                'message' => "Successfully generated WooCommerce import with {$rowcount} products"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'rows' => 0,
                'files' => [],
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get configuration schema for this output handler
     * 
     * @return array Configuration schema
     */
    public function getConfigurationSchema()
    {
        return [
            'sort_by' => [
                'label' => 'Sort By',
                'type' => 'select',
                'description' => 'Sort products by price or stock updates',
                'default' => 'price',
                'options' => [
                    'price' => 'Price Updates',
                    'stock' => 'Stock Updates'
                ]
            ]
        ];
    }

    /**
     * Validate that all required configuration is present and valid
     * 
     * @return array Validation result
     */
    public function validateConfiguration()
    {
        $errors = [];
        
        if (!$this->getDatabase()) {
            $errors[] = 'Database connection not available';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get a human-readable status message about this handler's readiness
     * 
     * @return string Status message
     */
    public function getStatus()
    {
        $validation = $this->validateConfiguration();
        
        if (!$validation['valid']) {
            return 'Not ready: ' . implode(', ', $validation['errors']);
        }
        
        return 'Ready to generate WooCommerce import';
    }

    /**
     * Get the full filename with path
     * 
     * @return string Full file path
     */
    protected function getFullFilename()
    {
        $count = $this->file_count > 0 ? "_{$this->file_count}" : '';
        return "{$this->file_base}{$count}.{$this->file_ext}";
    }

    /**
     * Set the SQL query for retrieving WooCommerce catalog data
     */
    public function setQuery()
    {
        $hasRelations = $this->hasWooRelationshipTable();
        $prefix = $this->getDatabase()->getTablePrefix();
        $relTable = $prefix . self::WOO_RELATION_TABLE;

        $this->query = "select 
                    t.woocommerce_id as ID, 
                    " . ($hasRelations
                        ? "CASE WHEN wpc.child_stock_id IS NOT NULL THEN 'variation' WHEN wpp.parent_stock_id IS NOT NULL THEN 'variable' ELSE 'simple' END as type,\n                    IFNULL(wpc.parent_stock_id, '') as parent,"
                        : "'simple' as type,\n                    '' as parent,") . "
                    " . TB_PREF . "stock_master.stock_id as SKU, 
                    a.description as Name, 
                    if( a.inactive, '0', '1') as published,
                    '0' as featured,
                    if( a.inactive, 'hidden', 'visible') as Visible, 
                    a.description, 
                    a.long_description, 
                    a.last_updated as stock_last_updated,
                    p.price as price, 
                    p.last_updated as price_last_updated, 
                    q.price as sale_price,
                    q.last_updated as sale_last_updated,  
                    CURDATE() - INTERVAL 56 DAY as sale_start_date, 
                    CURDATE() - INTERVAL 28 DAY as sale_end_date, 
                    'taxable' as TaxStatus,
                    'Standard' as TaxClass,
                    '1' as instock, 
                    ifnull( c.c_qty, 0 ) as hg_qty, 	
                    '1' as backorder,
                    a.lowstock,  
                    '0' as sold_individually, 
                    a.category, 
                    '' as tags,
                    'parcel' as shipping_class,
                    '1' as allow_customer_reviews, 
                    if( a.inactive, 'no', 'yes')  as sync_with_square 
                from 
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

        if ($hasRelations) {
            // Variation rows: child_stock_id -> parent_stock_id
            // Variable rows: any stock_id present as a parent_stock_id
            $this->query .= "\n                LEFT JOIN (\n                    SELECT parent_stock_id, child_stock_id\n                    FROM {$relTable}\n                ) as wpc ON wpc.child_stock_id = " . TB_PREF . "stock_master.stock_id\n                LEFT JOIN (\n                    SELECT DISTINCT parent_stock_id\n                    FROM {$relTable}\n                ) as wpp ON wpp.parent_stock_id = " . TB_PREF . "stock_master.stock_id\n            ";
        }
                
        if (strncasecmp($this->sort_by, "stock", 5) === 0) {
            $this->query .= "	order by a.last_updated DESC, p.last_updated DESC ";
        } else {
            $this->query .= "	order by p.last_updated DESC, a.last_updated DESC ";
        }
    }

    /**
     * Create the WooCommerce import CSV file
     * 
     * @return int Number of rows processed
     */
    public function createFile()
    {
        $this->setQuery();
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $result = db_query($this->query, "Couldn't grab inventory to export to WooCommerce");

        $rowcount = 0;
        while ($row = db_fetch($result)) {
            $this->processProductRow($row);
            $this->writeProductRow($row);
            $rowcount++;
            
            if ($rowcount >= $this->max_rows_file) {
                $this->startNewFile();
                $rowcount = 0;
            }
        }
        
        $this->write_file->close();
        $rc = $this->file_count * $this->max_rows_file + $rowcount;
        
        if ($rc > 0) {
            $this->emailFile("WooCommerce Catalog");
        }
        
        return $rc;
    }

    /**
     * Process a product row to handle special categories and labels
     * 
     * @param array $row Product row data
     */
    protected function processProductRow(&$row)
    {
        // Clean up HTML entities
        $bad_decode = ["&#039;", ";", "&#150;", "&quot;", "?"];
        $good_decode = ["'", ".", ".", "'", "-"];
        $row['Name'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['Name']));
        $row['description'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['description']));
        $row['long_description'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['long_description']));
        $row['category'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['category']));

        if (strlen($row['description']) > 2) {
            $this->processSpecialCategories($row);
        }

        if (($row['hg_qty'] > 0) && $row['published'] == 0) {
            if (function_exists('display_notification')) {
                display_notification("Product is inactive but has quantity:: " . $row['SKU']);
            }
        }
    }

    /**
     * Process special category prefixes (discontinued, clearance, special order, custom)
     * 
     * @param array $row Product row data
     */
    protected function processSpecialCategories(&$row)
    {
        $firstChar = substr($row['description'], 0, 1);
        
        if ($firstChar === $this->DISCONTINUED_PREFIX) {
            $this->processDiscontinued($row);
        } elseif ($firstChar === $this->SPECIAL_ORDER_PREFIX) {
            $this->processSpecialOrder($row);
        } elseif ($firstChar === $this->CLEARANCE_PREFIX) {
            $this->processClearance($row);
        } elseif ($firstChar === $this->CUSTOM_PREFIX) {
            $this->processCustom($row);
        }
    }

    /**
     * Process discontinued products
     */
    protected function processDiscontinued(&$row)
    {
        $row['long_description'] .= " --" . $this->DISCONTINUED_LABEL;
        $row['description'] = substr($row['description'], 1) . " --" . $this->DISCONTINUED_LABEL;
        $row['Name'] = substr($row['Name'], 1);
        $row["sale_start_date"] = $this->DISCONTINUED_SALE_START_DATE;
        $row["sale_end_date"] = $this->DISCONTINUED_SALE_END_DATE;
        $row['backorder'] = 0;
        $row['featured'] = 1;
        $row['allow_customer_reviews'] = 0;
        
        if ($row['hg_qty'] == 0 && $row['published'] == 1) {
            $row['published'] = 0;
            $row['instock'] = 0;
            if (function_exists('display_notification')) {
                display_notification("Product discontinued and ZERO inventory but is Active :: " . print_r($row, true));
            }
        }
        $row['category'] .= ", " . $this->DISCONTINUED_CATEGORIES;
    }

    /**
     * Process special order products
     */
    protected function processSpecialOrder(&$row)
    {
        $row['long_description'] .= " --SPECIAL ORDER";
        $row['description'] = substr($row['description'], 1) . " --" . $this->SPECIAL_ORDER_LABEL;
        $row['Name'] = substr($row['Name'], 1);
        $row['category'] .= ", " . $this->SPECIAL_ORDER_CATEGORIES;
    }

    /**
     * Process clearance products
     */
    protected function processClearance(&$row)
    {
        $row['description'] = substr($row['description'], 1) . " --" . $this->CLEARANCE_LABEL;
        $row['long_description'] .= " --" . $this->CLEARANCE_LABEL;
        $row['Name'] = substr($row['Name'], 1);
        $row["sale_start_date"] = $this->CLEARANCE_SALE_START_DATE;
        $row["sale_end_date"] = $this->CLEARANCE_SALE_END_DATE;
        $row['backorder'] = 0;
        $row['featured'] = 1;
        $row['allow_customer_reviews'] = 0;
        
        if ($row['hg_qty'] == 0 && $row['published'] == 1) {
            $row['published'] = 0;
            $row['instock'] = 0;
            if (function_exists('display_notification')) {
                display_notification("Product clearance and ZERO inventory but is Active :: " . print_r($row, true));
            }
        }
        $row['category'] = $this->CLEARANCE_CATEGORIES . ", " . $row['category'];
    }

    /**
     * Process custom products
     */
    protected function processCustom(&$row)
    {
        $row['long_description'] .= " --" . $this->CUSTOM_LABEL;
        $row['Name'] = substr($row['Name'], 1);
        $row['description'] = substr($row['description'], 1) . " --" . $this->CUSTOM_LABEL;
        $row['category'] .= ", " . $this->CUSTOM_CATEGORIES;
    }

    /**
     * Write a product row to the CSV file
     * 
     * @param array $row Product row data
     */
    protected function writeProductRow($row)
    {
        $this->write_file->write_array_to_csv([
            $row["ID"],
            $row['type'] ?? 'simple',
            $row['parent'] ?? '',
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
            $row['hg_qty'],
            $row["backorder"],
            $row["lowstock"],
            $row["sold_individually"],
            $row['category'],
            $row['tags'],
            $row['shipping_class'],
            $row['allow_customer_reviews'],
            $row["sync_with_square"],
            $row["price_last_updated"],
            $row["stock_last_updated"],
            $row["sale_last_updated"],
        ]);
    }

    /**
     * Start a new output file when max rows is reached
     */
    protected function startNewFile()
    {
        $this->write_file->close();
        $this->file_count++;
        $this->setFileName();
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);
        
        if (function_exists('display_notification')) {
            display_notification("Another file " . $this->filename);
        }
    }
}
