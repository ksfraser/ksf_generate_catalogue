<?php

/**
 * Generator Discovery Demo Script
 * 
 * This script demonstrates the automatic generator discovery functionality.
 * It shows how generators are automatically found and registered without
 * needing to manually update factory methods.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Ksfraser\Frontaccounting\GenCat\GeneratorDiscovery;
use Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory;
use Ksfraser\Frontaccounting\GenCat\DatabaseInterface;

echo "=== Generator Discovery Demo ===\n\n";

// Create discovery service
$discovery = new GeneratorDiscovery();

echo "1. Discovering generators automatically...\n";
$generators = $discovery->discoverGenerators();

echo "Found " . count($generators) . " generators:\n\n";

foreach ($generators as $generator) {
    echo "Name: " . $generator['name'] . "\n";
    echo "Title: " . $generator['title'] . "\n";
    echo "Description: " . $generator['description'] . "\n";
    echo "Category: " . $generator['category'] . "\n";
    echo "Priority: " . $generator['priority'] . "\n";
    echo "Class: " . $generator['full_class_name'] . "\n";
    echo "---\n";
}

echo "\n2. Testing category-based filtering...\n";

$categories = [];
foreach ($generators as $generator) {
    $category = $generator['category'];
    if (!isset($categories[$category])) {
        $categories[$category] = [];
    }
    $categories[$category][] = $generator['name'];
}

foreach ($categories as $category => $generatorNames) {
    echo "Category '$category': " . implode(', ', $generatorNames) . "\n";
}

echo "\n3. Testing specific generator lookup...\n";

$pricebook = $discovery->findGenerator('pricebook');
if ($pricebook) {
    echo "Found pricebook generator: " . $pricebook['title'] . "\n";
    echo "Description: " . $pricebook['description'] . "\n";
}

$amazon = $discovery->findGenerator('amazon');
if ($amazon) {
    echo "Found new amazon generator: " . $amazon['title'] . "\n";
    echo "Description: " . $amazon['description'] . "\n";
} else {
    echo "Amazon generator not found (may need to be in correct directory)\n";
}

echo "\n4. Testing factory integration...\n";

// Create a mock database for testing
$mockDatabase = new class implements DatabaseInterface {
    public function query($query, $error_message = 'Database query failed') { return null; }
    public function fetch($result) { return false; }
    public function escape($value) { return $value; }
    public function getTablePrefix() { return 'test_'; }
};

$factory = new CatalogueGeneratorFactory($mockDatabase, 'test_prefs');
$factoryGenerators = $factory->getAvailableGenerators();

echo "Factory found " . count($factoryGenerators) . " generators via discovery\n";

// Test category filtering
$ecommerceGenerators = $factory->getGeneratorsByCategory('ecommerce');
echo "E-commerce generators: " . count($ecommerceGenerators) . "\n";

foreach ($ecommerceGenerators as $gen) {
    echo "  - " . $gen['title'] . "\n";
}

echo "\n=== Discovery Demo Complete ===\n";
echo "\nTo add a new generator:\n";
echo "1. Create a PHP class extending BaseCatalogueGenerator\n";
echo "2. Implement the GeneratorMetadataInterface methods\n";  
echo "3. Drop the file in the src/ directory\n";
echo "4. The system will automatically discover and register it!\n";

?>
