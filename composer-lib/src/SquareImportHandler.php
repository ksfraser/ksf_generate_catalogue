<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Square Token Import Handler
 * 
 * Handles importing Square tokens from CSV files to update the square_tokens table
 */
class SquareImportHandler
{
    protected $database;
    protected $import_log = [];
    protected $processed_count = 0;
    protected $updated_count = 0;
    protected $created_count = 0;
    
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
    }

    /**
     * Import Square tokens from CSV file
     * 
     * @param string $csv_file_path Path to the CSV file
     * @return array Import results
     */
    public function importFromCsv($csv_file_path)
    {
        if (!file_exists($csv_file_path)) {
            throw new \Exception("CSV file not found: " . $csv_file_path);
        }

        $this->resetCounters();
        $this->import_log[] = "Starting import from: " . basename($csv_file_path);

        $handle = fopen($csv_file_path, 'r');
        if (!$handle) {
            throw new \Exception("Could not open CSV file: " . $csv_file_path);
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            throw new \Exception("Could not read CSV headers");
        }

        // Find required column indices
        $token_index = $this->findColumnIndex($headers, ['Token', 'token']);
        $sku_index = $this->findColumnIndex($headers, ['SKU', 'sku', 'Item Name', 'item_name']);
        
        if ($token_index === false || $sku_index === false) {
            fclose($handle);
            throw new \Exception("Required columns not found. Need 'Token' and 'SKU' columns.");
        }

        // Process data rows
        $row_number = 1;
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_number++;
            
            $token = trim($data[$token_index] ?? '');
            $sku = trim($data[$sku_index] ?? '');
            
            if (empty($token) || empty($sku)) {
                continue; // Skip rows with missing data
            }

            $this->processTokenRow($sku, $token, $row_number);
            $this->processed_count++;
        }

        fclose($handle);
        
        // Log the import
        $this->logImport(basename($csv_file_path), 'success');
        
        return $this->getImportSummary();
    }

    /**
     * Process a single token row
     * 
     * @param string $sku Stock ID
     * @param string $token Square token
     * @param int $row_number Row number for logging
     */
    protected function processTokenRow($sku, $token, $row_number)
    {
        // Check if stock_id exists in FrontAccounting
        $check_stock = "SELECT stock_id FROM " . $this->database->getTablePrefix() . "stock_master WHERE stock_id = ?";
        $result = $this->database->query($check_stock, [$sku]);
        
        if (empty($result)) {
            $this->import_log[] = "Row $row_number: SKU '$sku' not found in stock_master, skipping";
            return;
        }

        // Check if token already exists
        $check_token = "SELECT stock_id FROM " . $this->database->getTablePrefix() . "square_tokens WHERE stock_id = ?";
        $existing = $this->database->query($check_token, [$sku]);
        
        if (!empty($existing)) {
            // Update existing token
            $update_sql = "UPDATE " . $this->database->getTablePrefix() . "square_tokens 
                          SET square_token = ?, last_updated = NOW()
                          WHERE stock_id = ?";
            
            if ($this->database->execute($update_sql, [$token, $sku])) {
                $this->updated_count++;
                $this->import_log[] = "Row $row_number: Updated token for SKU '$sku'";
            }
        } else {
            // Insert new token
            $insert_sql = "INSERT INTO " . $this->database->getTablePrefix() . "square_tokens 
                          (stock_id, square_token, last_created, last_updated) 
                          VALUES (?, ?, NOW(), NOW())";
            
            if ($this->database->execute($insert_sql, [$sku, $token])) {
                $this->created_count++;
                $this->import_log[] = "Row $row_number: Created token for SKU '$sku'";
            }
        }
    }

    /**
     * Find column index by name (case insensitive)
     * 
     * @param array $headers Header row
     * @param array $possible_names Possible column names
     * @return int|false Column index or false if not found
     */
    protected function findColumnIndex($headers, $possible_names)
    {
        $headers_lower = array_map('strtolower', $headers);
        
        foreach ($possible_names as $name) {
            $index = array_search(strtolower($name), $headers_lower);
            if ($index !== false) {
                return $index;
            }
        }
        
        return false;
    }

    /**
     * Log the import to database
     * 
     * @param string $filename Imported filename
     * @param string $status Import status
     */
    protected function logImport($filename, $status)
    {
        $notes = implode("\n", $this->import_log);
        
        $sql = "INSERT INTO " . $this->database->getTablePrefix() . "square_import_log 
                (filename, records_processed, records_updated, records_created, import_status, notes)
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->database->execute($sql, [
            $filename,
            $this->processed_count,
            $this->updated_count, 
            $this->created_count,
            $status,
            $notes
        ]);
    }

    /**
     * Get import summary
     * 
     * @return array Import summary
     */
    protected function getImportSummary()
    {
        return [
            'processed' => $this->processed_count,
            'updated' => $this->updated_count,
            'created' => $this->created_count,
            'log' => $this->import_log
        ];
    }

    /**
     * Reset import counters
     */
    protected function resetCounters()
    {
        $this->import_log = [];
        $this->processed_count = 0;
        $this->updated_count = 0;
        $this->created_count = 0;
    }

    /**
     * Get recent import history
     * 
     * @param int $limit Number of records to return
     * @return array Import history
     */
    public function getImportHistory($limit = 10)
    {
        $sql = "SELECT * FROM " . $this->database->getTablePrefix() . "square_import_log 
                ORDER BY import_date DESC 
                LIMIT " . (int)$limit;
        
        return $this->database->query($sql);
    }
}
