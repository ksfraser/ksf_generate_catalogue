# FrontAccounting Generate Catalogue Library

A composer library for generating catalogue exports from FrontAccounting ERP system.

## Overview

This library refactors the KSF Generate Catalogue module into a reusable composer package that provides:

- **BaseCatalogueGenerator**: Abstract base class for all catalogue generators
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

### Basic Usage

```php
use Ksfraser\Frontaccounting\GenCat\CatalogueGeneratorFactory;
use Ksfraser\Frontaccounting\GenCat\DatabaseInterface;

// Create database adapter
$database = new YourDatabaseAdapter(); // implements DatabaseInterface

// Create factory
$factory = new CatalogueGeneratorFactory($database, 'preferences_table');

// Generate WooCommerce catalog
$generator = $factory->createWoocommerceGenerator();
$fileCount = $generator->createFile();
```

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

## Documentation

Generate API documentation:

```bash
composer docs
```

This will create documentation in the `docs/` directory using PHPDocumentor.

## Architecture

### Hybrid Integration

This library is designed to work as part of a hybrid architecture:

- **Base Module**: Contains FrontAccounting-specific integration code
- **Composer Library**: Contains reusable business logic classes
- **Automatic Fallback**: Falls back to legacy classes if composer library unavailable

### Database Abstraction

The `DatabaseInterface` allows the library to work with different database implementations:

- `FrontAccountingDatabase`: For FrontAccounting integration
- Custom implementations for other frameworks
- Mock implementations for testing

## VCS Package Management

All `ksf-*` packages are hosted in VCS repositories. The composer.json includes repository definitions:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ksfraser/frontaccounting-common"
        },
        {
            "type": "vcs", 
            "url": "https://github.com/ksfraser/ksf-file"
        }
    ]
}
```

These packages are marked as "suggest" rather than "require" to allow the library to work independently when needed.

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
