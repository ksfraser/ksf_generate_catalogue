<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Labels File Generator
 * 
 * Generates CSV files for printing product labels
 */
class LabelsFile extends BaseCatalogueGenerator
{
    protected $last_delivery_no;

    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->delivery_no = 0;
        $this->filename = "labels.csv";
        $this->hline = '"stock_id", "Title", "barcode", "category", "price"';
        $this->query = "select s.stock_id as stock_id, s.description as description, q.instock as instock, c.description as category, 0 as price from " . TB_PREF . "stock_master s, " . TB_PREF . "ksf_qoh q, " . TB_PREF . "stock_category c where s.inactive=0 and s.stock_id=q.stock_id and s.category_id = c.category_id order by c.description, s.description";
    }

    /**
     * Get generator metadata for dynamic discovery
     * 
     * @return array Generator metadata
     */
    public static function getGeneratorMetadata()
    {
        return [
            'name' => 'labels',
            'title' => 'Labels File',
            'class' => 'LabelsFile',
            'description' => 'Generate product labels CSV file for printing',
            'method' => 'createLabelsFile',
            'category' => 'printing',
            'version' => '1.0.0',
            'author' => 'KS Fraser'
        ];
    }

    /**
     * Get the priority/order for this generator
     * 
     * @return int Priority order (50 = lower priority, usually used separately)
     */
    public static function getGeneratorPriority()
    {
        return 50; // Lower priority - usually used separately from catalogue generation
    }

    /**
     * Create the labels CSV file
     * 
     * @return int Number of rows processed
     */
    public function createFile()
    {
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $result = db_query($this->query, "Couldn't grab inventory to export labels");

        $rowcount = 0;
        while ($row = db_fetch($result)) {
            $num = $row['instock'];
            // If we have 6 items instock, we need 6 labels to print so we can put on product
            for ($num; $num > 0; $num--) {
                $this->writeSkuLabelsLine(
                    $row['stock_id'], 
                    $row['category'], 
                    $row['description'], 
                    $row['price'], 
                    $this->thermal_printer
                );
                $rowcount++;
            }
        }
        
        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile("Labels File");
        }
        return $rowcount;	
    }

    /**
     * Create SKU labels from a purchase order
     * 
     * @param int $delivery_no Delivery number
     * @return int Number of rows processed
     */
    public function createSkuLabelsFromPO($delivery_no)
    {
        $this->delivery_no = $delivery_no;
        $this->filename = "delivery_" . $this->delivery_no . "_labels.csv";
        
        $this->prepWriteFile();
        if ($this->include_header) {
            $this->write_file->write_line($this->hline);
        }

        $this->setPOQuery();
        $result = db_query($this->query, "Couldn't grab purchase order items for labels");

        $rowcount = 0;
        while ($row = db_fetch($result)) {
            $quantity = $row['quantity_ordered'];
            for ($i = 0; $i < $quantity; $i++) {
                $this->writeSkuLabelsLine(
                    $row['stock_id'], 
                    $row['category'], 
                    $row['description'], 
                    $row['price'], 
                    $this->thermal_printer
                );
                $rowcount++;
            }
        }
        
        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile("PO Labels - Delivery " . $delivery_no);
        }
        return $rowcount;
    }

    /**
     * Create SKU labels from a single stock ID
     * 
     * @return int Number of rows processed
     */
    public function createSkuLabelsFromSku()
    {
        if (empty($this->stock_id)) {
            throw new \Exception("Stock ID not set for single SKU label generation");
        }

        $this->filename = "sku_" . $this->stock_id . "_labels.csv";
        
        $this->prepWriteFile();
        if ($this->include_header) {
            $this->write_file->write_line($this->hline);
        }

        $this->setSingleSkuQuery();
        $result = db_query($this->query, "Couldn't grab stock item for labels");

        $rowcount = 0;
        if ($row = db_fetch($result)) {
            // Generate just one label for the specified SKU
            $this->writeSkuLabelsLine(
                $row['stock_id'], 
                $row['category'], 
                $row['description'], 
                $row['price'], 
                $this->thermal_printer
            );
            $rowcount = 1;
        }
        
        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile("SKU Label - " . $this->stock_id);
        }
        return $rowcount;
    }

    /**
     * Set query for purchase order labels
     */
    protected function setPOQuery()
    {
        $this->query = "SELECT 
            pdi.stock_id,
            sm.description,
            sc.description as category,
            p.price,
            pdi.quantity_ordered
        FROM " . TB_PREF . "purch_order_details pdi
        JOIN " . TB_PREF . "stock_master sm ON pdi.stock_id = sm.stock_id
        JOIN " . TB_PREF . "stock_category sc ON sm.category_id = sc.category_id
        LEFT JOIN " . TB_PREF . "prices p ON sm.stock_id = p.stock_id 
            AND p.sales_type_id = (SELECT id FROM " . TB_PREF . "sales_types WHERE sales_type = '" . $this->RETAIL_type . "')
            AND p.curr_abrev = 'CAD'
        WHERE pdi.order_no >= " . $this->delivery_no;
        
        if (isset($this->last_delivery_no) && $this->last_delivery_no > $this->delivery_no) {
            $this->query .= " AND pdi.order_no <= " . $this->last_delivery_no;
        }
        
        $this->query .= " ORDER BY sc.description, sm.description";
    }

    /**
     * Set query for single SKU labels
     */
    protected function setSingleSkuQuery()
    {
        $this->query = "SELECT 
            sm.stock_id,
            sm.description,
            sc.description as category,
            p.price
        FROM " . TB_PREF . "stock_master sm
        JOIN " . TB_PREF . "stock_category sc ON sm.category_id = sc.category_id
        LEFT JOIN " . TB_PREF . "prices p ON sm.stock_id = p.stock_id 
            AND p.sales_type_id = (SELECT id FROM " . TB_PREF . "sales_types WHERE sales_type = '" . $this->RETAIL_type . "')
            AND p.curr_abrev = 'CAD'
        WHERE sm.stock_id = '" . $this->stock_id . "'";
    }

    /**
     * Write a single SKU label line to the CSV file
     * 
     * @param string $stock_id Stock ID
     * @param string $category Product category
     * @param string $description Product description
     * @param float $price Product price
     * @param bool $thermal_printer Whether using thermal printer
     */
    protected function writeSkuLabelsLine($stock_id, $category, $description, $price, $thermal_printer = false)
    {
        // Clean up HTML entities in description
        $bad_decode = ["&#039;", ";", "&#150;", "&quot;", "?"];
        $good_decode = ["'", ".", ".", "'", "-"];
        $description = str_replace($bad_decode, $good_decode, html_entity_decode($description));
        $category = str_replace($bad_decode, $good_decode, html_entity_decode($category));

        // For thermal printer, don't use special characters in barcode
        $barcode = $thermal_printer ? $stock_id : $stock_id;
        
        // Format price
        $formatted_price = number_format($price, 2);

        $this->write_file->write_array_to_csv([
            $stock_id,
            $description,
            $barcode,
            $category,
            $formatted_price
        ]);
    }

    /**
     * Set the last delivery number for range processing
     * 
     * @param int $last_delivery_no Last delivery number
     */
    public function setLastDeliveryNo($last_delivery_no)
    {
        $this->last_delivery_no = $last_delivery_no;
    }

    /**
     * Set the delivery number
     * 
     * @param int $delivery_no Delivery number
     */
    public function setDeliveryNo($delivery_no)
    {
        $this->delivery_no = $delivery_no;
    }
}
