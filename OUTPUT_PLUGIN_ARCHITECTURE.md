# Plugin-Based Output Handler Architecture

## Overview

The KSF Generate Catalogue system has been refactored to use a **plugin-based architecture** with automatic discovery of output handlers. This enables you to:

- ✅ **Generate to one or all destinations** with a single command
- ✅ **Auto-detect available output handlers** without manual configuration
- ✅ **Add new output destinations** by simply creating a new class
- ✅ **Configure outputs independently** through database preferences
- ✅ **Handle multiple output types** (CSV, PDF, JSON, direct printer, etc.)
- ✅ **Apply SOLID principles** (SRP, DI) for maintainable code

## Architecture Components

### 1. OutputHandlerInterface

The core interface that all output handlers must implement:

```php
interface OutputHandlerInterface
{
    // Metadata for discovery
    public static function getOutputHandlerMetadata();
    public static function getOutputHandlerPriority();
    public static function isOutputHandlerAvailable();
    
    // Configuration
    public function getConfigurationSchema();
    public function validateConfiguration();
    public function getStatus();
    
    // Execution
    public function generateOutput();
}
```

### 2. OutputHandlerDiscovery

Automatically scans directories and discovers output handler classes:

- Finds all classes implementing `OutputHandlerInterface`
- Extracts metadata from each handler
- Sorts by priority
- Caches results for performance
- Filters by category

### 3. OutputHandlerFactory

Creates output handler instances with proper dependency injection:

- Instantiates handlers based on name
- Injects database connections
- Applies configuration
- Creates single or multiple handlers
- Validates handler availability

### 4. CatalogueOutputOrchestrator

Main orchestrator that coordinates the entire output process:

- Loads configuration from database
- Discovers available outputs
- Selects which outputs to generate
- Executes generation (single or batch)
- Collects and reports results
- Handles errors gracefully

## Available Output Handlers

### E-commerce
- **WooCommerce** (`woocommerce`) - Product import CSV for WooCommerce
- **Square** (`square`) - Catalog CSV for Square POS system

### Printing  
- **Labels** (`labels`) - Product label CSV
- **Phomemo Thermal Printer** (`phomemo`) - Labels for Phomemo M110/M220 printers (CSV or PDF)

### Other
- **Pricebook** (`pricebook`) - Price list CSV
- **Amazon** (`amazon`) - Amazon product feed

## Quick Start

### Generate a Single Output

```php
use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate Square catalog
$result = $orchestrator->generateOutput('square');

if ($result['success']) {
    echo "Generated {$result['rows']} products\n";
}
```

### Generate Multiple Outputs

```php
// Generate specific outputs
$results = $orchestrator->generateOutputs(['square', 'woocommerce']);

// Or generate all available outputs
$results = $orchestrator->generateAllOutputs();

// Get summary
$summary = $orchestrator->getResultsSummary();
echo "{$summary['successful']}/{$summary['total_handlers']} successful\n";
```

### Using Configuration

```php
// Set configuration
$config = [
    'online_sale_pricebook_id' => 2,
    'phomemo_output_format' => 'pdf',
    'phomemo_label_width' => 80
];

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);

// Or load from database
$orchestrator->loadConfigFromDatabase('output_');

// Enable specific outputs
$orchestrator->setEnabledOutputs(['square', 'woocommerce', 'phomemo']);

// Generate enabled outputs only
$results = $orchestrator->generateOutputs();
```

## Creating a New Output Handler

### Step 1: Create the Class

Create a new PHP file in `composer-lib/src/`:

```php
<?php
namespace Ksfraser\Frontaccounting\GenCat;

class PayPalCommerceOutput extends BaseCatalogueGenerator implements OutputHandlerInterface
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = "paypal_products.csv";
    }

    /**
     * Describe this handler
     */
    public static function getOutputHandlerMetadata()
    {
        return [
            'name' => 'paypal',
            'title' => 'PayPal Commerce',
            'class' => 'PayPalCommerceOutput',
            'description' => 'Generate product feed for PayPal Commerce Platform',
            'category' => 'ecommerce',
            'output_type' => 'csv',
            'version' => '1.0.0',
            'author' => 'Your Name',
            'requires_config' => true
        ];
    }

    /**
     * Set priority (lower = higher priority)
     */
    public static function getOutputHandlerPriority()
    {
        return 30;
    }

    /**
     * Check if available
     */
    public static function isOutputHandlerAvailable()
    {
        // Could check for PayPal API credentials, etc.
        return true;
    }

    /**
     * Define configuration options
     */
    public function getConfigurationSchema()
    {
        return [
            'paypal_merchant_id' => [
                'label' => 'PayPal Merchant ID',
                'type' => 'text',
                'description' => 'Your PayPal merchant identifier',
                'required' => true
            ]
        ];
    }

    /**
     * Validate configuration
     */
    public function validateConfiguration()
    {
        $errors = [];
        
        if (empty($this->getPreference('paypal_merchant_id'))) {
            $errors[] = 'PayPal Merchant ID is required';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get status message
     */
    public function getStatus()
    {
        $validation = $this->validateConfiguration();
        return $validation['valid'] 
            ? 'Ready to generate PayPal feed' 
            : 'Configuration incomplete';
    }

    /**
     * Generate the output
     */
    public function generateOutput()
    {
        try {
            $rowcount = $this->createFile();
            
            return [
                'success' => true,
                'rows' => $rowcount,
                'files' => [$this->filename],
                'message' => "Generated PayPal feed with {$rowcount} products"
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
     * Implement your file generation logic
     */
    public function createFile()
    {
        // Your custom logic here
        $this->setQuery();
        $this->prepWriteFile();
        
        // Write CSV data
        // ...
        
        $this->write_file->close();
        return $rowcount;
    }
    
    protected function setQuery()
    {
        // Define your SQL query
        $this->query = "SELECT ...";
    }
}
```

### Step 2: That's It!

The system will automatically:
- ✅ Discover your new handler
- ✅ Register it in the factory
- ✅ Make it available in the orchestrator
- ✅ Sort it by priority
- ✅ Validate its configuration

### Step 3: Use It

```php
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Your new handler is automatically available
$result = $orchestrator->generateOutput('paypal');
```

## Configuration Management

### Database Storage

Store output preferences in your preferences table:

```sql
INSERT INTO your_prefs_table (pref_name, pref_value) VALUES
  ('output_enabled_outputs', 'square,woocommerce,phomemo'),
  ('output_phomemo_format', 'pdf'),
  ('output_phomemo_label_width', '80'),
  ('output_online_sale_pricebook_id', '2');
```

### Loading Configuration

```php
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Load all preferences starting with 'output_'
$orchestrator->loadConfigFromDatabase('output_');

// Now configuration is loaded and will be used
$results = $orchestrator->generateOutputs();
```

### Configuration Schema

Each handler defines its own configuration schema:

```php
$factory = new OutputHandlerFactory($database, $prefs_table);
$schema = $factory->getHandlerConfigSchema('phomemo');

// Returns:
// [
//     'phomemo_output_format' => [
//         'label' => 'Output Format',
//         'type' => 'select',
//         'options' => ['csv' => 'CSV', 'pdf' => 'PDF'],
//         'required' => true,
//         'default' => 'csv'
//     ],
//     ...
// ]
```

This can be used to dynamically build UI forms.

## Advanced Features

### Category-Based Selection

```php
// Generate only e-commerce outputs
$factory = new OutputHandlerFactory($database, $prefs_table);
$ecommerceHandlers = $factory->getHandlersByCategory('ecommerce');

// Or exclude categories
$results = $orchestrator->generateAllOutputs(
    $excludeCategories = ['printing']
);
```

### Status Checking

```php
$statuses = $orchestrator->getHandlerStatuses();

foreach ($statuses as $name => $status) {
    echo "{$status['title']}: {$status['status']}\n";
    // Output: "Square Catalog: Ready to generate"
    // Output: "Phomemo Thermal Printer: Missing printer driver"
}
```

### Error Handling

```php
// Stop on first error
$results = $orchestrator->generateOutputs(
    ['square', 'woocommerce'],
    $stopOnError = true
);

// Or continue even if some fail
$results = $orchestrator->generateOutputs(
    ['square', 'woocommerce'],
    $stopOnError = false
);
```

### Custom Discovery Directories

```php
$factory = new OutputHandlerFactory($database, $prefs_table);

// Add custom directory
$factory->addHandlerDirectory('/path/to/custom/handlers');

// Now custom handlers will be discovered
$handlers = $factory->getAvailableHandlers();
```

## Phomemo Thermal Printer

The Phomemo output handler supports:

### Models
- **M110** - 50mm width labels
- **M220** - 80mm width labels

### Output Formats
- **CSV** - For importing into label design software
- **PDF** - For direct printing via print dialog

### Configuration

```php
$config = [
    'phomemo_output_format' => 'pdf',      // 'csv' or 'pdf'
    'phomemo_label_width' => 80,           // 50 or 80 (mm)
    'phomemo_label_height' => 30,          // Height in mm
    'phomemo_include_barcode' => true,     // Include barcode
    'phomemo_include_price' => true,       // Include price
    'phomemo_font_size' => 10,             // Font size in points
    'phomemo_in_stock_only' => true        // Only in-stock items
];

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);
$result = $orchestrator->generateOutput('phomemo');
```

### Label Generation

Generates one label per item in stock by default. For example, if you have 5 units of product A, it will generate 5 labels for product A.

## Integration with Existing Code

### Backward Compatibility

The refactored classes maintain backward compatibility:

```php
// Old way still works
$factory = new CatalogueGeneratorFactory($database, $prefs_table);
$square = $factory->createSquareCatalog();
$square->createFile();

// New way provides more control
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$result = $orchestrator->generateOutput('square');
```

### Migration Path

1. **Phase 1**: Use both systems in parallel
2. **Phase 2**: Gradually move to orchestrator for new features
3. **Phase 3**: Eventually deprecate old direct calls

## Extending for Other Platforms

### E-commerce Platforms
- Shopify
- BigCommerce  
- Magento
- Amazon Seller Central

### Label/Receipt Printers
- Zebra printers
- Dymo LabelWriter
- Brother QL series

### Data Formats
- JSON feeds
- XML feeds
- Direct API integration
- FTP upload

## Best Practices

### 1. Single Responsibility Principle (SRP)

Each output handler handles ONE output format:
- ✅ SquareCatalog handles Square only
- ✅ PhomemoPrinterOutput handles Phomemo only
- ✅ Orchestrator coordinates, doesn't generate

### 2. Dependency Injection (DI)

Dependencies are injected, not hard-coded:
```php
// Good ✅
$handler = new SquareCatalog($prefs_table);
$handler->setDatabase($database);

// Bad ✗
// Handler creates its own database connection
```

### 3. Configuration Over Code

Use configuration for behavior:
```php
// Good ✅
$config = ['phomemo_output_format' => 'pdf'];

// Bad ✗
// Hardcoded in class: $this->format = 'pdf';
```

### 4. Fail Gracefully

Always return structured results:
```php
return [
    'success' => false,
    'rows' => 0,
    'files' => [],
    'message' => 'Clear error message'
];
```

## Troubleshooting

### Handler Not Discovered

1. Check class implements `OutputHandlerInterface`
2. Check file is in scan directory
3. Check `isOutputHandlerAvailable()` returns true
4. Force refresh: `$orchestrator->getAvailableOutputs($forceRefresh = true)`

### Configuration Not Working

1. Check preference key format matches schema
2. Verify database connection
3. Check table prefix is correct
4. Use `validateConfiguration()` to debug

### Output Generation Fails

1. Check `getStatus()` for readiness
2. Validate configuration first
3. Check database permissions
4. Review error in result array

## Performance Considerations

- Discovery results are cached
- Use `generateOutputs()` instead of multiple `generateOutput()` calls
- Database queries are optimized per handler
- Consider running large generations in background

## Security

- Sanitize all database inputs
- Validate file paths
- Check permissions before file write
- Use prepared statements in queries
- Don't expose sensitive config in logs

## Future Enhancements

- Web UI for configuration management
- Scheduled/automated generation
- Webhook notifications
- Cloud storage integration (S3, Google Drive)
- API endpoints for external systems
- Real-time progress indicators
- Rollback/versioning of outputs

## Support

For issues or questions:
- Check example_usage.php for code samples
- Review handler implementation in src/
- Test with validation methods before running
- Check logs for error details
