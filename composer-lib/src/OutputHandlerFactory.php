<?php

/**
 * Output Handler Factory
 * 
 * Factory class for creating output handler instances with proper dependency injection.
 * Enhanced with automatic output handler discovery capabilities. Uses the SRP principle
 * to separate concerns of handler discovery, instantiation, and configuration.
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
 * Factory for creating output handlers with dependency injection
 * 
 * This factory uses the OutputHandlerDiscovery service to automatically
 * find and instantiate output handlers. It handles dependency injection
 * and configuration management for all handlers.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class OutputHandlerFactory
{
    /**
     * Database interface instance
     * 
     * @var DatabaseInterface
     */
    private $database;
    
    /**
     * Preferences table name
     * 
     * @var string
     */
    private $prefs_tablename;
    
    /**
     * Output handler discovery service
     * 
     * @var OutputHandlerDiscovery
     */
    private $handlerDiscovery;
    
    /**
     * Configuration manager
     * 
     * @var array
     */
    private $globalConfig;
    
    /**
     * Constructor
     * 
     * @param DatabaseInterface $database Database interface instance
     * @param string $prefs_tablename Preferences table name
     * @param array $config Global configuration array (optional)
     */
    public function __construct(DatabaseInterface $database, $prefs_tablename, array $config = [])
    {
        $this->database = $database;
        $this->prefs_tablename = $prefs_tablename;
        $this->globalConfig = $config;
        $this->handlerDiscovery = new OutputHandlerDiscovery();
    }
    
    /**
     * Add a directory to scan for output handlers
     * 
     * @param string $directory Absolute path to directory
     * @return self For method chaining
     */
    public function addHandlerDirectory(string $directory)
    {
        $this->handlerDiscovery->addScanDirectory($directory);
        return $this;
    }
    
    /**
     * Get list of available output handlers using dynamic discovery
     * 
     * @param bool $forceRefresh Force refresh of discovered handlers
     * @return array List of available handler types with metadata
     */
    public function getAvailableHandlers($forceRefresh = false)
    {
        return $this->handlerDiscovery->discoverOutputHandlers($forceRefresh);
    }
    
    /**
     * Get output handlers filtered by category
     * 
     * @param string $category Category to filter by (e.g., 'pos', 'ecommerce', 'printing')
     * @param bool $forceRefresh Force refresh of cached results
     * @return array Filtered array of output handler metadata
     */
    public function getHandlersByCategory(string $category, bool $forceRefresh = false)
    {
        return $this->handlerDiscovery->getHandlersByCategory($category, $forceRefresh);
    }
    
    /**
     * Create an output handler by name
     * 
     * @param string $handlerName Handler name (e.g., 'square', 'woocommerce', 'labels')
     * @param array $config Handler-specific configuration (optional)
     * @return OutputHandlerInterface|null Instantiated handler or null if not found
     * @throws Exception If handler cannot be created
     */
    public function createHandler(string $handlerName, array $config = [])
    {
        $metadata = $this->handlerDiscovery->getHandlerByName($handlerName);
        
        if (!$metadata) {
            throw new Exception("Output handler not found: {$handlerName}");
        }
        
        $className = $metadata['class_name'];
        
        if (!class_exists($className)) {
            throw new Exception("Output handler class does not exist: {$className}");
        }
        
        // Instantiate the handler (assuming it extends BaseCatalogueGenerator)
        $handler = new $className($this->prefs_tablename);
        
        // Inject database dependency
        if (method_exists($handler, 'setDatabase')) {
            $handler->setDatabase($this->database);
        }
        
        // Apply configuration
        $mergedConfig = array_merge($this->globalConfig, $config);
        $this->applyConfig($handler, $mergedConfig);
        
        return $handler;
    }
    
    /**
     * Create multiple output handlers at once
     * 
     * @param array $handlerNames Array of handler names to create
     * @param array $config Global configuration to apply to all handlers
     * @return array Array of instantiated handlers indexed by handler name
     */
    public function createHandlers(array $handlerNames, array $config = [])
    {
        $handlers = [];
        
        foreach ($handlerNames as $name) {
            try {
                $handlers[$name] = $this->createHandler($name, $config);
            } catch (Exception $e) {
                // Log error but continue creating other handlers
                error_log("OutputHandlerFactory: Failed to create handler '{$name}': " . $e->getMessage());
            }
        }
        
        return $handlers;
    }
    
    /**
     * Create all available output handlers
     * 
     * @param array $config Global configuration to apply to all handlers
     * @param array $excludeCategories Categories to exclude (optional)
     * @return array Array of instantiated handlers indexed by handler name
     */
    public function createAllHandlers(array $config = [], array $excludeCategories = [])
    {
        $allHandlers = $this->getAvailableHandlers();
        $handlers = [];
        
        foreach ($allHandlers as $metadata) {
            // Skip excluded categories
            if (in_array($metadata['category'], $excludeCategories)) {
                continue;
            }
            
            try {
                $handlers[$metadata['name']] = $this->createHandler($metadata['name'], $config);
            } catch (Exception $e) {
                error_log("OutputHandlerFactory: Failed to create handler '{$metadata['name']}': " . $e->getMessage());
            }
        }
        
        return $handlers;
    }
    
    /**
     * Apply configuration to a handler
     * 
     * @param object $handler Handler instance
     * @param array $config Configuration array
     * @return void
     */
    private function applyConfig($handler, array $config)
    {
        foreach ($config as $key => $value) {
            $setterMethod = 'set' . str_replace('_', '', ucwords($key, '_'));
            
            if (method_exists($handler, $setterMethod)) {
                $handler->$setterMethod($value);
            } elseif (property_exists($handler, $key)) {
                $handler->$key = $value;
            }
        }
    }
    
    /**
     * Get configuration schema for a specific handler
     * 
     * @param string $handlerName Handler name
     * @return array Configuration schema
     * @throws Exception If handler not found
     */
    public function getHandlerConfigSchema(string $handlerName)
    {
        $handler = $this->createHandler($handlerName);
        
        if (!$handler) {
            throw new Exception("Cannot get config schema: handler not found: {$handlerName}");
        }
        
        return $handler->getConfigurationSchema();
    }
    
    /**
     * Validate configuration for a specific handler
     * 
     * @param string $handlerName Handler name
     * @param array $config Configuration to validate
     * @return array Validation result with 'valid' and 'errors' keys
     */
    public function validateHandlerConfig(string $handlerName, array $config)
    {
        try {
            $handler = $this->createHandler($handlerName, $config);
            return $handler->validateConfiguration();
        } catch (Exception $e) {
            return [
                'valid' => false,
                'errors' => ['Failed to create handler: ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Get status information for all available handlers
     * 
     * @return array Array of handler statuses indexed by handler name
     */
    public function getHandlerStatuses()
    {
        $allHandlers = $this->getAvailableHandlers();
        $statuses = [];
        
        foreach ($allHandlers as $metadata) {
            try {
                $handler = $this->createHandler($metadata['name']);
                $statuses[$metadata['name']] = [
                    'title' => $metadata['title'],
                    'category' => $metadata['category'],
                    'status' => $handler->getStatus(),
                    'available' => true
                ];
            } catch (Exception $e) {
                $statuses[$metadata['name']] = [
                    'title' => $metadata['title'],
                    'category' => $metadata['category'],
                    'status' => 'Error: ' . $e->getMessage(),
                    'available' => false
                ];
            }
        }
        
        return $statuses;
    }
    
    /**
     * Check if a specific handler is available
     * 
     * @param string $handlerName Handler name
     * @return bool True if handler is available
     */
    public function isHandlerAvailable(string $handlerName)
    {
        $metadata = $this->handlerDiscovery->getHandlerByName($handlerName);
        return $metadata !== null;
    }
}
