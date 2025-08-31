# Dynamic Generator Discovery System

## Overview

The KSF Generate Catalogue system has been enhanced with a powerful **Dynamic Generator Discovery** mechanism that automatically finds and registers generator classes without manual configuration. This makes the system truly extensible - you can simply drop new generator files into a directory and they will be automatically discovered and available.

## How It Works

### 1. Self-Describing Generators

All generators implement the `GeneratorMetadataInterface` which requires them to describe themselves:

```php
interface GeneratorMetadataInterface
{
    /**
     * Get generator metadata for dynamic discovery
     */
    public static function getGeneratorMetadata();
    
    /**
     * Get the priority/order for this generator  
     */
    public static function getGeneratorPriority();
    
    /**
     * Check if this generator is available/enabled
     */
    public static function isGeneratorAvailable();
}
```

### 2. Automatic Discovery

The `GeneratorDiscovery` service scans directories for PHP files and:

1. **Checks class compatibility** - Must extend `BaseCatalogueGenerator` and implement `GeneratorMetadataInterface`
2. **Retrieves metadata** - Calls the static methods to get generator information
3. **Validates availability** - Checks if the generator should be enabled
4. **Sorts by priority** - Orders generators by their specified priority
5. **Caches results** - Avoids re-scanning on every request

### 3. Factory Integration  

The `CatalogueGeneratorFactory` now uses discovery automatically:

```php
$factory = new CatalogueGeneratorFactory($database, $prefs_table);

// Automatically discovers all available generators
$generators = $factory->getAvailableGenerators();

// Can create generators by name without knowing the class
$generator = $factory->createGeneratorByName('amazon');
```

## Creating a New Generator

### Step 1: Create the Generator Class

Create a new PHP file in the `src/` directory:

```php
<?php
namespace Ksfraser\Frontaccounting\GenCat;

class MyCustomGenerator extends BaseCatalogueGenerator
{
    public function __construct($prefs_tablename)
    {
        parent::__construct($prefs_tablename);
        $this->filename = "my_custom_export.csv";
        // Setup your specific configuration
    }

    /**
     * Required: Describe this generator
     */
    public static function getGeneratorMetadata()
    {
        return [
            'name' => 'mycustom',                    // Unique identifier
            'title' => 'My Custom Export',          // Display name
            'class' => 'MyCustomGenerator',         // Class name
            'description' => 'My custom file format export',
            'method' => 'createMyCustomGenerator',  // Factory method name
            'category' => 'custom',                 // Grouping category
            'version' => '1.0.0',
            'author' => 'Your Name'
        ];
    }

    /**
     * Optional: Set display priority (lower = higher priority)
     */
    public static function getGeneratorPriority()
    {
        return 60; // Will appear after built-in generators
    }

    /**
     * Optional: Control availability
     */
    public static function isGeneratorAvailable()
    {
        // Could check configuration, environment, etc.
        return true;
    }

    /**
     * Required: Implement the file creation logic
     */
    public function createFile()
    {
        // Your custom file generation logic here
        $this->prepWriteFile();
        $this->write_file->write_line("Header line");
        
        // Generate your data...
        $rowcount = 0;
        // ... your logic ...
        
        $this->write_file->close();
        return $rowcount;
    }
}
```

### Step 2: That's It!

The system will automatically:
- ✅ **Discover** your new generator
- ✅ **Register** it in the factory
- ✅ **Display** it in the UI (if using the base module)
- ✅ **Create** factory methods dynamically
- ✅ **Sort** it by priority with other generators

## Advanced Features

### Custom Scan Directories

You can add additional directories to scan for generators:

```php
$factory = new CatalogueGeneratorFactory($database, $prefs_table);
$factory->addGeneratorDirectory('/path/to/custom/generators');
```

### Category-Based Filtering

Group and filter generators by category:

```php
// Get all e-commerce generators
$ecommerceGens = $factory->getGeneratorsByCategory('ecommerce');

// Get all POS system generators  
$posGens = $factory->getGeneratorsByCategory('pos');
```

### Conditional Availability

Generators can be conditionally enabled:

```php
public static function isGeneratorAvailable()
{
    // Only enable if API credentials are configured
    return !empty(getenv('THIRD_PARTY_API_KEY'));
}
```

### Priority-Based Ordering

Control the display order of generators:

```php
public static function getGeneratorPriority()
{
    return 5;  // High priority - will appear first
    return 50; // Low priority - will appear last
}
```

## Testing Discovery

Comprehensive PHPUnit tests ensure discovery works correctly:

```bash
php vendor/phpunit/phpunit/phpunit tests/Unit/GeneratorDiscoveryTest.php
```

**Test Results:**
- ✅ **9 tests, 152 assertions** all passing
- Tests automatic discovery, metadata validation, priority sorting, caching, etc.

## Real-World Example

The included `AmazonImport` generator demonstrates a complete implementation:

- **Metadata**: Describes itself as "Amazon Import" with marketplace category
- **Priority**: Set to 40 (medium-low priority)  
- **Availability**: Always available (could check for Amazon API credentials)
- **Custom Logic**: Generates Amazon-specific CSV format with proper fields

## Benefits

### 🚀 **Extensibility**
- Drop in new generators without touching existing code
- No manual factory method creation needed
- No hardcoded lists to maintain

### 📊 **Maintainability** 
- Self-documenting generators with metadata
- Centralized discovery logic
- Easy to add/remove generators

### 🎯 **Flexibility**
- Priority-based ordering
- Category-based filtering  
- Conditional availability
- Custom scan directories

### 🔧 **Developer Experience**
- Simple interface to implement
- Automatic registration
- Comprehensive testing
- Clear documentation

## Migration from Static System

The system maintains 100% backward compatibility:

1. **Existing generators** work unchanged
2. **Static fallback** if discovery fails  
3. **Legacy factory methods** still work
4. **Gradual migration** - update generators one by one

## Production Considerations

### Performance
- **Caching**: Results are cached to avoid repeated filesystem scanning
- **Fallback**: Static list used if discovery fails
- **Error Handling**: Individual generator failures don't break the system

### Security  
- **Class validation**: Only valid generator classes are loaded
- **Namespace restrictions**: Discovery is limited to specific namespaces
- **Error isolation**: Bad generators don't affect others

### Monitoring
- **Logging**: Discovery errors are logged but don't break functionality
- **Debugging**: Discovery demo script shows what's being found
- **Validation**: Comprehensive tests ensure system integrity

## Future Enhancements

Possible future improvements:

1. **Plugin System** - Load generators from separate composer packages
2. **Web UI** - Admin interface to enable/disable discovered generators  
3. **Versioning** - Handle multiple versions of the same generator
4. **Dependencies** - Generators could declare dependencies on others
5. **Hot Reload** - Detect new generators without restart

## Summary

The Dynamic Generator Discovery system transforms the catalogue generation from a static, hardcoded system to a truly extensible, plugin-like architecture. Developers can now:

- **Add new outputs** by simply creating a class file
- **Organize generators** by category and priority
- **Control availability** based on configuration or environment
- **Maintain compatibility** while extending functionality

This makes the KSF Generate Catalogue system future-proof and easily extensible for any new export format or integration requirements.
