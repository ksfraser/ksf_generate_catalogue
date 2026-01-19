<?php

/**
 * Output Configuration Manager
 * 
 * Manages output handler configuration stored in database preferences.
 * Provides a simple interface for getting/setting preferences without
 * directly dealing with database queries.
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
use Ksfraser\Prefs\Prefs;
use Ksfraser\Prefs\Stores\CallableDbTableStore;
use Ksfraser\Prefs\Stores\PrefixedStore;

/**
 * Configuration Manager for Output Handlers
 * 
 * Provides centralized configuration management with database persistence.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class OutputConfigurationManager
{
    /** @var DatabaseInterface */
    private $database;

    /** @var string */
    private $prefs_tablename;

    /** @var string */
    private $prefix = 'output_';

    /** @var Prefs */
    private $prefs;

    /**
     * Legacy cache (kept for backward compatibility with helper methods).
     *
     * @var array
     */
    private $cache = [];
    
    /**
     * Constructor
     * 
     * @param DatabaseInterface $database Database interface
     * @param string $prefs_tablename Preferences table name
     * @param string $prefix Preference key prefix (default: 'output_')
     */
    public function __construct(DatabaseInterface $database, $prefs_tablename, $prefix = 'output_')
    {
        $this->database = $database;
        $this->prefs_tablename = $prefs_tablename;
        $this->prefix = $prefix;

        // Bridge GenCat's DatabaseInterface into ksfraser/prefs using callables.
        $escape = function ($value) use ($database) {
            if (is_object($database) && method_exists($database, 'escape')) {
                return $database->escape($value);
            }
            if (function_exists('db_escape')) {
                return db_escape($value);
            }
            return addslashes((string)$value);
        };

        $store = new CallableDbTableStore(
            [$database, 'query'],
            [$database, 'fetch'],
            $escape,
            [$database, 'getTablePrefix'],
            $prefs_tablename
        );

        $this->prefs = new Prefs(new PrefixedStore($store, $this->prefix));
    }
    
    /**
     * Get a configuration value
     * 
     * @param string $key Configuration key (without prefix)
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public function get($key, $default = null)
    {
        try {
            return $this->prefs->get($key, $default);
        } catch (Exception $e) {
            error_log("OutputConfigurationManager: Error getting config '{$key}': " . $e->getMessage());
        }
        
        return $default;
    }
    
    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key (without prefix)
     * @param mixed $value Configuration value
     * @return bool Success
     */
    public function set($key, $value)
    {
        try {
            $this->prefs->set($key, $value);
            return true;
        } catch (Exception $e) {
            error_log("OutputConfigurationManager: Error setting config '{$key}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a configuration value
     * 
     * @param string $key Configuration key (without prefix)
     * @return bool Success
     */
    public function delete($key)
    {
        try {
            $this->prefs->delete($key);
            return true;
        } catch (Exception $e) {
            error_log("OutputConfigurationManager: Error deleting config '{$key}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all configuration values
     * 
     * @return array All configuration values (without prefix in keys)
     */
    public function getAll()
    {
        try {
            return $this->prefs->all();
        } catch (Exception $e) {
            error_log("OutputConfigurationManager: Error getting all config: " . $e->getMessage());
        }

        return [];
    }
    
    /**
     * Set multiple configuration values
     * 
     * @param array $config Associative array of key => value pairs
     * @return bool Success
     */
    public function setMultiple(array $config)
    {
        $success = true;
        
        foreach ($config as $key => $value) {
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get enabled output handlers
     * 
     * @return array Array of enabled handler names
     */
    public function getEnabledOutputs()
    {
        $enabled = $this->get('enabled_outputs', []);
        
        // Handle string format (comma-separated)
        if (is_string($enabled)) {
            $enabled = array_filter(array_map('trim', explode(',', $enabled)));
        }
        
        return is_array($enabled) ? $enabled : [];
    }
    
    /**
     * Set enabled output handlers
     * 
     * @param array $outputs Array of handler names to enable
     * @return bool Success
     */
    public function setEnabledOutputs(array $outputs)
    {
        return $this->set('enabled_outputs', $outputs);
    }
    
    /**
     * Check if an output handler is enabled
     * 
     * @param string $handlerName Handler name
     * @return bool True if enabled
     */
    public function isOutputEnabled($handlerName)
    {
        $enabled = $this->getEnabledOutputs();
        return in_array($handlerName, $enabled);
    }
    
    /**
     * Enable a specific output handler
     * 
     * @param string $handlerName Handler name
     * @return bool Success
     */
    public function enableOutput($handlerName)
    {
        $enabled = $this->getEnabledOutputs();
        
        if (!in_array($handlerName, $enabled)) {
            $enabled[] = $handlerName;
            return $this->setEnabledOutputs($enabled);
        }
        
        return true;
    }
    
    /**
     * Disable a specific output handler
     * 
     * @param string $handlerName Handler name
     * @return bool Success
     */
    public function disableOutput($handlerName)
    {
        $enabled = $this->getEnabledOutputs();
        $enabled = array_filter($enabled, function($name) use ($handlerName) {
            return $name !== $handlerName;
        });
        
        return $this->setEnabledOutputs(array_values($enabled));
    }
    
    /**
     * Get configuration for a specific handler
     * 
     * @param string $handlerName Handler name
     * @return array Handler configuration
     */
    public function getHandlerConfig($handlerName)
    {
        $allConfig = $this->getAll();
        $handlerConfig = [];
        
        // Find all config keys that start with handler name
        foreach ($allConfig as $key => $value) {
            if (strpos($key, $handlerName . '_') === 0) {
                $handlerConfig[$key] = $value;
            }
        }
        
        return $handlerConfig;
    }
    
    /**
     * Clear all cached values
     * 
     * @return void
     */
    public function clearCache()
    {
        $this->cache = [];
    }
    
    /**
     * Serialize a value for storage
     * 
     * @param mixed $value Value to serialize
     * @return string Serialized value
     */
    private function serializeValue($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        return (string)$value;
    }
    
    /**
     * Unserialize a value from storage
     * 
     * @param string $value Serialized value
     * @return mixed Unserialized value
     */
    private function unserializeValue($value)
    {
        // Try JSON decode first
        if ($value && ($value[0] === '[' || $value[0] === '{')) {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        
        // Handle boolean strings
        if ($value === '1' || $value === 'true') {
            return true;
        }
        if ($value === '0' || $value === 'false') {
            return false;
        }
        
        // Return as-is
        return $value;
    }
    
    /**
     * Export configuration to array (for backup/transfer)
     * 
     * @return array Configuration array
     */
    public function exportConfig()
    {
        return $this->getAll();
    }
    
    /**
     * Import configuration from array
     * 
     * @param array $config Configuration array
     * @param bool $overwrite Overwrite existing values (default: true)
     * @return bool Success
     */
    public function importConfig(array $config, $overwrite = true)
    {
        $success = true;
        
        foreach ($config as $key => $value) {
            // Skip if not overwriting and value exists
            if (!$overwrite && $this->get($key) !== null) {
                continue;
            }
            
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }
        
        return $success;
    }
}
