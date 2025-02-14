# Easy Digital Downloads - Discount Cloner

A WordPress plugin that adds the ability to clone discount codes in Easy Digital Downloads, including all settings and metadata.

## Description

This plugin adds a "Clone" action to the Easy Digital Downloads discount codes table. When cloning a discount code, it creates an exact copy of the original discount with:

- A new name (original name with "(Copy)" appended)
- A unique code (original code with "-1", "-2", etc. appended)
- Inactive status (to prevent accidental use)
- Reset usage count
- All other settings preserved, including:
  - Amount and type (percentage or flat)
  - Start and end dates
  - Minimum purchase requirements
  - Maximum uses
  - Product requirements and exclusions
  - Category requirements and exclusions
  - Usage restrictions

After cloning, you'll be shown a success message with a direct link to edit the newly created discount code.

## Installation

1. Download the plugin zip file
2. Go to WordPress admin > Plugins > Add New
3. Click "Upload Plugin"
4. Upload the zip file
5. Activate the plugin

Or manually:

1. Download and unzip the plugin
2. Upload the `edd-discount-cloner` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin

## Usage

1. Go to Downloads > Discount Codes
2. Hover over any discount code
3. Click the "Clone" link that appears
4. A new inactive copy of the discount will be created
5. Click the "Edit the cloned discount" link in the success message to review and modify the new discount

## Requirements

- WordPress 4.7 or higher
- Easy Digital Downloads 2.9 or higher
- PHP 5.6 or higher

## Security

The plugin includes several security measures:

- Nonce verification for clone actions
- Capability checks (`manage_shop_discounts` required)
- Data sanitization and validation
- Safe redirects and escaped output

## Support

For bug reports or feature requests, please [open an issue](https://github.com/GravityKit/edd-discount-cloner/issues) on GitHub.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.0.0
- Initial release
- Added ability to clone discount codes
- Added direct link to edit newly created discounts 