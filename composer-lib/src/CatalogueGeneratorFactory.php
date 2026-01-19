<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Catalogue Generator Factory
 * 
 * Factory class for creating catalogue generators with proper dependency injection
 * Enhanced with automatic generator discovery capabilities.
 */
class CatalogueGeneratorFactory
{
    private $database;
    private $prefs_tablename;
    private $generatorDiscovery;

    public function __construct(DatabaseInterface $database, $prefs_tablename)
    {
        $this->database = $database;
        $this->prefs_tablename = $prefs_tablename;
        $this->generatorDiscovery = new GeneratorDiscovery();
    }

    /**
     * Create a WooCommerce import generator
     * 
     * @param array $config Configuration array
     * @return WoocommerceImport
     */
    public function createWoocommerceImport($config = [])
    {
        $generator = new WoocommerceImport($this->prefs_tablename);
        $generator->setDatabase($this->database);
        $this->applyConfig($generator, $config);
        return $generator;
    }

    /**
     * Create a Square catalog generator
     * 
     * @param array $config Configuration array
     * @return SquareCatalog
     */
    public function createSquareCatalog($config = [])
    {
        $generator = new SquareCatalog($this->prefs_tablename);
        $generator->setDatabase($this->database);
        $this->applyConfig($generator, $config);
        return $generator;
    }

    /**
     * Create a labels file generator
     * 
     * @param array $config Configuration array
     * @return LabelsFile
     */
    public function createLabelsFile($config = [])
    {
        $generator = new LabelsFile($this->prefs_tablename);
        $generator->setDatabase($this->database);
        $this->applyConfig($generator, $config);
        return $generator;
    }

    /**
     * Create a pricebook file generator
     * 
     * @param array $config Configuration array
     * @return PricebookFile
     */
    public function createPricebookFile($config = [])
    {
        $generator = new PricebookFile($this->prefs_tablename);
        $generator->setDatabase($this->database);
        $this->applyConfig($generator, $config);
        return $generator;
    }

    /**
     * Create a WooPOS count generator
     * 
     * @param array $config Configuration array
     * @return WooPOSCount
     */
    public function createWooPOSCount($config = [])
    {
        $generator = new WooPOSCount($this->prefs_tablename);
        $generator->setDatabase($this->database);
        $this->applyConfig($generator, $config);
        return $generator;
    }

    /**
     * Create an Amazon import generator
     *
     * @param array $config Configuration array
     * @return AmazonImport
     */
    public function createAmazonImport($config = [])
    {
        $generator = new AmazonImport($this->prefs_tablename);
        $generator->setDatabase($this->database);
        $this->applyConfig($generator, $config);
        return $generator;
    }

    /**
     * Create a Phomemo thermal printer labels generator
     *
     * @param array $config Configuration array
     * @return PhomemoPrinterOutput
     */
    public function createPhomemoPrinterOutput($config = [])
    {
        $generator = new PhomemoPrinterOutput($this->prefs_tablename);
        $generator->setDatabase($this->database);
        $this->applyConfig($generator, $config);
        return $generator;
    }

    /**
     * Get list of available generators using dynamic discovery
     * 
     * @param bool $forceRefresh Force refresh of discovered generators
     * @return array List of available generator types with metadata
     */
    public function getAvailableGenerators($forceRefresh = false)
    {
        try {
            // Use discovery service to find generators dynamically
            return $this->generatorDiscovery->discoverGenerators($forceRefresh);
        } catch (\Exception $e) {
            // Fallback to static list if discovery fails
            error_log("Generator discovery failed, using static fallback: " . $e->getMessage());
            return $this->getStaticGeneratorList();
        }
    }

    /**
     * Static fallback list of generators
     * 
     * @return array Static list of generators for fallback
     */
    private function getStaticGeneratorList()
    {
        return [
            [
                'name' => 'pricebook',
                'title' => 'Pricebook File',
                'class' => 'PricebookFile',
                'description' => 'Generate pricebook CSV file for retail pricing and catalogues',
                'method' => 'createPricebookFile',
                'category' => 'catalogue',
                'version' => '1.0.0',
                'author' => 'KS Fraser',
                'priority' => 10,
                'full_class_name' => 'Ksfraser\\Frontaccounting\\GenCat\\PricebookFile'
            ],
            [
                'name' => 'square',
                'title' => 'Square Catalog',
                'class' => 'SquareCatalog', 
                'description' => 'Generate Square catalog import CSV file for POS integration',
                'method' => 'createSquareCatalog',
                'category' => 'pos',
                'version' => '1.0.0',
                'author' => 'KS Fraser',
                'priority' => 25,
                'full_class_name' => 'Ksfraser\\Frontaccounting\\GenCat\\SquareCatalog'
            ],
            [
                'name' => 'woocommerce',
                'title' => 'WooCommerce Import',
                'class' => 'WoocommerceImport',
                'description' => 'Generate WooCommerce product import CSV file',
                'method' => 'createWoocommerceImport',
                'category' => 'ecommerce',
                'version' => '1.0.0',
                'author' => 'KS Fraser',
                'priority' => 20,
                'full_class_name' => 'Ksfraser\\Frontaccounting\\GenCat\\WoocommerceImport'
            ],
            [
                'name' => 'woopos',
                'title' => 'WooPOS Count',
                'class' => 'WooPOSCount',
                'description' => 'Generate WooCommerce POS inventory count CSV file',
                'method' => 'createWooPOSCount',
                'category' => 'inventory',
                'version' => '1.0.0',
                'author' => 'KS Fraser',
                'priority' => 30,
                'full_class_name' => 'Ksfraser\\Frontaccounting\\GenCat\\WooPOSCount'
            ],
            [
                'name' => 'labels',
                'title' => 'Labels File',
                'class' => 'LabelsFile',
                'description' => 'Generate product labels CSV file for printing',
                'method' => 'createLabelsFile',
                'category' => 'printing',
                'version' => '1.0.0',
                'author' => 'KS Fraser',
                'priority' => 50,
                'full_class_name' => 'Ksfraser\\Frontaccounting\\GenCat\\LabelsFile'
            ]
        ];
    }

    /**
     * Create generator by name using dynamic discovery
     * 
     * @param string $generatorName Name of the generator
     * @param array $config Configuration array
     * @return BaseCatalogueGenerator
     * @throws \Exception If generator not found
     */
    public function createGeneratorByName($generatorName, $config = [])
    {
        // First try to find the generator via discovery
        $generatorInfo = $this->generatorDiscovery->findGenerator($generatorName);
        
        if ($generatorInfo) {
            $className = $generatorInfo['full_class_name'];
            if (class_exists($className)) {
                $generator = new $className($this->prefs_tablename);
                $generator->setDatabase($this->database);
                $this->applyConfig($generator, $config);
                return $generator;
            }
        }
        
        // Fallback to static factory methods
        $generators = $this->getAvailableGenerators();
        
        foreach ($generators as $generatorInfo) {
            if ($generatorInfo['name'] === $generatorName) {
                $methodName = $generatorInfo['method'];
                if (method_exists($this, $methodName)) {
                    return $this->$methodName($config);
                }
            }
        }
        
        throw new \Exception("Generator '$generatorName' not found");
    }

    /**
     * Add additional directory to scan for generators
     * 
     * @param string $directory Directory path to scan
     * @return self
     */
    public function addGeneratorDirectory($directory)
    {
        $this->generatorDiscovery->addScanDirectory($directory);
        return $this;
    }

    /**
     * Get generators by category
     * 
     * @param string $category Category to filter by
     * @return array Array of generators in the specified category
     */
    public function getGeneratorsByCategory($category)
    {
        return $this->generatorDiscovery->getGeneratorsByCategory($category);
    }

    /**
     * Clear discovery cache and force refresh
     * 
     * @return self
     */
    public function refreshGenerators()
    {
        $this->generatorDiscovery->clearCache();
        return $this;
    }

    /**
     * Apply configuration to a generator
     * 
     * @param BaseCatalogueGenerator $generator
     * @param array $config
     */
    private function applyConfig(BaseCatalogueGenerator $generator, $config)
    {
        foreach ($config as $key => $value) {
            $method = 'set' . ucfirst($key);
            if (method_exists($generator, $method)) {
                $generator->$method($value);
            }
        }
    }
}
