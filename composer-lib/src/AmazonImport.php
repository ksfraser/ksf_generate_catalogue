<?php

/**
 * Example Custom Generator - Amazon Import
 * 
 * This is an example of how to create a new generator that will be
 * automatically discovered by the system.
 * 
 * @package   Ksfraser\Frontaccounting\GenCat
 * @author    KS Fraser <kevin@ksfraser.com>
 * @copyright 2025 KS Fraser
 * @license   GPL-3.0-or-later
 * @version   1.0.0
 * @since     1.0.0
 */

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Amazon Import File Generator
 * 
 * Example generator for creating Amazon import CSV files.
 * This class demonstrates how to create a new generator that will be
 * automatically discovered and registered by the system.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class AmazonImport extends BaseCatalogueGenerator
{
    /**
     * Constructor
     * 
     * @param string $prefs_tablename Preferences table name
     */
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->file_count = 0;
        $this->file_base = "amazon_catalog";
        $this->file_ext = "csv";
        $this->setFileName();

        // Amazon-specific CSV header format
        $this->hline = '"Product Type","Seller SKU","Product Name","Product Description","Listing Description",' .
                      '"Manufacturer","Brand Name","Category","Product Category","Update Delete","Standard Price",' .
                      '"Sale Price","Sale Start Date","Sale End Date","Currency","Quantity","Condition Type",' .
                      '"Condition Note","ASIN","Product Tax Code","Launch Date","Restock Date","Fulfillment Center ID"';
    }

    /**
     * Get generator metadata for dynamic discovery
     * 
     * @return array Generator metadata
     */
    public static function getGeneratorMetadata()
    {
        return [
            'name' => 'amazon',
            'title' => 'Amazon Import',
            'class' => 'AmazonImport',
            'description' => 'Generate Amazon marketplace import CSV file for product listings',
            'method' => 'createAmazonImport',
            'category' => 'marketplace',
            'version' => '1.0.0',
            'author' => 'KS Fraser'
        ];
    }

    /**
     * Get the priority/order for this generator
     * 
     * @return int Priority order (40 = medium-low priority)
     */
    public static function getGeneratorPriority()
    {
        return 40; // Medium-low priority - specialized marketplace
    }

    /**
     * Check if this generator is available/enabled
     * 
     * This could check for Amazon API credentials, specific configuration, etc.
     * 
     * @return bool True if generator is available, false otherwise
     */
    public static function isGeneratorAvailable()
    {
        // Example: Could check if Amazon integration is configured
        // For now, always return true for demonstration
        return true;
        
        // Example of conditional availability:
        // return !empty(getenv('AMAZON_API_KEY')) && !empty(getenv('AMAZON_SECRET'));
    }

    /**
     * Set the SQL query for retrieving Amazon catalog data
     */
    public function setQuery()
    {
        $this->query = "SELECT 
                    'Product' as product_type,
                    sm.stock_id as seller_sku,
                    sm.description as product_name,
                    COALESCE(sm.long_description, sm.description) as product_description,
                    COALESCE(sm.long_description, sm.description) as listing_description,
                    '' as manufacturer,
                    '' as brand_name,
                    sc.description as category,
                    sc.description as product_category,
                    '' as update_delete,
                    COALESCE(p.price, 0) as standard_price,
                    CASE 
                        WHEN sp.price IS NOT NULL AND sp.price > 0 THEN sp.price
                        ELSE ''
                    END as sale_price,
                    CASE 
                        WHEN sp.price IS NOT NULL THEN '" . ($this->SALE_START_DATE ?? '') . "'
                        ELSE ''
                    END as sale_start_date,
                    CASE 
                        WHEN sp.price IS NOT NULL THEN '" . ($this->SALE_END_DATE ?? '') . "'
                        ELSE ''
                    END as sale_end_date,
                    'CAD' as currency,
                    COALESCE(SUM(stm.qty), 0) as quantity,
                    'New' as condition_type,
                    '' as condition_note,
                    '' as asin,
                    '' as product_tax_code,
                    DATE(NOW()) as launch_date,
                    DATE(DATE_ADD(NOW(), INTERVAL 30 DAY)) as restock_date,
                    '' as fulfillment_center_id
                FROM " . TB_PREF . "stock_master sm
                LEFT JOIN " . TB_PREF . "stock_category sc ON sm.category_id = sc.category_id
                LEFT JOIN " . TB_PREF . "prices p ON sm.stock_id = p.stock_id 
                    AND p.sales_type_id = '" . ($this->RETAIL_type ?? 'Retail') . "'
                LEFT JOIN " . TB_PREF . "prices sp ON sm.stock_id = sp.stock_id 
                    AND sp.sales_type_id = '" . ($this->SALEPRICE_type ?? 'Sale') . "'
                LEFT JOIN " . TB_PREF . "stock_moves stm ON sm.stock_id = stm.stock_id
                    AND stm.loc_code IN ('" . ($this->PRIMARY_LOC ?? 'HG') . "', '" . ($this->SECONDARY_LOC ?? 'HOLD') . "')
                WHERE sm.inactive = 0
                GROUP BY sm.stock_id, sm.description, sm.long_description, sc.description, p.price, sp.price
                ORDER BY sc.description, sm.description";
    }

    /**
     * Create the Amazon import CSV file
     * 
     * @return int Number of rows processed
     */
    public function createFile()
    {
        $this->setQuery();
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $database = $this->getDatabase();
        $result = $database->query($this->query);
        
        $rowcount = 0;
        while ($row = $database->fetch($result)) {
            $line = '"' . implode('","', [
                $row['product_type'],
                $row['seller_sku'],
                str_replace('"', '""', $row['product_name']),
                str_replace('"', '""', $row['product_description']),
                str_replace('"', '""', $row['listing_description']),
                $row['manufacturer'],
                $row['brand_name'],
                $row['category'],
                $row['product_category'],
                $row['update_delete'],
                number_format($row['standard_price'], 2, '.', ''),
                $row['sale_price'] ? number_format($row['sale_price'], 2, '.', '') : '',
                $row['sale_start_date'],
                $row['sale_end_date'],
                $row['currency'],
                $row['quantity'],
                $row['condition_type'],
                $row['condition_note'],
                $row['asin'],
                $row['product_tax_code'],
                $row['launch_date'],
                $row['restock_date'],
                $row['fulfillment_center_id']
            ]) . '"';
            
            $this->write_file->write_line($line);
            $rowcount++;
        }
        
        $this->write_file->close();
        
        if ($rowcount > 0) {
            $this->emailFile("Amazon Import File - $rowcount products");
        }
        
        return $rowcount;
    }
}
