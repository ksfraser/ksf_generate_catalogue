# FrontAccounting Generate Catalogue Library

A composer library for generating catalogue exports from FrontAccounting ERP system.

## Overview

This library refactors the KSF Generate Catalogue module into a reusable composer package with **automatic generator discovery**:

- **BaseCatalogueGenerator**: Abstract base class for all catalogue generators
- **Dynamic Generator Discovery**: Automatically finds and registers generator classes
- **Self-Describing Generators**: Generators report their own metadata
- **Extensible Architecture**: Add new generators by simply dropping in class files
- **WoocommerceImport**: Generate WooCommerce CSV imports
- **SquareCatalog**: Generate Square catalog imports  
- **LabelsFile**: Generate product label files
- **PricebookFile**: Generate pricebook exports
- **DatabaseInterface**: Abstract database operations for flexibility

## Requirements

- PHP 7.3 or higher
- Composer for dependency management

## Installation

### Basic Installation

```bash
composer require ksfraser/frontaccounting-gencat
```

### Installation with VCS Dependencies

This library works with other `ksf-*` packages that are hosted in private VCS repositories. To use the full functionality:

1. **Configure GitHub authentication** (for private repositories):
   ```bash
   composer config --global github-oauth.github.com YOUR_GITHUB_TOKEN
   ```
   
   Get a token from: https://github.com/settings/tokens

2. **Install with suggested packages**:
   ```bash
   composer require ksfraser/frontaccounting-gencat
   composer require ksfraser/frontaccounting-common
   composer require ksfraser/ksf-file
   ```

### Development Installation

For development with testing and documentation tools:

```bash
git clone https://github.com/ksfraser/frontaccounting-gencat.git
cd frontaccounting-gencat
composer install --dev
```

## Usage

### Auto-Discovery Usage

The system automatically discovers all available generators:

```php
use Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory;

// Create factory
$factory = new CatalogueGeneratorFactory($database, 'preferences_table');

// Get all available generators (automatically discovered)
$generators = $factory->getAvailableGenerators();
// Returns: ['woocommerce' => [...], 'square' => [...], 'amazon' => [...], ...]

// Create any generator by name
$generator = $factory->createGeneratorByName('square');
$fileCount = $generator->createFile();

// Filter by category
$ecommerceGens = $factory->getGeneratorsByCategory('ecommerce');
$posGens = $factory->getGeneratorsByCategory('pos');
```

### Adding New Generators

Simply create a new generator class - **no manual registration required**:

```php
<?php
namespace Ksfraser\Frontaccounting\GenCat;

class MyCustomGenerator extends BaseCatalogueGenerator
{
    // Tell the system about this generator
    public static function getGeneratorMetadata() {
        return [
            'name' => 'mycustom',
            'title' => 'My Custom Export',
            'description' => 'Custom file format export',
            'category' => 'custom'
        ];
    }

    // Your file generation logic
    public function createFile() {
        // ... implement your export logic
    }
}
```

The system will **automatically discover** your new generator!

See [QUICK_START_GENERATOR.md](../QUICK_START_GENERATOR.md) for a complete tutorial.

### With FrontAccounting Integration

```php
// In your FrontAccounting module
include_once('path/to/vendor/autoload.php');

use Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory;
use Ksfraser\Frontaccounting\GenCat\FrontAccountingDatabase;

$database = new FrontAccountingDatabase();
$factory = new CatalogueGeneratorFactory($database, $this->prefs_tablename);

$generator = $factory->createSquareGenerator();
$result = $generator->createFile();
```

## Testing

Run the test suite:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

### Discovery System Testing

Test the automatic discovery system:

```bash
# Run discovery-specific tests
php vendor/phpunit/phpunit/phpunit tests/Unit/GeneratorDiscoveryTest.php

# Test discovery in action
php discovery_demo.php
```

**Current Test Results:**
- ✅ **20 tests, 225 assertions** - All passing
- ✅ **9 discovery tests, 152 assertions** - Auto-discovery fully tested
- ✅ **6 generators discovered** automatically including the example AmazonImport

## Documentation

Generate API documentation:

```bash
composer docs
```

This will create documentation in the `docs/` directory using PHPDocumentor.

## Architecture

### Dynamic Discovery System

The library features a powerful **Dynamic Generator Discovery** mechanism:

- 🔍 **Auto-Discovery**: Scans directories for generator classes
- 📋 **Self-Describing**: Generators report their own metadata  
- 🎯 **Priority Ordering**: Generators specify their display order
- 📂 **Category Grouping**: Organize generators by type (ecommerce, pos, marketplace, etc.)
- ✅ **Conditional Availability**: Generators can enable/disable themselves
- 🚀 **Drop-in Extensible**: Add new generators by simply creating class files

### Core Components

1. **GeneratorDiscovery**: Service that automatically finds and registers generators
2. **GeneratorMetadataInterface**: Interface for self-describing generators
3. **CatalogueGeneratorFactory**: Factory with automatic generator discovery
4. **BaseCatalogueGenerator**: Abstract base class providing common functionality
5. **Individual Generator Classes**: Specialized classes for each output format

### Class Hierarchy

```
GeneratorMetadataInterface
BaseCatalogueGenerator (abstract) + GeneratorMetadataInterface
├── PricebookFileGenerator
├── WoocommerceImportGenerator  
├── SquareCatalogGenerator
├── WooPOSCountGenerator
├── LabelsFileGenerator
└── AmazonImportGenerator (example)
```

For complete documentation see: [DYNAMIC_DISCOVERY_SYSTEM.md](../DYNAMIC_DISCOVERY_SYSTEM.md)

## VCS Package Management

These libraries are not published on Packagist. If you're consuming this library from another project, you must tell Composer where to fetch the `ksfraser/*` packages from.

Important: `repositories` are NOT transitive in Composer. Each consuming project (the “root” `composer.json`) needs its own `repositories` entries.

### GitHub-only setup (copy/paste)

Add this to the consuming project's `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/ksfraser/Prefs.git" },
        { "type": "vcs", "url": "https://github.com/ksfraser/ksf_ModulesDAO.git" },
        { "type": "vcs", "url": "https://github.com/ksfraser/html.git" }
    ]
}
```

Then require the packages normally:

```bash
composer require ksfraser/frontaccounting-gencat
```

### Local development mode (optional)

When developing across multiple repos locally, prefer `path` repositories in the *root* project (before the `vcs` entries):

```json
{
    "repositories": [
        { "type": "path", "url": "../Prefs", "options": { "symlink": true } },
        { "type": "path", "url": "../ModulesDAO", "options": { "symlink": true } },
        { "type": "path", "url": "../html", "options": { "symlink": true } },
        { "type": "vcs", "url": "https://github.com/ksfraser/Prefs.git" },
        { "type": "vcs", "url": "https://github.com/ksfraser/ksf_ModulesDAO.git" },
        { "type": "vcs", "url": "https://github.com/ksfraser/html.git" }
    ]
}
```

This allows you to work on the libraries in-place locally, while still having a clean “GitHub-only” path for deployments.

## PHP 7.3 Compatibility

This library is designed to be compatible with PHP 7.3+ while taking advantage of newer PHP features when available:

- Uses compatible PHPUnit 9.x for testing
- Compatible type hints and return types
- PSR-4 autoloading
- Modern namespace structure

## License

GPL-3.0-or-later

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Run the test suite: `composer test`
5. Submit a pull request

## Support

For issues and questions:

- GitHub Issues: https://github.com/ksfraser/frontaccounting-gencat/issues
- Email: kevin@ksfraser.com
