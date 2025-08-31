<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * WooPOS Count File Generator
 * 
 * Generates CSV files for WooCommerce POS inventory counts
 */
class WooPOSCount extends BaseCatalogueGenerator
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = "woopos_count.csv";
        $this->hline = '"SKU", "Name", "Current Stock", "New Stock", "Category"';
        $this->setQuery();
    }

    /**
     * Get generator metadata for dynamic discovery
     * 
     * @return array Generator metadata
     */
    public static function getGeneratorMetadata()
    {
        return [
            'name' => 'woopos',
            'title' => 'WooPOS Count',
            'class' => 'WooPOSCount',
            'description' => 'Generate WooCommerce POS inventory count CSV file',
            'method' => 'createWooPOSCount',
            'category' => 'inventory',
            'version' => '1.0.0',
            'author' => 'KS Fraser'
        ];
    }

    /**
     * Get the priority/order for this generator
     * 
     * @return int Priority order (30 = lower priority)
     */
    public static function getGeneratorPriority()
    {
        return 30; // Lower priority - specialized use case
    }

    /**
     * Set the SQL query for retrieving WooPOS count data
     */
    public function setQuery()
    {
        $this->query = "SELECT 
            sm.stock_id as SKU,
            sm.description as Name,
            COALESCE(SUM(stm.qty), 0) as Current_Stock,
            COALESCE(SUM(stm.qty), 0) as New_Stock,
            sc.description as Category
        FROM " . TB_PREF . "stock_master sm
        LEFT JOIN " . TB_PREF . "stock_moves stm ON sm.stock_id = stm.stock_id 
            AND stm.loc_code = '" . $this->PRIMARY_LOC . "'
        LEFT JOIN " . TB_PREF . "stock_category sc ON sm.category_id = sc.category_id
        WHERE sm.inactive = 0
        GROUP BY sm.stock_id, sm.description, sc.description
        ORDER BY sc.description, sm.description";
    }

    /**
     * Create the WooPOS count CSV file
     * 
     * @return int Number of rows processed
     */
    public function createFile()
    {
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $result = db_query($this->query, "Couldn't grab inventory to export WooPOS count");

        $rowcount = 0;
        while ($row = db_fetch($result)) {
            $this->processWooPOSRow($row);
            $this->writeWooPOSRow($row);
            $rowcount++;
        }
        
        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile("WooPOS Count File");
        }
        return $rowcount;
    }

    /**
     * Process a WooPOS count row
     * 
     * @param array $row Product row data
     */
    protected function processWooPOSRow(&$row)
    {
        // Clean up HTML entities
        $bad_decode = ["&#039;", ";", "&#150;", "&quot;", "?"];
        $good_decode = ["'", ".", ".", "'", "-"];
        $row['Name'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['Name']));
        $row['Category'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['Category']));

        // Ensure stock quantities are numeric
        $row['Current_Stock'] = max(0, intval($row['Current_Stock']));
        $row['New_Stock'] = max(0, intval($row['New_Stock']));
    }

    /**
     * Write a WooPOS count row to the CSV file
     * 
     * @param array $row Product row data
     */
    protected function writeWooPOSRow($row)
    {
        $this->write_file->write_array_to_csv([
            $row['SKU'] ?? '',
            $row['Name'] ?? '',
            $row['Current_Stock'] ?? '0',
            $row['New_Stock'] ?? '0',
            $row['Category'] ?? ''
        ]);
    }
}
