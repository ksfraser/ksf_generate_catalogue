<?php

/**
 * Output Handler Interface for Self-Describing Output Plugins
 * 
 * This interface ensures all output handlers can describe themselves with metadata
 * that can be used for dynamic discovery and registration. Output handlers are
 * responsible for generating files in specific formats for different destinations
 * (e-commerce platforms, POS systems, printers, etc.).
 * 
 * @package   Ksfraser\Frontaccounting\GenCat
 * @author    KS Fraser <kevin@ksfraser.com>
 * @copyright 2025 KS Fraser
 * @license   GPL-3.0-or-later
 * @version   1.0.0
 * @since     1.0.0
 */

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Interface for self-describing output handlers
 * 
 * All output handlers should implement this interface to provide
 * metadata about themselves for dynamic discovery and registration.
 * This enables a plugin-based architecture where new output formats
 * can be added by simply creating a new class that implements this interface.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
interface OutputHandlerInterface
{
    /**
     * Get output handler metadata for dynamic discovery
     * 
     * @return array Associative array containing:
     *               - 'name': string - Unique identifier for the handler (e.g., 'square', 'woocommerce')
     *               - 'title': string - Human-readable title (e.g., 'Square Catalog')
     *               - 'class': string - Class name (without namespace)
     *               - 'description': string - Description of what this handler outputs
     *               - 'category': string - Category for grouping (e.g., 'pos', 'ecommerce', 'printing')
     *               - 'output_type': string - Type of output (e.g., 'csv', 'pdf', 'json', 'direct')
     *               - 'version': string - Handler version (optional)
     *               - 'author': string - Handler author (optional)
     *               - 'requires_config': bool - Whether this handler requires additional configuration (optional)
     */
    public static function getOutputHandlerMetadata();

    /**
     * Get the priority/order for this output handler
     * 
     * Lower numbers = higher priority (displayed/executed first)
     * Default priority is 100
     * Typical priority ranges:
     *   10-19: Critical outputs (main e-commerce site)
     *   20-39: Important outputs (POS systems)
     *   40-59: Standard outputs (supplementary systems)
     *   60+:   Optional outputs (labels, reports)
     * 
     * @return int Priority order for display/execution
     */
    public static function getOutputHandlerPriority();

    /**
     * Check if this output handler is available/enabled
     * 
     * Can be used to disable handlers based on environment,
     * configuration, or missing dependencies (e.g., printer drivers)
     * 
     * @return bool True if handler is available, false otherwise
     */
    public static function isOutputHandlerAvailable();

    /**
     * Generate the output file(s)
     * 
     * This is the main method that performs the actual output generation.
     * It should handle all aspects of creating the output in the specific
     * format required by this handler.
     * 
     * @return array Result information containing:
     *               - 'success': bool - Whether generation succeeded
     *               - 'rows': int - Number of rows/items processed
     *               - 'files': array - List of generated file paths
     *               - 'message': string - Status or error message
     */
    public function generateOutput();

    /**
     * Get configuration schema for this output handler
     * 
     * Returns an array describing the configuration options this handler needs.
     * Used to dynamically build UI forms and validate configuration.
     * 
     * @return array Configuration schema with keys being config names and values containing:
     *               - 'label': string - Display label for the option
     *               - 'type': string - Input type (text, select, yes_no, number, etc.)
     *               - 'description': string - Help text for the option
     *               - 'required': bool - Whether this option is required (optional, default false)
     *               - 'default': mixed - Default value (optional)
     *               - 'options': array - For select types, the available options (optional)
     */
    public function getConfigurationSchema();

    /**
     * Validate that all required configuration is present and valid
     * 
     * @return array Validation result:
     *               - 'valid': bool - Whether configuration is valid
     *               - 'errors': array - List of validation error messages
     */
    public function validateConfiguration();

    /**
     * Get a human-readable status message about this handler's readiness
     * 
     * @return string Status message (e.g., "Ready to generate", "Missing printer driver")
     */
    public function getStatus();
}
