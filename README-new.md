# Membershiping Inventory & Trading System

Advanced inventory and trading system addon for Membershiping CRM with NFT support, custom currencies, virtual items, and enhanced WooCommerce integration.

## Features

### Core Functionality
- **Virtual Items Management** - Create, manage, and track virtual items and collectibles
- **Custom Currencies** - Multiple currency systems with exchange rates and user balances
- **NFT Support** - Blockchain-based unique item verification and trading
- **Trading System** - Secure peer-to-peer item and currency trading
- **Flag Awards** - Automatic flag awarding based on WooCommerce product purchases

### Enhanced WooCommerce Integration
- **Currency Payments** - Allow customers to purchase products using plugin currencies
- **Item-Based Special Pricing** - Special pricing for users who own specific items
- **Admin Configuration** - Easy setup through WooCommerce product edit pages
- **Real-time Balance Checking** - Validates user balances before purchases
- **Automatic Processing** - Seamless integration with WooCommerce checkout

### Security & Compliance
- **Comprehensive Security** - Input validation, capability checks, nonce verification
- **Audit Logging** - Complete transaction and security event logging
- **HPOS Compatibility** - Full support for WooCommerce High-Performance Order Storage
- **WordPress Standards** - Follows WordPress coding standards and best practices

## Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 8.1 or higher
- **WooCommerce**: 8.0 or higher
- **Membershiping Core Plugin**: Required dependency

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Ensure Membershiping Core Plugin is installed and active
4. Configure currencies and items through the admin interface

## Configuration

### Basic Setup
1. Go to **Membershiping → Inventory** in WordPress admin
2. Create currencies in the **Currencies** section
3. Create items in the **Items** section
4. Configure trading settings if needed

### WooCommerce Integration
1. Edit any WooCommerce product
2. Scroll to **Enhanced Inventory Pricing** section
3. Configure currency payments and/or item-based pricing
4. Save the product

## Documentation

- [Enhanced WooCommerce Integration](ENHANCED-WOOCOMMERCE-DOCUMENTATION.md)
- [Flag Awards System](FLAG-AWARDS-DOCUMENTATION.md)
- [Complete System Documentation](FULL-DOCUMENTATION.md)

## Testing

Run the comprehensive test suite:
- **Enhanced WooCommerce**: `/test-enhanced-woocommerce.php`
- **Flag Awards**: `/test-flag-awards.php`
- **General Functionality**: `/test-plugin-functionality.php`

## Changelog

### Version 1.0.0 (2025-09-07)
- Initial release with complete inventory management system
- Custom currencies with user balances and exchange rates
- Virtual items and collectibles with rarity system
- NFT support and blockchain integration
- Trading system for peer-to-peer exchanges
- Flag awards based on WooCommerce purchases
- Enhanced WooCommerce integration with currency payments
- Item-based special pricing system
- Comprehensive admin interface
- Security and audit logging
- HPOS compatibility

## Support

For technical support and documentation:
1. Check the test files for system validation
2. Review the documentation files for detailed setup instructions
3. Check WordPress debug logs for error messages
4. Ensure all prerequisites are met

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Author

Membershiping Team - https://membershiping.com

---

**Note**: This addon requires the Membershiping Core Plugin to function properly. Ensure proper licensing and compatibility before deployment.
