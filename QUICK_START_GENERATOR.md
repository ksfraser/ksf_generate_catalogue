# Quick Start: Adding a New Generator

## TL;DR - Adding a New Generator

1. Create a new PHP class file in the `src/` directory
2. Extend `BaseCatalogueGenerator` 
3. Implement `GeneratorMetadataInterface`
4. Add your file generation logic
5. **Done!** - It will be automatically discovered

## 5-Minute Example

Let's create a simple **CSV export for accounting software**:

### Step 1: Create `src/AccountingSoftwareGenerator.php`

```php
<?php
namespace Ksfraser\Frontaccounting\GenCat;

class AccountingSoftwareGenerator extends BaseCatalogueGenerator
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = "accounting_export.csv";
    }

    // Tell the system about this generator
    public static function getGeneratorMetadata()
    {
        return [
            'name' => 'accounting',
            'title' => 'Accounting Software Export',
            'class' => 'AccountingSoftwareGenerator',
            'description' => 'CSV export for accounting software import',
            'method' => 'createAccountingSoftwareGenerator',
            'category' => 'accounting'
        ];
    }

    // Optional: Set where it appears in lists (lower = higher up)
    public static function getGeneratorPriority()
    {
        return 35;
    }

    // Optional: Control when it's available
    public static function isGeneratorAvailable()
    {
        return true; // Always available
    }

    // Your file generation logic
    public function createFile()
    {
        $this->prepWriteFile();
        
        // Write header
        $this->write_file->write_line("SKU,Description,Price,Category");
        
        $rowcount = 0;
        
        // Get your data and write rows
        foreach ($this->fetchInventoryData() as $item) {
            $line = sprintf("%s,%s,%.2f,%s",
                $this->csvEscape($item['stock_id']),
                $this->csvEscape($item['description']),
                $item['material_cost'],
                $this->csvEscape($item['category'])
            );
            $this->write_file->write_line($line);
            $rowcount++;
        }
        
        $this->write_file->close();
        return $rowcount;
    }
    
    private function csvEscape($value)
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
```

### Step 2: Test It

```bash
# Check that it's discovered
php discovery_demo.php

# Run tests to make sure nothing broke
php vendor/phpunit/phpunit/phpunit
```

### Step 3: Use It

```php
$factory = new CatalogueGeneratorFactory($database, $prefs_table);

// Your new generator is automatically available
$generators = $factory->getAvailableGenerators();
// Will include: ['accounting' => ['title' => 'Accounting Software Export', ...]]

// Create and use it
$generator = $factory->createGeneratorByName('accounting');
$rowcount = $generator->createFile();
$filename = $generator->getFilename();
```

## That's It!

Your new generator is now:
- ✅ **Automatically discovered** by the system
- ✅ **Available in the factory** without any manual registration
- ✅ **Listed with other generators** in the correct priority order
- ✅ **Ready to generate files** using your custom logic

## Common Patterns

### E-commerce Platform
```php
public static function getGeneratorMetadata()
{
    return [
        'name' => 'shopify',
        'title' => 'Shopify Product Import',
        'category' => 'ecommerce',
        // ... rest
    ];
}
```

### Point of Sale System  
```php
public static function getGeneratorMetadata()
{
    return [
        'name' => 'clover',
        'title' => 'Clover POS Import',
        'category' => 'pos',
        // ... rest  
    ];
}
```

### Marketplace
```php  
public static function getGeneratorMetadata()
{
    return [
        'name' => 'ebay',
        'title' => 'eBay Listing Export',
        'category' => 'marketplace',
        // ... rest
    ];
}
```

### Conditional Availability
```php
public static function isGeneratorAvailable()
{
    // Only available if API key is configured
    return !empty($GLOBALS['my_api_key']);
}
```

## Need Help?

- **See existing generators** in `src/` for examples
- **Run tests** to ensure your generator works correctly  
- **Check discovery demo** to see if it's being found
- **Read full docs** in `DYNAMIC_DISCOVERY_SYSTEM.md`
