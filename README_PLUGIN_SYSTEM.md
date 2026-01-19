# KSF Generate Catalogue - Plugin-Based Output System

> **NEW**: Completely refactored with plugin-based architecture, auto-discovery, and centralized configuration!

A modern, extensible system for generating catalogue outputs to multiple destinations including e-commerce platforms, POS systems, and thermal printers.

## 🚀 What's New in This Refactoring

- ✅ **Plugin Architecture** - Auto-discovery of output handlers, no manual registration
- ✅ **Single Responsibility Principle** - Clean separation of concerns
- ✅ **Dependency Injection** - Loosely coupled, easily testable
- ✅ **One or All Outputs** - Generate to single destination or all at once
- ✅ **Centralized Configuration** - Database-backed preferences
- ✅ **Thermal Printer Support** - NEW: Phomemo M110/M220 label output (CSV/PDF)
- ✅ **Easy to Extend** - Add new outputs without modifying core code

## 📦 Quick Start (5 Minutes)

```php
<?php
require_once 'composer-lib/vendor/autoload.php';

use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

// Initialize with your database connection
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);

// Generate one output
$result = $orchestrator->generateOutput('square');
echo $result['success'] ? "✓ Success!" : "✗ Failed";

// Generate multiple outputs
$results = $orchestrator->generateOutputs(['square', 'woocommerce', 'phomemo']);

// Generate ALL outputs
$results = $orchestrator->generateAllOutputs();

// Get summary
$summary = $orchestrator->getResultsSummary();
echo "{$summary['successful']}/{$summary['total_handlers']} successful\n";
?>
```

See **[QUICK_START.md](QUICK_START.md)** for more examples!

## 📚 Documentation

| Document | What It Covers |
|----------|----------------|
| **[QUICK_START.md](QUICK_START.md)** ⚡ | Get started in 5 minutes with copy-paste examples |
| **[OUTPUT_PLUGIN_ARCHITECTURE.md](OUTPUT_PLUGIN_ARCHITECTURE.md)** 📖 | Complete architecture documentation and best practices |
| **[MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)** 🔄 | Step-by-step guide for migrating from old system |
| **[REFACTORING_SUMMARY.md](REFACTORING_SUMMARY.md)** 📝 | What was built and why |
| **[composer-lib/example_usage.php](composer-lib/example_usage.php)** 💡 | 14 practical code examples |

## 🔌 Output Handlers

| Handler | Description | Category | Format |
|---------|-------------|----------|--------|
| **square** | Square POS catalog import | POS | CSV |
| **woocommerce** | WooCommerce product import | E-commerce | CSV |
| **labels** | Product labels for printing | Printing | CSV |
| **phomemo** 🆕 | Phomemo M110/M220 thermal printer labels | Printing | CSV/PDF |
| **pricebook** | Price list export | General | CSV |

## 🎯 Key Features

### Generate One or All

```php
// Single output
$orchestrator->generateOutput('square');

// Specific outputs
$orchestrator->generateOutputs(['square', 'woocommerce']);

// All outputs
$orchestrator->generateAllOutputs();
```

### Thermal Printer Support 🆕

```php
$config = [
    'phomemo_output_format' => 'pdf',      // CSV or PDF
    'phomemo_label_width' => 80,           // 50mm (M110) or 80mm (M220)
    'phomemo_include_barcode' => true,
    'phomemo_include_price' => true,
    'phomemo_in_stock_only' => true        // One label per item in stock
];

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);
$result = $orchestrator->generateOutput('phomemo');

// PDF file ready for printing to Phomemo thermal printer!
```

### Centralized Configuration

```php
// From array
$config = [
    'online_sale_pricebook_id' => 2,
    'phomemo_output_format' => 'pdf'
];
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table, $config);

// From database
$orchestrator->loadConfigFromDatabase('output_');

// Enable specific outputs
$orchestrator->setEnabledOutputs(['square', 'woocommerce', 'phomemo']);
```

### Auto-Discovery

```php
// Automatically discovers all output handlers
$handlers = $orchestrator->getAvailableOutputs();

foreach ($handlers as $handler) {
    echo "{$handler['title']}: {$handler['description']}\n";
}
```

### Error Handling

```php
$result = $orchestrator->generateOutput('square');

if (!$result['success']) {
    echo "Error: {$result['message']}\n";
    
    // Validate configuration
    $validation = $orchestrator->validateHandlerConfig('square');
    print_r($validation['errors']);
}
```

## 🛠️ Adding a New Output Handler

Just create a new file in `composer-lib/src/`:

```php
<?php
namespace Ksfraser\Frontaccounting\GenCat;

class ShopifyOutput extends BaseCatalogueGenerator implements OutputHandlerInterface
{
    public static function getOutputHandlerMetadata()
    {
        return [
            'name' => 'shopify',
            'title' => 'Shopify Export',
            'description' => 'Product export for Shopify',
            'category' => 'ecommerce',
            'output_type' => 'csv'
        ];
    }
    
    // Implement other required methods...
}
```

**That's it!** The new handler is automatically discovered:

```php
$result = $orchestrator->generateOutput('shopify');
```

See [OUTPUT_PLUGIN_ARCHITECTURE.md](OUTPUT_PLUGIN_ARCHITECTURE.md) for complete guide.

## 🏗️ Architecture

```
CatalogueOutputOrchestrator  ← Main entry point
├── OutputHandlerFactory     ← Creates handlers with DI
├── OutputHandlerDiscovery   ← Auto-finds plugin classes
└── OutputConfigurationMgr   ← Manages preferences

Output Handlers (Plugins):
├── SquareCatalog           ← Square POS
├── WoocommerceImport       ← WooCommerce
├── LabelsFile              ← Product labels
├── PhomemoPrinterOutput    ← Thermal printer (NEW!)
└── Your custom handlers... ← Add yours!
```

### Design Principles

- **SRP**: Each class has one clear responsibility
- **DI**: Dependencies injected, not hard-coded
- **Open/Closed**: Open for extension, closed for modification
- **Plugin Architecture**: New handlers require zero core changes

## 📋 Migration from Old System

### Before (Old Approach)
```php
include('class.square_catalog.php');
$square = new ksf_generate_square($prefs);
$square->set_var('db', $db);
$square->createFile();
```

### After (New Approach)
```php
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$result = $orchestrator->generateOutput('square');
```

See [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) for complete migration steps.

## 🔧 Setup

### 1. Database Configuration

```sql
CREATE TABLE IF NOT EXISTS your_prefs_table (
    pref_name VARCHAR(100) PRIMARY KEY,
    pref_value TEXT
);

INSERT INTO your_prefs_table (pref_name, pref_value) VALUES
  ('output_enabled_outputs', '["square","woocommerce","phomemo"]'),
  ('output_phomemo_output_format', 'pdf'),
  ('output_phomemo_label_width', '80'),
  ('output_online_sale_pricebook_id', '2');
```

### 2. PHP Code

```php
use Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator;

$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$orchestrator->loadConfigFromDatabase('output_');

// Ready to use!
$results = $orchestrator->generateOutputs();
```

## 🧪 Testing

```bash
# Test discovery
php -r "require 'vendor/autoload.php'; 
  \$o = new Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator(\$db, 'prefs');
  print_r(\$o->getAvailableOutputs());"

# Test generation
php -r "require 'vendor/autoload.php';
  \$o = new Ksfraser\Frontaccounting\GenCat\CatalogueOutputOrchestrator(\$db, 'prefs');
  print_r(\$o->generateOutput('square'));"
```

## 📈 Comparison: Before vs After

| Aspect | Before | After |
|--------|--------|-------|
| **Add New Output** | Edit multiple files | Drop in one new class |
| **Configuration** | Scattered across classes | Centralized in DB |
| **Generate All** | Manual loops | `generateAllOutputs()` |
| **Error Handling** | Try/catch per handler | Unified result format |
| **Dependencies** | Hard-coded | Injected |
| **Discovery** | Manual includes | Automatic |
| **Testing** | Difficult | Easy with DI |

## 🎨 Real-World Examples

### Web UI
```php
$handlers = $orchestrator->getAvailableOutputs();
?>
<form method="post">
    <?php foreach ($handlers as $h): ?>
        <label>
            <input type="checkbox" name="outputs[]" value="<?= $h['name'] ?>">
            <?= $h['title'] ?> - <?= $h['description'] ?>
        </label>
    <?php endforeach; ?>
    <button>Generate</button>
</form>
```

### Cron Job
```php
// Daily catalogue generation
$orchestrator = new CatalogueOutputOrchestrator($database, $prefs_table);
$orchestrator->loadConfigFromDatabase('output_');

$results = $orchestrator->generateOutputs();
$summary = $orchestrator->getResultsSummary();

// Email admin
mail($admin, 'Catalogue Report', 
    "{$summary['successful']} outputs generated successfully");
```

### API Endpoint
```php
// api/generate.php
$output = $_POST['output'] ?? 'all';

if ($output === 'all') {
    $results = $orchestrator->generateAllOutputs();
} else {
    $results = [$output => $orchestrator->generateOutput($output)];
}

header('Content-Type: application/json');
echo json_encode($results);
```

## 🔐 Security

- All database inputs sanitized
- File paths validated
- Configuration validated before use
- No sensitive data logged
- Prepared statements used

## 🚧 Future Features

- [ ] Web UI for configuration
- [ ] Scheduled generation (cron integration)
- [ ] Webhook notifications
- [ ] Cloud storage (S3, Google Drive)
- [ ] More handlers (Shopify, Amazon, Zebra, Dymo)
- [ ] REST API
- [ ] Progress tracking

## 📄 License

GPL-3.0-or-later

## 👤 Author

KS Fraser <kevin@ksfraser.com>

## 🤝 Contributing

Want to add support for a new platform?

1. Create a class implementing `OutputHandlerInterface`
2. Place it in `composer-lib/src/`
3. Done! It's auto-discovered

See [OUTPUT_PLUGIN_ARCHITECTURE.md](OUTPUT_PLUGIN_ARCHITECTURE.md) for guidelines.

## 💬 Support

- **Quick Start**: [QUICK_START.md](QUICK_START.md) ← Start here!
- **Full Docs**: [OUTPUT_PLUGIN_ARCHITECTURE.md](OUTPUT_PLUGIN_ARCHITECTURE.md)
- **Migration**: [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)
- **Examples**: [example_usage.php](composer-lib/example_usage.php)

---

**Ready to get started?** → [QUICK_START.md](QUICK_START.md) (5 minutes!)
