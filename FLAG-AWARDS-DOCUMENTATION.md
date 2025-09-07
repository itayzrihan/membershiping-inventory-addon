# Flag Awarding System Documentation

## Overview

The Membershiping Inventory addon includes a comprehensive flag awarding system that automatically awards flags to users when they purchase specific WooCommerce products. This system supports both registered users and guest purchases, with automatic claiming when guests register.

---

## 🏗️ **System Architecture**

### Database Structure

The system uses the `membershiping_inventory_product_flags` table to link products to flags:

```sql
CREATE TABLE {prefix}membershiping_inventory_product_flags (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    product_id bigint(20) UNSIGNED NOT NULL,
    flag_id mediumint(9) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY product_flag_unique (product_id, flag_id)
);
```

### Core Components

1. **Flag Awards Class** (`class-flag-awards.php`)
   - Manages product-flag relationships
   - Handles flag awarding logic
   - Provides admin interface for configuration

2. **WooCommerce Integration** (`class-woocommerce-integration.php`)
   - Hooks into order completion events
   - Processes flag awards on purchase

3. **Database Management** (`class-database.php`)
   - Creates and manages flag tables
   - Provides data access methods

---

## 🔧 **Setup and Configuration**

### Step 1: Link Products to Flags

1. **Go to WooCommerce Products**
   - Navigate to WooCommerce → Products
   - Edit any product you want to link to flags

2. **Configure Flag Awards**
   - Scroll down to the "Membershiping Inventory Settings" meta box
   - In the "Flag Awards Configuration" section:
     - Enter a Flag ID (numeric ID from your flag system)
     - Enter a Flag Name for reference
     - Click "Link Flag"

3. **Alternative Configuration (Meta Fields)**
   - In the product data panel, look for "Flag Awards" section
   - Enable flag awards
   - Configure flag name, quantity, and type (add/set/multiply)

### Step 2: Verify Integration

1. **Check Database Tables**
   - Ensure `membershiping_inventory_product_flags` table exists
   - Verify foreign key relationships are properly set

2. **Test Flag Awarding**
   - Use the test file: `test-flag-awards.php`
   - Visit: `/wp-admin/admin.php?page=membershiping-inventory&test_flag_awards=1`

---

## 🎯 **Flag Awarding Workflow**

### For Registered Users

1. **User completes purchase** → WooCommerce order status changes to "completed" or "processing"
2. **System processes order** → `process_order_completion()` is called
3. **Flag configuration retrieved** → System checks linked flags for each product
4. **Flags awarded** → Flags are awarded via Membershiping Core integration
5. **Activity logged** → All awards are logged for audit purposes

### For Guest Users

1. **Guest completes purchase** → Order processed normally
2. **Awards stored** → Flag awards stored in `guest_flag_awards` table
3. **User registers/logs in** → System checks for pending awards by email
4. **Awards claimed** → Pending awards automatically transferred to user account

---

## 🔌 **Integration Points**

### Membershiping Core Integration

The system integrates with Membershiping Core using multiple fallback methods:

```php
// Primary integration
if (function_exists('membershiping_award_flag')) {
    membershiping_award_flag($user_id, $flag_name, $quantity, $type);
}

// Alternative integration
elseif (function_exists('membershiping_set_user_flag')) {
    membershiping_set_user_flag($user_id, $flag_name, $calculated_value);
}

// Fallback storage
else {
    $this->store_flag_award($user_id, $flag_name, $quantity, $type, $order_id, $product_id);
}
```

### WooCommerce Hooks

- `woocommerce_order_status_completed` - Processes flag awards on order completion
- `woocommerce_order_status_processing` - Processes flag awards for processing orders
- `woocommerce_process_product_meta` - Saves flag configuration in admin

---

## 📝 **Configuration Options**

### Flag Award Types

- **Add**: Adds quantity to existing flag value
- **Set**: Sets flag value to specified quantity
- **Multiply**: Multiplies existing flag value by quantity

### Product Meta Fields

- `_membershiping_enable_flag_awards` - Enable/disable flag awards for product
- `_membershiping_flag_awards` - Array of flag configurations
- `_membershiping_flag_name_{flag_id}` - Flag name for reference
- `_membershiping_flag_quantity_{flag_id}` - Quantity to award
- `_membershiping_flag_type_{flag_id}` - Award type (add/set/multiply)

---

## 🛠️ **Developer API**

### Link Product to Flag

```php
$flag_awards = new Membershiping_Inventory_Flag_Awards();
$result = $flag_awards->link_product_to_flag($product_id, $flag_id);

if (is_wp_error($result)) {
    // Handle error
    echo $result->get_error_message();
} else {
    // Success - $result contains the link ID
    echo "Linked successfully with ID: " . $result;
}
```

### Get Product Flags

```php
$flag_awards = new Membershiping_Inventory_Flag_Awards();
$linked_flags = $flag_awards->get_product_linked_flags($product_id);

foreach ($linked_flags as $flag) {
    echo "Flag ID: " . $flag->flag_id . " (linked on " . $flag->created_at . ")";
}
```

### Process Flag Awards Manually

```php
$flag_awards = new Membershiping_Inventory_Flag_Awards();
$flag_awards->process_order_completion($order_id);
```

---

## 🧪 **Testing**

### Automated Testing

Use the built-in test file to verify system functionality:

```bash
# Access via admin URL
/wp-admin/admin.php?page=membershiping-inventory&test_flag_awards=1
```

### Manual Testing

1. **Create a test product** with flag awards enabled
2. **Link to a test flag** using the admin interface
3. **Complete a test purchase** as a registered user
4. **Verify flag was awarded** in your flag system
5. **Check logs** for audit trail

### Test Scenarios

- ✅ Registered user purchase
- ✅ Guest user purchase + later registration
- ✅ Multiple flags per product
- ✅ Different award types (add/set/multiply)
- ✅ Error handling and validation
- ✅ Database integrity

---

## 🔍 **Troubleshooting**

### Common Issues

**Flags not being awarded:**
- Check that WooCommerce hooks are properly registered
- Verify product-flag links in database
- Ensure Membershiping Core integration is working
- Check order completion triggers

**Database errors:**
- Verify table structure matches schema
- Check foreign key constraints
- Ensure proper user permissions

**Admin interface not working:**
- Verify AJAX handlers are registered
- Check user capabilities
- Ensure nonce validation is working

### Debug Information

Enable debug logging by adding this to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs for:
- Flag award attempts
- Database operations
- Integration errors
- Security events

---

## 🔐 **Security Considerations**

### Access Control

- Admin functions require `edit_posts` capability
- AJAX requests use nonce verification
- All inputs are properly sanitized
- Database queries use prepared statements

### Audit Logging

All flag award activities are logged including:
- User ID and flag details
- Order and product information
- Timestamps and IP addresses
- Success/failure status

### Data Validation

- Product IDs validated against actual products
- Flag IDs must be positive integers
- Flag names restricted to alphanumeric characters
- Order completion checked to prevent duplicate awards

---

## 🚀 **Performance Optimization**

### Database Optimization

- Indexed columns for fast lookups
- Efficient queries with proper joins
- Cleanup of expired guest awards
- Optimized table structure

### Caching

- Meta field caching for configuration
- Reduced database calls
- Efficient flag retrieval

### Background Processing

- Guest award cleanup runs daily
- Bulk operations for large datasets
- Non-blocking flag award processing

---

## 📊 **Monitoring and Analytics**

### Available Metrics

- Total flags awarded
- Flag awards per product
- Guest vs registered user awards
- Error rates and types
- Processing times

### Log Analysis

Check system logs for:
```
Membershiping Inventory: Flag awarded - User: 123, Flag: premium_member, Quantity: 1
Membershiping Inventory: Product-flag linked - Product: 456, Flag: 789
```

---

## 🔄 **Maintenance**

### Regular Tasks

1. **Monitor database table sizes**
2. **Clean up expired guest awards**
3. **Review error logs**
4. **Validate flag configurations**
5. **Test order processing flow**

### Update Procedures

When updating the plugin:
1. Backup flag configuration
2. Test on staging environment
3. Verify database schema changes
4. Confirm integration still works
5. Update documentation as needed
