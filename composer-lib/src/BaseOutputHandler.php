<?php

/**
 * Base Output Handler
 * 
 * Abstract base class providing common functionality for output handlers.
 * Extends BaseCatalogueGenerator and implements OutputHandlerInterface with
 * sensible defaults, allowing concrete handlers to focus on their specific
 * output format logic.
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
 * Abstract base class for output handlers
 * 
 * Provides default implementations of OutputHandlerInterface methods
 * and common functionality, reducing boilerplate in concrete handlers.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
abstract class BaseOutputHandler extends BaseCatalogueGenerator implements OutputHandlerInterface
{
    /**
     * Generate the output file(s)
     * 
     * Default implementation calls the existing createFile() method
     * and wraps the result in the expected format.
     * 
     * @return array Result information
     */
    public function generateOutput()
    {
        try {
            $rowcount = $this->createFile();
            
            return [
                'success' => true,
                'rows' => $rowcount,
                'files' => [$this->getFullFilename()],
                'message' => "Successfully generated {$rowcount} rows"
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
     * Get configuration schema for this output handler
     * 
     * Default implementation returns an empty array.
     * Override this in concrete classes to define configuration options.
     * 
     * @return array Configuration schema
     */
    public function getConfigurationSchema()
    {
        return [];
    }
    
    /**
     * Validate that all required configuration is present and valid
     * 
     * Default implementation performs basic validation.
     * Override this in concrete classes for custom validation logic.
     * 
     * @return array Validation result
     */
    public function validateConfiguration()
    {
        $errors = [];
        $schema = $this->getConfigurationSchema();
        
        foreach ($schema as $key => $definition) {
            if (isset($definition['required']) && $definition['required']) {
                $value = $this->getPreference($key);
                
                if ($value === null || $value === '') {
                    $label = $definition['label'] ?? $key;
                    $errors[] = "Required configuration missing: {$label}";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get a human-readable status message about this handler's readiness
     * 
     * Default implementation checks basic requirements.
     * Override this in concrete classes for custom status checks.
     * 
     * @return string Status message
     */
    public function getStatus()
    {
        $validation = $this->validateConfiguration();
        
        if (!$validation['valid']) {
            return 'Configuration incomplete: ' . implode(', ', $validation['errors']);
        }
        
        return 'Ready to generate';
    }
    
    /**
     * Default implementation: handler is always available
     * 
     * Override this in concrete classes to check for dependencies,
     * environment requirements, etc.
     * 
     * @return bool True if handler is available
     */
    public static function isOutputHandlerAvailable()
    {
        return true;
    }
    
    /**
     * Default priority implementation
     * 
     * Override this in concrete classes to set specific priority.
     * 
     * @return int Priority order (100 = default)
     */
    public static function getOutputHandlerPriority()
    {
        // Try to use the generator priority if it exists
        if (method_exists(get_called_class(), 'getGeneratorPriority')) {
            return static::getGeneratorPriority();
        }
        
        return 100; // Default priority
    }
    
    /**
     * Helper method to get preference with fallback to property
     * 
     * @param string $key Preference key
     * @param mixed $default Default value
     * @return mixed Preference value
     */
    protected function getPreference($key, $default = null)
    {
        // First try to get from database preferences if method exists
        if (method_exists(parent::class, 'getPreference')) {
            $value = parent::getPreference($key, null);
            if ($value !== null) {
                return $value;
            }
        }
        
        // Then try as property
        if (property_exists($this, $key)) {
            return $this->$key;
        }
        
        return $default;
    }
    
    /**
     * Get the full filename with path
     * 
     * @return string Full file path
     */
    protected function getFullFilename()
    {
        // Handle different filename patterns
        if (isset($this->filename)) {
            return $this->filename;
        }
        
        if (isset($this->file_base) && isset($this->file_ext)) {
            $count = isset($this->file_count) ? "_{$this->file_count}" : '';
            return "{$this->file_base}{$count}.{$this->file_ext}";
        }
        
        return 'output.csv';
    }
}
