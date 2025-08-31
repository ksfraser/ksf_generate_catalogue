<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Social Media Fields Manager
 * 
 * Handles UI and database operations for social media fields per SKU
 */
class SocialMediaFieldsManager
{
    protected $database;
    protected $table_prefix;
    
    public function __construct(DatabaseInterface $database)
    {
        $this->database = $database;
        $this->table_prefix = $database->getTablePrefix();
    }

    /**
     * Get social media fields for a specific stock item
     * 
     * @param string $stock_id Stock ID
     * @return array Social media fields or defaults
     */
    public function getSocialMediaFields($stock_id)
    {
        $sql = "SELECT * FROM " . $this->table_prefix . "social_media_fields WHERE stock_id = ?";
        $result = $this->database->query($sql, [$stock_id]);
        
        if (!empty($result)) {
            return $result[0];
        }
        
        return [
            'stock_id' => $stock_id,
            'social_media_title' => '',
            'social_media_description' => ''
        ];
    }

    /**
     * Update social media fields for a stock item
     * 
     * @param string $stock_id Stock ID
     * @param string $title Social media title
     * @param string $description Social media description
     * @return bool Success status
     */
    public function updateSocialMediaFields($stock_id, $title, $description)
    {
        // Check if record exists
        $existing = $this->getSocialMediaFields($stock_id);
        
        if (isset($existing['id'])) {
            // Update existing record
            $sql = "UPDATE " . $this->table_prefix . "social_media_fields 
                    SET social_media_title = ?, 
                        social_media_description = ?,
                        last_updated = NOW()
                    WHERE stock_id = ?";
            return $this->database->execute($sql, [$title, $description, $stock_id]);
        } else {
            // Insert new record
            $sql = "INSERT INTO " . $this->table_prefix . "social_media_fields 
                    (stock_id, social_media_title, social_media_description, last_created, last_updated) 
                    VALUES (?, ?, ?, NOW(), NOW())";
            return $this->database->execute($sql, [$stock_id, $title, $description]);
        }
    }

    /**
     * Get all stock items with optional filtering
     * 
     * @param bool $show_inactive Include inactive items
     * @param string $category_filter Filter by category
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Stock items with social media data
     */
    public function getStockItemsWithSocialMedia($show_inactive = false, $category_filter = '', $limit = 50, $offset = 0)
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
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        $sql = "SELECT 
                    sm.stock_id,
                    sm.description as stock_description,
                    sm.inactive,
                    sc.description as category,
                    IFNULL(smf.social_media_title, '') as social_media_title,
                    IFNULL(smf.social_media_description, '') as social_media_description,
                    smf.last_updated
                FROM " . $this->table_prefix . "stock_master sm
                LEFT JOIN " . $this->table_prefix . "stock_category sc ON sm.category_id = sc.category_id
                LEFT JOIN " . $this->table_prefix . "social_media_fields smf ON sm.stock_id = smf.stock_id
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
     * @return int Total count
     */
    public function getStockItemsCount($show_inactive = false, $category_filter = '')
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
        
        $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
        
        $sql = "SELECT COUNT(*) as total
                FROM " . $this->table_prefix . "stock_master sm
                LEFT JOIN " . $this->table_prefix . "stock_category sc ON sm.category_id = sc.category_id
                $where_sql";
        
        $result = $this->database->query($sql, $params);
        return $result[0]['total'] ?? 0;
    }

    /**
     * Delete social media fields for a stock item
     * 
     * @param string $stock_id Stock ID
     * @return bool Success status
     */
    public function deleteSocialMediaFields($stock_id)
    {
        $sql = "DELETE FROM " . $this->table_prefix . "social_media_fields WHERE stock_id = ?";
        return $this->database->execute($sql, [$stock_id]);
    }

    /**
     * Bulk update social media fields from array
     * 
     * @param array $updates Array of updates [stock_id => [title, description]]
     * @return array Results array with success/failure info
     */
    public function bulkUpdateSocialMedia($updates)
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($updates as $stock_id => $data) {
            try {
                $title = $data['title'] ?? '';
                $description = $data['description'] ?? '';
                
                if ($this->updateSocialMediaFields($stock_id, $title, $description)) {
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
     * Export social media fields to CSV
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
                    IFNULL(smf.social_media_title, '') as social_media_title,
                    IFNULL(smf.social_media_description, '') as social_media_description
                FROM " . $this->table_prefix . "stock_master sm
                LEFT JOIN " . $this->table_prefix . "stock_category sc ON sm.category_id = sc.category_id
                LEFT JOIN " . $this->table_prefix . "social_media_fields smf ON sm.stock_id = smf.stock_id
                WHERE sm.inactive = 0
                ORDER BY sc.description, sm.description";
        
        $data = $this->database->query($sql);
        
        $handle = fopen($filename, 'w');
        if (!$handle) {
            return false;
        }
        
        // Write header
        fputcsv($handle, ['Stock ID', 'Description', 'Category', 'Social Media Title', 'Social Media Description']);
        
        // Write data
        foreach ($data as $row) {
            fputcsv($handle, [
                $row['stock_id'],
                $row['stock_description'],
                $row['category'],
                $row['social_media_title'],
                $row['social_media_description']
            ]);
        }
        
        fclose($handle);
        return true;
    }
}
