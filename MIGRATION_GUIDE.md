# Migration Guide: Moving to Plugin-Based Output Architecture

## Overview

This guide helps you migrate from the old direct-call approach to the new plugin-based architecture with auto-discovery and configuration management.

## What's Changed

### Before (Old Approach)
```php
// Manual instantiation
include('class.square_catalog.php');
include('class.woocommerce_import.php');
include('class.labels_file.php');

$square = new ksf_generate_square($prefs_table);
$square->set_var('db', $db);
$square->createFile();

$woo = new ksf_generate_woocommerce($prefs_table);
$woo->set_var('db', $db);
$woo->createFile();
```

**Problems:**
- ❌ Manual inclusion of each file
- ❌ Need to know class names
- ❌ Repetitive setup code
- ❌ Hard to add new outputs
- ❌ No unified configuration
- ❌ No error handling

### After (New Approach)
```php
use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$results = $orchestrator->generateOutputs(['square', 'woocommerce']);
```

**Benefits:**
- ✅ Auto-discovery of handlers
- ✅ Use simple names, not classes
- ✅ Centralized configuration
- ✅ Proper error handling
- ✅ Generate one or all outputs
- ✅ Easy to extend

## Migration Steps

### Step 1: Update Composer Autoloader

Ensure your `composer.json` includes the new files:

```json
{
    "autoload": {
        "psr-4": {
            "Ksfraser\\Frontaccounting\\GenCat\\": "src/"
        }
    }
}
```

Run:
```bash
composer dump-autoload
```

### Step 2: Update Database Schema

Add configuration table if not exists:

```sql
-- If you don't have a preferences table yet
CREATE TABLE IF NOT EXISTS your_prefs_table (
    pref_name VARCHAR(100) PRIMARY KEY,
    pref_value TEXT
);

-- Add default output preferences
INSERT INTO your_prefs_table (pref_name, pref_value) VALUES
  ('output_enabled_outputs', '["square","woocommerce"]'),
  ('output_phomemo_output_format', 'csv'),
  ('output_phomemo_label_width', '50'),
  ('output_online_sale_pricebook_id', '2')
ON DUPLICATE KEY UPDATE pref_value=VALUES(pref_value);
```

### Step 3: Migrate Individual Calls

#### Old Code:
```php
// old_generate_square.php
include('class.square_catalog.php');

$square = new ksf_generate_square(KSF_GENERATE_CATALOGUE_PREFS);
$square->set_var('db', $db);
$square->set_var('online_sale_pricebook_id', 2);
$rowcount = $square->createFile();

echo "Generated {$rowcount} rows";
```

#### New Code:
```php
// new_generate_square.php
use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Optional: set config if not in database
$orchestrator->setConfig('online_sale_pricebook_id', 2);

$result = $orchestrator->generateOutput('square');

if ($result['success']) {
    echo "Generated {$result['rows']} rows";
} else {
    echo "Error: {$result['message']}";
}
```

### Step 4: Migrate Batch Generation

#### Old Code:
```php
// old_generate_all.php
include('class.square_catalog.php');
include('class.woocommerce_import.php');
include('class.labels_file.php');

$handlers = [
    new ksf_generate_square($prefs_table),
    new ksf_generate_woocommerce($prefs_table),
    new ksf_generate_labels($prefs_table)
];

foreach ($handlers as $handler) {
    $handler->set_var('db', $db);
    try {
        $handler->createFile();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
```

#### New Code:
```php
// new_generate_all.php
use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate all outputs
$results = $orchestrator->generateAllOutputs();

// Or generate specific ones
// $results = $orchestrator->generateOutputs(['square', 'woocommerce', 'labels']);

// Display summary
$summary = $orchestrator->getResultsSummary();
echo "{$summary['successful']}/{$summary['total_handlers']} successful\n";
echo "Total rows: {$summary['total_rows']}\n";

// Display details
foreach ($results as $name => $result) {
    if ($result['success']) {
        echo "✓ {$name}: {$result['rows']} rows\n";
    } else {
        echo "✗ {$name}: {$result['message']}\n";
    }
}
```

### Step 5: Migrate Configuration

#### Old Code:
```php
// Configuration scattered in multiple places
$square->set_var('online_sale_pricebook_id', 2);
$square->set_var('use_sale_prices', true);

$woo->set_var('sort_by', 'price');
$woo->set_var('max_rows_file', 1000);

$labels->set_var('thermal_printer', true);
```

#### New Code:
```php
// Centralized configuration
$config = [
    // Square config
    'online_sale_pricebook_id' => 2,
    'use_sale_prices' => true,
    
    // WooCommerce config
    'sort_by' => 'price',
    'max_rows_file' => 1000,
    
    // Labels config
    'thermal_printer' => true,
    
    // Phomemo config
    'phomemo_output_format' => 'pdf',
    'phomemo_label_width' => 80
];

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);

// Or load from database
$orchestrator->loadConfigFromDatabase('output_');
```

### Step 6: Update Frontend/UI Code

#### Old Code:
```php
// old_ui.php
<form method="post">
    <input type="checkbox" name="generate_square" value="1"> Square
    <input type="checkbox" name="generate_woo" value="1"> WooCommerce
    <input type="checkbox" name="generate_labels" value="1"> Labels
    <button type="submit">Generate</button>
</form>

<?php
if ($_POST) {
    if (isset($_POST['generate_square'])) {
        $square = new ksf_generate_square($prefs_table);
        $square->createFile();
    }
    if (isset($_POST['generate_woo'])) {
        $woo = new ksf_generate_woocommerce($prefs_table);
        $woo->createFile();
    }
    // etc...
}
?>
```

#### New Code:
```php
// new_ui.php
use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Get available handlers dynamically
$handlers = $orchestrator->getAvailableOutputs();
?>

<form method="post">
    <?php foreach ($handlers as $handler): ?>
        <label>
            <input type="checkbox" name="outputs[]" value="<?= $handler['name'] ?>">
            <?= $handler['title'] ?>
            <small><?= $handler['description'] ?></small>
        </label>
    <?php endforeach; ?>
    <button type="submit">Generate Selected</button>
</form>

<?php
if ($_POST && isset($_POST['outputs'])) {
    $results = $orchestrator->generateOutputs($_POST['outputs']);
    
    foreach ($results as $name => $result) {
        if ($result['success']) {
            echo "<div class='success'>✓ {$name}: Generated {$result['rows']} rows</div>";
        } else {
            echo "<div class='error'>✗ {$name}: {$result['message']}</div>";
        }
    }
}
?>
```

## Configuration Migration

### Option 1: Store in Database

```php
use Ksfraser\Frontaccounting\GenCat\OutputConfigurationManager;

$configManager = new OutputConfigurationManager($database, $prefs_table);

// Migrate old preferences
$configManager->set('online_sale_pricebook_id', 2);
$configManager->set('phomemo_output_format', 'pdf');
$configManager->setEnabledOutputs(['square', 'woocommerce']);

// Use in orchestrator
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$orchestrator->loadConfigFromDatabase('output_');
```

### Option 2: Configuration File

```php
// config/outputs.php
return [
    'enabled_outputs' => ['square', 'woocommerce', 'phomemo'],
    'online_sale_pricebook_id' => 2,
    'phomemo_output_format' => 'pdf',
    'phomemo_label_width' => 80
];

// Usage
$config = require 'config/outputs.php';
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);
```

## Backward Compatibility

The new system maintains backward compatibility:

```php
// Old way still works
$factory = new CatalogueGeneratorFactory($database, $prefs_table);
$square = $factory->createSquareCatalog();
$square->createFile();

// New way is recommended
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$result = $orchestrator->generateOutput('square');
```

## Common Migration Patterns

### Pattern 1: Conditional Generation

#### Old:
```php
if ($condition) {
    $square = new ksf_generate_square($prefs_table);
    $square->createFile();
}
```

#### New:
```php
if ($condition) {
    $orchestrator->generateOutput('square');
}
```

### Pattern 2: Error Handling

#### Old:
```php
try {
    $square = new ksf_generate_square($prefs_table);
    $square->createFile();
} catch (Exception $e) {
    error_log($e->getMessage());
}
```

#### New:
```php
$result = $orchestrator->generateOutput('square');
if (!$result['success']) {
    error_log($result['message']);
}
```

### Pattern 3: Scheduled Tasks

#### Old:
```php
// cron_job.php
include('class.square_catalog.php');
$square = new ksf_generate_square($prefs_table);
$square->createFile();
```

#### New:
```php
// cron_job.php
use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$orchestrator->loadConfigFromDatabase('output_');

// Generate enabled outputs
$results = $orchestrator->generateOutputs();

// Email results summary
$summary = $orchestrator->getResultsSummary();
mail($admin_email, 'Catalogue Generation Report', json_encode($summary));
```

## Testing Your Migration

### 1. Test Discovery

```php
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$handlers = $orchestrator->getAvailableOutputs();

echo "Discovered handlers: " . count($handlers) . "\n";
foreach ($handlers as $handler) {
    echo "- {$handler['title']}\n";
}
```

### 2. Test Configuration

```php
$orchestrator->setConfig('online_sale_pricebook_id', 2);
$validation = $orchestrator->validateHandlerConfig('square');

if ($validation['valid']) {
    echo "✓ Configuration valid\n";
} else {
    echo "✗ Errors:\n";
    foreach ($validation['errors'] as $error) {
        echo "  - {$error}\n";
    }
}
```

### 3. Test Generation

```php
// Test single output
$result = $orchestrator->generateOutput('square');
print_r($result);

// Test batch generation
$results = $orchestrator->generateOutputs(['square', 'woocommerce']);
$summary = $orchestrator->getResultsSummary();
print_r($summary);
```

## Rollback Plan

If you need to rollback:

1. Keep old classes alongside new ones
2. Use feature flags to switch between old/new
3. Old code continues to work as-is

```php
// Feature flag approach
$useNewSystem = true; // Set to false to rollback

if ($useNewSystem) {
    $orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
    $result = $orchestrator->generateOutput('square');
} else {
    $square = new ksf_generate_square($prefs_table);
    $square->createFile();
}
```

## Troubleshooting

### Issue: Handlers Not Discovered

**Solution**: Check file locations and autoloading
```bash
composer dump-autoload
```

### Issue: Configuration Not Loading

**Solution**: Verify table and prefix
```php
$configManager = new OutputConfigurationManager($database, $prefs_table);
$all = $configManager->getAll();
print_r($all); // Debug what's stored
```

### Issue: Old Code Still Running

**Solution**: Check includes and ensure new code is reached
```php
error_log("Using new system"); // Add logging
```

## Support

For migration assistance:
- Review [OUTPUT_PLUGIN_ARCHITECTURE.md](OUTPUT_PLUGIN_ARCHITECTURE.md) for full documentation
- Check [example_usage.php](composer-lib/example_usage.php) for code examples
- Test with a development environment first
- Keep old code until migration is verified
