# FrontAccounting Generate Catalogue Base Module

This is the refactored base module for FrontAccounting Generate Catalogue functionality. It has been designed to work with the composer library `ksfraser/frontaccounting-gencat` while maintaining backward compatibility with legacy code.

## Architecture

The module now follows a hybrid approach:

1. **Primary Mode**: Uses the composer library `ksfraser/frontaccounting-gencat` for all catalogue generation functionality
2. **Fallback Mode**: Falls back to legacy classes if the composer library is not available

## Installation

### Method 1: Using Composer (Recommended)

1. Navigate to your FrontAccounting modules directory
2. Add GitHub repositories (these packages are not on Packagist):
   ```json
   {
      "repositories": [
         { "type": "vcs", "url": "https://github.com/ksfraser/Prefs.git" },
         { "type": "vcs", "url": "https://github.com/ksfraser/ksf_ModulesDAO.git" },
         { "type": "vcs", "url": "https://github.com/ksfraser/html.git" },
         { "type": "vcs", "url": "https://github.com/ksfraser/ksf_generate_catalogue.git" }
      ]
   }
   ```
3. Install the module with composer:
   ```bash
   composer install
   ```

### Method 2: Legacy Installation

If you don't want to use composer, the legacy classes are still included for backward compatibility.

## File Structure

```
base-module/
├── class.ksf_generate_catalogue.php    # Main module class (refactored)
├── hooks.php                           # FrontAccounting hooks
├── ksf_generate_catalogue.php          # Module entry point
├── ksf_generate_catalogue.inc.php      # Module constants
├── composer.json                       # Composer dependencies
└── README.md                          # This file
```

## Features

- **WooCommerce Import**: Generate CSV files for WooCommerce product import
- **Square Catalog**: Generate CSV files for Square POS import
- **Labels Generation**: Create product labels for various purposes
- **Pricebook Generation**: Create price lists and catalogues
- **Purchase Order Labels**: Generate labels for incoming purchase orders
- **Single SKU Labels**: Generate labels for individual products

## Composer Library Integration

When the composer library is available, the module will use it for:
- Better code organization
- Improved testability
- More maintainable code structure
- Dependency injection for database operations

## Legacy Compatibility

The module maintains full backward compatibility. If the composer library is not available, it will automatically fall back to the original classes:
- `class.pricebook_file.php`
- `class.square_catalog.php`
- `class.woocommerce_import.php`
- `class.labels_file.php`
- `class.WooPOS_Count.php`

## Configuration

The module uses the same configuration system as the original. All settings are managed through the FrontAccounting preferences system.

## Dependencies

### Required (Composer Mode)
- `ksfraser/frontaccounting-gencat` - The main composer library
- `ksfraser/frontaccounting-common` - Common FA utilities
- `ksfraser/ksf-file` - File handling utilities

### Optional (Legacy Mode)
- Legacy classes in the original module directory

## Migration Notes

For existing installations:

1. **Automatic Migration**: The module will detect if composer dependencies are available and use them automatically
2. **No Configuration Changes**: All existing configurations will continue to work
3. **Gradual Migration**: You can migrate to composer gradually, with full fallback support

## Development

For development work on this module:

1. Make changes to the composer library for business logic
2. Update the base module only for FrontAccounting integration
3. Test both composer and legacy modes

## License

GPL-3.0-or-later
