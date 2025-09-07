<?php
/**
 * Enhanced WooCommerce Integration for Currency Payments and Item-Based Pricing
 * 
 * Extends the main WooCommerce integration to support:
 * - Currency payments (using plugin currencies instead of regular money)
 * - Item-based special pricing (similar to flag/badge pricing)
 * - Product purchase requirements based on owned items
 * 
 * @package Membershiping_Inventory
 * @subpackage Enhanced_Integrations
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Enhanced_WooCommerce_Integration {
    
    private $currencies;
    private $items;
    private $database;
    private $security;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Initialize dependencies - use safe loading
        try {
            $this->currencies = new Membershiping_Inventory_Currencies();
            $this->items = new Membershiping_Inventory_Items();
            $this->database = new Membershiping_Inventory_Database();
            $this->security = new Membershiping_Inventory_Security();
            
            error_log('Membershiping Inventory Enhanced WooCommerce: Successfully initialized dependencies');
        } catch (Exception $e) {
            error_log('Membershiping Inventory Enhanced WooCommerce: Failed to initialize dependencies - ' . $e->getMessage());
            return;
        }
        
        $this->init_hooks();
        error_log('Membershiping Inventory Enhanced WooCommerce: Hooks initialized successfully');
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin meta boxes for currency pricing and item-based pricing - run after core plugin (priority 30)
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_enhanced_pricing_fields'), 30);
        add_action('woocommerce_process_product_meta', array($this, 'save_enhanced_pricing_fields'), 30);
        
        // Frontend price display modifications
        add_filter('woocommerce_get_price_html', array($this, 'modify_price_display_for_currencies'), 25, 2);
        add_action('woocommerce_single_product_summary', array($this, 'display_currency_payment_options'), 30);
        add_action('woocommerce_single_product_summary', array($this, 'display_item_based_pricing'), 35);
        
        // Cart and checkout display
        add_action('woocommerce_cart_item_name', array($this, 'display_currency_prices_in_cart'), 10, 3);
        add_action('woocommerce_review_order_before_payment', array($this, 'display_currency_checkout_options'));
        add_action('woocommerce_checkout_before_order_review', array($this, 'display_currency_checkout_summary'));
        
        // Cart and checkout modifications for currency payments
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_currency_payment'), 15, 3);
        add_action('woocommerce_checkout_create_order', array($this, 'process_currency_payment'), 10, 2);
        
        // Price calculation hooks for item-based pricing
        add_filter('woocommerce_product_get_price', array($this, 'apply_item_based_pricing'), 20, 2);
        add_filter('woocommerce_product_variation_get_price', array($this, 'apply_item_based_pricing'), 20, 2);
        
        // AJAX handlers
        add_action('wp_ajax_membershiping_check_currency_balance', array($this, 'ajax_check_currency_balance'));
        add_action('wp_ajax_nopriv_membershiping_check_currency_balance', array($this, 'ajax_check_currency_balance'));
        add_action('wp_ajax_membershiping_get_item_pricing', array($this, 'ajax_get_item_pricing'));
        add_action('wp_ajax_nopriv_membershiping_get_item_pricing', array($this, 'ajax_get_item_pricing'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }
    
    /**
     * Add enhanced pricing fields to product admin
     */
    public function add_enhanced_pricing_fields() {
        global $post, $wpdb;
        
        error_log('Membershiping Inventory Enhanced: add_enhanced_pricing_fields called for post ID: ' . ($post ? $post->ID : 'unknown'));
        
        // Check if we have currencies available
        if (!$this->currencies) {
            error_log('Membershiping Inventory Enhanced: Currencies class not available');
            echo '<div class="notice notice-error"><p>' . __('Currencies class not initialized', 'membershiping-inventory') . '</p></div>';
            return;
        }
        
        try {
            $available_currencies = $this->currencies->get_all_currencies();
            error_log('Membershiping Inventory Enhanced: Currency query returned: ' . print_r($available_currencies, true));
        } catch (Exception $e) {
            error_log('Membershiping Inventory Enhanced: Currency query failed: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>' . __('Error loading currencies: ', 'membershiping-inventory') . esc_html($e->getMessage()) . '</p></div>';
            return;
        }
        
        if (empty($available_currencies)) {
            error_log('Membershiping Inventory Enhanced: No currencies found');
            echo '<div class="notice notice-warning"><p>' . __('No currencies found. Please create currencies first in the Inventory System. <a href="admin.php?page=membershiping-inventory-currencies">Create Currencies</a>', 'membershiping-inventory') . '</p></div>';
            return;
        }
        
        error_log('Membershiping Inventory Enhanced: Found ' . count($available_currencies) . ' currencies');
        
        echo '<div class="options_group">';
        echo '<h3>' . __('💰 Enhanced Inventory Pricing', 'membershiping-inventory') . '</h3>';
        
        // Currency Payment Section
        echo '<h4>' . __('Currency Payment Options', 'membershiping-inventory') . '</h4>';
        
        woocommerce_wp_checkbox(array(
            'id' => '_membershiping_allow_currency_payment',
            'label' => __('Allow Currency Payment', 'membershiping-inventory'),
            'description' => __('Allow customers to purchase using plugin currencies', 'membershiping-inventory'),
        ));
        
        echo '<div class="membershiping-currency-pricing-section" style="margin-left: 20px;">';
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . __('Currency', 'membershiping-inventory') . '</th><th>' . __('Price', 'membershiping-inventory') . '</th><th>' . __('Actions', 'membershiping-inventory') . '</th></tr></thead>';
        echo '<tbody id="currency-pricing-rows">';
        
        // Get existing currency prices
        $currency_prices = get_post_meta($post->ID, '_membershiping_currency_prices', true);
        $currency_prices = $currency_prices ? json_decode($currency_prices, true) : array();
        
        if (!empty($currency_prices)) {
            foreach ($currency_prices as $index => $price_data) {
                $this->render_currency_price_row($index, $price_data, $available_currencies);
            }
        } else {
            $this->render_currency_price_row(0, array(), $available_currencies);
        }
        
        echo '</tbody></table>';
        echo '<button type="button" class="button" id="add-currency-price">' . __('Add Currency Price', 'membershiping-inventory') . '</button>';
        echo '</div>';
        
        // Item-Based Pricing Section
        echo '<h4>' . __('Item-Based Special Pricing', 'membershiping-inventory') . '</h4>';
        echo '<p class="description">' . __('Set special prices for users who own specific items', 'membershiping-inventory') . '</p>';
        
        woocommerce_wp_checkbox(array(
            'id' => '_membershiping_show_item_pricing_to_all',
            'label' => __('Show Item Pricing to All', 'membershiping-inventory'),
            'description' => __('Display item pricing information to users who don\'t have the required items', 'membershiping-inventory'),
        ));
        
        // Get available items
        $items_table = $this->database->get_table('items');
        $available_items = $wpdb->get_results("SELECT id, name FROM $items_table WHERE status = 'active' ORDER BY name");
        
        echo '<div class="membershiping-item-pricing-section">';
        echo '<table class="widefat">';
        echo '<thead><tr><th>' . __('Item', 'membershiping-inventory') . '</th><th>' . __('Quantity Required', 'membershiping-inventory') . '</th><th>' . __('Special Price', 'membershiping-inventory') . '</th><th>' . __('Actions', 'membershiping-inventory') . '</th></tr></thead>';
        echo '<tbody id="item-pricing-rows">';
        
        // Get existing item-based prices
        $item_prices = get_post_meta($post->ID, '_membershiping_item_specific_prices', true);
        $item_prices = $item_prices ? json_decode($item_prices, true) : array();
        
        if (!empty($item_prices)) {
            foreach ($item_prices as $index => $price_data) {
                $this->render_item_price_row($index, $price_data, $available_items);
            }
        } else {
            $this->render_item_price_row(0, array(), $available_items);
        }
        
        echo '</tbody></table>';
        echo '<button type="button" class="button" id="add-item-price">' . __('Add Item Price', 'membershiping-inventory') . '</button>';
        echo '</div>';
        
        echo '</div>';
        
        // Add JavaScript for dynamic fields
        $this->add_admin_scripts($available_currencies, $available_items);
    }
    
    /**
     * Render currency price row
     */
    private function render_currency_price_row($index, $price_data, $available_currencies) {
        $currency_id = isset($price_data['currency_id']) ? $price_data['currency_id'] : '';
        $price = isset($price_data['price']) ? $price_data['price'] : '';
        
        echo '<tr class="currency-price-row">';
        echo '<td>';
        echo '<select name="membershiping_currency_prices[' . $index . '][currency_id]" class="currency-select">';
        echo '<option value="">' . __('Select Currency', 'membershiping-inventory') . '</option>';
        foreach ($available_currencies as $currency) {
            echo '<option value="' . $currency->id . '"' . selected($currency_id, $currency->id, false) . '>';
            echo esc_html($currency->name . ' (' . $currency->symbol . ')');
            echo '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '<td><input type="number" name="membershiping_currency_prices[' . $index . '][price]" value="' . esc_attr($price) . '" step="0.01" min="0" /></td>';
        echo '<td><button type="button" class="button remove-currency-price">Remove</button></td>';
        echo '</tr>';
    }
    
    /**
     * Render item price row
     */
    private function render_item_price_row($index, $price_data, $available_items) {
        $item_id = isset($price_data['item_id']) ? $price_data['item_id'] : '';
        $quantity = isset($price_data['quantity']) ? $price_data['quantity'] : 1;
        $price = isset($price_data['price']) ? $price_data['price'] : '';
        
        echo '<tr class="item-price-row">';
        echo '<td>';
        echo '<select name="membershiping_item_prices[' . $index . '][item_id]" class="item-select">';
        echo '<option value="">' . __('Select Item', 'membershiping-inventory') . '</option>';
        foreach ($available_items as $item) {
            echo '<option value="' . $item->id . '"' . selected($item_id, $item->id, false) . '>';
            echo esc_html($item->name);
            echo '</option>';
        }
        echo '</select>';
        echo '</td>';
        echo '<td><input type="number" name="membershiping_item_prices[' . $index . '][quantity]" value="' . esc_attr($quantity) . '" min="1" /></td>';
        echo '<td><input type="number" name="membershiping_item_prices[' . $index . '][price]" value="' . esc_attr($price) . '" step="0.01" min="0" /></td>';
        echo '<td><button type="button" class="button remove-item-price">Remove</button></td>';
        echo '</tr>';
    }
    
    /**
     * Save enhanced pricing fields
     */
    public function save_enhanced_pricing_fields($post_id) {
        // Save currency payment setting
        $allow_currency_payment = isset($_POST['_membershiping_allow_currency_payment']) ? 'yes' : 'no';
        update_post_meta($post_id, '_membershiping_allow_currency_payment', $allow_currency_payment);
        
        // Save currency prices
        $currency_prices = array();
        if (isset($_POST['membershiping_currency_prices'])) {
            foreach ($_POST['membershiping_currency_prices'] as $price_data) {
                if (!empty($price_data['currency_id']) && !empty($price_data['price'])) {
                    $currency_prices[] = array(
                        'currency_id' => intval($price_data['currency_id']),
                        'price' => floatval($price_data['price'])
                    );
                }
            }
        }
        update_post_meta($post_id, '_membershiping_currency_prices', json_encode($currency_prices));
        
        // Save item-based pricing settings
        $show_item_pricing_to_all = isset($_POST['_membershiping_show_item_pricing_to_all']) ? 'yes' : 'no';
        update_post_meta($post_id, '_membershiping_show_item_pricing_to_all', $show_item_pricing_to_all);
        
        // Save item-specific prices
        $item_prices = array();
        if (isset($_POST['membershiping_item_prices'])) {
            foreach ($_POST['membershiping_item_prices'] as $price_data) {
                if (!empty($price_data['item_id']) && !empty($price_data['price'])) {
                    $item_prices[] = array(
                        'item_id' => intval($price_data['item_id']),
                        'quantity' => intval($price_data['quantity']) ?: 1,
                        'price' => floatval($price_data['price'])
                    );
                }
            }
        }
        update_post_meta($post_id, '_membershiping_item_specific_prices', json_encode($item_prices));
    }
    
    /**
     * Modify price display for currency options
     */
    public function modify_price_display_for_currencies($price_html, $product) {
        $product_id = $product->get_id();
        $allow_currency_payment = get_post_meta($product_id, '_membershiping_allow_currency_payment', true);
        
        if ($allow_currency_payment !== 'yes') {
            return $price_html;
        }
        
        $currency_prices = get_post_meta($product_id, '_membershiping_currency_prices', true);
        $currency_prices = $currency_prices ? json_decode($currency_prices, true) : array();
        
        if (empty($currency_prices)) {
            return $price_html;
        }
        
        // Add currency pricing info
        $currency_info = '';
        foreach ($currency_prices as $price_data) {
            $currency = $this->currencies->get_currency($price_data['currency_id']);
            if ($currency) {
                $currency_info .= '<br><small class="membershiping-currency-price" style="color: #1976d2; font-weight: 500;">';
                $currency_info .= sprintf(__('Or %s %s%s', 'membershiping-inventory'), 
                    number_format($price_data['price'], 2), 
                    esc_html($currency->symbol), 
                    esc_html($currency->name)
                );
                $currency_info .= '</small>';
            }
        }
        
        return $price_html . $currency_info;
    }
    
    /**
     * Display currency payment options on product page
     */
    public function display_currency_payment_options() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        $allow_currency_payment = get_post_meta($product_id, '_membershiping_allow_currency_payment', true);
        
        if ($allow_currency_payment !== 'yes') {
            return;
        }
        
        $currency_prices = get_post_meta($product_id, '_membershiping_currency_prices', true);
        $currency_prices = $currency_prices ? json_decode($currency_prices, true) : array();
        
        if (empty($currency_prices)) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<div class="membershiping-currency-payment-notice">';
            echo '<p>' . __('Login to see currency payment options', 'membershiping-inventory') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<div class="membershiping-currency-payment-options">';
        echo '<h4>' . __('Payment Options', 'membershiping-inventory') . '</h4>';
        
        foreach ($currency_prices as $price_data) {
            $currency = $this->currencies->get_currency($price_data['currency_id']);
            if (!$currency) {
                continue;
            }
            
            $user_balance = $this->currencies->get_user_balance($user_id, $currency->id);
            $can_afford = $user_balance >= $price_data['price'];
            
            echo '<div class="currency-payment-option" data-currency-id="' . $currency->id . '" data-price="' . $price_data['price'] . '">';
            echo '<label>';
            echo '<input type="radio" name="payment_method" value="currency_' . $currency->id . '"' . ($can_afford ? '' : ' disabled') . ' />';
            echo '<span class="currency-option-label">';
            echo sprintf(__('Pay with %s %s%s', 'membershiping-inventory'), 
                number_format($price_data['price'], 2), 
                esc_html($currency->symbol), 
                esc_html($currency->name)
            );
            echo '</span>';
            echo '<small class="currency-balance" style="display: block; margin-left: 20px;">';
            echo sprintf(__('Your balance: %s %s%s', 'membershiping-inventory'), 
                number_format($user_balance, 2), 
                esc_html($currency->symbol), 
                esc_html($currency->name)
            );
            if (!$can_afford) {
                echo ' <span style="color: #d32f2f;">(' . __('Insufficient funds', 'membershiping-inventory') . ')</span>';
            }
            echo '</small>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display item-based special pricing
     */
    public function display_item_based_pricing() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $product_id = $product->get_id();
        $item_prices = get_post_meta($product_id, '_membershiping_item_specific_prices', true);
        $item_prices = $item_prices ? json_decode($item_prices, true) : array();
        
        if (empty($item_prices)) {
            return;
        }
        
        $show_to_all = get_post_meta($product_id, '_membershiping_show_item_pricing_to_all', true);
        $user_id = get_current_user_id();
        $regular_price = $product->get_regular_price();
        
        echo '<div class="membershiping-item-based-pricing">';
        echo '<h4>' . __('Item-Based Special Pricing', 'membershiping-inventory') . '</h4>';
        
        foreach ($item_prices as $price_data) {
            $item = $this->items->get_item($price_data['item_id']);
            if (!$item) {
                continue;
            }
            
            $required_quantity = $price_data['quantity'];
            $special_price = $price_data['price'];
            $savings = $regular_price - $special_price;
            
            if ($user_id) {
                $user_quantity = $this->items->get_user_item_quantity($user_id, $item->id);
                $has_enough = $user_quantity >= $required_quantity;
                
                if ($has_enough) {
                    // User qualifies for this pricing
                    echo '<div class="item-pricing-qualified" style="color: #2e7d32; font-weight: 500;">';
                    echo '✓ ' . sprintf(__('You have %s %s - Special price: %s (Save %s)', 'membershiping-inventory'),
                        $user_quantity,
                        esc_html($item->name),
                        wc_price($special_price),
                        wc_price($savings)
                    );
                    echo '</div>';
                } elseif ($show_to_all === 'yes') {
                    // Show what they could get
                    echo '<div class="item-pricing-unqualified" style="color: #f57c00;">';
                    echo '✗ ' . sprintf(__('Need %d %s for special price: %s (Save %s)', 'membershiping-inventory'),
                        $required_quantity,
                        esc_html($item->name),
                        wc_price($special_price),
                        wc_price($savings)
                    );
                    echo '<br><small>' . sprintf(__('You have: %d', 'membershiping-inventory'), $user_quantity) . '</small>';
                    echo '</div>';
                }
            } elseif ($show_to_all === 'yes') {
                // Show login prompt
                echo '<div class="item-pricing-login-required" style="color: #666;">';
                echo sprintf(__('Login to see if you qualify for %s special pricing (%s)', 'membershiping-inventory'),
                    esc_html($item->name),
                    wc_price($special_price)
                );
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * Apply item-based pricing to product price
     */
    public function apply_item_based_pricing($price, $product) {
        if (!is_user_logged_in()) {
            return $price;
        }
        
        $product_id = $product->get_id();
        $item_prices = get_post_meta($product_id, '_membershiping_item_specific_prices', true);
        $item_prices = $item_prices ? json_decode($item_prices, true) : array();
        
        if (empty($item_prices)) {
            return $price;
        }
        
        $user_id = get_current_user_id();
        $best_price = floatval($price);
        
        foreach ($item_prices as $price_data) {
            $item_id = $price_data['item_id'];
            $required_quantity = $price_data['quantity'];
            $special_price = floatval($price_data['price']);
            
            $user_quantity = $this->items->get_user_item_quantity($user_id, $item_id);
            
            if ($user_quantity >= $required_quantity && $special_price < $best_price) {
                $best_price = $special_price;
            }
        }
        
        return $best_price;
    }
    
    /**
     * Validate currency payment
     */
    public function validate_currency_payment($passed, $product_id, $quantity) {
        if (!isset($_POST['payment_method']) || strpos($_POST['payment_method'], 'currency_') !== 0) {
            return $passed;
        }
        
        $currency_id = str_replace('currency_', '', $_POST['payment_method']);
        $currency_prices = get_post_meta($product_id, '_membershiping_currency_prices', true);
        $currency_prices = $currency_prices ? json_decode($currency_prices, true) : array();
        
        $required_price = null;
        foreach ($currency_prices as $price_data) {
            if ($price_data['currency_id'] == $currency_id) {
                $required_price = $price_data['price'] * $quantity;
                break;
            }
        }
        
        if ($required_price === null) {
            wc_add_notice(__('Invalid payment method selected', 'membershiping-inventory'), 'error');
            return false;
        }
        
        $user_id = get_current_user_id();
        $user_balance = $this->currencies->get_user_balance($user_id, $currency_id);
        
        if ($user_balance < $required_price) {
            $currency = $this->currencies->get_currency($currency_id);
            wc_add_notice(
                sprintf(__('Insufficient %s balance. Required: %s, Available: %s', 'membershiping-inventory'),
                    $currency->name,
                    number_format($required_price, 2),
                    number_format($user_balance, 2)
                ),
                'error'
            );
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Process currency payment on order creation
     */
    public function process_currency_payment($order, $data) {
        if (!isset($_POST['payment_method']) || strpos($_POST['payment_method'], 'currency_') !== 0) {
            return;
        }
        
        $currency_id = str_replace('currency_', '', $_POST['payment_method']);
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            return;
        }
        
        $total_cost = 0;
        
        // Calculate total currency cost
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $quantity = $item->get_quantity();
            
            $currency_prices = get_post_meta($product_id, '_membershiping_currency_prices', true);
            $currency_prices = $currency_prices ? json_decode($currency_prices, true) : array();
            
            foreach ($currency_prices as $price_data) {
                if ($price_data['currency_id'] == $currency_id) {
                    $total_cost += $price_data['price'] * $quantity;
                    break;
                }
            }
        }
        
        if ($total_cost > 0) {
            // Deduct currency from user balance
            $result = $this->currencies->deduct_user_balance($user_id, $currency_id, $total_cost);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Store payment method info in order meta
            $order->update_meta_data('_membershiping_payment_method', 'currency');
            $order->update_meta_data('_membershiping_currency_id', $currency_id);
            $order->update_meta_data('_membershiping_currency_cost', $total_cost);
            
            // Set order total to 0 since it was paid with currency
            $order->set_total(0);
            $order->save();
            
            // Log the transaction
            $currency = $this->currencies->get_currency($currency_id);
            $this->security->log_security_event('currency_payment_processed', $user_id, array(
                'order_id' => $order->get_id(),
                'currency_id' => $currency_id,
                'currency_name' => $currency->name,
                'amount' => $total_cost
            ));
        }
    }
    
    /**
     * AJAX: Check currency balance
     */
    public function ajax_check_currency_balance() {
        check_ajax_referer('membershiping_nonce', 'nonce');
        
        $currency_id = intval($_POST['currency_id']);
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(__('User not logged in', 'membershiping-inventory'));
        }
        
        $balance = $this->currencies->get_user_balance($user_id, $currency_id);
        $currency = $this->currencies->get_currency($currency_id);
        
        wp_send_json_success(array(
            'balance' => $balance,
            'formatted_balance' => number_format($balance, 2) . ' ' . $currency->symbol . $currency->name
        ));
    }
    
    /**
     * AJAX: Get item pricing info
     */
    public function ajax_get_item_pricing() {
        check_ajax_referer('membershiping_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $user_id = get_current_user_id();
        
        $item_prices = get_post_meta($product_id, '_membershiping_item_specific_prices', true);
        $item_prices = $item_prices ? json_decode($item_prices, true) : array();
        
        $pricing_info = array();
        
        foreach ($item_prices as $price_data) {
            $item = $this->items->get_item($price_data['item_id']);
            if (!$item) {
                continue;
            }
            
            $user_quantity = $user_id ? $this->items->get_user_item_quantity($user_id, $item->id) : 0;
            $qualifies = $user_quantity >= $price_data['quantity'];
            
            $pricing_info[] = array(
                'item_name' => $item->name,
                'required_quantity' => $price_data['quantity'],
                'user_quantity' => $user_quantity,
                'special_price' => $price_data['price'],
                'qualifies' => $qualifies
            );
        }
        
        wp_send_json_success($pricing_info);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (is_product()) {
            wp_enqueue_script(
                'membershiping-enhanced-woocommerce',
                plugin_dir_url(__FILE__) . '../assets/js/enhanced-woocommerce.js',
                array('jquery'),
                '1.0.0',
                true
            );
            
            wp_localize_script('membershiping-enhanced-woocommerce', 'membershiping_enhanced_wc', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('membershiping_nonce'),
                'strings' => array(
                    'insufficient_funds' => __('Insufficient funds', 'membershiping-inventory'),
                    'checking_balance' => __('Checking balance...', 'membershiping-inventory'),
                    'loading' => __('Loading...', 'membershiping-inventory')
                )
            ));
            
            wp_enqueue_style(
                'membershiping-enhanced-woocommerce',
                plugin_dir_url(__FILE__) . '../assets/css/enhanced-woocommerce.css',
                array(),
                '1.0.0'
            );
        }
    }
    
    /**
     * Add admin scripts for dynamic fields
     */
    private function add_admin_scripts($available_currencies, $available_items) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currencyPriceIndex = $('#currency-pricing-rows .currency-price-row').length;
            var itemPriceIndex = $('#item-pricing-rows .item-price-row').length;
            
            // Add currency price row
            $('#add-currency-price').on('click', function(e) {
                e.preventDefault();
                var newRow = $('<tr class="currency-price-row">' +
                    '<td><select name="membershiping_currency_prices[' + currencyPriceIndex + '][currency_id]" class="currency-select">' +
                    '<option value="">Select Currency</option>' +
                    <?php foreach ($available_currencies as $currency): ?>
                    '<option value="<?php echo $currency->id; ?>"><?php echo esc_js($currency->name . ' (' . $currency->symbol . ')'); ?></option>' +
                    <?php endforeach; ?>
                    '</select></td>' +
                    '<td><input type="number" name="membershiping_currency_prices[' + currencyPriceIndex + '][price]" step="0.01" min="0" /></td>' +
                    '<td><button type="button" class="button remove-currency-price">Remove</button></td>' +
                    '</tr>');
                $('#currency-pricing-rows').append(newRow);
                currencyPriceIndex++;
            });
            
            // Add item price row
            $('#add-item-price').on('click', function(e) {
                e.preventDefault();
                var newRow = $('<tr class="item-price-row">' +
                    '<td><select name="membershiping_item_prices[' + itemPriceIndex + '][item_id]" class="item-select">' +
                    '<option value="">Select Item</option>' +
                    <?php foreach ($available_items as $item): ?>
                    '<option value="<?php echo $item->id; ?>"><?php echo esc_js($item->name); ?></option>' +
                    <?php endforeach; ?>
                    '</select></td>' +
                    '<td><input type="number" name="membershiping_item_prices[' + itemPriceIndex + '][quantity]" value="1" min="1" /></td>' +
                    '<td><input type="number" name="membershiping_item_prices[' + itemPriceIndex + '][price]" step="0.01" min="0" /></td>' +
                    '<td><button type="button" class="button remove-item-price">Remove</button></td>' +
                    '</tr>');
                $('#item-pricing-rows').append(newRow);
                itemPriceIndex++;
            });
            
            // Remove rows
            $(document).on('click', '.remove-currency-price, .remove-item-price', function(e) {
                e.preventDefault();
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Display currency prices in cart items
     */
    public function display_currency_prices_in_cart($product_name, $cart_item, $cart_item_key) {
        if (!isset($cart_item['product_id'])) {
            return $product_name;
        }
        
        $product_id = $cart_item['product_id'];
        $allow_currency_payment = get_post_meta($product_id, '_membershiping_allow_currency_payment', true);
        
        if ($allow_currency_payment !== 'yes') {
            return $product_name;
        }
        
        $currency_prices = get_post_meta($product_id, '_membershiping_currency_prices', true);
        $currency_prices = $currency_prices ? json_decode($currency_prices, true) : array();
        
        if (empty($currency_prices)) {
            return $product_name;
        }
        
        $currency_info = '<div class="cart-currency-options" style="margin-top: 5px;">';
        foreach ($currency_prices as $price_data) {
            $currency = $this->currencies->get_currency($price_data['currency_id']);
            if ($currency) {
                $currency_info .= '<small style="display: block; color: #1976d2;">';
                $currency_info .= sprintf(__('Or pay %s %s%s', 'membershiping-inventory'), 
                    number_format($price_data['price'], 2), 
                    esc_html($currency->symbol), 
                    esc_html($currency->name)
                );
                $currency_info .= '</small>';
            }
        }
        $currency_info .= '</div>';
        
        return $product_name . $currency_info;
    }
    
    /**
     * Display currency options before payment methods on checkout
     */
    public function display_currency_checkout_options() {
        if (is_admin()) {
            return;
        }
        
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $has_currency_products = false;
        $currency_products = array();
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $allow_currency_payment = get_post_meta($product_id, '_membershiping_allow_currency_payment', true);
            
            if ($allow_currency_payment === 'yes') {
                $has_currency_products = true;
                $currency_prices = get_post_meta($product_id, '_membershiping_currency_prices', true);
                $currency_prices = $currency_prices ? json_decode($currency_prices, true) : array();
                
                if (!empty($currency_prices)) {
                    $currency_products[$product_id] = array(
                        'name' => $cart_item['data']->get_name(),
                        'quantity' => $cart_item['quantity'],
                        'currency_prices' => $currency_prices
                    );
                }
            }
        }
        
        if (!$has_currency_products) {
            return;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            echo '<div class="checkout-currency-notice">';
            echo '<p>' . __('Login to see currency payment options', 'membershiping-inventory') . '</p>';
            echo '</div>';
            return;
        }
        
        ?>
        <div class="checkout-currency-options">
            <h3><?php _e('Currency Payment Options', 'membershiping-inventory'); ?></h3>
            <p class="description"><?php _e('You can pay for eligible items using your currency balances instead of regular payment.', 'membershiping-inventory'); ?></p>
            
            <?php foreach ($currency_products as $product_id => $product_data): ?>
                <div class="currency-product-option" style="margin: 15px 0; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <h4><?php echo esc_html($product_data['name']); ?> (×<?php echo $product_data['quantity']; ?>)</h4>
                    
                    <?php foreach ($product_data['currency_prices'] as $price_data): ?>
                        <?php
                        $currency = $this->currencies->get_currency($price_data['currency_id']);
                        if (!$currency) continue;
                        
                        $total_cost = $price_data['price'] * $product_data['quantity'];
                        $user_balance = $this->currencies->get_user_balance($user_id, $currency->id);
                        $can_afford = $user_balance >= $total_cost;
                        ?>
                        <div class="currency-checkout-option" style="margin: 5px 0;">
                            <label>
                                <input type="checkbox" 
                                       name="use_currency_payment[<?php echo $product_id; ?>]" 
                                       value="<?php echo $currency->id; ?>"
                                       data-total-cost="<?php echo $total_cost; ?>"
                                       <?php echo $can_afford ? '' : 'disabled'; ?> />
                                <?php echo sprintf(__('Pay %s %s%s (Total: %s)', 'membershiping-inventory'),
                                    number_format($price_data['price'], 2),
                                    esc_html($currency->symbol),
                                    esc_html($currency->name),
                                    number_format($total_cost, 2)
                                ); ?>
                            </label>
                            <small style="display: block; margin-left: 20px; color: <?php echo $can_afford ? '#2e7d32' : '#d32f2f'; ?>;">
                                <?php echo sprintf(__('Your balance: %s %s%s', 'membershiping-inventory'),
                                    number_format($user_balance, 2),
                                    esc_html($currency->symbol),
                                    esc_html($currency->name)
                                ); ?>
                                <?php if (!$can_afford): ?>
                                    - <?php _e('Insufficient funds', 'membershiping-inventory'); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Display currency checkout summary
     */
    public function display_currency_checkout_summary() {
        // This will show a summary of currency payments before order review
        echo '<div id="currency-payment-summary" style="display: none; margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
        echo '<h4>' . __('Currency Payment Summary', 'membershiping-inventory') . '</h4>';
        echo '<div id="currency-summary-content"></div>';
        echo '</div>';
        
        // Add JavaScript to handle currency payment selection
        ?>
        <script>
        jQuery(document).ready(function($) {
            function updateCurrencyPaymentSummary() {
                var summary = $('#currency-payment-summary');
                var content = $('#currency-summary-content');
                var selectedPayments = [];
                
                $('.currency-checkout-option input[type="checkbox"]:checked').each(function() {
                    var label = $(this).parent().text().trim();
                    var cost = $(this).data('total-cost');
                    selectedPayments.push({
                        label: label,
                        cost: parseFloat(cost)
                    });
                });
                
                if (selectedPayments.length > 0) {
                    var html = '<ul>';
                    var totalReduction = 0;
                    
                    selectedPayments.forEach(function(payment) {
                        html += '<li>' + payment.label + '</li>';
                        totalReduction += payment.cost;
                    });
                    
                    html += '</ul>';
                    html += '<p><strong><?php _e("Order total reduction:", "membershiping-inventory"); ?> ' + wc_price_format(totalReduction) + '</strong></p>';
                    
                    content.html(html);
                    summary.show();
                } else {
                    summary.hide();
                }
            }
            
            // Update summary when currency options change
            $(document).on('change', '.currency-checkout-option input[type="checkbox"]', updateCurrencyPaymentSummary);
            
            // Format price for display
            function wc_price_format(amount) {
                return '<?php echo get_woocommerce_currency_symbol(); ?>' + amount.toFixed(2);
            }
        });
        </script>
        <?php
    }
}
