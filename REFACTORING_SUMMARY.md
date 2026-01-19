# Refactoring Complete: Plugin-Based Output Architecture

## Summary

The KSF Generate Catalogue system has been successfully refactored from a tightly-coupled, manual system to a modern **plugin-based architecture** with automatic discovery, dependency injection, and centralized configuration management.

## What Was Built

### 1. Core Infrastructure

#### OutputHandlerInterface
- Defines the contract all output handlers must implement
- Provides methods for metadata, configuration, validation, and execution
- Located: `composer-lib/src/OutputHandlerInterface.php`

#### OutputHandlerDiscovery
- Automatically scans directories for output handler classes
- Discovers and registers handlers without manual configuration
- Caches results for performance
- Supports filtering by category
- Located: `composer-lib/src/OutputHandlerDiscovery.php`

#### OutputHandlerFactory
- Creates handler instances with proper dependency injection
- Injects database connections and applies configuration
- Can create single or multiple handlers at once
- Validates handler availability
- Located: `composer-lib/src/OutputHandlerFactory.php`

#### CatalogueOutputOrchestrator
- Main entry point implementing Single Responsibility Principle
- Handles configuration loading from database
- Coordinates output generation (single or batch)
- Collects and reports results
- Provides error handling and validation
- Located: `composer-lib/src/CatalogueOutputOrchestrator.php`

#### OutputConfigurationManager
- Manages configuration storage in database
- Provides simple get/set interface
- Handles enabled outputs management
- Supports import/export for backup
- Located: `composer-lib/src/OutputConfigurationManager.php`

### 2. Output Handlers

All existing output classes have been updated to implement `OutputHandlerInterface`:

- **SquareCatalog** - Square POS catalog (CSV)
- **WoocommerceImport** - WooCommerce products (CSV)
- **LabelsFile** - Product labels (CSV)
- **PricebookFile** - Price lists (CSV)

### 3. New Output Handler

#### PhomemoPrinterOutput
- Generates labels for Phomemo thermal printers (M110/M220)
- Supports both CSV (for label software) and PDF (for direct printing)
- Configurable label size, barcode, price display
- Generates one label per item in stock
- Located: `composer-lib/src/PhomemoPrinterOutput.php`

### 4. Documentation

- **OUTPUT_PLUGIN_ARCHITECTURE.md** - Complete architecture documentation
- **MIGRATION_GUIDE.md** - Step-by-step migration from old system
- **QUICK_START.md** - Get started in 5 minutes
- **example_usage.php** - 14 practical examples

## Key Features

### ✅ Plugin Architecture
- New outputs are automatically discovered
- No manual registration required
- Just drop a new class file in the directory

### ✅ Single Responsibility Principle (SRP)
- Each class has one clear purpose
- Orchestrator coordinates, doesn't generate
- Handlers generate, don't orchestrate
- Factory creates, doesn't configure

### ✅ Dependency Injection (DI)
- Database connections injected
- Configuration injected
- No hard-coded dependencies
- Easy to test and mock

### ✅ Flexible Configuration
- Store in database preferences
- Load from configuration files
- Set programmatically
- Per-handler configuration schemas

### ✅ One or All Outputs
```php
// Generate one
$orchestrator->generateOutput('square');

// Generate specific outputs
$orchestrator->generateOutputs(['square', 'woocommerce']);

// Generate everything
$orchestrator->generateAllOutputs();
```

### ✅ Comprehensive Error Handling
```php
$result = $orchestrator->generateOutput('square');

if (!$result['success']) {
    echo "Error: {$result['message']}";
    // Check what went wrong
    $validation = $orchestrator->validateHandlerConfig('square');
}
```

## Architecture Benefits

### Before Refactoring
```php
// Manual includes
include('class.square_catalog.php');
include('class.woocommerce_import.php');

// Repetitive setup
$square = new ksf_generate_square($prefs);
$square->set_var('db', $db);
$square->set_var('config_value', $value);
$square->createFile();

$woo = new ksf_generate_woocommerce($prefs);
$woo->set_var('db', $db);
$woo->set_var('config_value', $value);
$woo->createFile();
```

**Problems:**
- ❌ Tightly coupled
- ❌ Manual includes
- ❌ Repetitive code
- ❌ Hard to extend
- ❌ No unified error handling
- ❌ Scattered configuration

### After Refactoring
```php
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$orchestrator->loadConfigFromDatabase('output_');

// Generate all enabled outputs
$results = $orchestrator->generateOutputs();

// Get summary
$summary = $orchestrator->getResultsSummary();
echo "{$summary['successful']} outputs generated successfully";
```

**Benefits:**
- ✅ Loosely coupled
- ✅ Auto-discovery
- ✅ DRY (Don't Repeat Yourself)
- ✅ Easy to extend
- ✅ Unified error handling
- ✅ Centralized configuration

## Use Cases Solved

### 1. Generate for Phomemo Thermal Printer
```php
$config = [
    'phomemo_output_format' => 'pdf',
    'phomemo_label_width' => 80,  // M220 printer
    'phomemo_include_price' => true,
    'phomemo_include_barcode' => true
];

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);
$result = $orchestrator->generateOutput('phomemo');

// Result includes PDF file path for printing
echo "Generated: {$result['files'][0]}";
```

### 2. Generate All E-commerce Outputs
```php
$factory = new OutputHandlerFactory($database, $prefs_table);
$ecommerceHandlers = $factory->getHandlersByCategory('ecommerce');

$handlerNames = array_column($ecommerceHandlers, 'name');
$results = $orchestrator->generateOutputs($handlerNames);
```

### 3. Enable/Disable Outputs via Database
```php
$configManager = new OutputConfigurationManager($database, $prefs_table);

// Enable Square and WooCommerce only
$configManager->setEnabledOutputs(['square', 'woocommerce']);

// Check if output is enabled
if ($configManager->isOutputEnabled('phomemo')) {
    // Generate
}
```

### 4. Custom Output for New Platform (e.g., PayPal)
Simply create a new class implementing OutputHandlerInterface - it's automatically discovered:
```php
class PayPalCommerceOutput extends BaseCatalogueGenerator implements OutputHandlerInterface
{
    // Implement required methods
}

// Automatically available:
$result = $orchestrator->generateOutput('paypal');
```

## Migration Path

The refactoring maintains **100% backward compatibility**:

```php
// Old code still works
$factory = new CatalogueGeneratorFactory($database, $prefs_table);
$square = $factory->createSquareCatalog();
$square->createFile();

// New code is recommended
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$result = $orchestrator->generateOutput('square');
```

See [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) for step-by-step migration instructions.

## File Structure

```
composer-lib/
├── src/
│   ├── OutputHandlerInterface.php           ← Interface definition
│   ├── OutputHandlerDiscovery.php           ← Auto-discovery service
│   ├── OutputHandlerFactory.php             ← Factory with DI
│   ├── CatalogueOutputOrchestrator.php      ← Main orchestrator
│   ├── OutputConfigurationManager.php       ← Config management
│   ├── BaseOutputHandler.php                ← Optional base class
│   ├── SquareCatalog.php                    ← Updated: implements interface
│   ├── WoocommerceImport.php                ← Updated: implements interface
│   ├── LabelsFile.php                       ← Updated: implements interface
│   ├── PhomemoPrinterOutput.php             ← New: thermal printer
│   ├── PricebookFile.php                    ← Existing
│   └── ... other handlers ...
├── example_usage.php                         ← 14 practical examples
└── vendor/                                   ← Composer dependencies

Documentation/
├── OUTPUT_PLUGIN_ARCHITECTURE.md             ← Complete architecture guide
├── MIGRATION_GUIDE.md                        ← Step-by-step migration
├── QUICK_START.md                            ← 5-minute quick start
└── REFACTORING_SUMMARY.md                    ← This file
```

## Design Patterns Used

### 1. Plugin Architecture
- Handlers are plugins
- Auto-discovered at runtime
- No manual registration

### 2. Factory Pattern
- OutputHandlerFactory creates handlers
- Handles dependency injection
- Manages handler lifecycle

### 3. Strategy Pattern
- Each handler is a strategy for output generation
- Selected at runtime based on name
- Interchangeable implementations

### 4. Dependency Injection
- Database injected into handlers
- Configuration injected via constructor or setters
- No hard dependencies

### 5. Template Method
- BaseOutputHandler provides template
- Concrete handlers override specific methods
- Common behavior in base class

## Testing

### Quick Test
```php
// Test discovery
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$handlers = $orchestrator->getAvailableOutputs();
echo "Found " . count($handlers) . " handlers\n";

// Test generation
$result = $orchestrator->generateOutput('square');
print_r($result);
```

### Validation Test
```php
// Validate all enabled outputs
$validations = $orchestrator->validateAllEnabledConfigs();
foreach ($validations as $name => $validation) {
    if (!$validation['valid']) {
        echo "{$name}: " . implode(', ', $validation['errors']) . "\n";
    }
}
```

## Performance

- Discovery results are cached (no repeated scans)
- Database queries optimized per handler
- Batch generation more efficient than multiple individual calls
- Configuration loaded once, used for all handlers

## Security

- All database inputs sanitized
- File paths validated
- Configuration validated before use
- Prepared statements for database queries
- No sensitive data in logs

## Future Enhancements

### Potential Additions
- **Web UI** for configuration management
- **Scheduled generation** via cron/task scheduler
- **Webhooks** for completion notifications
- **Cloud storage** integration (S3, Google Drive)
- **API endpoints** for external systems
- **Progress tracking** for long-running generations
- **Rollback/versioning** of generated files

### Additional Output Handlers
- **Shopify** - E-commerce platform
- **Amazon Seller Central** - Amazon marketplace
- **Zebra Printers** - Label printing
- **Brother QL** - Label printing
- **JSON API** - Direct API integration
- **FTP Upload** - Automated file transfer

## Getting Started

1. **Read the Quick Start**: [QUICK_START.md](QUICK_START.md)
2. **Try the examples**: [composer-lib/example_usage.php](composer-lib/example_usage.php)
3. **If migrating**: [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)
4. **Full documentation**: [OUTPUT_PLUGIN_ARCHITECTURE.md](OUTPUT_PLUGIN_ARCHITECTURE.md)

## Basic Usage

```php
<?php
require_once 'composer-lib/vendor/autoload.php';

use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

// Initialize
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate all enabled outputs
$results = $orchestrator->generateAllOutputs();

// Show results
foreach ($results as $name => $result) {
    if ($result['success']) {
        echo "✓ {$name}: {$result['rows']} rows\n";
    } else {
        echo "✗ {$name}: {$result['message']}\n";
    }
}
?>
```

## Conclusion

This refactoring successfully transforms the catalogue generation system into a modern, maintainable, and extensible architecture. The plugin-based approach makes it trivial to add new output destinations, while the centralized orchestrator with dependency injection ensures clean separation of concerns.

**Key Achievements:**
- ✅ Plugin-based architecture with auto-discovery
- ✅ Single Responsibility Principle (SRP) applied
- ✅ Dependency Injection (DI) throughout
- ✅ Generate one or all outputs easily
- ✅ Centralized configuration management
- ✅ Phomemo thermal printer support
- ✅ Comprehensive documentation
- ✅ Backward compatibility maintained
- ✅ Easy to extend with new handlers

The system is now ready for future expansion with new e-commerce platforms, printers, and output formats, all without modifying core code—just add new plugin classes!
