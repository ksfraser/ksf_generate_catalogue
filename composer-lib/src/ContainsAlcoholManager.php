<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Contains Alcohol Manager
 * 
 * Handles UI and database operations for alcohol content flags per SKU
 */
class ContainsAlcoholManager
{
    protected $database;
    protected $table_prefix;
    
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
        $this->table_prefix = $database->getTablePrefix();
    }

    /**
     * Get alcohol flag for a specific stock item
     * 
     * @param string $stock_id Stock ID
     * @return bool Contains alcohol flag
     */
    public function getAlcoholFlag($stock_id)
    {
        $sql = "SELECT contains_alcohol FROM " . $this->table_prefix . "contains_alcohol WHERE stock_id = ?";
        $result = $this->database->query($sql, [$stock_id]);
        
        if (!empty($result)) {
            return (bool)$result[0]['contains_alcohol'];
        }
        
        return false; // Default to no alcohol
    }

    /**
     * Update alcohol flag for a stock item
     * 
     * @param string $stock_id Stock ID
     * @param bool $contains_alcohol Contains alcohol flag
     * @return bool Success status
     */
    public function updateAlcoholFlag($stock_id, $contains_alcohol)
    {
        $alcohol_int = $contains_alcohol ? 1 : 0;
        
        // Check if record exists
        $sql = "SELECT id FROM " . $this->table_prefix . "contains_alcohol WHERE stock_id = ?";
        $existing = $this->database->query($sql, [$stock_id]);
        
        if (!empty($existing)) {
            // Update existing record
            $sql = "UPDATE " . $this->table_prefix . "contains_alcohol 
                    SET contains_alcohol = ?, last_updated = NOW()
                    WHERE stock_id = ?";
            return $this->database->execute($sql, [$alcohol_int, $stock_id]);
        } else {
            // Insert new record
            $sql = "INSERT INTO " . $this->table_prefix . "contains_alcohol 
                    (stock_id, contains_alcohol, last_created, last_updated) 
                    VALUES (?, ?, NOW(), NOW())";
            return $this->database->execute($sql, [$stock_id, $alcohol_int]);
        }
    }

    /**
     * Get all stock items with alcohol flags
     * 
     * @param bool $show_inactive Include inactive items
     * @param string $category_filter Filter by category
     * @param bool $alcohol_only Show only items marked as containing alcohol
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Stock items with alcohol data
     */
    public function getStockItemsWithAlcohol($show_inactive = false, $category_filter = '', $alcohol_only = false, $limit = 50, $offset = 0)
    {
        $where_clauses = [];
        $params = [];
        
        if (!$show_inactive) {
            $where_clauses[] = "sm.inactive = 0";
        }
        
        if (!empty($category_filter)) {
            $where_clauses[] = "sc.description LIKE ?";
            $params[] = "%$category_filter%";
        }
        
        if ($alcohol_only) {
            $where_clauses[] = "IFNULL(alc.contains_alcohol, 0) = 1";
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        $sql = "SELECT 
                    sm.stock_id,
                    sm.description as stock_description,
                    sm.inactive,
                    sc.description as category,
                    IFNULL(alc.contains_alcohol, 0) as contains_alcohol,
                    alc.last_updated
                FROM " . $this->table_prefix . "stock_master sm
                LEFT JOIN " . $this->table_prefix . "stock_category sc ON sm.category_id = sc.category_id
                LEFT JOIN " . $this->table_prefix . "contains_alcohol alc ON sm.stock_id = alc.stock_id
                $where_sql
                ORDER BY sc.description, sm.description
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        return $this->database->query($sql, $params);
    }

    /**
     * Get count of stock items for pagination
     * 
     * @param bool $show_inactive Include inactive items
     * @param string $category_filter Filter by category
     * @param bool $alcohol_only Show only items marked as containing alcohol
     * @return int Total count
     */
    public function getStockItemsCount($show_inactive = false, $category_filter = '', $alcohol_only = false)
    {
        $where_clauses = [];
        $params = [];
        
        if (!$show_inactive) {
            $where_clauses[] = "sm.inactive = 0";
        }
        
        if (!empty($category_filter)) {
            $where_clauses[] = "sc.description LIKE ?";
            $params[] = "%$category_filter%";
        }
        
        if ($alcohol_only) {
            $where_clauses[] = "IFNULL(alc.contains_alcohol, 0) = 1";
        }
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        $sql = "SELECT COUNT(*) as total
                FROM " . $this->table_prefix . "stock_master sm
                LEFT JOIN " . $this->table_prefix . "stock_category sc ON sm.category_id = sc.category_id
                LEFT JOIN " . $this->table_prefix . "contains_alcohol alc ON sm.stock_id = alc.stock_id
                $where_sql";
        
        $result = $this->database->query($sql, $params);
        return $result[0]['total'] ?? 0;
    }

    /**
     * Delete alcohol flag for a stock item
     * 
     * @param string $stock_id Stock ID
     * @return bool Success status
     */
    public function deleteAlcoholFlag($stock_id)
    {
        $sql = "DELETE FROM " . $this->table_prefix . "contains_alcohol WHERE stock_id = ?";
        return $this->database->execute($sql, [$stock_id]);
    }

    /**
     * Bulk update alcohol flags from array
     * 
     * @param array $updates Array of updates [stock_id => bool]
     * @return array Results array with success/failure info
     */
    public function bulkUpdateAlcohol($updates)
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($updates as $stock_id => $contains_alcohol) {
            try {
                if ($this->updateAlcoholFlag($stock_id, $contains_alcohol)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to update $stock_id";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Error updating $stock_id: " . $e->getMessage();
            }
        }
        
        return $results;
    }

    /**
     * Auto-detect alcohol products by name patterns
     * 
     * @param bool $dry_run If true, return matches without updating database
     * @return array Results with detected items
     */
    public function autoDetectAlcohol($dry_run = true)
    {
        // Common alcohol keywords
        $alcohol_keywords = [
            'wine', 'beer', 'whisky', 'whiskey', 'vodka', 'gin', 'rum', 'brandy',
            'tequila', 'cognac', 'champagne', 'alcohol', 'liqueur', 'scotch',
            'bourbon', 'rye', 'malt', 'lager', 'ale', 'stout', 'porter'
        ];
        
        $keyword_pattern = implode('|', array_map('preg_quote', $alcohol_keywords));
        
        $sql = "SELECT stock_id, description 
                FROM " . $this->table_prefix . "stock_master 
                WHERE inactive = 0 
                AND (description REGEXP ? OR description REGEXP ?)
                ORDER BY description";
        
        $params = [
            '(?i)\\b(' . $keyword_pattern . ')\\b',  // Case-insensitive word boundary match
            '(?i)(' . $keyword_pattern . ')'         // Case-insensitive partial match
        ];
        
        $detected = $this->database->query($sql, $params);
        
        $results = [
            'detected' => count($detected),
            'updated' => 0,
            'items' => $detected,
            'errors' => []
        ];
        
        if (!$dry_run) {
            foreach ($detected as $item) {
                try {
                    if ($this->updateAlcoholFlag($item['stock_id'], true)) {
                        $results['updated']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Error updating " . $item['stock_id'] . ": " . $e->getMessage();
                }
            }
        }
        
        return $results;
    }

    /**
     * Get alcohol content statistics
     * 
     * @return array Statistics about alcohol flags
     */
    public function getAlcoholStatistics()
    {
        $sql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN alc.contains_alcohol = 1 THEN 1 ELSE 0 END) as alcohol_items,
                    COUNT(alc.stock_id) as flagged_items
                FROM " . $this->table_prefix . "stock_master sm
                LEFT JOIN " . $this->table_prefix . "contains_alcohol alc ON sm.stock_id = alc.stock_id
                WHERE sm.inactive = 0";
        
        $result = $this->database->query($sql);
        $stats = $result[0] ?? [];
        
        $stats['non_alcohol_items'] = ($stats['flagged_items'] ?? 0) - ($stats['alcohol_items'] ?? 0);
        $stats['unflagged_items'] = ($stats['total_items'] ?? 0) - ($stats['flagged_items'] ?? 0);
        
        return $stats;
    }

    /**
     * Export alcohol flags to CSV
     * 
     * @param string $filename Output filename
     * @return bool Success status
     */
    public function exportToCsv($filename)
    {
        $sql = "SELECT 
                    sm.stock_id,
                    sm.description as stock_description,
                    sc.description as category,
                    IFNULL(alc.contains_alcohol, 0) as contains_alcohol
                FROM " . $this->table_prefix . "stock_master sm
                LEFT JOIN " . $this->table_prefix . "stock_category sc ON sm.category_id = sc.category_id
                LEFT JOIN " . $this->table_prefix . "contains_alcohol alc ON sm.stock_id = alc.stock_id
                WHERE sm.inactive = 0
                ORDER BY sc.description, sm.description";
        
        $data = $this->database->query($sql);
        
        $handle = fopen($filename, 'w');
        if (!$handle) {
            return false;
        }
        
        // Write header
        fputcsv($handle, ['Stock ID', 'Description', 'Category', 'Contains Alcohol']);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['stock_id'],
                $row['stock_description'],
                $row['category'],
                $row['contains_alcohol'] ? 'Yes' : 'No'
            ]);
        }
        
        fclose($handle);
        return true;
    }
}
