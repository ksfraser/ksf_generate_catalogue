<?php

/**
 * Catalogue Output Orchestrator
 * 
 * Main orchestrator class that handles configuration management and coordinates
 * the generation of catalogue outputs to multiple destinations. Implements the
 * Single Responsibility Principle by delegating specific concerns to specialized
 * components (discovery, factory, handlers).
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
 * Orchestrates catalogue output generation across multiple destinations
 * 
 * This class is the main entry point for generating catalogue outputs.
 * It handles:
 * - Loading and managing configuration
 * - Discovering available output handlers
 * - Creating and configuring output handlers via factory
 * - Executing output generation (single or multiple destinations)
 * - Collecting and reporting results
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class CatalogueOutputOrchestrator
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
     * Output handler factory
     * 
     * @var OutputHandlerFactory
     */
    private $handlerFactory;
    
    /**
     * Global configuration array
     * 
     * @var array
     */
    private $config;
    
    /**
     * Execution results
     * 
     * @var array
     */
    private $results;
    
    /**
     * Constructor
     * 
     * @param DatabaseInterface $database Database interface instance
     * @param string $prefs_tablename Preferences table name
     * @param array $config Initial configuration (optional)
     */
    public function __construct(DatabaseInterface $database, $prefs_tablename, array $config = [])
    {
        $this->database = $database;
        $this->prefs_tablename = $prefs_tablename;
        $this->config = $config;
        $this->results = [];
        
        // Initialize factory with database and preferences
        $this->handlerFactory = new OutputHandlerFactory($database, $prefs_tablename, $config);
    }
    
    /**
     * Load configuration from database preferences
     * 
     * @param string $prefix Preference key prefix (optional)
     * @return self For method chaining
     */
    public function loadConfigFromDatabase(string $prefix = 'output_')
    {
        try {
            $configManager = new OutputConfigurationManager($this->database, $this->prefs_tablename, $prefix);
            $this->setConfigs($configManager->getAll());
        } catch (Exception $e) {
            error_log("CatalogueOutputOrchestrator: Error loading config: " . $e->getMessage());
        }
        
        return $this;
    }
    
    /**
     * Set a configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return self For method chaining
     */
    public function setConfig(string $key, $value)
    {
        $this->config[$key] = $value;
        return $this;
    }
    
    /**
     * Set multiple configuration values
     * 
     * @param array $config Configuration array
     * @return self For method chaining
     */
    public function setConfigs(array $config)
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
    
    /**
     * Get a configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Get all configuration
     * 
     * @return array Complete configuration array
     */
    public function getAllConfig()
    {
        return $this->config;
    }
    
    /**
     * Get list of all available output handlers
     * 
     * @param bool $forceRefresh Force refresh of discovered handlers
     * @return array List of handler metadata
     */
    public function getAvailableOutputs($forceRefresh = false)
    {
        return $this->handlerFactory->getAvailableHandlers($forceRefresh);
    }
    
    /**
     * Get enabled output handlers from configuration
     * 
     * @return array Array of enabled handler names
     */
    public function getEnabledOutputs()
    {
        $enabledOutputs = $this->getConfig('enabled_outputs', []);
        
        // If it's a string, convert to array
        if (is_string($enabledOutputs)) {
            $enabledOutputs = array_filter(explode(',', $enabledOutputs));
        }
        
        return $enabledOutputs;
    }
    
    /**
     * Set which outputs are enabled
     * 
     * @param array $outputNames Array of output handler names to enable
     * @return self For method chaining
     */
    public function setEnabledOutputs(array $outputNames)
    {
        $this->config['enabled_outputs'] = $outputNames;
        return $this;
    }
    
    /**
     * Generate output for a single destination
     * 
     * @param string $outputName Output handler name
     * @param array $config Handler-specific configuration (optional)
     * @return array Result information
     */
    public function generateOutput(string $outputName, array $config = [])
    {
        $startTime = microtime(true);
        
        try {
            // Create the output handler
            $handler = $this->handlerFactory->createHandler($outputName, $config);
            
            // Generate the output
            $result = $handler->generateOutput();
            
            // Add execution time
            $result['execution_time'] = microtime(true) - $startTime;
            $result['handler_name'] = $outputName;
            
            // Store result
            $this->results[$outputName] = $result;
            
            return $result;
            
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'handler_name' => $outputName,
                'rows' => 0,
                'files' => [],
                'message' => 'Error: ' . $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ];
            
            $this->results[$outputName] = $result;
            
            return $result;
        }
    }
    
    /**
     * Generate outputs for multiple destinations
     * 
     * @param array $outputNames Array of output handler names (if empty, uses enabled outputs)
     * @param bool $stopOnError Stop execution if an output fails (default: false)
     * @return array Array of results indexed by handler name
     */
    public function generateOutputs(array $outputNames = [], bool $stopOnError = false)
    {
        // If no outputs specified, use enabled outputs from config
        if (empty($outputNames)) {
            $outputNames = $this->getEnabledOutputs();
        }
        
        // If still empty, use all available outputs
        if (empty($outputNames)) {
            $allHandlers = $this->getAvailableOutputs();
            $outputNames = array_column($allHandlers, 'name');
        }
        
        $results = [];
        
        foreach ($outputNames as $outputName) {
            $result = $this->generateOutput($outputName);
            $results[$outputName] = $result;
            
            // Stop on error if requested
            if ($stopOnError && !$result['success']) {
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Generate all available outputs
     * 
     * @param array $excludeCategories Categories to exclude (optional)
     * @param bool $stopOnError Stop execution if an output fails (default: false)
     * @return array Array of results indexed by handler name
     */
    public function generateAllOutputs(array $excludeCategories = [], bool $stopOnError = false)
    {
        $allHandlers = $this->getAvailableOutputs();
        $outputNames = [];
        
        foreach ($allHandlers as $handler) {
            if (!in_array($handler['category'], $excludeCategories)) {
                $outputNames[] = $handler['name'];
            }
        }
        
        return $this->generateOutputs($outputNames, $stopOnError);
    }
    
    /**
     * Get results from previous executions
     * 
     * @param string $outputName Specific output name (optional, returns all if not specified)
     * @return array|null Results array or null if not found
     */
    public function getResults(string $outputName = null)
    {
        if ($outputName === null) {
            return $this->results;
        }
        
        return $this->results[$outputName] ?? null;
    }
    
    /**
     * Get a summary of all results
     * 
     * @return array Summary with total counts and status
     */
    public function getResultsSummary()
    {
        $summary = [
            'total_handlers' => count($this->results),
            'successful' => 0,
            'failed' => 0,
            'total_rows' => 0,
            'total_files' => 0,
            'total_execution_time' => 0
        ];
        
        foreach ($this->results as $result) {
            if ($result['success']) {
                $summary['successful']++;
            } else {
                $summary['failed']++;
            }
            
            $summary['total_rows'] += $result['rows'] ?? 0;
            $summary['total_files'] += count($result['files'] ?? []);
            $summary['total_execution_time'] += $result['execution_time'] ?? 0;
        }
        
        return $summary;
    }
    
    /**
     * Clear stored results
     * 
     * @return self For method chaining
     */
    public function clearResults()
    {
        $this->results = [];
        return $this;
    }
    
    /**
     * Get status information for all available handlers
     * 
     * @return array Array of handler statuses
     */
    public function getHandlerStatuses()
    {
        return $this->handlerFactory->getHandlerStatuses();
    }
    
    /**
     * Validate configuration for a specific handler
     * 
     * @param string $handlerName Handler name
     * @return array Validation result with 'valid' and 'errors' keys
     */
    public function validateHandlerConfig(string $handlerName)
    {
        return $this->handlerFactory->validateHandlerConfig($handlerName, $this->config);
    }
    
    /**
     * Validate configuration for all enabled handlers
     * 
     * @return array Validation results indexed by handler name
     */
    public function validateAllEnabledConfigs()
    {
        $enabledOutputs = $this->getEnabledOutputs();
        $validationResults = [];
        
        foreach ($enabledOutputs as $outputName) {
            $validationResults[$outputName] = $this->validateHandlerConfig($outputName);
        }
        
        return $validationResults;
    }
}
