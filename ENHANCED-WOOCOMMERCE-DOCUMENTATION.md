# Enhanced WooCommerce Integration Documentation

## Overview

The Enhanced WooCommerce Integration extends the Membershiping Inventory addon with advanced e-commerce features that allow customers to purchase products using plugin currencies and receive special pricing based on owned items.

## Key Features

### 1. Currency Payment System
- **Purchase with Plugin Currencies**: Customers can buy products using currencies from the inventory system instead of regular money
- **Multiple Currency Options**: Products can accept payment in multiple different currencies
- **Real-time Balance Checking**: System validates user balances before allowing purchases
- **Automatic Deduction**: Currency amounts are automatically deducted from user accounts upon order completion

### 2. Item-Based Special Pricing
- **Ownership-Based Discounts**: Users who own specific items receive special pricing
- **Quantity Requirements**: Set minimum quantities of items required for special pricing
- **Multiple Item Tiers**: Different items can provide different discount levels
- **Transparent Display**: Users can see what items they need for better pricing

### 3. Admin Configuration Interface
- **Product Meta Boxes**: Easy-to-use admin interface in WooCommerce product edit pages
- **Dynamic Fields**: Add/remove currency pricing and item requirements as needed
- **Validation**: Built-in validation ensures proper configuration

## Setup Guide

### Prerequisites
- WordPress with WooCommerce active
- Membershiping Core Plugin installed
- Membershiping Inventory Addon active
- At least one currency configured in the inventory system
- At least one item configured in the inventory system

### Step 1: Configure Product for Currency Payments

1. Go to **WooCommerce → Products**
2. Edit any product or create a new one
3. Scroll down to the **"Enhanced Inventory Pricing"** section
4. Under **"Currency Payment Options"**:
   - Check **"Allow Currency Payment"**
   - Click **"Add Currency Price"** to add currency options
   - Select a currency from the dropdown
   - Enter the price in that currency
   - Add multiple currencies as needed

### Step 2: Configure Item-Based Special Pricing

1. In the same **"Enhanced Inventory Pricing"** section
2. Under **"Item-Based Special Pricing"**:
   - Optionally check **"Show Item Pricing to All"** to display pricing info to users who don't qualify
   - Click **"Add Item Price"** to create special pricing rules
   - Select an item from the dropdown
   - Set the **quantity required** of that item
   - Set the **special price** for users who own that quantity
   - Add multiple item pricing rules as needed

### Step 3: Test the Configuration

1. Visit the product page on the frontend
2. If currency payments are enabled, you should see **"Payment Options"** section
3. If item-based pricing is configured, you should see **"Item-Based Special Pricing"** section
4. The system will show user balances and item ownership status

## Frontend User Experience

### Currency Payment Options
When a product supports currency payments, users will see:
- Available payment methods with currency options
- Current balance for each currency
- "Insufficient funds" warnings if balance is too low
- Real-time balance updates when quantity changes

### Item-Based Pricing Display
When item-based pricing is configured, users will see:
- Items they own that qualify for special pricing (✓ green)
- Items they don't own that could provide special pricing (✗ orange)
- Required quantities vs. owned quantities
- Potential savings amounts

### Checkout Process
- Currency payments automatically deduct from user balances
- Order total is set to $0 when paid with currency
- Item-based pricing applies automatically to qualified users
- All transactions are logged for audit purposes

## Technical Implementation

### Database Integration
- Uses existing `membershiping_inventory_currencies` table
- Uses existing `membershiping_inventory_user_currencies` table for balances
- Uses existing `membershiping_inventory_items` table
- Uses existing `membershiping_inventory_user_items` table for ownership
- Stores product configuration in WooCommerce meta fields

### Meta Fields Used
- `_membershiping_allow_currency_payment`: Enable/disable currency payments
- `_membershiping_currency_prices`: JSON array of currency pricing options
- `_membershiping_show_item_pricing_to_all`: Show item pricing to non-qualifying users
- `_membershiping_item_specific_prices`: JSON array of item-based pricing rules

### Security Features
- Nonce validation for all AJAX requests
- User capability checks
- Input sanitization and validation
- Transaction logging
- Balance verification before deduction

## API Reference

### Main Class: `Membershiping_Inventory_Enhanced_WooCommerce_Integration`

#### Key Methods

**`add_enhanced_pricing_fields()`**
- Adds admin interface fields to product edit pages
- Creates dynamic forms for currency and item pricing

**`save_enhanced_pricing_fields($post_id)`**
- Saves currency and item pricing configuration
- Validates and sanitizes all input data

**`modify_price_display_for_currencies($price_html, $product)`**
- Modifies product price display to show currency options
- Adds currency pricing information to price HTML

**`display_currency_payment_options()`**
- Shows currency payment options on product pages
- Displays user balances and affordability status

**`display_item_based_pricing()`**
- Shows item-based special pricing information
- Displays user qualification status and requirements

**`apply_item_based_pricing($price, $product)`**
- Calculates and applies item-based special pricing
- Returns the best available price for the user

**`validate_currency_payment($passed, $product_id, $quantity)`**
- Validates currency payment before adding to cart
- Checks user balances and currency availability

**`process_currency_payment($order, $data)`**
- Processes currency payment during checkout
- Deducts currency amounts and updates order total

### Currency Management Methods

**`Membershiping_Inventory_Currencies::get_user_balance($user_id, $currency_id)`**
- Returns user's balance for a specific currency
- Used for balance checking and display

**`Membershiping_Inventory_Currencies::deduct_user_balance($user_id, $currency_id, $amount)`**
- Deducts specified amount from user's currency balance
- Includes validation and error handling

### Item Management Methods

**`Membershiping_Inventory_Items::get_user_item_quantity($user_id, $item_id)`**
- Returns user's quantity of a specific item
- Used for item-based pricing qualification

## JavaScript Integration

### Frontend Script: `enhanced-woocommerce.js`
- Real-time balance checking
- Payment method validation
- Dynamic pricing updates
- User interface enhancements

### AJAX Endpoints
- `membershiping_check_currency_balance`: Check user currency balance
- `membershiping_get_item_pricing`: Get item pricing information

## CSS Styling

### Style Classes
- `.membershiping-currency-payment-options`: Currency payment section
- `.currency-payment-option`: Individual currency option
- `.membershiping-item-based-pricing`: Item pricing section
- `.item-pricing-qualified`: Qualified item pricing (green)
- `.item-pricing-unqualified`: Unqualified item pricing (orange)

## Troubleshooting

### Common Issues

**Currency payments not showing**
- Verify WooCommerce is active
- Check that currency payment is enabled for the product
- Ensure at least one currency price is configured
- Verify user is logged in

**Item-based pricing not applying**
- Check that item-based pricing is configured for the product
- Verify user owns the required quantity of items
- Ensure items exist and are active in the system

**Balance deduction not working**
- Check user has sufficient balance
- Verify currency is active
- Check for PHP errors in logs
- Ensure proper user permissions

**Admin interface not showing**
- Verify enhanced integration class is loaded
- Check for JavaScript errors in browser console
- Ensure proper WordPress admin permissions

### Debug Mode
Add this to wp-config.php for detailed logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs in `/wp-content/debug.log` for detailed error information.

## Compatibility

### Core Plugin Integration
- Compatible with existing Membershiping flag/badge pricing
- Works alongside standard WooCommerce pricing
- Integrates with membership levels and restrictions

### Theme Compatibility
- Uses standard WooCommerce hooks and filters
- CSS can be customized for theme integration
- JavaScript follows WordPress standards

### Plugin Compatibility
- Compatible with most WooCommerce extensions
- May require testing with complex checkout plugins
- Works with membership and restriction plugins

## Performance Considerations

### Database Optimization
- Uses indexed database queries
- Minimal additional database calls
- Caches user balances when possible

### Frontend Performance
- Loads scripts only on product pages
- Minified CSS and JavaScript
- Efficient AJAX calls with proper caching

## Future Enhancements

### Planned Features
- Bulk pricing configuration
- Currency exchange rate integration
- Advanced item requirement logic (AND/OR conditions)
- Subscription-based currency payments
- Enhanced reporting and analytics

### Extension Points
- Custom hooks for third-party integration
- Filter hooks for pricing calculations
- Action hooks for transaction events
- API endpoints for external systems

## Support

For technical support and questions:
1. Check the test file: `/test-enhanced-woocommerce.php`
2. Review debug logs for error messages
3. Verify all prerequisites are met
4. Test with default WordPress theme to isolate conflicts

## Changelog

### Version 1.0.0
- Initial release with currency payments
- Item-based special pricing system
- Complete admin interface
- Frontend user experience
- Security and validation features
