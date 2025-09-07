# Core Plugin Integration Guide

## Content Restrictions Integration

The Membershiping Inventory & Trading System now **integrates directly with your core plugin's restriction system** instead of providing a separate content restriction interface.

### How It Works

The addon adds new restriction types to your existing core plugin's restriction page:

#### 1. **Inventory Items** Restriction Type
- Restrict content based on owned inventory items
- Set required quantity, minimum rarity, and consumption rules
- Icon: Archive symbol
- Location: Core plugin's restriction interface

#### 2. **Virtual Currencies** Restriction Type  
- Restrict content based on currency balances
- Set required amounts and optional currency deduction
- Icon: Money symbol
- Location: Core plugin's restriction interface

#### 3. **NFT Ownership** Restriction Type
- Restrict content based on owned NFTs
- Set minimum rarity and required count
- Icon: Images symbol
- Location: Core plugin's restriction interface

#### 4. **User Level** Restriction Type
- Restrict content based on user level/experience
- Set minimum level and experience requirements
- Icon: Chart line symbol
- Location: Core plugin's restriction interface

### Usage in Core Plugin

When editing content (posts, pages, etc.) in your core plugin's restriction interface, you'll now see these new restriction types available alongside your existing restrictions.

#### Setting Up Item Restrictions
1. Go to your core plugin's restriction page
2. Select "Inventory Items" as restriction type
3. Choose required items, quantities, and rarities
4. Optionally enable item consumption on access
5. Save your restrictions

#### Setting Up Currency Restrictions
1. In core plugin's restriction interface
2. Select "Virtual Currencies" as restriction type
3. Enable specific currencies and set required amounts
4. Optionally enable currency deduction on access
5. Save your restrictions

#### Setting Up NFT Restrictions
1. In core plugin's restriction interface
2. Select "NFT Ownership" as restriction type
3. Choose NFT items and minimum rarities
4. Set required NFT count
5. Save your restrictions

#### Setting Up Level Restrictions
1. In core plugin's restriction interface
2. Select "User Level" as restriction type
3. Set minimum level and experience requirements
4. Save your restrictions

### Integration Points

The system integrates with your core plugin through these hooks:

- `membershiping_restriction_types` - Adds inventory restriction types
- `membershiping_restriction_options` - Provides restriction configuration options
- `membershiping_check_user_restrictions` - Validates user access based on inventory
- `membershiping_content_access_check` - Content filtering integration
- `membershiping_shortcode_restrictions` - Shortcode support integration

### Shortcode Support

The integration also adds inventory-based shortcodes to your core plugin:

```php
[membershiping_require_item item_id="1" quantity="3" rarity="rare" message="You need 3 rare Crystal Swords to view this content."]
Content here...
[/membershiping_require_item]

[membershiping_require_currency currency_id="1" amount="100" message="You need 100 Gold Coins to view this content."]
Content here...
[/membershiping_require_currency]

[membershiping_require_nft item_id="1" rarity="legendary" count="1" message="You need a Legendary Dragon NFT to view this content."]
Content here...
[/membershiping_require_nft]
```

### Admin Features

#### User Inventory Preview
- Preview any user's inventory from the restriction interface
- View items, currencies, and NFTs in organized tabs
- Check if users meet specific restrictions

#### Restriction Validation
- Real-time validation of restriction settings
- Visual feedback for requirement types
- Error checking before saving

#### Integration with Core Admin
- Seamless integration with your existing admin interface
- Consistent styling and user experience
- No separate inventory restriction pages needed

### Database Integration

The restriction settings are stored using your core plugin's meta structure:
- `_membershiping_inventory_restrictions` - Contains all inventory-based restrictions
- Integrates with your existing restriction storage system
- Maintains compatibility with core plugin's data structure

### API Integration

The system provides these methods for programmatic access:

```php
// Check if user has required items
$has_access = apply_filters('membershiping_check_user_restrictions', true, $restrictions, $user_id);

// Validate inventory access
$can_access = apply_filters('membershiping_content_access_check', true, $post_id, $user_id, 'post');

// Get restriction types
$types = apply_filters('membershiping_restriction_types', array());
```

### Frontend Integration

The restrictions work seamlessly with your core plugin's frontend:
- Content is automatically filtered based on inventory requirements
- Users see appropriate restriction messages
- Shortcodes work within your core plugin's content system
- Responsive design matches your core plugin's styling

### Migration from Standalone

If you were using the previous standalone restriction system, the integration automatically:
- Preserves existing restriction configurations
- Migrates settings to core plugin format
- Maintains backward compatibility
- Provides upgrade path for existing restrictions

### Customization

You can customize the integration by:
- Modifying restriction field templates
- Adding custom restriction types
- Extending validation rules
- Customizing admin interface styling

### Troubleshooting

**Restrictions not appearing in core plugin:**
- Ensure core plugin is active and updated
- Check that required hooks are available
- Verify addon is properly activated

**Restriction validation not working:**
- Check user has required inventory items
- Verify restriction settings are saved correctly
- Test with different user accounts

**Admin interface issues:**
- Clear browser cache
- Check for JavaScript console errors
- Ensure admin assets are loading correctly

### Support

This integration provides seamless inventory-based restrictions within your existing core plugin workflow, eliminating the need for separate restriction management while providing powerful inventory-based access control.
