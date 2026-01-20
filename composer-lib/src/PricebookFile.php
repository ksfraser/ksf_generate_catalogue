<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Pricebook File Generator
 * 
 * Generates CSV files for price lists/catalogues
 */
class PricebookFile extends LabelsFile
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = "pricebook.csv";
        $this->query = "select s.stock_id as stock_id, s.description as description, q.instock as instock, c.description as category, p.price as price from " . TB_PREF . "stock_master s, " . TB_PREF . "ksf_qoh q, " . TB_PREF . "stock_category c, " . TB_PREF . "prices p where s.inactive=0 and s.stock_id=q.stock_id and s.category_id = c.category_id and s.stock_id=p.stock_id and p.curr_abrev='CAD' and p.sales_type_id=1 order by c.description, s.description";
    }

    /**
     * Get generator metadata for dynamic discovery
     * 
     * @return array Generator metadata
     */
    public static function getGeneratorMetadata()
    {
        return [
            'name' => 'pricebook',
            'title' => 'Pricebook File',
            'class' => 'PricebookFile',
            'description' => 'Generate pricebook CSV file for retail pricing and catalogues',
            'method' => 'createPricebookFile',
            'category' => 'catalogue',
            'version' => '1.0.0',
            'author' => 'KS Fraser'
        ];
    }

    /**
     * Get the priority/order for this generator
     * 
     * @return int Priority order (10 = high priority, displayed first)
     */
    public static function getGeneratorPriority()
    {
        return 10; // High priority - pricebooks are commonly used
    }

    /**
     * Create the pricebook CSV file
     * 
     * @return int Number of rows processed
     */
    public function createFile()
    {
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $db = $this->getDatabase();
        $result = $db->query($this->query, "Couldn't grab inventory to export pricebook");
        
        $rowcount = 0;
        while ($row = $db->fetch($result)) {
            // For pricebooks, we typically want to show the catalogue format
            // Hardcoding false for thermal printer as pricebooks are more like printed catalogues
            $this->writeSkuLabelsLine(
                $row['stock_id'], 
                $row['category'], 
                $row['description'], 
                $row['price'], 
                false  // Always use standard format for pricebooks
            );
            $rowcount++;
        }
        
        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile("Price Book File");
        }
        return $rowcount;
    }
}
