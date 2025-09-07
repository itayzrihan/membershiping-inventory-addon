# Membershiping Inventory Shortcodes - Usage Examples

## 📋 Complete Shortcode Reference

This document provides comprehensive examples of all available shortcodes in the Membershiping Inventory plugin.

---

## 🎒 Inventory Display Shortcodes

### Basic Inventory Display
```php
[membershiping_inventory]
```
Displays the user's complete inventory with default settings.

### Filtered Inventory
```php
// Show only consumable items
[membershiping_inventory type="consumable"]

// Show only equipment in 2 columns
[membershiping_inventory type="equipment" columns="2"]

// Show collectibles without use buttons
[membershiping_inventory type="collectible" show_use_button="no"]
```

### Customized Inventory
```php
// Full customization
[membershiping_inventory 
    type="all" 
    columns="4" 
    show_stats="yes" 
    show_use_button="yes" 
    show_trade_button="no"]
```

### Alternative Names
```php
// These work exactly the same as membershiping_inventory
[membershiping_user_inventory]
[membershiping_user_inventory type="consumable" columns="3"]
```

---

## 💰 Currency Display Shortcodes

### Basic Currency Display
```php
[membershiping_currencies]
```
Shows user's currency balances.

### Currency with Transactions
```php
// Show last 5 transactions
[membershiping_currencies show_transactions="yes" transaction_limit="5"]

// Show last 20 transactions
[membershiping_currencies show_transactions="yes" transaction_limit="20"]
```

### Alternative Names
```php
// These work exactly the same as membershiping_currencies
[membershiping_inventory_currencies]
[membershiping_inventory_currencies show_transactions="yes"]
```

---

## 🎨 NFT Display Shortcodes

### Basic NFT Gallery
```php
[membershiping_nfts]
```
Displays user's NFT collection.

### Customized NFT Gallery
```php
// 4 columns with certificates
[membershiping_nfts columns="4" show_certificate="yes"]

// 2 columns without certificates
[membershiping_nfts columns="2" show_certificate="no"]
```

### Alternative Names
```php
// Works exactly the same as membershiping_nfts
[membershiping_nft_gallery]
[membershiping_nft_gallery columns="3"]
```

---

## 🔄 Trading Interface Shortcodes

### Full Trading Interface
```php
[membershiping_trading]
```
Complete trading system with dashboard, create trade, and history.

### Specific Trading Views
```php
// Start on create trade tab
[membershiping_trading view="create"]

// Start on history tab
[membershiping_trading view="history"]

// Trading without user search
[membershiping_trading show_search="no"]
```

### Alternative Names
```php
// Works exactly the same as membershiping_trading
[membershiping_trading_interface]
[membershiping_trading_interface view="create"]
```

---

## 🔒 Conditional Content Shortcodes

### Item-Based Content Restrictions

#### Basic Item Requirement
```php
[membershiping_require_item item_id="1" quantity="1"]
This content is only visible to users who have at least 1 unit of item #1.
[/membershiping_require_item]
```

#### Multiple Items Required
```php
[membershiping_require_item item_id="5" quantity="3"]
You need 3 VIP Passes to access this exclusive content!
[/membershiping_require_item]
```

#### Custom Denial Messages
```php
[membershiping_require_item 
    item_id="10" 
    quantity="1" 
    deny_message="🚫 You need a Premium Membership Card to view this content!"
    show_deny_message="yes"]
Premium member exclusive content here.
[/membershiping_require_item]
```

#### Silent Restrictions (No Message)
```php
[membershiping_require_item 
    item_id="7" 
    quantity="1" 
    show_deny_message="no"]
This content just won't show if they don't have the item.
[/membershiping_require_item]
```

### Currency-Based Content Restrictions

#### Basic Currency Requirement
```php
[membershiping_if_has_currency currency_id="1" amount="100"]
This content requires at least 100 coins.
[/membershiping_if_has_currency]
```

#### High-Value Content
```php
[membershiping_if_has_currency currency_id="2" amount="1000"]
💎 Diamond tier content - requires 1000 gems!
[/membershiping_if_has_currency]
```

#### Custom Currency Messages
```php
[membershiping_if_has_currency 
    currency_id="3" 
    amount="50.5" 
    deny_message="💰 You need at least 50.5 Premium Points to access this!"
    show_deny_message="yes"]
Premium points exclusive content.
[/membershiping_if_has_currency]
```

### Combined Restrictions (Nested)
```php
[membershiping_require_item item_id="1" quantity="1"]
    [membershiping_if_has_currency currency_id="1" amount="500"]
    This content requires BOTH a specific item AND 500 coins!
    [/membershiping_if_has_currency]
[/membershiping_require_item]
```

---

## ℹ️ Restriction Information Shortcodes

### Current Post Restrictions
```php
[membershiping_restriction_message]
```
Shows restriction info for the current post.

### Specific Post Restrictions
```php
[membershiping_restriction_message post_id="123"]
```
Shows restriction info for post ID 123.

### Detailed Requirements
```php
[membershiping_restriction_message 
    post_id="456" 
    show_requirements="yes"]
```
Shows detailed list of required items and currencies.

### Simple Restriction Message
```php
[membershiping_restriction_message 
    show_requirements="no"]
```
Shows only the basic restriction message without details.

---

## 🎯 Real-World Use Cases

### Membership Site Example
```php
<!-- On a premium article page -->
[membershiping_require_item item_id="1" quantity="1" deny_message="Upgrade to Premium Membership to read this article!"]

# Premium Business Strategy Guide

This exclusive content is only available to premium members...

[/membershiping_require_item]
```

### Gaming Community Example
```php
<!-- Guild-only content -->
[membershiping_require_item item_id="25" quantity="1"]

## Guild Member Exclusive Strategy

[membershiping_if_has_currency currency_id="5" amount="1000"]
### Elite Strategy (1000+ Guild Points)
Advanced tactics for elite guild members...
[/membershiping_if_has_currency]

[/membershiping_require_item]
```

### Course Platform Example
```php
<!-- Course module restriction -->
[membershiping_require_item 
    item_id="15" 
    quantity="1" 
    deny_message="Complete the prerequisite course to unlock this module."]

# Advanced JavaScript Module

Welcome to the advanced module...

[/membershiping_require_item]
```

### VIP Content Example
```php
<!-- VIP tier content -->
[membershiping_if_has_currency 
    currency_id="2" 
    amount="5000" 
    deny_message="VIP members only! Upgrade to access exclusive content."]

## VIP Member Exclusive Content

[membershiping_inventory type="equipment" columns="3"]

Access your VIP equipment inventory above.

[/membershiping_if_has_currency]
```

---

## 🛠️ Developer Notes

### Shortcode Priority
1. Content inside conditional shortcodes is processed with `do_shortcode()`
2. Nested shortcodes work correctly
3. Conditional shortcodes check user login status first

### Error Handling
- Non-logged-in users see empty content for inventory shortcodes
- Invalid parameters show HTML comments in source (for debugging)
- Missing items/currencies are handled gracefully

### Performance Tips
- Use specific `type` filters for inventory shortcodes when possible
- Limit transaction history with reasonable `transaction_limit` values
- Consider caching for high-traffic pages with complex restrictions

### Styling Classes
All shortcodes output semantic CSS classes for easy styling:
- `.membershiping-inventory-container`
- `.membershiping-currencies-container`
- `.membershiping-nfts-container`
- `.membershiping-trading-container`
- `.membershiping-restriction-message`
- `.membershiping-restriction-info`
