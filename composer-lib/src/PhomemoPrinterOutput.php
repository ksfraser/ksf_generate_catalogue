<?php

/**
 * Phomemo Thermal Printer Label Output Handler
 * 
 * Generates labels for Phomemo thermal printers. Can output to CSV for import
 * into label software, or generate PDF files that can be printed directly.
 * 
 * The Phomemo printers support:
 * - M110: 50mm width labels
 * - M220: 80mm width labels  
 * - Direct thermal printing (no ink/toner required)
 * - PDF printing via standard print dialog
 * - CSV import for batch printing software
 * 
 * @package   Ksfraser\Frontaccounting\GenCat
 * @author    KS Fraser <kevin@ksfraser.com>
 * @copyright 2025 KS Fraser
 * @license   GPL-3.0-or-later
 * @version   1.0.0
 * @since     1.0.0
 */

namespace Ksfraser\Frontaccounting\GenCat;

use Exception;

/**
 * Phomemo Thermal Printer Output Handler
 * 
 * Creates labels optimized for Phomemo thermal printers in either
 * CSV format (for label software) or PDF format (for direct printing).
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class PhomemoPrinterOutput extends BaseOutputHandler
{
    /**
     * Output format (csv or pdf)
     * 
     * @var string
     */
    protected $output_format = 'csv';
    
    /**
     * Label width in mm (50 for M110, 80 for M220)
     * 
     * @var int
     */
    protected $label_width = 50;
    
    /**
     * Label height in mm
     * 
     * @var int
     */
    protected $label_height = 30;
    
    /**
     * Include barcode on label
     * 
     * @var bool
     */
    protected $include_barcode = true;
    
    /**
     * Include price on label
     * 
     * @var bool
     */
    protected $include_price = true;
    
    /**
     * Font size for label text
     * 
     * @var int
     */
    protected $font_size = 10;
    
    /**
     * Generate labels for items with stock only
     * 
     * @var bool
     */
    protected $in_stock_only = true;
    
    /**
     * Constructor
     * 
     * @param string $prefs_tablename Preferences table name
     */
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        
        $this->filename = "phomemo_labels.csv";
        $this->hline = '"SKU","Product Name","Price","Barcode","Category","Quantity"';
        
        // Load preferences
        $this->loadPreferences();
    }
    
    /**
     * Load preferences from database
     */
    protected function loadPreferences()
    {
        $this->output_format = $this->getPreference('phomemo_output_format', 'csv');
        $this->label_width = (int)$this->getPreference('phomemo_label_width', 50);
        $this->label_height = (int)$this->getPreference('phomemo_label_height', 30);
        $this->include_barcode = (bool)$this->getPreference('phomemo_include_barcode', true);
        $this->include_price = (bool)$this->getPreference('phomemo_include_price', true);
        $this->font_size = (int)$this->getPreference('phomemo_font_size', 10);
        $this->in_stock_only = (bool)$this->getPreference('phomemo_in_stock_only', true);
        
        // Update filename based on output format
        if ($this->output_format === 'pdf') {
            $this->filename = "phomemo_labels.pdf";
        }
    }
    
    /**
     * Get output handler metadata for dynamic discovery
     * 
     * @return array Handler metadata
     */
    public static function getOutputHandlerMetadata()
    {
        return [
            'name' => 'phomemo',
            'title' => 'Phomemo Thermal Printer',
            'class' => 'PhomemoPrinterOutput',
            'description' => 'Generate labels for Phomemo thermal printers (M110/M220) in CSV or PDF format',
            'category' => 'printing',
            'output_type' => 'csv_or_pdf',
            'version' => '1.0.0',
            'author' => 'KS Fraser',
            'requires_config' => true
        ];
    }
    
    /**
     * Get generator metadata (for backward compatibility)
     * 
     * @return array Generator metadata
     */
    public static function getGeneratorMetadata()
    {
        $metadata = self::getOutputHandlerMetadata();
        // Keep compatibility with GeneratorDiscovery/CatalogueGeneratorFactory.
        $metadata['method'] = 'createPhomemoPrinterOutput';
        return $metadata;
    }
    
    /**
     * Get the priority/order for this output handler
     * 
     * @return int Priority order (60 = optional/supplementary)
     */
    public static function getOutputHandlerPriority()
    {
        return 60;
    }
    
    /**
     * Get the priority/order for this generator (backward compatibility)
     * 
     * @return int Priority order
     */
    public static function getGeneratorPriority()
    {
        return 60;
    }
    
    /**
     * Check if this output handler is available
     * 
     * @return bool True if handler is available
     */
    public static function isOutputHandlerAvailable()
    {
        // Check if PDF library is available if PDF output is needed
        // For now, always return true as CSV is always available
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
            if ($this->output_format === 'pdf') {
                $rowcount = $this->createPdfLabels();
            } else {
                $rowcount = $this->createCsvLabels();
            }
            
            return [
                'success' => true,
                'rows' => $rowcount,
                'files' => [$this->filename],
                'message' => "Successfully generated {$rowcount} thermal printer labels"
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
     * BaseCatalogueGenerator compatibility.
     *
     * @return int
     */
    public function createFile()
    {
        return $this->output_format === 'pdf'
            ? $this->createPdfLabels()
            : $this->createCsvLabels();
    }
    
    /**
     * Create CSV format labels
     * 
     * @return int Number of labels generated
     */
    protected function createCsvLabels()
    {
        $this->setQuery();
        $this->prepWriteFile();
        $this->write_file->write_line($this->hline);
        
        $result = $this->getDatabase()->query($this->query, "Couldn't retrieve products for labels");
        
        $rowcount = 0;
        while ($row = $this->getDatabase()->fetch($result)) {
            $quantity = (int)($row['qty'] ?? 0);
            
            // Generate one label per item in stock
            for ($i = 0; $i < $quantity; $i++) {
                $this->writeLabelRow($row);
                $rowcount++;
            }
        }
        
        $this->write_file->close();
        
        if ($rowcount > 0) {
            $this->emailFile("Phomemo Thermal Printer Labels");
        }
        
        return $rowcount;
    }
    
    /**
     * Create PDF format labels
     * 
     * @return int Number of labels generated
     */
    protected function createPdfLabels()
    {
        // For PDF generation, we would need a PDF library like TCPDF or FPDF
        // This is a placeholder implementation that creates a simple text file
        // that describes what the PDF should contain
        
        $this->setQuery();
        $result = $this->getDatabase()->query($this->query, "Couldn't retrieve products for labels");
        
        $pdfContent = "PDF Label Generation - Phomemo Thermal Printer\n";
        $pdfContent .= "Label Size: {$this->label_width}mm x {$this->label_height}mm\n";
        $pdfContent .= "Font Size: {$this->font_size}pt\n";
        $pdfContent .= str_repeat("=", 60) . "\n\n";
        
        $rowcount = 0;
        while ($row = $this->getDatabase()->fetch($result)) {
            $quantity = (int)($row['qty'] ?? 0);
            
            for ($i = 0; $i < $quantity; $i++) {
                $pdfContent .= $this->formatLabelForPdf($row);
                $pdfContent .= "\n" . str_repeat("-", 60) . "\n\n";
                $rowcount++;
            }
        }
        
        // Write to file (would be PDF in production)
        file_put_contents($this->filename, $pdfContent);
        
        if ($rowcount > 0) {
            $this->emailFile("Phomemo Thermal Printer Labels (PDF)");
        }
        
        return $rowcount;
    }
    
    /**
     * Set the SQL query for retrieving label data
     */
    protected function setQuery()
    {
        $inStockCondition = $this->in_stock_only ? "HAVING qty > 0" : "";
        
        $this->query = "SELECT 
                s.stock_id as sku,
                s.description as name,
                s.category_id,
                c.description as category,
                p.price,
                IFNULL(SUM(sm.qty), 0) as qty
            FROM " . TB_PREF . "stock_master s
            LEFT JOIN " . TB_PREF . "stock_category c ON s.category_id = c.category_id
            LEFT JOIN " . TB_PREF . "prices p ON s.stock_id = p.stock_id 
                AND p.sales_type_id = 1 
                AND p.curr_abrev = 'CAD'
            LEFT JOIN " . TB_PREF . "stock_moves sm ON s.stock_id = sm.stock_id
            WHERE s.inactive = 0
            GROUP BY s.stock_id, s.description, s.category_id, c.description, p.price
            {$inStockCondition}
            ORDER BY c.description, s.description";
    }
    
    /**
     * Write a label row to CSV
     * 
     * @param array $row Product data
     */
    protected function writeLabelRow($row)
    {
        $sku = $row['sku'] ?? '';
        $name = $this->cleanText($row['name'] ?? '');
        $price = $this->include_price ? number_format($row['price'] ?? 0, 2) : '';
        $barcode = $this->include_barcode ? $sku : '';
        $category = $this->cleanText($row['category'] ?? '');
        
        $this->write_file->write_array_to_csv([
            $sku,
            $name,
            $price,
            $barcode,
            $category,
            '1' // Quantity for this label
        ]);
    }
    
    /**
     * Format a label for PDF output
     * 
     * @param array $row Product data
     * @return string Formatted label text
     */
    protected function formatLabelForPdf($row)
    {
        $sku = $row['sku'] ?? '';
        $name = $this->cleanText($row['name'] ?? '');
        $price = number_format($row['price'] ?? 0, 2);
        $category = $this->cleanText($row['category'] ?? '');
        
        $label = "SKU: {$sku}\n";
        $label .= "Name: {$name}\n";
        $label .= "Category: {$category}\n";
        
        if ($this->include_price) {
            $label .= "Price: \${$price}\n";
        }
        
        if ($this->include_barcode) {
            $label .= "Barcode: {$sku}\n";
        }
        
        return $label;
    }
    
    /**
     * Clean text for label output
     * 
     * @param string $text Text to clean
     * @return string Cleaned text
     */
    protected function cleanText($text)
    {
        $bad = ["&#039;", ";", "&#150;", "&quot;", "?"];
        $good = ["'", ".", ".", "'", "-"];
        
        return str_replace($bad, $good, html_entity_decode($text));
    }
    
    /**
     * Get configuration schema for this output handler
     * 
     * @return array Configuration schema
     */
    public function getConfigurationSchema()
    {
        return [
            'phomemo_output_format' => [
                'label' => 'Output Format',
                'type' => 'select',
                'description' => 'Choose output format for labels',
                'required' => true,
                'default' => 'csv',
                'options' => [
                    'csv' => 'CSV (for label software)',
                    'pdf' => 'PDF (for direct printing)'
                ]
            ],
            'phomemo_label_width' => [
                'label' => 'Label Width (mm)',
                'type' => 'select',
                'description' => 'Select label width based on your Phomemo printer model',
                'required' => true,
                'default' => 50,
                'options' => [
                    50 => '50mm (M110)',
                    80 => '80mm (M220)'
                ]
            ],
            'phomemo_label_height' => [
                'label' => 'Label Height (mm)',
                'type' => 'number',
                'description' => 'Label height in millimeters',
                'default' => 30
            ],
            'phomemo_include_barcode' => [
                'label' => 'Include Barcode',
                'type' => 'yes_no',
                'description' => 'Include barcode on labels',
                'default' => true
            ],
            'phomemo_include_price' => [
                'label' => 'Include Price',
                'type' => 'yes_no',
                'description' => 'Include price on labels',
                'default' => true
            ],
            'phomemo_font_size' => [
                'label' => 'Font Size',
                'type' => 'number',
                'description' => 'Font size for label text (points)',
                'default' => 10
            ],
            'phomemo_in_stock_only' => [
                'label' => 'In-Stock Items Only',
                'type' => 'yes_no',
                'description' => 'Generate labels only for items currently in stock',
                'default' => true
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
        
        if (!in_array($this->output_format, ['csv', 'pdf'])) {
            $errors[] = 'Invalid output format';
        }
        
        if (!in_array($this->label_width, [50, 80])) {
            $errors[] = 'Invalid label width (must be 50mm or 80mm)';
        }
        
        if ($this->output_format === 'pdf' && !class_exists('TCPDF') && !class_exists('FPDF')) {
            $errors[] = 'PDF output requires TCPDF or FPDF library (currently using text fallback)';
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
            return 'Configuration issues: ' . implode(', ', $validation['errors']);
        }
        
        $format = strtoupper($this->output_format);
        $width = $this->label_width;
        $model = ($width == 50) ? 'M110' : 'M220';
        
        return "Ready to generate {$format} labels for Phomemo {$model} ({$width}mm)";
    }
}
