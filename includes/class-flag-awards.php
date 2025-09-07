<?php
/**
 * Flag Award Integration for Membershiping Inventory
 * Connects product purchases to flag awards for both registered users and guests
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Flag_Awards {
    
    private $wpdb;
    private $database;
    private $security;
    private $items;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = Membershiping_Inventory_Security::get_instance();
        $this->items = new Membershiping_Inventory_Items();
        
        $this->init_hooks();
        
        error_log('Membershiping Inventory Flag Awards: Class initialized and hooks added');
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WooCommerce order hooks
        add_action('woocommerce_order_status_completed', array($this, 'process_order_completion'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'process_order_completion'), 10, 1);
        
    // Frontend product page: show flag awarding section
    // Place after short description (priority ~25-30) and before add to cart (30)
    add_action('woocommerce_single_product_summary', array($this, 'render_product_flag_awards_section'), 28);

        // Product configuration hooks
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_flag_fields'), 25);
        add_action('woocommerce_process_product_meta', array($this, 'save_product_flag_fields'), 25);
        
        // Admin meta boxes
        add_action('add_meta_boxes', array($this, 'add_product_meta_boxes'));
        
        // AJAX handlers for admin
        add_action('wp_ajax_membershiping_award_flags_to_guests', array($this, 'ajax_award_flags_to_guests'));
        add_action('wp_ajax_membershiping_get_guest_orders', array($this, 'ajax_get_guest_orders'));
        add_action('wp_ajax_membershiping_link_product_flag', array($this, 'ajax_link_product_flag'));
        add_action('wp_ajax_membershiping_unlink_product_flag', array($this, 'ajax_unlink_product_flag'));
        
        // Scheduled cleanup
        add_action('membershiping_inventory_cleanup_guest_awards', array($this, 'cleanup_old_guest_awards'));
        if (!wp_next_scheduled('membershiping_inventory_cleanup_guest_awards')) {
            wp_schedule_event(time(), 'daily', 'membershiping_inventory_cleanup_guest_awards');
        }
    }

    /**
     * Render a small "Flag awards" section on the single product page
     */
    public function render_product_flag_awards_section() {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }

        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }

        $product_id = $product->get_id();

        // Get configured flags (from DB links or meta fallback)
        $flag_config = $this->get_product_flag_config($product_id);

        // Also respect the explicit enable meta if present (but don't block DB-linked flags)
        $enabled_meta = get_post_meta($product_id, '_membershiping_enable_flag_awards', true);

        if (empty($flag_config) && $enabled_meta !== 'yes') {
            // Nothing to show
            return;
        }

        // Allow themes/admins to disable rendering
        $show = apply_filters('membershiping_inventory_show_product_flag_awards', true, $product_id, $flag_config);
        if (!$show) {
            return;
        }

        // Basic markup for the section
        echo '<div class="membershiping-flag-awards woocommerce-product-details__short-description" style="margin:12px 0;">';
        echo '<h3 style="font-size:1.1em;margin:0 0 6px;">' . esc_html__('Awards you get', 'membershiping-inventory') . '</h3>';

        if (!empty($flag_config)) {
            echo '<ul class="membershiping-flag-awards__list" style="list-style:disc;padding-left:18px;margin:0;">';
            foreach ($flag_config as $award) {
                $name = isset($award['flag']) ? $award['flag'] : (isset($award['flag_id']) ? ('#' . $award['flag_id']) : __('Flag', 'membershiping-inventory'));
                $qty  = isset($award['quantity']) ? intval($award['quantity']) : 1;
                $type = isset($award['type']) ? $award['type'] : 'add';

                // Humanize type
                switch ($type) {
                    case 'set':
                        $type_label = __('set to', 'membershiping-inventory');
                        break;
                    case 'multiply':
                        $type_label = __('multiplied by', 'membershiping-inventory');
                        break;
                    case 'add':
                    default:
                        $type_label = __('+', 'membershiping-inventory');
                        break;
                }

                // Compose line like: Gold Member (+ 1) or (set to 1)
                $line = sprintf('%s (%s %s)', esc_html($name), esc_html($type_label), esc_html($qty));
                echo '<li class="membershiping-flag-awards__item">' . $line . '</li>';
            }
            echo '</ul>';
        } else {
            // Enabled but not configured – hint admin-only text for logged-in admins
            if (current_user_can('edit_products')) {
                echo '<p class="membershiping-flag-awards__notice" style="margin:0;opacity:.8;">' . esc_html__('Flag awards enabled but not configured for this product.', 'membershiping-inventory') . '</p>';
            }
        }

        echo '</div>';
    }
    
    /**
     * Process order completion and award flags
     */
    public function process_order_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Check if flags have already been awarded for this order
        if (get_post_meta($order_id, '_membershiping_flags_awarded', true)) {
            return;
        }
        
        $user_id = $order->get_user_id();
        $customer_email = $order->get_billing_email();
        
        // Process each item in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            
            // Get flag configuration for this product
            $flag_config = $this->get_product_flag_config($product_id);
            
            if (!empty($flag_config)) {
                if ($user_id > 0) {
                    // Registered user - award flags directly
                    $this->award_flags_to_user($user_id, $flag_config, $quantity, $order_id, $product_id);
                } else {
                    // Guest user - store for later claiming
                    $this->store_guest_flag_award($customer_email, $flag_config, $quantity, $order_id, $product_id);
                }
            }
            
            // Check if this product should award inventory items
            $this->process_inventory_item_awards($user_id, $customer_email, $product_id, $quantity, $order_id);
        }
        
        // Mark flags as awarded
        update_post_meta($order_id, '_membershiping_flags_awarded', true);
        update_post_meta($order_id, '_membershiping_flags_awarded_date', current_time('mysql'));
        
        // Log the flag award event
        $this->security->log_security_event('flags_awarded_order', $user_id ?: 0, array(
            'order_id' => $order_id,
            'customer_email' => $customer_email,
            'is_guest' => $user_id <= 0
        ));
    }
    
    /**
     * Award flags to registered user
     */
    private function award_flags_to_user($user_id, $flag_config, $quantity, $order_id, $product_id) {
        if (!class_exists('Membershiping')) {
            return;
        }
        
        foreach ($flag_config as $flag_data) {
            $flag_name = $flag_data['flag'];
            $flag_quantity = intval($flag_data['quantity']) * $quantity;
            $award_type = $flag_data['type'] ?? 'add'; // add, set, multiply
            
            // Use Membershiping Core's flag system
            $this->award_flag_via_core($user_id, $flag_name, $flag_quantity, $award_type, $order_id, $product_id);
        }
    }
    
    /**
     * Award flag using Membershiping Core
     */
    private function award_flag_via_core($user_id, $flag_name, $quantity, $type, $order_id, $product_id) {
        // This integrates with Membershiping Core's flag system
        
        // First priority: Try the core's Membershiping_User_Flags class (most reliable)
        if (class_exists('Membershiping_User_Flags')) {
            $user_flags = new Membershiping_User_Flags();
            
            // Get flag by slug first (most reliable)
            $flag = $user_flags->get_flag_by_slug($flag_name);
            
            if (!$flag) {
                // Try to find by name if slug doesn't work
                global $wpdb;
                $flags_table = $wpdb->prefix . 'membershiping_user_flags';
                $flag = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$flags_table} WHERE name = %s OR slug = %s LIMIT 1",
                    $flag_name, sanitize_title($flag_name)
                ));
            }
            
            if ($flag) {
                // For core integration, we assign the flag to the user
                // The core plugin handles flag assignment, points, etc.
                $assignment_result = $user_flags->assign_flag_to_user($user_id, $flag->id, get_current_user_id());
                if ($assignment_result) {
                    error_log("Membershiping Inventory: Successfully assigned flag '{$flag_name}' (ID: {$flag->id}) to user {$user_id} via core plugin");
                    return true;
                } else {
                    error_log("Membershiping Inventory: Failed to assign flag '{$flag_name}' to user {$user_id} via core plugin");
                }
            } else {
                error_log("Membershiping Inventory: Flag '{$flag_name}' not found in core plugin. Creating fallback flag award.");
            }
        }
        
        // Legacy support: Try global functions if they exist
        if (function_exists('membershiping_award_flag')) {
            membershiping_award_flag($user_id, $flag_name, $quantity, $type);
            return true;
        } elseif (class_exists('Membershiping_Flags')) {
            $flags = new Membershiping_Flags();
            if (method_exists($flags, 'award_flag')) {
                $flags->award_flag($user_id, $flag_name, $quantity, $type);
                return true;
            }
        } elseif (function_exists('membershiping_set_user_flag')) {
            // Alternative function name
            if ($type === 'add') {
                $current = membershiping_get_user_flag($user_id, $flag_name) ?: 0;
                membershiping_set_user_flag($user_id, $flag_name, $current + $quantity);
            } elseif ($type === 'set') {
                membershiping_set_user_flag($user_id, $flag_name, $quantity);
            } elseif ($type === 'multiply') {
                $current = membershiping_get_user_flag($user_id, $flag_name) ?: 1;
                membershiping_set_user_flag($user_id, $flag_name, $current * $quantity);
            }
            return true;
        }
        
        // If no core integration method worked, use fallback
        error_log("Membershiping Inventory: Core plugin integration not available. Using fallback flag storage for '{$flag_name}'.");
        $this->store_flag_award($user_id, $flag_name, $quantity, $type, $order_id, $product_id);
        return false;
        
        // Log individual flag award
        $this->security->log_security_event('flag_awarded', $user_id, array(
            'flag_name' => $flag_name,
            'quantity' => $quantity,
            'type' => $type,
            'order_id' => $order_id,
            'product_id' => $product_id
        ));
    }
    
    /**
     * Store guest flag award for later claiming
     */
    private function store_guest_flag_award($email, $flag_config, $quantity, $order_id, $product_id) {
        $table_name = $this->database->get_table_name('guest_flag_awards');
        
        foreach ($flag_config as $flag_data) {
            $flag_name = $flag_data['flag'];
            $flag_quantity = intval($flag_data['quantity']) * $quantity;
            $award_type = $flag_data['type'] ?? 'add';
            
            $this->wpdb->insert(
                $table_name,
                array(
                    'email' => sanitize_email($email),
                    'flag_name' => sanitize_text_field($flag_name),
                    'quantity' => $flag_quantity,
                    'award_type' => $award_type,
                    'order_id' => $order_id,
                    'product_id' => $product_id,
                    'status' => 'pending',
                    'created_at' => current_time('mysql'),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year'))
                ),
                array('%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Process inventory item awards
     */
    private function process_inventory_item_awards($user_id, $email, $product_id, $quantity, $order_id) {
        // Check if this product is configured as an inventory item
        $inventory_config = $this->get_product_inventory_config($product_id);
        
        if (!$inventory_config) {
            return;
        }
        
        if ($user_id > 0) {
            // Registered user - award items directly
            $result = $this->items->add_user_item($user_id, $inventory_config['item_id'], $quantity);
            
            if (!is_wp_error($result)) {
                // Check if NFT should be minted
                if ($inventory_config['mint_nft']) {
                    for ($i = 0; $i < max(1, intval($quantity)); $i++) {
                        $this->mint_nft_for_purchase($user_id, $inventory_config['item_id'], $order_id);
                    }
                }
            }
        } else {
            // Guest user - store for later claiming
            $this->store_guest_item_award($email, $inventory_config, $quantity, $order_id);
        }
    }
    
    /**
     * Mint NFT for purchase
     */
    private function mint_nft_for_purchase($user_id, $item_id, $order_id) {
        if (!class_exists('Membershiping_Inventory_NFTs')) {
            return;
        }
        
        $nfts = new Membershiping_Inventory_NFTs();
        
        $metadata = array(
            'purchase_order' => $order_id,
            'purchase_date' => current_time('mysql'),
            'acquisition_method' => 'purchase'
        );
        
    $result = $nfts->mint_nft($item_id, $user_id, 'common', $metadata);
        
        if (!is_wp_error($result)) {
            $this->security->log_security_event('nft_minted_purchase', $user_id, array(
                'nft_id' => $result,
                'item_id' => $item_id,
                'order_id' => $order_id
            ));
        }
    }
    
    /**
     * Store guest item award
     */
    private function store_guest_item_award($email, $inventory_config, $quantity, $order_id) {
        $table_name = $this->database->get_table_name('guest_item_awards');
        
        $this->wpdb->insert(
            $table_name,
            array(
                'email' => sanitize_email($email),
                'item_id' => $inventory_config['item_id'],
                'quantity' => $quantity,
                'mint_nft' => $inventory_config['mint_nft'] ? 1 : 0,
                'order_id' => $order_id,
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year'))
            ),
            array('%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get product flag configuration
     */
    private function get_product_flag_config($product_id) {
        // First try the new database table approach
        $db_flags = $this->get_product_flags_from_db($product_id);
        if (!empty($db_flags)) {
            return $db_flags;
        }
        
        // Fallback to meta field approach for backward compatibility
        $flag_config = get_post_meta($product_id, '_membershiping_flag_awards', true);
        
        if (empty($flag_config)) {
            return array();
        }
        
        // Ensure it's an array
        if (!is_array($flag_config)) {
            return array();
        }
        
        return $flag_config;
    }
    
    /**
     * Get product flags from database table
     */
    private function get_product_flags_from_db($product_id) {
        $table_name = $this->database->get_table('product_flags');
        
        $flags = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT pf.flag_id, pf.created_at
             FROM {$table_name} pf
             WHERE pf.product_id = %d",
            $product_id
        ));
        
        if (empty($flags)) {
            return array();
        }
        
        $flag_config = array();
        foreach ($flags as $flag) {
            // Check if we have additional configuration stored as meta
            $flag_name = get_post_meta($product_id, "_membershiping_flag_name_{$flag->flag_id}", true);
            $flag_quantity = get_post_meta($product_id, "_membershiping_flag_quantity_{$flag->flag_id}", true) ?: 1;
            $flag_type = get_post_meta($product_id, "_membershiping_flag_type_{$flag->flag_id}", true) ?: 'add';
            
            $flag_config[] = array(
                'flag' => $flag_name ?: $flag->flag_id, // Use name if available, otherwise ID
                'flag_id' => $flag->flag_id,
                'quantity' => intval($flag_quantity),
                'type' => $flag_type
            );
        }
        
        return $flag_config;
    }
    
    /**
     * Link product to flag in database
     */
    public function link_product_to_flag($product_id, $flag_id) {
        // Validate inputs
        if (!$this->validate_product_flag_link($product_id, $flag_id)) {
            return new WP_Error('validation_failed', 'Invalid product or flag ID');
        }
        
        $table_name = $this->database->get_table('product_flags');
        
        // Check if link already exists
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE product_id = %d AND flag_id = %d",
            $product_id,
            $flag_id
        ));
        
        if ($existing) {
            return new WP_Error('link_exists', 'Product-flag link already exists');
        }
        
        $result = $this->wpdb->insert(
            $table_name,
            array(
                'product_id' => $product_id,
                'flag_id' => $flag_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create product-flag link: ' . $this->wpdb->last_error);
        }
        
        // Log the linking action
        $this->security->log_security_event('product_flag_linked', get_current_user_id(), array(
            'product_id' => $product_id,
            'flag_id' => $flag_id
        ));
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Validate product-flag link
     */
    private function validate_product_flag_link($product_id, $flag_id) {
        // Validate product exists and is valid
        if (!$product_id || !get_post($product_id) || get_post_type($product_id) !== 'product') {
            return false;
        }
        
        // Validate flag ID
        if (!$flag_id || !is_numeric($flag_id) || $flag_id <= 0) {
            return false;
        }
        
        // Additional validation: check if product is published
        if (get_post_status($product_id) !== 'publish') {
            return false;
        }
        
        return true;
    }
    
    /**
     * Remove product-flag link
     */
    public function unlink_product_from_flag($product_id, $flag_id) {
        $table_name = $this->database->get_table('product_flags');
        
        $result = $this->wpdb->delete(
            $table_name,
            array(
                'product_id' => $product_id,
                'flag_id' => $flag_id
            ),
            array('%d', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get all flags linked to a product
     */
    public function get_product_linked_flags($product_id) {
        $table_name = $this->database->get_table('product_flags');
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT flag_id, created_at FROM {$table_name} WHERE product_id = %d",
            $product_id
        ));
    }
    
    /**
     * Get all products linked to a flag
     */
    public function get_flag_linked_products($flag_id) {
        $table_name = $this->database->get_table('product_flags');
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT product_id, created_at FROM {$table_name} WHERE flag_id = %d",
            $flag_id
        ));
    }
    
    /**
     * Get product inventory configuration
     */
    private function get_product_inventory_config($product_id) {
        $is_inventory_item = get_post_meta($product_id, '_membershiping_is_inventory_item', true);
        
        if (!$is_inventory_item) {
            return null;
        }
        
        $mint = get_post_meta($product_id, '_membershiping_mint_nft', true);
        if ($mint === '') { $mint = 'yes'; }
        return array(
            'item_id' => get_post_meta($product_id, '_membershiping_inventory_item_id', true),
            'mint_nft' => $mint,
            'is_stackable' => get_post_meta($product_id, '_membershiping_is_stackable', true)
        );
    }
    
    /**
     * Claim guest awards when user registers/logs in
     */
    public function claim_guest_awards($user_id, $email) {
        // Claim flag awards
        $this->claim_guest_flag_awards($user_id, $email);
        
        // Claim item awards
        $this->claim_guest_item_awards($user_id, $email);
    }
    
    /**
     * Claim guest flag awards
     */
    private function claim_guest_flag_awards($user_id, $email) {
        $table_name = $this->database->get_table_name('guest_flag_awards');
        
        $guest_awards = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table_name} 
                 WHERE email = %s AND status = 'pending' AND expires_at > NOW()",
                $email
            )
        );
        
        foreach ($guest_awards as $award) {
            // Award the flag
            $this->award_flag_via_core(
                $user_id, 
                $award->flag_name, 
                $award->quantity, 
                $award->award_type, 
                $award->order_id, 
                $award->product_id
            );
            
            // Mark as claimed
            $this->wpdb->update(
                $table_name,
                array(
                    'status' => 'claimed',
                    'claimed_by_user_id' => $user_id,
                    'claimed_at' => current_time('mysql')
                ),
                array('id' => $award->id),
                array('%s', '%d', '%s'),
                array('%d')
            );
        }
        
        if (!empty($guest_awards)) {
            $this->security->log_security_event('guest_flags_claimed', $user_id, array(
                'email' => $email,
                'awards_count' => count($guest_awards),
                'award_ids' => wp_list_pluck($guest_awards, 'id')
            ));
        }
    }
    
    /**
     * Claim guest item awards
     */
    private function claim_guest_item_awards($user_id, $email) {
        $table_name = $this->database->get_table_name('guest_item_awards');
        
        $guest_awards = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table_name} 
                 WHERE email = %s AND status = 'pending' AND expires_at > NOW()",
                $email
            )
        );
        
        foreach ($guest_awards as $award) {
            // Award the item
            $result = $this->items->add_user_item($user_id, $award->item_id, $award->quantity);
            
            if (!is_wp_error($result)) {
                // Check if NFT should be minted
                if ($award->mint_nft) {
                    for ($i = 0; $i < max(1, intval($award->quantity)); $i++) {
                        $this->mint_nft_for_purchase($user_id, $award->item_id, $award->order_id);
                    }
                }
                
                // Mark as claimed
                $this->wpdb->update(
                    $table_name,
                    array(
                        'status' => 'claimed',
                        'claimed_by_user_id' => $user_id,
                        'claimed_at' => current_time('mysql')
                    ),
                    array('id' => $award->id),
                    array('%s', '%d', '%s'),
                    array('%d')
                );
            }
        }
        
        if (!empty($guest_awards)) {
            $this->security->log_security_event('guest_items_claimed', $user_id, array(
                'email' => $email,
                'awards_count' => count($guest_awards),
                'award_ids' => wp_list_pluck($guest_awards, 'id')
            ));
        }
    }
    
    /**
     * Store flag award in our system (fallback)
     */
    private function store_flag_award($user_id, $flag_name, $quantity, $type, $order_id, $product_id) {
        $table_name = $this->database->get_table_name('user_flags');
        
        // Get current flag value
        $current_value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT value FROM {$table_name} WHERE user_id = %d AND flag_name = %s",
                $user_id, $flag_name
            )
        );
        
        $current_value = $current_value ? intval($current_value) : 0;
        
        // Calculate new value based on award type
        switch ($type) {
            case 'set':
                $new_value = $quantity;
                break;
            case 'multiply':
                $new_value = $current_value * $quantity;
                break;
            case 'add':
            default:
                $new_value = $current_value + $quantity;
                break;
        }
        
        // Insert or update flag
        $this->wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'flag_name' => $flag_name,
                'value' => $new_value,
                'last_updated' => current_time('mysql'),
                'source_order_id' => $order_id,
                'source_product_id' => $product_id
            ),
            array('%d', '%s', '%d', '%s', '%d', '%d')
        );
    }
    
    /**
     * Add product flag fields to admin
     */
    public function add_product_flag_fields() {
        global $post;
        
        error_log('Membershiping Inventory: add_product_flag_fields called for post ID: ' . ($post ? $post->ID : 'unknown'));
        
        ?>
        <div class="options_group">
            <p class="form-field">
                <label for="membershiping_enable_flag_awards"><?php _e('Flag Awards', 'membershiping-inventory'); ?></label>
                <input type="checkbox" class="checkbox" style="" name="membershiping_enable_flag_awards" id="membershiping_enable_flag_awards" <?php checked(get_post_meta($post->ID, '_membershiping_enable_flag_awards', true), 'yes'); ?>>
                <span class="description"><?php _e('Award flags when this product is purchased', 'membershiping-inventory'); ?></span>
            </p>
            
            <div id="membershiping_flag_awards_config" style="<?php echo get_post_meta($post->ID, '_membershiping_enable_flag_awards', true) === 'yes' ? '' : 'display:none;'; ?>">
                <p class="form-field">
                    <label><?php _e('Flag Configuration', 'membershiping-inventory'); ?></label>
                    <span class="description"><?php _e('Configure which flags to award and their quantities', 'membershiping-inventory'); ?></span>
                </p>
                
                <div id="flag_awards_container">
                    <?php
                    $flag_awards = get_post_meta($post->ID, '_membershiping_flag_awards', true);
                    if (!empty($flag_awards) && is_array($flag_awards)) {
                        foreach ($flag_awards as $index => $award) {
                            $this->render_flag_award_row($index, $award);
                        }
                    } else {
                        $this->render_flag_award_row(0, array());
                    }
                    ?>
                </div>
                
                <p>
                    <button type="button" class="button" id="add_flag_award"><?php _e('Add Flag Award', 'membershiping-inventory'); ?></button>
                </p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#membershiping_enable_flag_awards').change(function() {
                if ($(this).is(':checked')) {
                    $('#membershiping_flag_awards_config').show();
                } else {
                    $('#membershiping_flag_awards_config').hide();
                }
            });
            
            $('#add_flag_award').click(function() {
                var container = $('#flag_awards_container');
                var index = container.children().length;
                var row = `<?php echo str_replace(array("\r", "\n"), '', $this->get_flag_award_row_template()); ?>`;
                row = row.replace(/\{INDEX\}/g, index);
                container.append(row);
            });
            
            $(document).on('click', '.remove_flag_award', function() {
                $(this).closest('.flag_award_row').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render flag award row
     */
    private function render_flag_award_row($index, $award) {
        ?>
        <div class="flag_award_row" style="border: 1px solid #ddd; padding: 10px; margin: 5px 0;">
            <p>
                <label><?php _e('Flag Name:', 'membershiping-inventory'); ?></label>
                <input type="text" name="flag_awards[<?php echo $index; ?>][flag]" value="<?php echo esc_attr($award['flag'] ?? ''); ?>" style="width: 200px;" placeholder="e.g., gold_coins">
                
                <label><?php _e('Quantity:', 'membershiping-inventory'); ?></label>
                <input type="number" name="flag_awards[<?php echo $index; ?>][quantity]" value="<?php echo esc_attr($award['quantity'] ?? 1); ?>" style="width: 80px;" min="1">
                
                <label><?php _e('Type:', 'membershiping-inventory'); ?></label>
                <select name="flag_awards[<?php echo $index; ?>][type]" style="width: 100px;">
                    <option value="add" <?php selected($award['type'] ?? 'add', 'add'); ?>><?php _e('Add', 'membershiping-inventory'); ?></option>
                    <option value="set" <?php selected($award['type'] ?? 'add', 'set'); ?>><?php _e('Set', 'membershiping-inventory'); ?></option>
                    <option value="multiply" <?php selected($award['type'] ?? 'add', 'multiply'); ?>><?php _e('Multiply', 'membershiping-inventory'); ?></option>
                </select>
                
                <button type="button" class="button remove_flag_award"><?php _e('Remove', 'membershiping-inventory'); ?></button>
            </p>
        </div>
        <?php
    }
    
    /**
     * Get flag award row template for JavaScript
     */
    private function get_flag_award_row_template() {
        ob_start();
        ?>
        <div class="flag_award_row" style="border: 1px solid #ddd; padding: 10px; margin: 5px 0;">
            <p>
                <label><?php _e('Flag Name:', 'membershiping-inventory'); ?></label>
                <input type="text" name="flag_awards[{INDEX}][flag]" value="" style="width: 200px;" placeholder="e.g., gold_coins">
                
                <label><?php _e('Quantity:', 'membershiping-inventory'); ?></label>
                <input type="number" name="flag_awards[{INDEX}][quantity]" value="1" style="width: 80px;" min="1">
                
                <label><?php _e('Type:', 'membershiping-inventory'); ?></label>
                <select name="flag_awards[{INDEX}][type]" style="width: 100px;">
                    <option value="add"><?php _e('Add', 'membershiping-inventory'); ?></option>
                    <option value="set"><?php _e('Set', 'membershiping-inventory'); ?></option>
                    <option value="multiply"><?php _e('Multiply', 'membershiping-inventory'); ?></option>
                </select>
                
                <button type="button" class="button remove_flag_award"><?php _e('Remove', 'membershiping-inventory'); ?></button>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Save product flag fields
     */
    public function save_product_flag_fields($post_id) {
        error_log('Membershiping Flag Awards: save_product_flag_fields called for post ' . $post_id);
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            error_log('Membershiping Flag Awards: Autosave detected, skipping');
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            error_log('Membershiping Flag Awards: User cannot edit post ' . $post_id);
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['membershiping_inventory_product_nonce']) || 
            !wp_verify_nonce($_POST['membershiping_inventory_product_nonce'], 'membershiping_inventory_product_meta')) {
            error_log('Membershiping Flag Awards: Nonce verification failed');
            return;
        }
        
        error_log('Membershiping Flag Awards: POST data - ' . print_r($_POST, true));
        
        // Save inventory item settings
        $is_inventory_item = isset($_POST['membershiping_is_inventory_item']) ? 'yes' : 'no';
        update_post_meta($post_id, '_membershiping_is_inventory_item', $is_inventory_item);
        error_log('Membershiping Flag Awards: Saved is_inventory_item = ' . $is_inventory_item);
        
        // Save selected inventory item ID
        if (isset($_POST['membershiping_inventory_item_id']) && !empty($_POST['membershiping_inventory_item_id'])) {
            $item_id = intval($_POST['membershiping_inventory_item_id']);
            update_post_meta($post_id, '_membershiping_inventory_item_id', $item_id);
            error_log('Membershiping Flag Awards: Saved inventory_item_id = ' . $item_id);
        } else {
            delete_post_meta($post_id, '_membershiping_inventory_item_id');
            error_log('Membershiping Flag Awards: Deleted inventory_item_id (empty)');
        }
        
        // Save NFT minting setting
        $mint_nft = isset($_POST['membershiping_mint_nft']) ? 'yes' : 'no';
        update_post_meta($post_id, '_membershiping_mint_nft', $mint_nft);
        error_log('Membershiping Flag Awards: Saved mint_nft = ' . $mint_nft);
        
        // Save stackable setting
        $is_stackable = isset($_POST['membershiping_is_stackable']) ? 'yes' : 'no';
        update_post_meta($post_id, '_membershiping_is_stackable', $is_stackable);
        error_log('Membershiping Flag Awards: Saved is_stackable = ' . $is_stackable);
        
        // Save exclude from shop setting
        $exclude_from_shop = isset($_POST['membershiping_exclude_from_shop']) ? 'yes' : 'no';
        update_post_meta($post_id, '_membershiping_exclude_from_shop', $exclude_from_shop);
        error_log('Membershiping Flag Awards: Saved exclude_from_shop = ' . $exclude_from_shop);
        
        // Save flag awards enablement
        $enable_flag_awards = isset($_POST['membershiping_enable_flag_awards']) ? 'yes' : 'no';
        update_post_meta($post_id, '_membershiping_enable_flag_awards', $enable_flag_awards);
        
        // Save flag awards configuration
        if (isset($_POST['flag_awards']) && is_array($_POST['flag_awards'])) {
            $flag_awards = array();
            
            foreach ($_POST['flag_awards'] as $award) {
                if (!empty($award['flag'])) {
                    $flag_awards[] = array(
                        'flag' => sanitize_text_field($award['flag']),
                        'quantity' => intval($award['quantity']),
                        'type' => sanitize_text_field($award['type'])
                    );
                }
            }
            
            update_post_meta($post_id, '_membershiping_flag_awards', $flag_awards);
        } else {
            delete_post_meta($post_id, '_membershiping_flag_awards');
        }
    }
    
    /**
     * Add product meta boxes
     */
    public function add_product_meta_boxes() {
        error_log('Membershiping Inventory Flag Awards: add_product_meta_boxes called');
        add_meta_box(
            'membershiping_inventory_product',
            __('Membershiping Inventory Settings', 'membershiping-inventory'),
            array($this, 'render_product_meta_box'),
            'product',
            'normal',
            'default'
        );
        error_log('Membershiping Inventory Flag Awards: Meta box added for product post type');
    }
    
    /**
     * Render product meta box
     */
    public function render_product_meta_box($post) {
        error_log('Membershiping Inventory Flag Awards: render_product_meta_box called for post ID: ' . ($post ? $post->ID : 'unknown'));
        wp_nonce_field('membershiping_inventory_product_meta', 'membershiping_inventory_product_nonce');
        
        $is_inventory_item = get_post_meta($post->ID, '_membershiping_is_inventory_item', true);
        $inventory_item_id = get_post_meta($post->ID, '_membershiping_inventory_item_id', true);
    $mint_nft = get_post_meta($post->ID, '_membershiping_mint_nft', true);
    if ($mint_nft === '') { $mint_nft = 'yes'; }
        $is_stackable = get_post_meta($post->ID, '_membershiping_is_stackable', true);
        $exclude_from_shop = get_post_meta($post->ID, '_membershiping_exclude_from_shop', true);
        
        // Get available inventory items
        $available_items = $this->items->get_all_items();
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="membershiping_is_inventory_item"><?php _e('Is Inventory Item', 'membershiping-inventory'); ?></label></th>
                <td>
                    <input type="checkbox" id="membershiping_is_inventory_item" name="membershiping_is_inventory_item" value="yes" <?php checked($is_inventory_item, 'yes'); ?>>
                    <span class="description"><?php _e('This product represents an inventory item', 'membershiping-inventory'); ?></span>
                </td>
            </tr>
            
            <tr id="inventory_item_settings" style="<?php echo $is_inventory_item === 'yes' ? '' : 'display:none;'; ?>">
                <th><label for="membershiping_inventory_item_id"><?php _e('Inventory Item', 'membershiping-inventory'); ?></label></th>
                <td>
                    <select id="membershiping_inventory_item_id" name="membershiping_inventory_item_id" style="width: 300px;">
                        <option value=""><?php _e('Select an inventory item...', 'membershiping-inventory'); ?></option>
                        <?php foreach ($available_items as $item): ?>
                            <option value="<?php echo esc_attr($item->id); ?>" <?php selected($inventory_item_id, $item->id); ?>>
                                <?php echo esc_html($item->name . ' (' . ucfirst($item->item_type) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php _e('Choose which inventory item this product represents', 'membershiping-inventory'); ?></span>
                </td>
            </tr>
            
            <tr class="inventory_item_option" style="<?php echo $is_inventory_item === 'yes' ? '' : 'display:none;'; ?>">
                <th><label for="membershiping_mint_nft"><?php _e('Mint NFT', 'membershiping-inventory'); ?></label></th>
                <td>
                    <input type="checkbox" id="membershiping_mint_nft" name="membershiping_mint_nft" value="yes" <?php checked($mint_nft, 'yes'); ?>>
                    <span class="description"><?php _e('Mint an NFT for each purchase (applies to all items, including stackable)', 'membershiping-inventory'); ?></span>
                </td>
            </tr>
            
            <tr class="inventory_item_option" style="<?php echo $is_inventory_item === 'yes' ? '' : 'display:none;'; ?>">
                <th><label for="membershiping_is_stackable"><?php _e('Is Stackable', 'membershiping-inventory'); ?></label></th>
                <td>
                    <input type="checkbox" id="membershiping_is_stackable" name="membershiping_is_stackable" value="yes" <?php checked($is_stackable, 'yes'); ?>>
                    <span class="description"><?php _e('Multiple copies can be stacked in inventory', 'membershiping-inventory'); ?></span>
                </td>
            </tr>
            
            <tr class="inventory_item_option" style="<?php echo $is_inventory_item === 'yes' ? '' : 'display:none;'; ?>">
                <th><label for="membershiping_exclude_from_shop"><?php _e('Hide from Shop', 'membershiping-inventory'); ?></label></th>
                <td>
                    <input type="checkbox" id="membershiping_exclude_from_shop" name="membershiping_exclude_from_shop" value="yes" <?php checked($exclude_from_shop, 'yes'); ?>>
                    <span class="description"><?php _e('Hide this product from shop (inventory only)', 'membershiping-inventory'); ?></span>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Flag Awards Configuration', 'membershiping-inventory'); ?></h3>
        <p class="description"><?php _e('Configure which flags to award when this product is purchased.', 'membershiping-inventory'); ?></p>
        
        <table class="form-table">
            <tr>
                <th><label><?php _e('Linked Flags', 'membershiping-inventory'); ?></label></th>
                <td>
                    <?php $this->render_flag_links_interface($post->ID); ?>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#membershiping_is_inventory_item').change(function() {
                if ($(this).is(':checked')) {
                    $('#inventory_item_settings, .inventory_item_option').show();
                } else {
                    $('#inventory_item_settings, .inventory_item_option').hide();
                }
            });
            
            // Flag management
            $('.add-flag-link').click(function() {
                var flagId = '';
                var flagName = '';
                var $select = $('#available_flags_select');

                if ($select.length && $select.val()) {
                    flagId = $select.val();
                    // Use slug if present, otherwise name
                    flagName = $select.find('option:selected').data('slug') || $select.find('option:selected').data('name') || '';
                } else {
                    // Fallback to manual inputs
                    flagId = $('#new_flag_id').val();
                    flagName = $('#new_flag_name').val();
                }

                if (!flagId) {
                    alert('Please select or enter a Flag ID');
                    return;
                }
                
                // Add flag link via AJAX
                var data = {
                    action: 'membershiping_link_product_flag',
                    product_id: <?php echo $post->ID; ?>,
                    flag_id: flagId,
                    flag_name: flagName,
                    nonce: '<?php echo wp_create_nonce('membershiping_flag_link'); ?>'
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        location.reload(); // Refresh to show the new link
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            $('.remove-flag-link').click(function() {
                var flagId = $(this).data('flag-id');
                
                if (!confirm('Are you sure you want to remove this flag link?')) {
                    return;
                }
                
                var data = {
                    action: 'membershiping_unlink_product_flag',
                    product_id: <?php echo $post->ID; ?>,
                    flag_id: flagId,
                    nonce: '<?php echo wp_create_nonce('membershiping_flag_link'); ?>'
                };
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        location.reload(); // Refresh to remove the link
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get pending guest awards
     */
    public function get_pending_guest_awards($email = null) {
        $flag_table = $this->database->get_table_name('guest_flag_awards');
        $item_table = $this->database->get_table_name('guest_item_awards');
        
        $where_clause = $email ? $this->wpdb->prepare("WHERE email = %s", $email) : '';
        
        $flag_awards = $this->wpdb->get_results(
            "SELECT 'flag' as type, email, flag_name as name, quantity, created_at 
             FROM {$flag_table} 
             WHERE status = 'pending' AND expires_at > NOW() {$where_clause}
             ORDER BY created_at DESC"
        );
        
        $item_awards = $this->wpdb->get_results(
            "SELECT 'item' as type, gia.email, i.name, gia.quantity, gia.created_at
             FROM {$item_table} gia
             JOIN {$this->database->get_table_name('items')} i ON gia.item_id = i.id
             WHERE gia.status = 'pending' AND gia.expires_at > NOW() {$where_clause}
             ORDER BY gia.created_at DESC"
        );
        
        return array_merge($flag_awards, $item_awards);
    }
    
    /**
     * Cleanup old guest awards
     */
    public function cleanup_old_guest_awards() {
        $flag_table = $this->database->get_table_name('guest_flag_awards');
        $item_table = $this->database->get_table_name('guest_item_awards');
        
        // Delete expired awards
        $deleted_flags = $this->wpdb->query(
            "DELETE FROM {$flag_table} WHERE expires_at < NOW()"
        );
        
        $deleted_items = $this->wpdb->query(
            "DELETE FROM {$item_table} WHERE expires_at < NOW()"
        );
        
        if ($deleted_flags > 0 || $deleted_items > 0) {
            $this->security->log_security_event('guest_awards_cleanup', 0, array(
                'deleted_flags' => $deleted_flags,
                'deleted_items' => $deleted_items
            ));
        }
    }
    
    /**
     * AJAX handler to award flags to guests retroactively
     */
    public function ajax_award_flags_to_guests() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!$email) {
            wp_send_json_error('Email is required');
        }
        
        // Get user by email
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        // Claim the awards
        $this->claim_guest_awards($user->ID, $email);
        
        wp_send_json_success('Awards claimed successfully');
    }
    
    /**
     * AJAX handler to get guest orders
     */
    public function ajax_get_guest_orders() {
        check_ajax_referer('membershiping_inventory_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (!$email) {
            wp_send_json_error('Email is required');
        }
        
        $pending_awards = $this->get_pending_guest_awards($email);
        
        wp_send_json_success($pending_awards);
    }
    
    /**
     * Render flag links interface in meta box
     */
    private function render_flag_links_interface($product_id) {
        $linked_flags = $this->get_product_linked_flags($product_id);
    $available_flags = $this->get_available_flags();
        
        ?>
        <div class="flag-links-container">
            <?php if (!empty($linked_flags)): ?>
                <h4><?php _e('Currently Linked Flags:', 'membershiping-inventory'); ?></h4>
                <ul>
                    <?php foreach ($linked_flags as $flag): ?>
                        <li>
                            <strong><?php _e('Flag ID:', 'membershiping-inventory'); ?> <?php echo esc_html($flag->flag_id); ?></strong>
                            <span class="description">(<?php _e('Linked on:', 'membershiping-inventory'); ?> <?php echo esc_html($flag->created_at); ?>)</span>
                            <button type="button" class="button button-small remove-flag-link" data-flag-id="<?php echo esc_attr($flag->flag_id); ?>">
                                <?php _e('Remove', 'membershiping-inventory'); ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><em><?php _e('No flags linked to this product yet.', 'membershiping-inventory'); ?></em></p>
            <?php endif; ?>
            
            <h4><?php _e('Add New Flag Link:', 'membershiping-inventory'); ?></h4>
            <?php if (!empty($available_flags)): ?>
                <table class="form-table">
                    <tr>
                        <td>
                            <label for="available_flags_select"><?php _e('Select Flag:', 'membershiping-inventory'); ?></label>
                            <select id="available_flags_select" name="available_flag_id" style="min-width: 280px;">
                                <option value=""><?php _e('Select a flag…', 'membershiping-inventory'); ?></option>
                                <?php foreach ($available_flags as $flag): ?>
                                    <option value="<?php echo esc_attr($flag['id']); ?>"
                                            data-name="<?php echo esc_attr($flag['name']); ?>"
                                            data-slug="<?php echo esc_attr($flag['slug']); ?>">
                                        <?php echo esc_html($flag['name'] . ($flag['slug'] ? ' (' . $flag['slug'] . ')' : '') . ' — ID: ' . $flag['id']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button button-primary add-flag-link"><?php _e('Link Flag', 'membershiping-inventory'); ?></button>
                        </td>
                    </tr>
                </table>
                <p class="description">
                    <?php _e('Choose a flag from your Membershiping system to link with this product.', 'membershiping-inventory'); ?>
                </p>
            <?php else: ?>
                <table class="form-table">
                    <tr>
                        <td>
                            <label for="new_flag_id"><?php _e('Flag ID:', 'membershiping-inventory'); ?></label>
                            <input type="number" id="new_flag_id" name="new_flag_id" style="width: 100px;" min="1" placeholder="123">
                        </td>
                        <td>
                            <label for="new_flag_name"><?php _e('Flag Name (slug):', 'membershiping-inventory'); ?></label>
                            <input type="text" id="new_flag_name" name="new_flag_name" style="width: 200px;" placeholder="e.g., premium_member">
                        </td>
                        <td>
                            <button type="button" class="button button-primary add-flag-link"><?php _e('Link Flag', 'membershiping-inventory'); ?></button>
                        </td>
                    </tr>
                </table>
                <p class="description">
                    <?php _e('Could not load flag list from the core plugin. Enter the Flag ID and optional slug manually.', 'membershiping-inventory'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Try to retrieve available flags from the Membershiping core plugin or DB as fallback
     * Returns array of [ ['id'=>int,'name'=>string,'slug'=>string], ... ]
     */
    private function get_available_flags() {
        $flags = array();

        // Preferred: Core user flags API
        if (class_exists('Membershiping_User_Flags')) {
            try {
                $uf = new Membershiping_User_Flags();
                if (method_exists($uf, 'get_all_flags')) {
                    $list = $uf->get_all_flags();
                } elseif (method_exists($uf, 'get_flags')) {
                    $list = $uf->get_flags();
                } else {
                    $list = array();
                }
                foreach ((array)$list as $f) {
                    $flags[] = array(
                        'id' => intval($f->id ?? $f['id'] ?? 0),
                        'name' => sanitize_text_field($f->name ?? $f['name'] ?? ''),
                        'slug' => sanitize_title($f->slug ?? $f['slug'] ?? '')
                    );
                }
            } catch (Exception $e) {}
        }

        // Alternative: Legacy Membershiping_Flags class
        if (empty($flags) && class_exists('Membershiping_Flags')) {
            try {
                $mf = new Membershiping_Flags();
                if (method_exists($mf, 'get_all_flags')) {
                    $list = $mf->get_all_flags();
                } elseif (method_exists($mf, 'get_flags')) {
                    $list = $mf->get_flags();
                } else {
                    $list = array();
                }
                foreach ((array)$list as $f) {
                    $flags[] = array(
                        'id' => intval($f->id ?? $f['id'] ?? 0),
                        'name' => sanitize_text_field($f->name ?? $f['name'] ?? ''),
                        'slug' => sanitize_title($f->slug ?? $f['slug'] ?? '')
                    );
                }
            } catch (Exception $e) {}
        }

        // DB fallback(s)
        if (empty($flags)) {
            global $wpdb;
            // Try a canonical flags table
            $table_flags = $wpdb->prefix . 'membershiping_flags';
            $exists = ($wpdb->get_var("SHOW TABLES LIKE '" . esc_sql($table_flags) . "'") === $table_flags);
            if ($exists) {
                $rows = $wpdb->get_results("SELECT id, name, slug FROM {$table_flags} ORDER BY name LIMIT 200");
                foreach ((array)$rows as $r) {
                    $flags[] = array(
                        'id' => intval($r->id),
                        'name' => sanitize_text_field($r->name),
                        'slug' => sanitize_title($r->slug)
                    );
                }
            }
        }

        // Deduplicate and filter empties
        $unique = array();
        foreach ($flags as $f) {
            // Must have a real numeric ID to be linkable
            if (empty($f['id']) || !is_numeric($f['id'])) { continue; }
            $key = $f['id'] . '|' . $f['slug'];
            $unique[$key] = $f;
        }

        // Sort by name
        $out = array_values($unique);
        usort($out, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $out;
    }
    
    /**
     * AJAX handler to link product to flag
     */
    public function ajax_link_product_flag() {
        check_ajax_referer('membershiping_flag_link', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $flag_id = intval($_POST['flag_id'] ?? 0);
        $flag_name = sanitize_text_field($_POST['flag_name'] ?? '');
        
        if (!$product_id || !$flag_id) {
            wp_send_json_error('Product ID and Flag ID are required');
        }
        
        // Additional validation
        if (!get_post($product_id) || get_post_type($product_id) !== 'product') {
            wp_send_json_error('Invalid product ID');
        }
        
        if ($flag_id <= 0) {
            wp_send_json_error('Flag ID must be a positive number');
        }
        
        // Validate flag name if provided
        if ($flag_name && !preg_match('/^[a-zA-Z0-9_-]+$/', $flag_name)) {
            wp_send_json_error('Flag name can only contain letters, numbers, underscores, and hyphens');
        }
        
        $result = $this->link_product_to_flag($product_id, $flag_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Store flag name and configuration as meta for reference
        if ($flag_name) {
            update_post_meta($product_id, "_membershiping_flag_name_{$flag_id}", $flag_name);
        }
        
        // Store default configuration
        update_post_meta($product_id, "_membershiping_flag_quantity_{$flag_id}", 1);
        update_post_meta($product_id, "_membershiping_flag_type_{$flag_id}", 'add');
        
        wp_send_json_success(array(
            'message' => 'Flag linked successfully',
            'link_id' => $result
        ));
    }
    
    /**
     * AJAX handler to unlink product from flag
     */
    public function ajax_unlink_product_flag() {
        check_ajax_referer('membershiping_flag_link', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $flag_id = intval($_POST['flag_id'] ?? 0);
        
        if (!$product_id || !$flag_id) {
            wp_send_json_error('Product ID and Flag ID are required');
        }
        
        // Validate product exists
        if (!get_post($product_id) || get_post_type($product_id) !== 'product') {
            wp_send_json_error('Invalid product ID');
        }
        
        $result = $this->unlink_product_from_flag($product_id, $flag_id);
        
        if (!$result) {
            wp_send_json_error('Failed to unlink flag - link may not exist');
        }
        
        // Remove flag configuration meta
        delete_post_meta($product_id, "_membershiping_flag_name_{$flag_id}");
        delete_post_meta($product_id, "_membershiping_flag_quantity_{$flag_id}");
        delete_post_meta($product_id, "_membershiping_flag_type_{$flag_id}");
        
        // Log the unlinking action
        $this->security->log_security_event('product_flag_unlinked', get_current_user_id(), array(
            'product_id' => $product_id,
            'flag_id' => $flag_id
        ));
        
        wp_send_json_success('Flag unlinked successfully');
    }
}
