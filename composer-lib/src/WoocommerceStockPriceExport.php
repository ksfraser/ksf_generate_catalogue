<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * WooCommerce Stock/Price Export
 *
 * Outputs a compact CSV for syncing stock and prices.
 * Columns: stock_id (SKU), Index (WooCommerce ID), Quantity on Hand, Price.
 */
class WoocommerceStockPriceExport extends BaseCatalogueGenerator implements OutputHandlerInterface
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = 'woo_stock_price.csv';
        $this->hline = '"stock_id","Index","Quantity on Hand","Price"';
    }

    public static function getGeneratorMetadata()
    {
        return self::getOutputHandlerMetadata();
    }

    public static function getOutputHandlerMetadata()
    {
        return [
            'name' => 'woocommerce_stock_price',
            'title' => 'WooCommerce Stock + Price',
            'class' => 'WoocommerceStockPriceExport',
            'description' => 'Export SKU + WooCommerce ID + quantity on hand + price',
            'method' => 'createWoocommerceStockPriceExport',
            'category' => 'ecommerce',
            'output_type' => 'csv',
            'version' => '1.0.0',
            'author' => 'KS Fraser',
            'requires_config' => false,
        ];
    }

    public static function getGeneratorPriority()
    {
        return 25;
    }

    public static function getOutputHandlerPriority()
    {
        return 25;
    }

    public static function isOutputHandlerAvailable()
    {
        return true;
    }

    public function generateOutput()
    {
        try {
            $rowcount = $this->createFile();
            return [
                'success' => true,
                'rows' => $rowcount,
                'files' => [$this->filename],
                'message' => "Successfully generated WooCommerce stock/price export with {$rowcount} rows",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'rows' => 0,
                'files' => [],
                'message' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    public function getConfigurationSchema()
    {
        return [];
    }

    public function validateConfiguration()
    {
        $errors = [];
        try {
            $this->getDatabase();
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function getStatus()
    {
        $validation = $this->validateConfiguration();
        if (!$validation['valid']) {
            return 'Not ready: ' . implode(', ', $validation['errors']);
        }
        return 'Ready to export WooCommerce stock/price';
    }

    public function setQuery()
    {
        $this->query = "SELECT
            sm.stock_id AS stock_id,
            IFNULL(wt.woocommerce_id, '') AS `Index`,
            COALESCE(qoh.qoh, 0) AS `Quantity on Hand`,
            COALESCE(p.price, 0.00) AS Price
        FROM " . TB_PREF . "stock_master sm
        LEFT JOIN " . TB_PREF . "woocommerce_tokens wt ON wt.stock_id = sm.stock_id
        LEFT JOIN (
            SELECT stock_id, SUM(qty) AS qoh
            FROM " . TB_PREF . "stock_moves
            WHERE loc_code = '" . $this->PRIMARY_LOC . "'
            GROUP BY stock_id
        ) qoh ON qoh.stock_id = sm.stock_id
        LEFT JOIN (
            SELECT stock_id, price
            FROM " . TB_PREF . "prices
            WHERE sales_type_id = 1 AND curr_abrev = 'CAD'
        ) p ON p.stock_id = sm.stock_id
        WHERE sm.inactive = 0
          AND wt.woocommerce_id IS NOT NULL
          AND wt.woocommerce_id <> ''
        ORDER BY sm.stock_id";
    }

    public function createFile()
    {
        $this->setQuery();
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);

        $result = $this->getDatabase()->query($this->query, "Couldn't export WooCommerce stock/price");
        $rowcount = 0;
        while ($row = $this->getDatabase()->fetch($result)) {
            $this->write_file->write_array_to_csv([
                $row['stock_id'] ?? '',
                $row['Index'] ?? '',
                $row['Quantity on Hand'] ?? 0,
                $row['Price'] ?? 0.00,
            ]);
            $rowcount++;
        }

        $this->write_file->close();
        if ($rowcount > 0) {
            $this->emailFile('WooCommerce Stock + Price');
        }
        return $rowcount;
    }
}
