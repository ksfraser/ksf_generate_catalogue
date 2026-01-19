# Quick Start: Plugin-Based Output System

## 5-Minute Quick Start

### 1. Generate One Output

```php
<?php
require_once 'composer-lib/vendor/autoload.php';

use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

// Initialize
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate Square catalog
$result = $orchestrator->generateOutput('square');

echo $result['success'] ? "✓ Success!" : "✗ Failed: {$result['message']}";
?>
```

### 2. Generate All Outputs

```php
<?php
// Generate everything
$results = $orchestrator->generateAllOutputs();

// Show summary
$summary = $orchestrator->getResultsSummary();
echo "Generated {$summary['successful']} out of {$summary['total_handlers']} outputs\n";
?>
```

### 3. Generate Specific Outputs

```php
<?php
// Generate Square, WooCommerce, and Phomemo labels
$results = $orchestrator->generateOutputs([
    'square',
    'woocommerce',
    'phomemo'
]);

foreach ($results as $name => $result) {
    if ($result['success']) {
        echo "✓ {$name}: {$result['rows']} rows\n";
    }
}
?>
```

### 4. With Configuration

```php
<?php
$config = [
    'phomemo_output_format' => 'pdf',
    'phomemo_label_width' => 80,
    'online_sale_pricebook_id' => 2
];

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);

// Generate Phomemo labels with this config
$result = $orchestrator->generateOutput('phomemo');
?>
```

### 5. Enable/Disable Outputs

```php
<?php
use Ksfraser\Frontaccounting\GenCat\OutputConfigurationManager;

$configManager = new OutputConfigurationManager($database, $prefs_table);

// Enable specific outputs
$configManager->setEnabledOutputs(['square', 'woocommerce', 'phomemo']);

// Now generate only enabled ones
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$orchestrator->loadConfigFromDatabase('output_');

$results = $orchestrator->generateOutputs(); // Uses enabled outputs
?>
```

## Available Outputs

| Name | Title | Category | Type | Description |
|------|-------|----------|------|-------------|
| `square` | Square Catalog | POS | CSV | Square POS catalog import |
| `woocommerce` | WooCommerce Import | E-commerce | CSV | WooCommerce product import |
| `labels` | Labels File | Printing | CSV | Product labels for printing |
| `phomemo` | Phomemo Thermal Printer | Printing | CSV/PDF | Labels for Phomemo M110/M220 |
| `pricebook` | Pricebook | General | CSV | Price list export |

## Common Configurations

### Square

```php
$config = [
    'online_sale_pricebook_id' => 2,  // Sales type ID for online prices
    'use_sale_prices' => true         // Include sale prices
];
```

### Phomemo Printer

```php
$config = [
    'phomemo_output_format' => 'pdf',    // 'csv' or 'pdf'
    'phomemo_label_width' => 80,         // 50 (M110) or 80 (M220)
    'phomemo_label_height' => 30,        // Height in mm
    'phomemo_include_barcode' => true,   // Include barcode
    'phomemo_include_price' => true,     // Include price
    'phomemo_in_stock_only' => true      // Only in-stock items
];
```

### WooCommerce

```php
$config = [
    'sort_by' => 'price',      // 'price' or 'stock'
    'max_rows_file' => 1000    // Max rows per file
];
```

## Check What's Available

```php
<?php
// List all available outputs
$handlers = $orchestrator->getAvailableOutputs();

foreach ($handlers as $handler) {
    echo "{$handler['title']} ({$handler['name']})\n";
    echo "  {$handler['description']}\n";
}
?>
```

## Check Status

```php
<?php
// Check if outputs are ready
$statuses = $orchestrator->getHandlerStatuses();

foreach ($statuses as $name => $status) {
    $icon = $status['available'] ? '✓' : '✗';
    echo "{$icon} {$status['title']}: {$status['status']}\n";
}
?>
```

## Error Handling

```php
<?php
$result = $orchestrator->generateOutput('square');

if (!$result['success']) {
    error_log("Square generation failed: {$result['message']}");
    
    // Check configuration
    $validation = $orchestrator->validateHandlerConfig('square');
    if (!$validation['valid']) {
        foreach ($validation['errors'] as $error) {
            error_log("Config error: {$error}");
        }
    }
}
?>
```

## Integration Example

Complete example for a web interface:

```php
<?php
require_once 'composer-lib/vendor/autoload.php';

use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

// Initialize
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$orchestrator->loadConfigFromDatabase('output_');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedOutputs = $_POST['outputs'] ?? [];
    
    if (!empty($selectedOutputs)) {
        // Generate selected outputs
        $results = $orchestrator->generateOutputs($selectedOutputs);
        
        // Display results
        foreach ($results as $name => $result) {
            if ($result['success']) {
                echo "<div class='success'>✓ {$name}: Generated {$result['rows']} rows</div>";
            } else {
                echo "<div class='error'>✗ {$name}: {$result['message']}</div>";
            }
        }
        
        // Show summary
        $summary = $orchestrator->getResultsSummary();
        echo "<p>Total: {$summary['successful']}/{$summary['total_handlers']} successful</p>";
    }
}

// Display form
$handlers = $orchestrator->getAvailableOutputs();
?>

<form method="POST">
    <h3>Generate Catalogue Outputs</h3>
    
    <?php foreach ($handlers as $handler): ?>
        <label>
            <input type="checkbox" name="outputs[]" value="<?= $handler['name'] ?>">
            <strong><?= $handler['title'] ?></strong>
            <br>
            <small><?= $handler['description'] ?></small>
        </label>
        <br>
    <?php endforeach; ?>
    
    <button type="submit">Generate Selected Outputs</button>
    <button type="button" onclick="selectAll()">Select All</button>
</form>

<script>
function selectAll() {
    document.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true);
}
</script>
```

## Adding a New Output

Create a new file `MyNewOutput.php` in `composer-lib/src/`:

```php
<?php
namespace Ksfraser\Frontaccounting\GenCat;

class MyNewOutput extends BaseCatalogueGenerator implements OutputHandlerInterface
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = "my_output.csv";
    }

    public static function getOutputHandlerMetadata()
    {
        return [
            'name' => 'mynew',
            'title' => 'My New Output',
            'class' => 'MyNewOutput',
            'description' => 'My custom output format',
            'category' => 'custom',
            'output_type' => 'csv',
            'version' => '1.0.0',
            'author' => 'Your Name'
        ];
    }

    public static function getOutputHandlerPriority()
    {
        return 50;
    }

    public static function isOutputHandlerAvailable()
    {
        return true;
    }

    public function generateOutput()
    {
        try {
            $rowcount = $this->createFile();
            return [
                'success' => true,
                'rows' => $rowcount,
                'files' => [$this->filename],
                'message' => "Generated {$rowcount} rows"
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

    public function getConfigurationSchema()
    {
        return [];
    }

    public function validateConfiguration()
    {
        return ['valid' => true, 'errors' => []];
    }

    public function getStatus()
    {
        return 'Ready to generate';
    }

    public function createFile()
    {
        // Your file generation logic
        $this->prepWriteFile();
        // Write data...
        $this->write_file->close();
        return $rowcount;
    }
}
```

That's it! Your new output will be automatically discovered and available.

## Database Setup

```sql
-- Create preferences table (if needed)
CREATE TABLE IF NOT EXISTS your_prefs_table (
    pref_name VARCHAR(100) PRIMARY KEY,
    pref_value TEXT
);

-- Add default configuration
INSERT INTO your_prefs_table (pref_name, pref_value) VALUES
  ('output_enabled_outputs', '["square","woocommerce"]'),
  ('output_phomemo_output_format', 'csv'),
  ('output_phomemo_label_width', '50'),
  ('output_online_sale_pricebook_id', '2')
ON DUPLICATE KEY UPDATE pref_value=VALUES(pref_value);
```

## Next Steps

1. ✅ Try the examples above
2. ✅ Read [OUTPUT_PLUGIN_ARCHITECTURE.md](OUTPUT_PLUGIN_ARCHITECTURE.md) for full documentation
3. ✅ Check [example_usage.php](composer-lib/example_usage.php) for more examples
4. ✅ See [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) if migrating from old system
5. ✅ Create your first custom output handler

## Support

- **Documentation**: OUTPUT_PLUGIN_ARCHITECTURE.md
- **Examples**: composer-lib/example_usage.php
- **Migration**: MIGRATION_GUIDE.md
