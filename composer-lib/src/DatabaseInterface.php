<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Database Interface for FrontAccounting Integration
 * 
 * This interface abstracts database operations to allow the library to work
 * with FrontAccounting's database functions while maintaining testability.
 */
interface DatabaseInterface
{
    /**
     * Execute a database query
     * 
     * @param string $query SQL query
     * @param string $error_message Error message if query fails
     * @return mixed Query result
     */
    public function query($query, $error_message = "Database query failed");

    /**
     * Fetch a row from a database result
     * 
     * @param mixed $result Database result
     * @return array|false Row data or false if no more rows
     */
    public function fetch($result);

    /**
     * Get the table prefix for the current company
     * 
     * @return string Table prefix (e.g., "0_", "1_")
     */
    public function getTablePrefix();
}

/**
 * FrontAccounting Database Implementation
 * 
 * Implementation that uses FrontAccounting's global database functions
 */
class FrontAccountingDatabase implements DatabaseInterface
{
    public function query($query, $error_message = "Database query failed")
    {
        if (!function_exists('db_query')) {
            throw new \Exception("FrontAccounting database functions not available");
        }
        return db_query($query, $error_message);
    }

    public function fetch($result)
    {
        if (!function_exists('db_fetch')) {
            throw new \Exception("FrontAccounting database functions not available");
        }
        return db_fetch($result);
    }

    public function getTablePrefix()
    {
        if (defined('TB_PREF')) {
            return TB_PREF;
        }
        return "0_"; // Default prefix
    }
}
