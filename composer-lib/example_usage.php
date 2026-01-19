<?php

/**
 * Example Usage of the Plugin-Based Output System
 * 
 * This file demonstrates how to use the new plugin-based architecture
 * for generating catalogue outputs to multiple destinations.
 * 
 * @package   Ksfraser\Frontaccounting\GenCat
 * @author    KS Fraser <kevin@ksfraser.com>
 * @since     1.0.0
 */

// Include required files
require_once __DIR__ . '/composer-lib/vendor/autoload.php';

use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;
use Ksfraser\Frontaccounting\GenCat\OutputHandlerFactory;
use Ksfraser\Frontaccounting\GenCat\OutputHandlerDiscovery;

// Assume we have a database connection and preferences table
// $database = new YourDatabaseImplementation();
// $prefs_table = 'your_prefs_table';

// =============================================================================
// EXAMPLE 1: Simple Usage - Generate a Single Output
// =============================================================================
echo "=== Example 1: Generate Single Output ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate Square catalog only
$result = $orchestrator->generateOutput('square');

if ($result['success']) {
    echo "✓ Success! Generated {$result['rows']} rows\n";
    echo "  Files: " . implode(', ', $result['files']) . "\n";
} else {
    echo "✗ Failed: {$result['message']}\n";
}

// =============================================================================
// EXAMPLE 2: Generate Multiple Specific Outputs
// =============================================================================
echo "\n=== Example 2: Generate Multiple Outputs ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate Square and WooCommerce
$results = $orchestrator->generateOutputs(['square', 'woocommerce']);

foreach ($results as $name => $result) {
    if ($result['success']) {
        echo "✓ {$name}: {$result['rows']} rows in {$result['execution_time']}s\n";
    } else {
        echo "✗ {$name}: {$result['message']}\n";
    }
}

// =============================================================================
// EXAMPLE 3: Generate All Available Outputs
// =============================================================================
echo "\n=== Example 3: Generate All Outputs ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate everything
$results = $orchestrator->generateAllOutputs();

$summary = $orchestrator->getResultsSummary();
echo "Total: {$summary['successful']}/{$summary['total_handlers']} successful\n";
echo "Rows: {$summary['total_rows']}, Files: {$summary['total_files']}\n";
echo "Time: " . number_format($summary['total_execution_time'], 2) . "s\n";

// =============================================================================
// EXAMPLE 4: Using Configuration
// =============================================================================
echo "\n=== Example 4: With Configuration ===\n";

$config = [
    'online_sale_pricebook_id' => 2,
    'use_sale_prices' => true,
    'phomemo_output_format' => 'pdf',
    'phomemo_label_width' => 80,
    'phomemo_include_price' => true
];

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);

// Or set config after construction
$orchestrator->setConfig('phomemo_include_barcode', true);

// Generate with config
$result = $orchestrator->generateOutput('phomemo');
echo "Phomemo: {$result['message']}\n";

// =============================================================================
// EXAMPLE 5: Load Configuration from Database
// =============================================================================
echo "\n=== Example 5: Database Configuration ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Load all preferences that start with 'output_'
$orchestrator->loadConfigFromDatabase('output_');

// Set enabled outputs in config
$orchestrator->setEnabledOutputs(['square', 'woocommerce', 'labels']);

// Generate only enabled outputs
$results = $orchestrator->generateOutputs(); // Uses enabled outputs from config

echo "Generated " . count($results) . " outputs\n";

// =============================================================================
// EXAMPLE 6: Discovery - List Available Handlers
// =============================================================================
echo "\n=== Example 6: Discover Available Handlers ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

$handlers = $orchestrator->getAvailableOutputs();

echo "Available Output Handlers:\n";
foreach ($handlers as $handler) {
    echo "  - {$handler['title']} ({$handler['name']})\n";
    echo "    Category: {$handler['category']}, Type: {$handler['output_type']}\n";
    echo "    {$handler['description']}\n";
}

// =============================================================================
// EXAMPLE 7: Check Handler Status
// =============================================================================
echo "\n=== Example 7: Check Handler Status ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

$statuses = $orchestrator->getHandlerStatuses();

foreach ($statuses as $name => $status) {
    $icon = $status['available'] ? '✓' : '✗';
    echo "{$icon} {$status['title']}: {$status['status']}\n";
}

// =============================================================================
// EXAMPLE 8: Validate Configuration
// =============================================================================
echo "\n=== Example 8: Validate Configuration ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);

$validation = $orchestrator->validateHandlerConfig('phomemo');

if ($validation['valid']) {
    echo "✓ Phomemo configuration is valid\n";
} else {
    echo "✗ Phomemo configuration errors:\n";
    foreach ($validation['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

// =============================================================================
// EXAMPLE 9: Using the Factory Directly
// =============================================================================
echo "\n=== Example 9: Direct Factory Usage ===\n";

$factory = new OutputHandlerFactory($database, $prefs_table);

// Get handlers by category
$ecommerceHandlers = $factory->getHandlersByCategory('ecommerce');
echo "E-commerce handlers: " . count($ecommerceHandlers) . "\n";

$printingHandlers = $factory->getHandlersByCategory('printing');
echo "Printing handlers: " . count($printingHandlers) . "\n";

// Create specific handler
$squareHandler = $factory->createHandler('square', ['use_sale_prices' => true]);
$result = $squareHandler->generateOutput();
echo "Square: {$result['message']}\n";

// =============================================================================
// EXAMPLE 10: Error Handling
// =============================================================================
echo "\n=== Example 10: Error Handling ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Try to generate a non-existent handler
try {
    $result = $orchestrator->generateOutput('nonexistent');
    echo "Result: {$result['message']}\n";
} catch (Exception $e) {
    echo "Caught exception: {$e->getMessage()}\n";
}

// Generate multiple with stop-on-error
$results = $orchestrator->generateOutputs(
    ['square', 'invalid', 'woocommerce'],
    $stopOnError = false // Continue even if one fails
);

foreach ($results as $name => $result) {
    $icon = $result['success'] ? '✓' : '✗';
    echo "{$icon} {$name}: {$result['message']}\n";
}

// =============================================================================
// EXAMPLE 11: Custom Discovery Directory
// =============================================================================
echo "\n=== Example 11: Add Custom Output Directory ===\n";

$factory = new OutputHandlerFactory($database, $prefs_table);

// Add a custom directory to scan for additional output handlers
$factory->addHandlerDirectory('/path/to/custom/handlers');

// Now discover all handlers including custom ones
$handlers = $factory->getAvailableHandlers();
echo "Total handlers (including custom): " . count($handlers) . "\n";

// =============================================================================
// EXAMPLE 12: Generate by Category
// =============================================================================
echo "\n=== Example 12: Generate by Category ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate all e-commerce outputs
$results = $orchestrator->generateAllOutputs(
    $excludeCategories = ['printing'] // Exclude printing category
);

echo "Generated " . count($results) . " non-printing outputs\n";

// =============================================================================
// EXAMPLE 13: Getting Results
// =============================================================================
echo "\n=== Example 13: Working with Results ===\n";

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate multiple outputs
$orchestrator->generateOutputs(['square', 'woocommerce', 'labels']);

// Get all results
$allResults = $orchestrator->getResults();

// Get specific result
$squareResult = $orchestrator->getResults('square');
if ($squareResult) {
    echo "Square generated {$squareResult['rows']} rows\n";
    echo "Files: " . implode(', ', $squareResult['files']) . "\n";
}

// Get summary
$summary = $orchestrator->getResultsSummary();
print_r($summary);

// Clear results for next run
$orchestrator->clearResults();

// =============================================================================
// EXAMPLE 14: Configuration Schema
// =============================================================================
echo "\n=== Example 14: Get Configuration Schema ===\n";

$factory = new OutputHandlerFactory($database, $prefs_table);

$schema = $factory->getHandlerConfigSchema('phomemo');

echo "Phomemo Configuration Options:\n";
foreach ($schema as $key => $definition) {
    $required = isset($definition['required']) && $definition['required'] ? ' *' : '';
    echo "  {$definition['label']}{$required}\n";
    echo "    {$definition['description']}\n";
    if (isset($definition['default'])) {
        echo "    Default: {$definition['default']}\n";
    }
}

?>
