<?php

/**
 * Catalogue Generator Interface for Self-Describing Generators
 * 
 * This interface ensures all generators can describe themselves with metadata
 * that can be used for dynamic discovery and registration.
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
 * Interface for self-describing catalogue generators
 * 
 * All catalogue generators should implement this interface to provide
 * metadata about themselves for dynamic discovery and registration.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
interface GeneratorMetadataInterface
{
    /**
     * Get generator metadata for dynamic discovery
     * 
     * @return array Associative array containing:
     *               - 'name': string - Unique identifier for the generator
     *               - 'title': string - Human-readable title
     *               - 'class': string - Class name (without namespace)
     *               - 'description': string - Description of what this generator does
     *               - 'method': string - Factory method name to create this generator
     *               - 'category': string - Category for grouping (optional)
     *               - 'version': string - Generator version (optional)
     *               - 'author': string - Generator author (optional)
     */
    public static function getGeneratorMetadata();

    /**
     * Get the priority/order for this generator
     * 
     * Lower numbers = higher priority (displayed first)
     * Default priority is 100
     * 
     * @return int Priority order for display/execution
     */
    public static function getGeneratorPriority();

    /**
     * Check if this generator is available/enabled
     * 
     * Can be used to disable generators based on environment,
     * configuration, or missing dependencies
     * 
     * @return bool True if generator is available, false otherwise
     */
    public static function isGeneratorAvailable();
}
