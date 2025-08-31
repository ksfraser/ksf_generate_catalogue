<?php

/**
 * Generator Discovery Service
 * 
 * This service scans directories for generator classes and automatically
 * registers them based on their metadata interface implementations.
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
 * Automatic Generator Discovery Service
 * 
 * Scans directories for PHP files containing generator classes that implement
 * GeneratorMetadataInterface and automatically discovers their metadata.
 * 
 * @package Ksfraser\Frontaccounting\GenCat
 * @author  KS Fraser <kevin@ksfraser.com>
 * @since   1.0.0
 */
class GeneratorDiscovery
{
    /**
     * Default directories to scan for generators
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
     * Cache of discovered generators
     * 
     * @var array|null
     */
    private $discoveredGenerators = null;
    
    /**
     * Constructor
     * 
     * @param array $scanDirectories Directories to scan for generators
     * @param string $namespacePrefix Namespace prefix for discovered classes
     */
    public function __construct(array $scanDirectories = null, string $namespacePrefix = 'Ksfraser\\Frontaccounting\\GenCat\\')
    {
        $this->scanDirectories = $scanDirectories ?: [__DIR__];
        $this->namespacePrefix = $namespacePrefix;
    }
    
    /**
     * Discover all available generators by scanning directories
     * 
     * @param bool $forceRefresh Force refresh of cached results
     * @return array Array of generator metadata sorted by priority
     */
    public function discoverGenerators(bool $forceRefresh = false): array
    {
        if ($this->discoveredGenerators !== null && !$forceRefresh) {
            return $this->discoveredGenerators;
        }
        
        $generators = [];
        
        foreach ($this->scanDirectories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }
            
            $generators = array_merge($generators, $this->scanDirectory($directory));
        }
        
        // Sort by priority (lower number = higher priority)
        usort($generators, function($a, $b) {
            $priorityA = $a['priority'] ?? 100;
            $priorityB = $b['priority'] ?? 100;
            
            if ($priorityA === $priorityB) {
                // If same priority, sort by title alphabetically
                return strcmp($a['title'], $b['title']);
            }
            
            return $priorityA - $priorityB;
        });
        
        $this->discoveredGenerators = $generators;
        return $generators;
    }
    
    /**
     * Scan a specific directory for generator classes
     * 
     * @param string $directory Directory path to scan
     * @return array Array of generator metadata from this directory
     */
    private function scanDirectory(string $directory): array
    {
        $generators = [];
        
        try {
            $iterator = new DirectoryIterator($directory);
            
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isDot() || !$fileInfo->isFile()) {
                    continue;
                }
                
                if ($fileInfo->getExtension() !== 'php') {
                    continue;
                }
                
                $generator = $this->analyzeFile($fileInfo->getPathname(), $fileInfo->getBasename('.php'));
                if ($generator !== null) {
                    $generators[] = $generator;
                }
            }
        } catch (Exception $e) {
            // Log error but continue - don't break discovery for one bad directory
            error_log("Generator discovery error in directory $directory: " . $e->getMessage());
        }
        
        return $generators;
    }
    
    /**
     * Analyze a PHP file to see if it contains a generator class
     * 
     * @param string $filepath Path to the PHP file
     * @param string $className Expected class name (from filename)
     * @return array|null Generator metadata or null if not a generator
     */
    private function analyzeFile(string $filepath, string $className): ?array
    {
        try {
            // Build the full class name with namespace
            $fullClassName = $this->namespacePrefix . $className;
            
            // Check if class exists (this will autoload if needed)
            if (!class_exists($fullClassName)) {
                return null;
            }
            
            $reflection = new ReflectionClass($fullClassName);
            
            // Skip abstract classes and interfaces
            if ($reflection->isAbstract() || $reflection->isInterface()) {
                return null;
            }
            
            // Check if it implements our metadata interface
            if (!$reflection->implementsInterface(GeneratorMetadataInterface::class)) {
                return null;
            }
            
            // Check if it extends BaseCatalogueGenerator (our base class)
            if (!$reflection->isSubclassOf(BaseCatalogueGenerator::class)) {
                return null;
            }
            
            // Get metadata from the class
            $metadata = $fullClassName::getGeneratorMetadata();
            $priority = $fullClassName::getGeneratorPriority();
            $available = $fullClassName::isGeneratorAvailable();
            
            // Skip if not available
            if (!$available) {
                return null;
            }
            
            // Ensure required metadata fields are present
            $requiredFields = ['name', 'title', 'class', 'description', 'method'];
            foreach ($requiredFields as $field) {
                if (empty($metadata[$field])) {
                    error_log("Generator $fullClassName missing required field: $field");
                    return null;
                }
            }
            
            // Add additional discovery metadata
            $metadata['priority'] = $priority;
            $metadata['full_class_name'] = $fullClassName;
            $metadata['file_path'] = $filepath;
            $metadata['discovered_at'] = date('c');
            
            return $metadata;
            
        } catch (Exception $e) {
            // Log error but continue - don't break discovery for one bad file
            error_log("Generator discovery error analyzing $filepath: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Add additional scan directory
     * 
     * @param string $directory Directory path to add to scan list
     * @return self
     */
    public function addScanDirectory(string $directory): self
    {
        if (!in_array($directory, $this->scanDirectories)) {
            $this->scanDirectories[] = $directory;
            $this->discoveredGenerators = null; // Reset cache
        }
        return $this;
    }
    
    /**
     * Get list of scan directories
     * 
     * @return array List of directories being scanned
     */
    public function getScanDirectories(): array
    {
        return $this->scanDirectories;
    }
    
    /**
     * Clear the discovery cache
     * 
     * @return self
     */
    public function clearCache(): self
    {
        $this->discoveredGenerators = null;
        return $this;
    }
    
    /**
     * Find a specific generator by name
     * 
     * @param string $generatorName Name of the generator to find
     * @return array|null Generator metadata or null if not found
     */
    public function findGenerator(string $generatorName): ?array
    {
        $generators = $this->discoverGenerators();
        
        foreach ($generators as $generator) {
            if ($generator['name'] === $generatorName) {
                return $generator;
            }
        }
        
        return null;
    }
    
    /**
     * Get generators by category
     * 
     * @param string $category Category to filter by
     * @return array Array of generators in the specified category
     */
    public function getGeneratorsByCategory(string $category): array
    {
        $generators = $this->discoverGenerators();
        
        return array_filter($generators, function($generator) use ($category) {
            return ($generator['category'] ?? 'default') === $category;
        });
    }
}
