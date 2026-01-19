<?php

/**
 * Output Handler Discovery Service
 * 
 * This service scans directories for output handler classes and automatically
 * registers them based on their metadata interface implementations.
 * Similar to GeneratorDiscovery but specifically for output destinations.
 * 
 * @package   Ksfraser\Frontaccounting\GenCat
 * @author    KS Fraser <kevin@ksfraser.com>
 * @copyright 2025 KS Fraser
 * @license   GPL-3.0-or-later
 * @version   1.0.0
 * @since     1.0.0
 */

namespace Ksfraser\Frontaccounting\GenCat;

use DirectoryIterator;
use ReflectionClass;
use Exception;

/**
 * Automatic Output Handler Discovery Service
 * 
 * Scans directories for PHP files containing output handler classes that implement
 * OutputHandlerInterface and automatically discovers their metadata.
 * Enables a true plugin architecture where new output destinations can be added
 * by simply dropping a new file into the scan directory.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class OutputHandlerDiscovery
{
    /**
     * Default directories to scan for output handlers
     * 
     * @var array
     */
    private $scanDirectories;
    
    /**
     * Namespace prefix for discovered classes
     * 
     * @var string
     */
    private $namespacePrefix;
    
    /**
     * Cache of discovered output handlers
     * 
     * @var array|null
     */
    private $discoveredHandlers = null;
    
    /**
     * Constructor
     * 
     * @param array $scanDirectories Directories to scan for output handlers
     * @param string $namespacePrefix Namespace prefix for discovered classes
     */
    public function __construct(array $scanDirectories = null, string $namespacePrefix = 'Ksfraser\\Frontaccounting\\GenCat\\')
    {
        $this->scanDirectories = $scanDirectories ?: [__DIR__];
        $this->namespacePrefix = $namespacePrefix;
    }
    
    /**
     * Add a directory to scan for output handlers
     * 
     * @param string $directory Absolute path to directory
     * @return self For method chaining
     */
    public function addScanDirectory(string $directory)
    {
        if (!in_array($directory, $this->scanDirectories)) {
            $this->scanDirectories[] = $directory;
            $this->discoveredHandlers = null; // Clear cache
        }
        return $this;
    }
    
    /**
     * Discover all available output handlers by scanning directories
     * 
     * @param bool $forceRefresh Force refresh of cached results
     * @return array Array of output handler metadata sorted by priority
     */
    public function discoverOutputHandlers(bool $forceRefresh = false): array
    {
        if ($this->discoveredHandlers !== null && !$forceRefresh) {
            return $this->discoveredHandlers;
        }
        
        $handlers = [];
        
        foreach ($this->scanDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            
            $handlers = array_merge($handlers, $this->scanDirectory($directory));
        }
        
        // Sort by priority (lower number = higher priority)
        usort($handlers, function($a, $b) {
            $priorityA = $a['priority'] ?? 100;
            $priorityB = $b['priority'] ?? 100;
            
            if ($priorityA === $priorityB) {
                // If same priority, sort by title alphabetically
                return strcmp($a['title'], $b['title']);
            }
            
            return $priorityA - $priorityB;
        });
        
        $this->discoveredHandlers = $handlers;
        return $handlers;
    }
    
    /**
     * Scan a directory for output handler classes
     * 
     * @param string $directory Directory to scan
     * @return array Array of discovered handler metadata
     */
    private function scanDirectory(string $directory): array
    {
        $handlers = [];
        
        try {
            $iterator = new DirectoryIterator($directory);
            
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot() || !$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
                    continue;
                }
                
                $className = $this->getClassNameFromFile($fileInfo->getPathname());
                
                if ($className && $this->isOutputHandler($className)) {
                    $metadata = $this->extractHandlerMetadata($className);
                    if ($metadata) {
                        $handlers[] = $metadata;
                    }
                }
            }
        } catch (Exception $e) {
            // Log error but continue - don't break discovery if one directory fails
            error_log("OutputHandlerDiscovery: Error scanning directory {$directory}: " . $e->getMessage());
        }
        
        return $handlers;
    }
    
    /**
     * Extract the class name from a PHP file
     * 
     * @param string $filePath Path to PHP file
     * @return string|null Fully qualified class name or null if not found
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);
        
        // Look for namespace declaration
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/i', $contents, $matches)) {
            $namespace = $matches[1];
        }
        
        // Look for class declaration
        if (preg_match('/class\s+(\w+)(?:\s+extends\s+\w+)?(?:\s+implements\s+[^{]+)?/i', $contents, $matches)) {
            $className = $matches[1];
            return $namespace ? $namespace . '\\' . $className : $className;
        }
        
        return null;
    }
    
    /**
     * Check if a class is a valid output handler
     * 
     * @param string $className Fully qualified class name
     * @return bool True if class implements OutputHandlerInterface
     */
    private function isOutputHandler(string $className): bool
    {
        try {
            if (!class_exists($className)) {
                return false;
            }
            
            $reflection = new ReflectionClass($className);
            
            // Must implement OutputHandlerInterface
            if (!$reflection->implementsInterface('Ksfraser\\Frontaccounting\\GenCat\\OutputHandlerInterface')) {
                return false;
            }
            
            // Must not be abstract
            if ($reflection->isAbstract()) {
                return false;
            }
            
            // Check if handler is available
            if (method_exists($className, 'isOutputHandlerAvailable')) {
                if (!$className::isOutputHandlerAvailable()) {
                    return false;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("OutputHandlerDiscovery: Error checking class {$className}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Extract metadata from an output handler class
     * 
     * @param string $className Fully qualified class name
     * @return array|null Handler metadata or null if extraction failed
     */
    private function extractHandlerMetadata(string $className): ?array
    {
        try {
            $metadata = $className::getOutputHandlerMetadata();
            $priority = $className::getOutputHandlerPriority();
            
            // Ensure required fields exist
            $requiredFields = ['name', 'title', 'class', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($metadata[$field]) || empty($metadata[$field])) {
                    error_log("OutputHandlerDiscovery: Handler {$className} missing required field: {$field}");
                    return null;
                }
            }
            
            // Add the priority and full class name to metadata
            $metadata['priority'] = $priority;
            $metadata['class_name'] = $className;
            
            // Set defaults for optional fields
            $metadata['category'] = $metadata['category'] ?? 'uncategorized';
            $metadata['output_type'] = $metadata['output_type'] ?? 'unknown';
            $metadata['version'] = $metadata['version'] ?? '1.0.0';
            $metadata['author'] = $metadata['author'] ?? 'Unknown';
            $metadata['requires_config'] = $metadata['requires_config'] ?? false;
            
            return $metadata;
            
        } catch (Exception $e) {
            error_log("OutputHandlerDiscovery: Error extracting metadata from {$className}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get output handlers filtered by category
     * 
     * @param string $category Category to filter by (e.g., 'pos', 'ecommerce', 'printing')
     * @param bool $forceRefresh Force refresh of cached results
     * @return array Filtered array of output handler metadata
     */
    public function getHandlersByCategory(string $category, bool $forceRefresh = false): array
    {
        $allHandlers = $this->discoverOutputHandlers($forceRefresh);
        
        return array_filter($allHandlers, function($handler) use ($category) {
            return ($handler['category'] ?? '') === $category;
        });
    }
    
    /**
     * Get a specific output handler by name
     * 
     * @param string $name Handler name (e.g., 'square', 'woocommerce')
     * @param bool $forceRefresh Force refresh of cached results
     * @return array|null Handler metadata or null if not found
     */
    public function getHandlerByName(string $name, bool $forceRefresh = false): ?array
    {
        $allHandlers = $this->discoverOutputHandlers($forceRefresh);
        
        foreach ($allHandlers as $handler) {
            if ($handler['name'] === $name) {
                return $handler;
            }
        }
        
        return null;
    }
    
    /**
     * Get list of all available categories
     * 
     * @param bool $forceRefresh Force refresh of cached results
     * @return array Array of unique category names
     */
    public function getAvailableCategories(bool $forceRefresh = false): array
    {
        $allHandlers = $this->discoverOutputHandlers($forceRefresh);
        
        $categories = array_map(function($handler) {
            return $handler['category'] ?? 'uncategorized';
        }, $allHandlers);
        
        return array_unique($categories);
    }
}
