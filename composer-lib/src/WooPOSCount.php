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
        $this->filename = 'woopos_count.csv';
        $this->hline = '"SKU","Name","Current Stock","New Stock","Category"';
    }

    public static function getGeneratorMetadata()
    {
        return self::getOutputHandlerMetadata();
    }

    public static function getOutputHandlerMetadata()
    {
        return [
            'name' => 'woopos',
            'title' => 'WooPOS Count',
            'class' => 'WooPOSCount',
            'description' => 'Export CSV for WooPOS inventory counts',
            'method' => 'createWooPOSCount',
            'category' => 'ecommerce',
            'output_type' => 'csv',
            'version' => '1.0.0',
            'author' => 'KS Fraser',
            'requires_config' => false,
        ];
    }

    public static function getGeneratorPriority()
    {
        return 30;
    }

    private function getTablePrefix(): string
    {
        $prefix = '';
        try {
            $prefix = (string) $this->getDatabase()->getTablePrefix();
        } catch (\Throwable $e) {
            $prefix = '';
        }

        if ($prefix === '' && defined('TB_PREF')) {
            $prefix = TB_PREF;
        }

        return $prefix;
    }

    /**
     * Set the SQL query for retrieving WooPOS count data
     */
    public function setQuery()
    {
        $prefix = $this->getTablePrefix();

        $this->query = "SELECT
            sm.stock_id as SKU,
            sm.description as Name,
            COALESCE(qoh.qoh, 0) as Current_Stock,
            COALESCE(qoh.qoh, 0) as New_Stock,
            sc.description as Category
        FROM {$prefix}stock_master sm
        LEFT JOIN (
            SELECT stock_id, SUM(qty) AS qoh
            FROM {$prefix}stock_moves
            WHERE loc_code = '" . $this->PRIMARY_LOC . "'
            GROUP BY stock_id
        ) qoh ON qoh.stock_id = sm.stock_id
        LEFT JOIN {$prefix}stock_category sc ON sm.category_id = sc.category_id
        WHERE sm.inactive = 0
        ORDER BY sc.description, sm.description";
    }

    /**
     * Create the WooPOS count CSV file
     *
     * @return int Number of rows processed
     */
    public function createFile()
    {
        $this->setQuery();
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $db = $this->getDatabase();
        $result = $db->query($this->query, "Couldn't grab inventory to export WooPOS count");

        $rowcount = 0;
        while ($row = $db->fetch($result)) {
            $this->processWooPOSRow($row);
            $this->writeWooPOSRow($row);
            $rowcount++;
        }

        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile('WooPOS Count File');
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
        $bad_decode = ["&#039;", ";", "&#150;", "&quot;", "?"];
        $good_decode = ["'", ".", ".", "'", "-"];
        $row['Name'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['Name'] ?? ''));
        $row['Category'] = str_replace($bad_decode, $good_decode, html_entity_decode($row['Category'] ?? ''));

        $row['Current_Stock'] = max(0, intval($row['Current_Stock'] ?? 0));
        $row['New_Stock'] = max(0, intval($row['New_Stock'] ?? 0));
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
            $row['Category'] ?? '',
        ]);
    }
}
