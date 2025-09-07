<?php
/**
 * Items management class for Membershiping Inventory System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Items {
    
    private $wpdb;
    private $database;
    private $security;
    private $currencies;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = Membershiping_Inventory_Security::get_instance();
        $this->currencies = new Membershiping_Inventory_Currencies();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Product display modifications
        add_filter('woocommerce_is_purchasable', array($this, 'check_item_purchasability'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'display_item_info'), 25);
        add_filter('woocommerce_product_is_visible', array($this, 'filter_shop_visibility'), 10, 2);
        
        // Custom currency pricing
        add_filter('woocommerce_get_price_html', array($this, 'display_currency_pricing'), 20, 2);
    }
    
    /**
     * Create virtual item from product
     */
    public function create_item($product_id, $data) {
        // Verify product exists
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'Product not found');
        }
        
        // Sanitize data
        $sanitized_data = $this->security->sanitize_item_data($data);
        $sanitized_data['product_id'] = $product_id;
        
        // Auto-fill name from product if not provided
        if (empty($sanitized_data['name'])) {
            $sanitized_data['name'] = $product->get_name();
        }
        
        // Auto-fill description from product if not provided
        if (empty($sanitized_data['description'])) {
            $sanitized_data['description'] = $product->get_short_description() ?: $product->get_description();
        }
        
        $items_table = $this->database->get_table('items');
        
        // Check if item already exists for this product
        $existing = $this->get_item_by_product($product_id);
        if ($existing) {
            return new WP_Error('item_exists', 'Item already exists for this product');
        }
        
    $result = $this->wpdb->insert(
            $items_table,
            array(
                'product_id' => $sanitized_data['product_id'],
                'name' => $sanitized_data['name'],
                'description' => $sanitized_data['description'],
                'item_type' => $sanitized_data['item_type'] ?? 'collectible',
                'rarity' => $sanitized_data['rarity'] ?? 'common',
                'base_image' => $sanitized_data['base_image'] ?? null,
                'rarity_images' => $sanitized_data['rarity_images'] ?? null,
                'stats' => $sanitized_data['stats'] ?? null,
                'requirements' => $sanitized_data['requirements'] ?? null,
                'is_tradeable' => $sanitized_data['is_tradeable'] ?? 1,
                'is_consumable' => $sanitized_data['is_consumable'] ?? 0,
                'is_stackable' => $sanitized_data['is_stackable'] ?? 1,
                'max_stack_size' => $sanitized_data['max_stack_size'] ?? 999,
        'mint_nft' => isset($sanitized_data['mint_nft']) ? (int)$sanitized_data['mint_nft'] : 1,
        'use_effect' => $sanitized_data['use_effect'] ?? null,
                'gift_box_items' => $sanitized_data['gift_box_items'] ?? null,
                'currency_prices' => $sanitized_data['currency_prices'] ?? null,
                'exclude_from_shop' => $sanitized_data['exclude_from_shop'] ?? 0,
                'allow_currency_purchase' => $sanitized_data['allow_currency_purchase'] ?? 1,
                'quantity_limit' => $sanitized_data['quantity_limit'] ?? null,
                'current_quantity' => 0,
                'status' => $sanitized_data['status'] ?? 'active'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create item');
        }
        
        $item_id = $this->wpdb->insert_id;
        
        // Update product meta
        update_post_meta($product_id, '_membershiping_inventory_item_id', $item_id);
        update_post_meta($product_id, '_membershiping_is_virtual_item', 'yes');
        
        // Log creation
        $this->security->log_security_event('item_created', get_current_user_id(), array(
            'item_id' => $item_id,
            'product_id' => $product_id,
            'item_name' => $sanitized_data['name']
        ));
        
        do_action('membershiping_inventory_item_created', $item_id, $product_id, $sanitized_data);
        
        return $item_id;
    }
    
    /**
     * Update item
     */
    public function update_item($item_id, $data) {
        $sanitized_data = $this->security->sanitize_item_data($data);
        
        $items_table = $this->database->get_table('items');
        
        $existing = $this->get_item($item_id);
        if (!$existing) {
            return new WP_Error('item_not_found', 'Item not found');
        }
        
        $result = $this->wpdb->update(
            $items_table,
            $sanitized_data,
            array('id' => $item_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update item');
        }
        
        $this->security->log_security_event('item_updated', get_current_user_id(), array(
            'item_id' => $item_id,
            'changes' => array_keys($sanitized_data)
        ));
        
        do_action('membershiping_inventory_item_updated', $item_id, $sanitized_data, $existing);
        
        return true;
    }
    
    /**
     * Delete item
     */
    public function delete_item($item_id) {
        $items_table = $this->database->get_table('items');
        
        $item = $this->get_item($item_id);
        if (!$item) {
            return new WP_Error('item_not_found', 'Item not found');
        }
        
        // Check if item is in use
        if ($this->is_item_in_use($item_id)) {
            return new WP_Error('item_in_use', 'Cannot delete item that is currently owned by users');
        }
        
        $result = $this->wpdb->delete(
            $items_table,
            array('id' => $item_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete item');
        }
        
        // Clean up product meta
        delete_post_meta($item->product_id, '_membershiping_inventory_item_id');
        delete_post_meta($item->product_id, '_membershiping_is_virtual_item');
        
        $this->security->log_security_event('item_deleted', get_current_user_id(), array(
            'item_id' => $item_id,
            'item_name' => $item->name
        ));
        
        do_action('membershiping_inventory_item_deleted', $item_id, $item);
        
        return true;
    }
    
    /**
     * Get item by ID
     */
    public function get_item($item_id) {
        $items_table = $this->database->get_table('items');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $items_table WHERE id = %d",
                $item_id
            )
        );
    }
    
    /**
     * Get item by product ID
     */
    public function get_item_by_product($product_id) {
        $items_table = $this->database->get_table('items');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $items_table WHERE product_id = %d",
                $product_id
            )
        );
    }
    
    /**
     * Get all items
     */
    public function get_all_items($status = 'active', $item_type = null, $limit = null, $offset = 0) {
        $items_table = $this->database->get_table('items');
        
        $where = array();
        $params = array();
        
        if ($status) {
            $where[] = "status = %s";
            $params[] = $status;
        }
        
        if ($item_type) {
            $where[] = "item_type = %s";
            $params[] = $item_type;
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $limit_clause = '';
        if ($limit) {
            $limit_clause = $this->wpdb->prepare(" LIMIT %d OFFSET %d", $limit, $offset);
        }
        
        $query = "SELECT * FROM $items_table $where_clause ORDER BY created_at DESC $limit_clause";
        
        if (!empty($params)) {
            return $this->wpdb->get_results($this->wpdb->prepare($query, ...$params));
        } else {
            return $this->wpdb->get_results($query);
        }
    }
    
    /**
     * Award item to user
     */
    public function award_item($user_id, $item_id, $quantity = 1, $award_type = 'admin', $source_reference = null) {
        $item = $this->get_item($item_id);
        if (!$item) {
            return new WP_Error('item_not_found', 'Item not found');
        }
        
        // Check quantity limits
        if ($item->quantity_limit && ($item->current_quantity + $quantity) > $item->quantity_limit) {
            return new WP_Error('quantity_limit', 'Item quantity limit exceeded');
        }
        
        $user_items_table = $this->database->get_table('user_items');
        $awards_table = $this->database->get_table('item_awards');
        
        $result = true;
        $nfts = new Membershiping_Inventory_NFTs();

        // Prepare optional metadata for NFTs
        $meta = array(
            'acquisition_method' => $award_type,
            'source_reference' => $source_reference,
        );
        // Include tags if present in item stats
        if (!empty($item->stats)) {
            $stats_arr = json_decode($item->stats, true);
            if (is_array($stats_arr) && !empty($stats_arr['tags'])) {
                $meta['tags'] = $stats_arr['tags'];
            }
        }
        // Include an image hint if available (per rarity or base)
        $image_url = null;
        if (!empty($item->rarity_images)) {
            $ri = json_decode($item->rarity_images, true);
            if (is_array($ri) && isset($ri[$item->rarity])) {
                if (is_array($ri[$item->rarity]) && !empty($ri[$item->rarity]['image'])) {
                    $image_url = $ri[$item->rarity]['image'];
                } elseif (is_string($ri[$item->rarity])) {
                    $image_url = $ri[$item->rarity];
                }
            }
        }
        if (!$image_url && !empty($item->base_image)) {
            $image_url = $item->base_image;
        }
        if ($image_url) {
            $meta['image_url'] = $image_url;
        }
        
        // Always mint NFTs for each quantity (even if stackable)
        for ($i = 0; $i < $quantity; $i++) {
            $nft_result = $nfts->mint_nft($item_id, $user_id, $item->rarity, $meta);
            if (is_wp_error($nft_result)) {
                return $nft_result;
            }
        }
        
        // Additionally maintain stack count for stackable items
        if ($item->is_stackable) {
            $existing = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM $user_items_table WHERE user_id = %d AND item_id = %d",
                    $user_id,
                    $item_id
                )
            );
            
            if ($existing) {
                $new_quantity = $existing->quantity + $quantity;
                // Check stack size limit
                if ($new_quantity > $item->max_stack_size) {
                    return new WP_Error('stack_limit', 'Stack size limit exceeded');
                }
                $result = $this->wpdb->update(
                    $user_items_table,
                    array('quantity' => $new_quantity),
                    array('id' => $existing->id),
                    array('%d'),
                    array('%d')
                );
            } else {
                $result = $this->wpdb->insert(
                    $user_items_table,
                    array(
                        'user_id' => $user_id,
                        'item_id' => $item_id,
                        'quantity' => $quantity,
                        'acquired_method' => $award_type
                    ),
                    array('%d', '%d', '%d', '%s')
                );
            }
        }
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to award item');
        }
        
        // Do not update items.current_quantity here; mint_nft() increments it per NFT
        
        // Log the award
        $this->wpdb->insert(
            $awards_table,
            array(
                'user_id' => $user_id,
                'item_id' => $item_id,
                'quantity' => $quantity,
                'award_type' => $award_type,
                'source_reference' => $source_reference,
                'processed' => 1,
                'processed_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%s', '%s', '%d', '%s')
        );
        
        $award_id = $this->wpdb->insert_id;
        
        // Log security event
        $this->security->log_security_event('item_awarded', $user_id, array(
            'item_id' => $item_id,
            'quantity' => $quantity,
            'award_type' => $award_type,
            'source_reference' => $source_reference
        ));
        
        do_action('membershiping_inventory_item_awarded', $user_id, $item_id, $quantity, $award_type, $award_id);
        
        return $award_id;
    }
    
    /**
     * Use consumable item
     */
    public function use_item($user_id, $item_id, $quantity = 1) {
        $item = $this->get_item($item_id);
        if (!$item) {
            return new WP_Error('item_not_found', 'Item not found');
        }
        
        if (!$item->is_consumable) {
            return new WP_Error('not_consumable', 'Item is not consumable');
        }
        
        // Check if user owns the item
        if (!$this->user_owns_item($user_id, $item_id, $quantity)) {
            return new WP_Error('insufficient_items', 'User does not own enough of this item');
        }
        
        // Remove items from inventory
        $remove_result = $this->remove_user_item($user_id, $item_id, $quantity);
        if (is_wp_error($remove_result)) {
            return $remove_result;
        }
        
        // Apply item effects
        $effect_result = $this->apply_item_effects($user_id, $item);
        
        // Log usage
        $this->security->log_security_event('item_used', $user_id, array(
            'item_id' => $item_id,
            'quantity' => $quantity,
            'effects_applied' => $effect_result
        ));
        
        do_action('membershiping_inventory_item_used', $user_id, $item_id, $quantity, $effect_result);
        
        return $effect_result;
    }
    
    /**
     * Apply item effects when used
     */
    private function apply_item_effects($user_id, $item) {
        if (empty($item->use_effect)) {
            return array();
        }
        
        $effects = json_decode($item->use_effect, true);
        if (!$effects) {
            return array();
        }
        
        $results = array();
        
        foreach ($effects as $effect) {
            switch ($effect['type']) {
                case 'currency':
                    if (isset($effect['currency_id']) && isset($effect['amount'])) {
                        $result = $this->currencies->add_currency(
                            $user_id,
                            $effect['currency_id'],
                            $effect['amount'],
                            'awarded',
                            'item_use',
                            $item->id,
                            sprintf('Used %s', $item->name)
                        );
                        $results[] = array(
                            'type' => 'currency',
                            'currency_id' => $effect['currency_id'],
                            'amount' => $effect['amount'],
                            'success' => !is_wp_error($result)
                        );
                    }
                    break;
                    
                case 'points':
                    if (isset($effect['points']) && class_exists('Membershiping_Points_System')) {
                        $points_system = new Membershiping_Points_System();
                        $result = $points_system->add_points(
                            $user_id,
                            $effect['points'],
                            'item_use',
                            sprintf('Used %s', $item->name)
                        );
                        $results[] = array(
                            'type' => 'points',
                            'points' => $effect['points'],
                            'success' => !is_wp_error($result)
                        );
                    }
                    break;
                    
                case 'flag':
                    if (isset($effect['flag_id']) && class_exists('Membershiping_User_Flags')) {
                        $user_flags = new Membershiping_User_Flags();
                        $result = $user_flags->assign_flag($user_id, $effect['flag_id']);
                        $results[] = array(
                            'type' => 'flag',
                            'flag_id' => $effect['flag_id'],
                            'success' => !is_wp_error($result)
                        );
                    }
                    break;
                    
                case 'random_item':
                    if (isset($effect['items']) && is_array($effect['items'])) {
                        $random_item = $effect['items'][array_rand($effect['items'])];
                        $result = $this->award_item(
                            $user_id,
                            $random_item['item_id'],
                            $random_item['quantity'] ?? 1,
                            'gift',
                            $item->id
                        );
                        $results[] = array(
                            'type' => 'random_item',
                            'item_id' => $random_item['item_id'],
                            'quantity' => $random_item['quantity'] ?? 1,
                            'success' => !is_wp_error($result)
                        );
                    }
                    break;
                    
                case 'upgrade_rarity':
                    if ($item->item_type === 'gift_box' && isset($effect['target_item_id'])) {
                        $nfts = new Membershiping_Inventory_NFTs();
                        $result = $nfts->upgrade_random_item($user_id, $effect['target_item_id']);
                        $results[] = array(
                            'type' => 'upgrade_rarity',
                            'target_item_id' => $effect['target_item_id'],
                            'success' => !is_wp_error($result)
                        );
                    }
                    break;
            }
        }
        
        return $results;
    }
    
    /**
     * Get user items
     */
    public function get_user_items($user_id, $item_type = null) {
        $user_items_table = $this->database->get_table('user_items');
        $items_table = $this->database->get_table('items');
        
        $where = "WHERE ui.user_id = %d AND i.status = 'active'";
        $params = array($user_id);
        
        if ($item_type) {
            $where .= " AND i.item_type = %s";
            $params[] = $item_type;
        }
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ui.*, i.name, i.description, i.item_type, i.rarity, i.base_image, 
                        i.is_tradeable, i.is_consumable, i.is_stackable, i.max_stack_size, 
                        i.use_effect, i.stats
                FROM $user_items_table ui 
                INNER JOIN $items_table i ON ui.item_id = i.id 
                $where 
                ORDER BY ui.acquired_at DESC",
                ...$params
            )
        );
    }
    
    /**
     * Check if user owns item
     */
    public function user_owns_item($user_id, $item_id, $required_quantity = 1) {
        $user_items_table = $this->database->get_table('user_items');
        
        $owned_quantity = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT quantity FROM $user_items_table WHERE user_id = %d AND item_id = %d",
                $user_id,
                $item_id
            )
        );
        
        return $owned_quantity >= $required_quantity;
    }
    
    /**
     * Remove item from user inventory
     */
    private function remove_user_item($user_id, $item_id, $quantity) {
        $user_items_table = $this->database->get_table('user_items');
        
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $user_items_table WHERE user_id = %d AND item_id = %d",
                $user_id,
                $item_id
            )
        );
        
        if (!$existing || $existing->quantity < $quantity) {
            return new WP_Error('insufficient_items', 'Not enough items in inventory');
        }
        
        $new_quantity = $existing->quantity - $quantity;
        
        if ($new_quantity <= 0) {
            // Remove the record entirely
            $result = $this->wpdb->delete(
                $user_items_table,
                array('id' => $existing->id),
                array('%d')
            );
        } else {
            // Update quantity
            $result = $this->wpdb->update(
                $user_items_table,
                array('quantity' => $new_quantity, 'last_used_at' => current_time('mysql')),
                array('id' => $existing->id),
                array('%d', '%s'),
                array('%d')
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Check if item is in use
     */
    private function is_item_in_use($item_id) {
        $user_items_table = $this->database->get_table('user_items');
        $nfts_table = $this->database->get_table('nfts');
        
        // Check stackable items
        $stackable_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $user_items_table WHERE item_id = %d",
                $item_id
            )
        );
        
        // Check NFTs
        $nft_count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM $nfts_table WHERE item_id = %d",
                $item_id
            )
        );
        
        return $stackable_count > 0 || $nft_count > 0;
    }
    
    /**
     * Check item purchasability
     */
    public function check_item_purchasability($is_purchasable, $product) {
        $item = $this->get_item_by_product($product->get_id());
        
        if ($item && $item->exclude_from_shop) {
            return false;
        }
        
        return $is_purchasable;
    }
    
    /**
     * Display item info on product page
     */
    public function display_item_info() {
        global $product;
        
        $item = $this->get_item_by_product($product->get_id());
        
        if (!$item) {
            return;
        }
        
        echo '<div class="membershiping-inventory-item-info">';
        echo '<h3>' . __('Item Information', 'membershiping-inventory') . '</h3>';
        echo '<p><strong>' . __('Type:', 'membershiping-inventory') . '</strong> ' . ucfirst($item->item_type) . '</p>';
        echo '<p><strong>' . __('Rarity:', 'membershiping-inventory') . '</strong> ' . ucfirst($item->rarity) . '</p>';
        $stats = array();
        if ($item->stats) {
            $stats = json_decode($item->stats, true);
            if ($stats) {
                echo '<div class="item-stats">';
                echo '<strong>' . __('Stats:', 'membershiping-inventory') . '</strong><br>';
                foreach ($stats as $stat => $value) {
                    if ($stat === 'tags') { continue; }
                    if (is_array($value) || is_object($value)) { continue; }
                    echo '<span class="stat">' . esc_html(ucfirst($stat)) . ': ' . esc_html($value) . '</span><br>';
                }
                echo '</div>';
            }
        }
        if (!empty($stats['tags']) && is_array($stats['tags'])) {
            echo '<p><strong>' . __('Tags:', 'membershiping-inventory') . '</strong> ' . esc_html(implode(', ', $stats['tags'])) . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Filter shop visibility
     */
    public function filter_shop_visibility($visible, $product_id) {
        $item = $this->get_item_by_product($product_id);
        
        if ($item && $item->exclude_from_shop) {
            return false;
        }
        
        return $visible;
    }
    
    /**
     * Display currency pricing
     */
    public function display_currency_pricing($price_html, $product) {
        $item = $this->get_item_by_product($product->get_id());
        
        if (!$item || !$item->currency_prices || !$item->allow_currency_purchase) {
            return $price_html;
        }
        
        $currency_prices = json_decode($item->currency_prices, true);
        if (!$currency_prices) {
            return $price_html;
        }
        
        $currency_options = array();
        foreach ($currency_prices as $currency_price) {
            $currency = $this->currencies->get_currency($currency_price['currency_id']);
            if ($currency) {
                $currency_options[] = sprintf(
                    '%s %s',
                    $currency->symbol,
                    number_format($currency_price['price'], $currency->decimal_places)
                );
            }
        }
        
        if (!empty($currency_options)) {
            $price_html .= '<br><span class="currency-prices">' . 
                         __('Or: ', 'membershiping-inventory') . 
                         implode(' / ', $currency_options) . 
                         '</span>';
        }
        
        return $price_html;
    }
    
    /**
     * Get item name by ID
     */
    public function get_item_name($item_id) {
        $items_table = $this->database->get_table('items');
        
        $name = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT name FROM {$items_table} WHERE id = %d",
            $item_id
        ));
        
        return $name ? $name : __('Unknown Item', 'membershiping-inventory');
    }
    
    /**
     * Get user's quantity of a specific item
     */
    public function get_user_item_quantity($user_id, $item_id) {
        $user_id = intval($user_id);
        $item_id = intval($item_id);
        
        if ($user_id <= 0 || $item_id <= 0) {
            return 0;
        }
        
        $user_items_table = $this->database->get_table('user_items');
        
        $quantity = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(quantity) FROM {$user_items_table} WHERE user_id = %d AND item_id = %d",
            $user_id,
            $item_id
        ));
        
        return intval($quantity);
    }
}
