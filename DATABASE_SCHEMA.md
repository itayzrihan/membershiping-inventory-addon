# Membershiping Inventory System Database Schema

## Overview
This document defines the database schema for the Membershiping Inventory & Trading System addon.
All tables follow the WordPress prefix convention: `{prefix}membershiping_inventory_*`

## Core Tables

### 1. `membershiping_inventory_currencies`
**Purpose:** Custom currencies for the inventory system
```sql
CREATE TABLE {prefix}membershiping_inventory_currencies (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    slug varchar(100) NOT NULL UNIQUE,
    symbol varchar(10) NOT NULL,
    description text,
    icon varchar(255) DEFAULT NULL,
    is_default tinyint(1) DEFAULT 0,
    decimal_places tinyint(2) DEFAULT 2,
    exchange_rate decimal(10,4) DEFAULT 1.0000 COMMENT 'Rate to default currency',
    status enum('active','inactive') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY slug (slug),
    KEY status (status)
);
```

### 2. `membershiping_inventory_items`
**Purpose:** Virtual item definitions linked to WooCommerce products
```sql
CREATE TABLE {prefix}membershiping_inventory_items (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    product_id bigint(20) UNSIGNED NOT NULL COMMENT 'WooCommerce product ID',
    name varchar(255) NOT NULL,
    description text,
    item_type enum('consumable','equipment','gift_box','material','collectible') DEFAULT 'collectible',
    rarity enum('common','uncommon','rare','epic','legendary','mythic') DEFAULT 'common',
    base_image varchar(255) DEFAULT NULL,
    rarity_images JSON DEFAULT NULL COMMENT 'Images for different rarity levels',
    stats JSON DEFAULT NULL COMMENT 'Item stats like attack, defense, etc',
    requirements JSON DEFAULT NULL COMMENT 'Level/flag requirements to use',
    is_tradeable tinyint(1) DEFAULT 1,
    is_consumable tinyint(1) DEFAULT 0,
    is_stackable tinyint(1) DEFAULT 1,
    max_stack_size int(11) DEFAULT 999,
    use_effect JSON DEFAULT NULL COMMENT 'Effects when item is used',
    gift_box_items JSON DEFAULT NULL COMMENT 'Possible rewards for gift boxes',
    currency_prices JSON DEFAULT NULL COMMENT 'Prices in custom currencies',
    exclude_from_shop tinyint(1) DEFAULT 0,
    allow_currency_purchase tinyint(1) DEFAULT 1,
    quantity_limit int(11) DEFAULT NULL COMMENT 'Global quantity limit',
    current_quantity int(11) DEFAULT 0 COMMENT 'Current minted quantity',
    status enum('active','inactive','draft') DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY product_id (product_id),
    KEY item_type (item_type),
    KEY rarity (rarity),
    KEY status (status),
    FOREIGN KEY (product_id) REFERENCES {prefix}posts(ID) ON DELETE CASCADE
);
```

### 3. `membershiping_inventory_nfts`
**Purpose:** Unique NFT records for non-stackable items
```sql
CREATE TABLE {prefix}membershiping_inventory_nfts (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    item_id mediumint(9) NOT NULL,
    nft_hash varchar(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash for uniqueness',
    nft_token varchar(128) NOT NULL UNIQUE COMMENT 'Unique token ID',
    owner_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Current owner user ID',
    original_owner_id bigint(20) UNSIGNED NOT NULL COMMENT 'Original owner user ID',
    rarity enum('common','uncommon','rare','epic','legendary','mythic') DEFAULT 'common',
    upgrade_level tinyint(3) DEFAULT 0 COMMENT 'Upgrade level from consumption',
    custom_stats JSON DEFAULT NULL COMMENT 'Individual NFT stats',
    custom_image varchar(255) DEFAULT NULL COMMENT 'Custom image for upgraded items',
    metadata JSON DEFAULT NULL COMMENT 'Additional NFT metadata',
    is_tradeable tinyint(1) DEFAULT 1,
    mint_transaction_id varchar(255) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY item_id (item_id),
    KEY owner_id (owner_id),
    KEY original_owner_id (original_owner_id),
    KEY nft_hash (nft_hash),
    KEY nft_token (nft_token),
    FOREIGN KEY (item_id) REFERENCES {prefix}membershiping_inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES {prefix}users(ID) ON DELETE SET NULL,
    FOREIGN KEY (original_owner_id) REFERENCES {prefix}users(ID) ON DELETE CASCADE
);
```

### 4. `membershiping_inventory_user_items`
**Purpose:** User inventory - stackable items
```sql
CREATE TABLE {prefix}membershiping_inventory_user_items (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    item_id mediumint(9) NOT NULL,
    quantity int(11) NOT NULL DEFAULT 1,
    acquired_method enum('purchase','awarded','trade','crafted','gift') DEFAULT 'awarded',
    acquired_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_used_at datetime DEFAULT NULL,
    notes text DEFAULT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY item_id (item_id),
    KEY acquired_method (acquired_method),
    UNIQUE KEY user_item_unique (user_id, item_id),
    FOREIGN KEY (user_id) REFERENCES {prefix}users(ID) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES {prefix}membershiping_inventory_items(id) ON DELETE CASCADE
);
```

### 5. `membershiping_inventory_user_currencies`
**Purpose:** User currency balances
```sql
CREATE TABLE {prefix}membershiping_inventory_user_currencies (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    currency_id mediumint(9) NOT NULL,
    balance decimal(15,4) NOT NULL DEFAULT 0.0000,
    total_earned decimal(15,4) NOT NULL DEFAULT 0.0000,
    total_spent decimal(15,4) NOT NULL DEFAULT 0.0000,
    last_transaction_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY currency_id (currency_id),
    UNIQUE KEY user_currency_unique (user_id, currency_id),
    FOREIGN KEY (user_id) REFERENCES {prefix}users(ID) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES {prefix}membershiping_inventory_currencies(id) ON DELETE CASCADE
);
```

### 6. `membershiping_inventory_trades`
**Purpose:** Trading system records
```sql
CREATE TABLE {prefix}membershiping_inventory_trades (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    trade_token varchar(64) NOT NULL UNIQUE COMMENT 'Secure trade token',
    initiator_id bigint(20) UNSIGNED NOT NULL,
    target_id bigint(20) UNSIGNED NOT NULL,
    status enum('pending','accepted','declined','completed','cancelled','expired') DEFAULT 'pending',
    initiator_items JSON NOT NULL COMMENT 'Items offered by initiator',
    initiator_currencies JSON DEFAULT NULL COMMENT 'Currencies offered by initiator',
    target_items JSON NOT NULL COMMENT 'Items requested from target',
    target_currencies JSON DEFAULT NULL COMMENT 'Currencies requested from target',
    message text DEFAULT NULL,
    expires_at datetime NOT NULL,
    completed_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY trade_token (trade_token),
    KEY initiator_id (initiator_id),
    KEY target_id (target_id),
    KEY status (status),
    KEY expires_at (expires_at),
    FOREIGN KEY (initiator_id) REFERENCES {prefix}users(ID) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES {prefix}users(ID) ON DELETE CASCADE
);
```

### 7. `membershiping_inventory_currency_transactions`
**Purpose:** Currency transaction history
```sql
CREATE TABLE {prefix}membershiping_inventory_currency_transactions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    currency_id mediumint(9) NOT NULL,
    amount decimal(15,4) NOT NULL,
    transaction_type enum('earned','spent','traded','awarded','purchase') NOT NULL,
    reference_type enum('trade','purchase','award','admin') DEFAULT NULL,
    reference_id bigint(20) DEFAULT NULL,
    description text DEFAULT NULL,
    balance_after decimal(15,4) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY currency_id (currency_id),
    KEY transaction_type (transaction_type),
    KEY reference_type (reference_type),
    KEY reference_id (reference_id),
    KEY created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES {prefix}users(ID) ON DELETE CASCADE,
    FOREIGN KEY (currency_id) REFERENCES {prefix}membershiping_inventory_currencies(id) ON DELETE CASCADE
);
```

### 8. `membershiping_inventory_item_awards`
**Purpose:** Item award history and automation
```sql
CREATE TABLE {prefix}membershiping_inventory_item_awards (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED DEFAULT NULL,
    guest_email varchar(255) DEFAULT NULL,
    item_id mediumint(9) NOT NULL,
    quantity int(11) NOT NULL DEFAULT 1,
    award_type enum('purchase','flag_award','admin','promotion','event') NOT NULL,
    source_reference varchar(255) DEFAULT NULL COMMENT 'Order ID, flag ID, etc',
    nft_id bigint(20) DEFAULT NULL COMMENT 'If NFT was minted',
    processed tinyint(1) DEFAULT 0,
    processed_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY guest_email (guest_email),
    KEY item_id (item_id),
    KEY award_type (award_type),
    KEY processed (processed),
    KEY created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES {prefix}users(ID) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES {prefix}membershiping_inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (nft_id) REFERENCES {prefix}membershiping_inventory_nfts(id) ON DELETE SET NULL
);
```

### 9. `membershiping_inventory_product_flags`
**Purpose:** Flag awards for product purchases
```sql
CREATE TABLE {prefix}membershiping_inventory_product_flags (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    product_id bigint(20) UNSIGNED NOT NULL,
    flag_id mediumint(9) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY product_id (product_id),
    KEY flag_id (flag_id),
    UNIQUE KEY product_flag_unique (product_id, flag_id),
    FOREIGN KEY (product_id) REFERENCES {prefix}posts(ID) ON DELETE CASCADE,
    FOREIGN KEY (flag_id) REFERENCES {prefix}membershiping_user_flags(id) ON DELETE CASCADE
);
```

### 10. `membershiping_inventory_logs`
**Purpose:** System activity logging
```sql
CREATE TABLE {prefix}membershiping_inventory_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED DEFAULT NULL,
    action enum('item_awarded','item_used','item_traded','currency_earned','currency_spent','nft_minted','trade_completed') NOT NULL,
    item_id mediumint(9) DEFAULT NULL,
    currency_id mediumint(9) DEFAULT NULL,
    quantity int(11) DEFAULT NULL,
    amount decimal(15,4) DEFAULT NULL,
    reference_type varchar(50) DEFAULT NULL,
    reference_id bigint(20) DEFAULT NULL,
    details JSON DEFAULT NULL,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY action (action),
    KEY item_id (item_id),
    KEY currency_id (currency_id),
    KEY created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES {prefix}users(ID) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES {prefix}membershiping_inventory_items(id) ON DELETE SET NULL,
    FOREIGN KEY (currency_id) REFERENCES {prefix}membershiping_inventory_currencies(id) ON DELETE SET NULL
);
```

## Data Relationships

### Primary Relationships
- **Products → Items**: WooCommerce products can be configured as inventory items
- **Users → Inventories**: Users have stackable items and currency balances  
- **Users → NFTs**: Users own unique NFT items
- **Items → NFTs**: Non-stackable items generate unique NFTs
- **Trades**: Users can trade items and currencies securely

### Key Features
1. **Dual Item System**: Stackable items (user_items) and unique NFTs (nfts)
2. **Multi-Currency Support**: Custom currencies with exchange rates
3. **Secure Trading**: Token-based trading with expiration
4. **Complete Audit Trail**: Comprehensive logging of all actions
5. **Flag Integration**: Products can award flags on purchase
6. **Rarity System**: Items can be upgraded and change appearance
7. **Usage Tracking**: Consumable items and usage statistics

## Security Considerations
- All foreign keys with appropriate CASCADE/SET NULL
- Unique constraints prevent duplicates
- JSON validation should be implemented in PHP
- Rate limiting on trades and transactions
- Audit logging for all financial operations
