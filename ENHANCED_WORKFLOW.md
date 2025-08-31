# Enhanced Catalogue Generation Workflow

## Overview

The KSF Generate Catalogue module has been enhanced with a dynamic, flexible workflow that allows users to:

1. **Generate all catalogue files at once** (existing functionality preserved)
2. **Generate individual catalogue files** (new functionality) 
3. **Dynamically discover available generators** from the composer library
4. **Maintain backward compatibility** with legacy classes

## New Features

### 1. Dynamic Generator Discovery

The system now dynamically discovers available catalogue generators through the `CatalogueGeneratorFactory::getAvailableGenerators()` method:

```php
public function getAvailableGenerators()
{
    return [
        [
            'name' => 'pricebook',
            'title' => 'Pricebook File', 
            'class' => 'PricebookFile',
            'description' => 'Generate pricebook CSV file for retail pricing',
            'method' => 'createPricebookFile'
        ],
        [
            'name' => 'square',
            'title' => 'Square Catalog',
            'class' => 'SquareCatalog',
            'description' => 'Generate Square catalog import file', 
            'method' => 'createSquareCatalog'
        ],
        // ... more generators
    ];
}
```

### 2. Individual Generator Creation

Users can now create generators by name using the factory:

```php
$generator = $factory->createGeneratorByName('pricebook', $config);
$rowCount = $generator->createFile();
```

### 3. Enhanced User Interface

The "Export File" tab now shows:

- **Generate All Files** section with the existing combined button
- **Generate Individual Files** section with dynamic buttons for each available generator
- Descriptive text for each generator type

## Architecture Enhancements

### Base Module Changes (`class.ksf_generate_catalogue.php`)

#### New Methods Added:

1. **`getAvailableGenerators()`** - Dynamically retrieves generators from factory or falls back to static list
2. **`createSingleGenerator($type)`** - Creates and runs a single generator type
3. **`createSingleGeneratorLegacy($type)`** - Legacy fallback for single generators
4. **`applyConfigToLegacyClass($instance)`** - Applies configuration to legacy class instances
5. **Individual form handlers** - `form_individual_pricebook()`, `form_individual_square()`, etc.

#### Enhanced Tab System:

New tabs added for individual generators:
```php
$this->tabs[] = array('title' => 'Pricebook Generated', 'action' => 'gen_pricebook', 'form' => 'form_individual_pricebook', 'hidden' => TRUE);
$this->tabs[] = array('title' => 'Square Catalog Generated', 'action' => 'gen_square', 'form' => 'form_individual_square', 'hidden' => TRUE);
// ... more individual tabs
```

#### Updated Form Display:

The `write_file_form()` method now displays:
- A combined "Generate All Files" section (preserving existing functionality)
- Individual generator buttons with descriptions
- Dynamic button generation based on available generators

### Composer Library Changes (`CatalogueGeneratorFactory.php`)

#### New Methods Added:

1. **`getAvailableGenerators()`** - Returns metadata about all available generators
2. **`createGeneratorByName($name, $config)`** - Creates generators dynamically by name using reflection

## Usage Examples

### For FrontAccounting Users

#### Generate All Files (Existing Workflow)
1. Go to "Export File" tab
2. Click "Create All Catalogue Files" (or "Create All Catalogue Files and Labels")
3. All generators run sequentially (Pricebook, Square, WooCommerce, WooPOS)

#### Generate Individual File (New Workflow)
1. Go to "Export File" tab
2. In the "Generate Individual Files" section, click the desired generator button:
   - "Generate Pricebook File"
   - "Generate Square Catalog" 
   - "Generate WooCommerce Import"
   - "Generate WooPOS Count"
3. Only the selected generator runs

### For Developers

#### Adding New Generators
To add a new generator type:

1. **Create the generator class** in the composer library extending `BaseCatalogueGenerator`
2. **Add factory method** in `CatalogueGeneratorFactory`
3. **Update the generator list** in `getAvailableGenerators()`
4. **Add tab and form handler** in the base module (optional, or it will auto-generate)

Example:
```php
// In CatalogueGeneratorFactory.php
public function createMyNewGenerator($config = [])
{
    $generator = new MyNewGenerator($this->prefs_tablename);
    $generator->setDatabase($this->database);
    $this->applyConfig($generator, $config);
    return $generator;
}

// Add to getAvailableGenerators() array:
[
    'name' => 'mynew',
    'title' => 'My New Generator',
    'class' => 'MyNewGenerator',
    'description' => 'Generate my new file format',
    'method' => 'createMyNewGenerator'
]
```

## Backward Compatibility

The system maintains 100% backward compatibility:

1. **Existing "Generate Catalogue" functionality** is preserved
2. **Legacy class fallbacks** work when composer library is unavailable
3. **Existing configuration** and preferences are maintained
4. **API compatibility** with existing FrontAccounting integration

## Testing

Comprehensive PHPUnit tests have been added:

- `BaseCatalogueGeneratorTest.php` - Tests base functionality (6 tests, 9 assertions)
- `CatalogueGeneratorFactoryTest.php` - Tests enhanced factory features (5 tests, 64 assertions)

All 11 tests pass with 73 total assertions.

### Running Tests

```bash
cd composer-lib
php vendor/phpunit/phpunit/phpunit tests/
```

## Benefits

1. **Flexibility** - Users can generate only what they need
2. **Efficiency** - Avoid generating unnecessary files
3. **Extensibility** - Easy to add new generator types
4. **Maintainability** - Dynamic discovery reduces hardcoded lists
5. **User Experience** - Clear, descriptive interface with individual options
6. **Performance** - Generate only required files instead of all files

## Future Enhancements

Possible future improvements:

1. **Batch Selection** - Allow users to select multiple individual generators
2. **Scheduling** - Individual generators could be scheduled separately  
3. **Progress Indicators** - Show progress for long-running individual generators
4. **Generator Configuration** - Per-generator configuration options
5. **Results Dashboard** - Summary of individual generation results

## Summary

The enhanced workflow provides a powerful, flexible system for catalogue generation while maintaining complete backward compatibility. Users can now choose between the convenience of generating all files at once or the precision of generating individual files as needed. The system is extensible and maintainable through dynamic generator discovery.
