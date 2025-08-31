<?php

namespace Ksfraser\Frontaccounting\GenCat;

/**
 * Catalogue Generator Factory
 * 
 * Factory class for creating catalogue generators with proper dependency injection
 */
class CatalogueGeneratorFactory
{
    private $database;
    private $prefs_tablename;

    public function __construct(DatabaseInterface $database, $prefs_tablename)
    {
        $this->database = $database;
        $this->prefs_tablename = $prefs_tablename;
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
