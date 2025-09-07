<?php
/**
 * WooCommerce Integration for Membershiping Inventory
 * 
 * Handles integration with WooCommerce for product restrictions,
 * inventory management, and e-commerce functionality.
 * 
 * @package Membershiping_Inventory
 * @subpackage Integrations
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_WooCommerce_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WooCommerce hooks
     */
    private function init_hooks() {
        // Product restrictions
        add_filter('woocommerce_is_purchasable', array($this, 'check_product_purchasable'), 10, 2);
        add_action('woocommerce_single_product_summary', array($this, 'show_restriction_notice'), 25);
        
        // Cart and checkout restrictions
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        add_action('woocommerce_check_cart_items', array($this, 'check_cart_restrictions'));
        
        // Order processing
        add_action('woocommerce_order_status_completed', array($this, 'process_completed_order'));
        add_action('woocommerce_order_status_processing', array($this, 'process_order'));
        
        // Admin integration
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_restriction_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_restriction_fields'));
        
        // AJAX handlers
        add_action('wp_ajax_membershiping_check_product_access', array($this, 'ajax_check_product_access'));
        add_action('wp_ajax_nopriv_membershiping_check_product_access', array($this, 'ajax_check_product_access'));
    }
    
    /**
     * Check if product is purchasable based on inventory restrictions
     */
    public function check_product_purchasable($purchasable, $product) {
        if (!$purchasable) {
            return $purchasable;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $purchasable;
        }
        
        $product_id = $product->get_id();
        $restriction_type = get_post_meta($product_id, '_membershiping_restriction_type', true);
        $restriction_value = get_post_meta($product_id, '_membershiping_restriction_value', true);
        
        if (empty($restriction_type)) {
            return $purchasable;
        }
        
        return $this->check_user_access($user_id, $restriction_type, $restriction_value);
    }
    
    /**
     * Show restriction notice on product page
     */
    public function show_restriction_notice() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $restriction_type = get_post_meta($product->get_id(), '_membershiping_restriction_type', true);
        
        if (empty($restriction_type)) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<div class="membershiping-restriction-notice">';
            echo '<p><strong>' . __('Access Required', 'membershiping-inventory') . '</strong></p>';
            echo '<p>' . __('You must be logged in to access this product.', 'membershiping-inventory') . '</p>';
            echo '</div>';
            return;
        }
        
        $restriction_value = get_post_meta($product->get_id(), '_membershiping_restriction_value', true);
        $has_access = $this->check_user_access($user_id, $restriction_type, $restriction_value);
        
        if (!$has_access) {
            echo '<div class="membershiping-restriction-notice">';
            echo '<p><strong>' . __('Access Restricted', 'membershiping-inventory') . '</strong></p>';
            echo '<p>' . $this->get_restriction_message($restriction_type, $restriction_value) . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Validate add to cart based on restrictions
     */
    public function validate_add_to_cart($passed, $product_id, $quantity) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return $passed;
        }
        
        $restriction_type = get_post_meta($product_id, '_membershiping_restriction_type', true);
        $restriction_value = get_post_meta($product_id, '_membershiping_restriction_value', true);
        
        if (empty($restriction_type)) {
            return $passed;
        }
        
        $has_access = $this->check_user_access($user_id, $restriction_type, $restriction_value);
        
        if (!$has_access) {
            wc_add_notice($this->get_restriction_message($restriction_type, $restriction_value), 'error');
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Check cart items for restrictions
     */
    public function check_cart_restrictions() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $restriction_type = get_post_meta($product_id, '_membershiping_restriction_type', true);
            $restriction_value = get_post_meta($product_id, '_membershiping_restriction_value', true);
            
            if (empty($restriction_type)) {
                continue;
            }
            
            $has_access = $this->check_user_access($user_id, $restriction_type, $restriction_value);
            
            if (!$has_access) {
                WC()->cart->remove_cart_item($cart_item_key);
                wc_add_notice(
                    sprintf(
                        __('Product "%s" was removed from your cart: %s', 'membershiping-inventory'),
                        get_the_title($product_id),
                        $this->get_restriction_message($restriction_type, $restriction_value)
                    ),
                    'error'
                );
            }
        }
    }
    
    /**
     * Process completed order
     */
    public function process_completed_order($order_id) {
        $this->process_order($order_id, 'completed');
    }
    
    /**
     * Process order (completed or processing)
     */
    public function process_order($order_id, $status = 'processing') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return;
        }
        
        // Award inventory items based on purchased products
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            
            // Check if this product awards inventory items
            $awards_items = get_post_meta($product_id, '_membershiping_awards_items', true);
            
            if ($awards_items) {
                $items_to_award = json_decode($awards_items, true);
                
                if (is_array($items_to_award)) {
                    foreach ($items_to_award as $item_award) {
                        $this->award_inventory_item($user_id, $item_award, $quantity);
                    }
                }
            }
            
            // Award currencies
            $awards_currency = get_post_meta($product_id, '_membershiping_awards_currency', true);
            
            if ($awards_currency) {
                $currency_awards = json_decode($awards_currency, true);
                
                if (is_array($currency_awards)) {
                    foreach ($currency_awards as $currency_award) {
                        $this->award_currency($user_id, $currency_award, $quantity);
                    }
                }
            }
        }
        
        // Log the order processing
        error_log("Membershiping Inventory: Processed order $order_id for user $user_id");
    }
    
    /**
     * Add product restriction fields to admin
     */
    public function add_product_restriction_fields() {
        global $post;
        
        echo '<div class="options_group">';
        echo '<h3>' . __('Membershiping Inventory Integration', 'membershiping-inventory') . '</h3>';
        
        // Purchase Requirements Section
        echo '<h4>' . __('Purchase Requirements', 'membershiping-inventory') . '</h4>';
        
        woocommerce_wp_select(array(
            'id' => '_membershiping_restriction_type',
            'label' => __('Restriction Type', 'membershiping-inventory'),
            'options' => array(
                '' => __('No restriction', 'membershiping-inventory'),
                'currency' => __('Currency requirement', 'membershiping-inventory'),
                'item' => __('Item requirement', 'membershiping-inventory'),
                'level' => __('Level requirement', 'membershiping-inventory'),
                'flag' => __('Flag requirement', 'membershiping-inventory'),
            ),
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_membershiping_restriction_value',
            'label' => __('Restriction Value', 'membershiping-inventory'),
            'desc_tip' => true,
            'description' => __('Specify the required value (currency amount, item ID, level, or flag ID)', 'membershiping-inventory'),
        ));
        
        // Item Awards Section
        echo '<h4>' . __('Item Awards (When Purchased)', 'membershiping-inventory') . '</h4>';
        
        echo '<div id="membershiping-item-awards">';
        
        // Get existing awards
        $awards_items = get_post_meta($post->ID, '_membershiping_awards_items', true);
        $item_awards = array();
        
        if ($awards_items) {
            $item_awards = json_decode($awards_items, true);
            if (!is_array($item_awards)) {
                $item_awards = array();
            }
        }
        
        // Get all available items (products)
        global $wpdb;
        $available_items = $wpdb->get_results("
            SELECT ID, post_title
            FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
            AND ID != {$post->ID}
            ORDER BY post_title
        ");
        
        echo '<div class="item-awards-container">';
        
        // Display existing awards
        if (!empty($item_awards)) {
            foreach ($item_awards as $index => $award) {
                $this->render_item_award_row($index, $award, $available_items);
            }
        } else {
            $this->render_item_award_row(0, array(), $available_items);
        }
        
        echo '</div>';
        
        echo '<button type="button" class="button" id="add-item-award">' . __('Add Another Item', 'membershiping-inventory') . '</button>';
        echo '</div>';
        
        // Currency Awards Section
        echo '<h4>' . __('Currency Awards (When Purchased)', 'membershiping-inventory') . '</h4>';
        
        echo '<div id="membershiping-currency-awards">';
        
        // Get existing currency awards
        $awards_currency = get_post_meta($post->ID, '_membershiping_awards_currency', true);
        $currency_awards = array();
        
        if ($awards_currency) {
            $currency_awards = json_decode($awards_currency, true);
            if (!is_array($currency_awards)) {
                $currency_awards = array();
            }
        }
        
        // Get all available currencies
        $available_currencies = $wpdb->get_results("
            SELECT id, name, symbol
            FROM {$wpdb->prefix}membershiping_inventory_currencies
            ORDER BY name
        ");
        
        echo '<div class="currency-awards-container">';
        
        // Display existing currency awards
        if (!empty($currency_awards)) {
            foreach ($currency_awards as $index => $award) {
                $this->render_currency_award_row($index, $award, $available_currencies);
            }
        } else {
            $this->render_currency_award_row(0, array(), $available_currencies);
        }
        
        echo '</div>';
        
        echo '<button type="button" class="button" id="add-currency-award">' . __('Add Another Currency', 'membershiping-inventory') . '</button>';
        echo '</div>';
        
        echo '</div>';
        
        // Add JavaScript for dynamic fields
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var itemAwardIndex = <?php echo count($item_awards); ?>;
            var currencyAwardIndex = <?php echo count($currency_awards); ?>;
            
            $('#add-item-award').on('click', function(e) {
                e.preventDefault();
                var newRow = <?php echo json_encode($this->get_item_award_row_template('TEMPLATE_INDEX', $available_items)); ?>;
                newRow = newRow.replace(/TEMPLATE_INDEX/g, itemAwardIndex);
                $('.item-awards-container').append(newRow);
                itemAwardIndex++;
            });
            
            $('#add-currency-award').on('click', function(e) {
                e.preventDefault();
                var newRow = <?php echo json_encode($this->get_currency_award_row_template('TEMPLATE_INDEX', $available_currencies)); ?>;
                newRow = newRow.replace(/TEMPLATE_INDEX/g, currencyAwardIndex);
                $('.currency-awards-container').append(newRow);
                currencyAwardIndex++;
            });
            
            $(document).on('click', '.remove-award-row', function(e) {
                e.preventDefault();
                $(this).closest('.award-row').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save product restriction fields
     */
    public function save_product_restriction_fields($post_id) {
        $restriction_type = sanitize_text_field($_POST['_membershiping_restriction_type'] ?? '');
        $restriction_value = sanitize_text_field($_POST['_membershiping_restriction_value'] ?? '');
        
        update_post_meta($post_id, '_membershiping_restriction_type', $restriction_type);
        update_post_meta($post_id, '_membershiping_restriction_value', $restriction_value);
        
        // Process item awards
        $item_awards = array();
        if (isset($_POST['membershiping_item_awards'])) {
            foreach ($_POST['membershiping_item_awards'] as $award) {
                if (!empty($award['item_id']) && !empty($award['quantity'])) {
                    $item_awards[] = array(
                        'item_id' => intval($award['item_id']),
                        'quantity' => intval($award['quantity'])
                    );
                }
            }
        }
        update_post_meta($post_id, '_membershiping_awards_items', json_encode($item_awards));
        
        // Process currency awards
        $currency_awards = array();
        if (isset($_POST['membershiping_currency_awards'])) {
            foreach ($_POST['membershiping_currency_awards'] as $award) {
                if (!empty($award['currency_id']) && !empty($award['amount'])) {
                    $currency_awards[] = array(
                        'currency_id' => intval($award['currency_id']),
                        'amount' => floatval($award['amount'])
                    );
                }
            }
        }
        update_post_meta($post_id, '_membershiping_awards_currency', json_encode($currency_awards));
    }
    
    /**
     * Render item award row
     */
    private function render_item_award_row($index, $award, $available_items) {
        echo '<div class="award-row" style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        
        echo '<select name="membershiping_item_awards[' . $index . '][item_id]" style="margin-right: 10px;">';
        echo '<option value="">' . __('Select an item...', 'membershiping-inventory') . '</option>';
        
        foreach ($available_items as $item) {
            $selected = isset($award['item_id']) && $award['item_id'] == $item->ID ? 'selected' : '';
            echo '<option value="' . $item->ID . '" ' . $selected . '>' . esc_html($item->post_title) . '</option>';
        }
        
        echo '</select>';
        
        $quantity = isset($award['quantity']) ? $award['quantity'] : 1;
        echo '<input type="number" name="membershiping_item_awards[' . $index . '][quantity]" value="' . $quantity . '" min="1" placeholder="' . __('Quantity', 'membershiping-inventory') . '" style="width: 80px; margin-right: 10px;">';
        
        echo '<button type="button" class="button remove-award-row">' . __('Remove', 'membershiping-inventory') . '</button>';
        
        echo '</div>';
    }
    
    /**
     * Render currency award row
     */
    private function render_currency_award_row($index, $award, $available_currencies) {
        echo '<div class="award-row" style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        
        echo '<select name="membershiping_currency_awards[' . $index . '][currency_id]" style="margin-right: 10px;">';
        echo '<option value="">' . __('Select a currency...', 'membershiping-inventory') . '</option>';
        
        foreach ($available_currencies as $currency) {
            $selected = isset($award['currency_id']) && $award['currency_id'] == $currency->id ? 'selected' : '';
            echo '<option value="' . $currency->id . '" ' . $selected . '>' . esc_html($currency->name . ' (' . $currency->symbol . ')') . '</option>';
        }
        
        echo '</select>';
        
        $amount = isset($award['amount']) ? $award['amount'] : 100;
        echo '<input type="number" name="membershiping_currency_awards[' . $index . '][amount]" value="' . $amount . '" min="0" step="0.01" placeholder="' . __('Amount', 'membershiping-inventory') . '" style="width: 100px; margin-right: 10px;">';
        
        echo '<button type="button" class="button remove-award-row">' . __('Remove', 'membershiping-inventory') . '</button>';
        
        echo '</div>';
    }
    
    /**
     * Get item award row template for JavaScript
     */
    private function get_item_award_row_template($index, $available_items) {
        ob_start();
        
        echo '<div class="award-row" style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        
        echo '<select name="membershiping_item_awards[' . $index . '][item_id]" style="margin-right: 10px;">';
        echo '<option value="">' . __('Select an item...', 'membershiping-inventory') . '</option>';
        
        foreach ($available_items as $item) {
            echo '<option value="' . $item->ID . '">' . esc_html($item->post_title) . '</option>';
        }
        
        echo '</select>';
        
        echo '<input type="number" name="membershiping_item_awards[' . $index . '][quantity]" value="1" min="1" placeholder="' . __('Quantity', 'membershiping-inventory') . '" style="width: 80px; margin-right: 10px;">';
        
        echo '<button type="button" class="button remove-award-row">' . __('Remove', 'membershiping-inventory') . '</button>';
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * Get currency award row template for JavaScript
     */
    private function get_currency_award_row_template($index, $available_currencies) {
        ob_start();
        
        echo '<div class="award-row" style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        
        echo '<select name="membershiping_currency_awards[' . $index . '][currency_id]" style="margin-right: 10px;">';
        echo '<option value="">' . __('Select a currency...', 'membershiping-inventory') . '</option>';
        
        foreach ($available_currencies as $currency) {
            echo '<option value="' . $currency->id . '">' . esc_html($currency->name . ' (' . $currency->symbol . ')') . '</option>';
        }
        
        echo '</select>';
        
        echo '<input type="number" name="membershiping_currency_awards[' . $index . '][amount]" value="100" min="0" step="0.01" placeholder="' . __('Amount', 'membershiping-inventory') . '" style="width: 100px; margin-right: 10px;">';
        
        echo '<button type="button" class="button remove-award-row">' . __('Remove', 'membershiping-inventory') . '</button>';
        
        echo '</div>';
        
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for checking product access
     */
    public function ajax_check_product_access() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!$product_id || !$user_id) {
            wp_send_json_error('Invalid request');
        }
        
        $restriction_type = get_post_meta($product_id, '_membershiping_restriction_type', true);
        $restriction_value = get_post_meta($product_id, '_membershiping_restriction_value', true);
        
        if (empty($restriction_type)) {
            wp_send_json_success(array('has_access' => true));
        }
        
        $has_access = $this->check_user_access($user_id, $restriction_type, $restriction_value);
        
        wp_send_json_success(array(
            'has_access' => $has_access,
            'message' => $has_access ? '' : $this->get_restriction_message($restriction_type, $restriction_value)
        ));
    }
    
    /**
     * Check if user has access based on restriction type
     */
    private function check_user_access($user_id, $restriction_type, $restriction_value) {
        switch ($restriction_type) {
            case 'currency':
                return $this->check_currency_requirement($user_id, $restriction_value);
            case 'item':
                return $this->check_item_requirement($user_id, $restriction_value);
            case 'level':
                return $this->check_level_requirement($user_id, $restriction_value);
            case 'flag':
                return $this->check_flag_requirement($user_id, $restriction_value);
            default:
                return true;
        }
    }
    
    /**
     * Check currency requirement
     */
    private function check_currency_requirement($user_id, $requirement) {
        // Parse requirement: "currency_id:amount"
        $parts = explode(':', $requirement);
        if (count($parts) !== 2) {
            return false;
        }
        
        $currency_id = intval($parts[0]);
        $required_amount = floatval($parts[1]);
        
        // Get user's currency balance
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        
        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT balance FROM $table_name WHERE user_id = %d AND currency_id = %d",
            $user_id,
            $currency_id
        ));
        
        return floatval($balance) >= $required_amount;
    }
    
    /**
     * Check item requirement
     */
    private function check_item_requirement($user_id, $item_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_items';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND item_id = %d",
            $user_id,
            intval($item_id)
        ));
        
        return intval($count) > 0;
    }
    
    /**
     * Check level requirement
     */
    private function check_level_requirement($user_id, $required_level) {
        $user_level = get_user_meta($user_id, 'membershiping_level', true);
        return intval($user_level) >= intval($required_level);
    }
    
    /**
     * Check flag requirement
     */
    private function check_flag_requirement($user_id, $flag_id) {
        if (function_exists('membershiping_user_has_flag')) {
            return membershiping_user_has_flag($user_id, intval($flag_id));
        }
        
        // Fallback check
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_user_flags';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND flag_id = %d",
            $user_id,
            intval($flag_id)
        ));
        
        return intval($count) > 0;
    }
    
    /**
     * Get restriction message
     */
    private function get_restriction_message($restriction_type, $restriction_value) {
        switch ($restriction_type) {
            case 'currency':
                return __('You need sufficient currency to access this product.', 'membershiping-inventory');
            case 'item':
                return __('You need a specific item to access this product.', 'membershiping-inventory');
            case 'level':
                return sprintf(__('You need to reach level %s to access this product.', 'membershiping-inventory'), $restriction_value);
            case 'flag':
                return __('You need a specific achievement to access this product.', 'membershiping-inventory');
            default:
                return __('Access to this product is restricted.', 'membershiping-inventory');
        }
    }
    
    /**
     * Award inventory item to user
     */
    private function award_inventory_item($user_id, $item_data, $quantity = 1) {
        if (!isset($item_data['item_id'])) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_items';
        
        $item_quantity = isset($item_data['quantity']) ? intval($item_data['quantity']) : 1;
        $total_quantity = $item_quantity * $quantity;
        
        for ($i = 0; $i < $total_quantity; $i++) {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'item_id' => intval($item_data['item_id']),
                    'acquired_date' => current_time('mysql'),
                    'source' => 'woocommerce_purchase'
                ),
                array('%d', '%d', '%s', '%s')
            );
        }
        
        error_log("Membershiping Inventory: Awarded $total_quantity of item {$item_data['item_id']} to user $user_id");
    }
    
    /**
     * Award currency to user
     */
    private function award_currency($user_id, $currency_data, $quantity = 1) {
        if (!isset($currency_data['currency_id']) || !isset($currency_data['amount'])) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'membershiping_inventory_user_currencies';
        
        $currency_id = intval($currency_data['currency_id']);
        $amount = floatval($currency_data['amount']) * $quantity;
        
        // Check if user already has this currency
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND currency_id = %d",
            $user_id,
            $currency_id
        ));
        
        if ($existing) {
            // Update existing balance
            $wpdb->update(
                $table_name,
                array('balance' => $existing->balance + $amount),
                array('user_id' => $user_id, 'currency_id' => $currency_id),
                array('%f'),
                array('%d', '%d')
            );
        } else {
            // Create new currency record
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'currency_id' => $currency_id,
                    'balance' => $amount
                ),
                array('%d', '%d', '%f')
            );
        }
        
        error_log("Membershiping Inventory: Awarded $amount of currency $currency_id to user $user_id");
    }
    
    /**
     * Process flag awards for order
     */
    public function process_flag_awards($order_id) {
        // Delegate to the flag awards class
        if (class_exists('Membershiping_Inventory_Flag_Awards')) {
            $flag_awards = new Membershiping_Inventory_Flag_Awards();
            $flag_awards->process_order_completion($order_id);
        }
    }
}
