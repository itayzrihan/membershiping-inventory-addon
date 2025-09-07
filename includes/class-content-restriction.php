<?php
/**
 * Content Restriction Enhancement for Membershiping Inventory
 * Restricts website content, pages, posts, and features based on inventory items
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Content_Restriction {
    
    private $wpdb;
    private $database;
    private $security;
    private $items;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = new Membershiping_Inventory_Security();
        $this->items = new Membershiping_Inventory_Items();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Content filtering hooks
        add_filter('the_content', array($this, 'filter_post_content'), 10, 1);
        add_filter('the_excerpt', array($this, 'filter_post_excerpt'), 10, 1);
        add_action('template_redirect', array($this, 'check_page_access'));
        
        // Admin meta boxes
        add_action('add_meta_boxes', array($this, 'add_restriction_meta_boxes'));
        add_action('save_post', array($this, 'save_restriction_meta_box'));
        
        // Shortcode for conditional content
        add_shortcode('membershiping_require_item', array($this, 'require_item_shortcode'));
        add_shortcode('membershiping_if_has_item', array($this, 'if_has_item_shortcode'));
        add_shortcode('membershiping_if_has_currency', array($this, 'if_has_currency_shortcode'));
        add_shortcode('membershiping_restriction_message', array($this, 'restriction_message_shortcode'));
        
        // Widget restrictions
        add_filter('dynamic_sidebar_params', array($this, 'filter_widget_display'));
        
        // Menu item restrictions
        add_filter('wp_nav_menu_objects', array($this, 'filter_menu_items'), 10, 2);
        
        // WooCommerce integration
        add_action('woocommerce_single_product_summary', array($this, 'check_product_access'), 5);
        add_filter('woocommerce_is_purchasable', array($this, 'filter_product_purchasable'), 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_membershiping_check_content_access', array($this, 'ajax_check_content_access'));
        add_action('wp_ajax_nopriv_membershiping_check_content_access', array($this, 'ajax_check_content_access'));
        
        // REST API hooks
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Comment restrictions
        add_filter('comments_open', array($this, 'filter_comments_access'), 10, 2);
        
        // Search filtering
        add_filter('pre_get_posts', array($this, 'filter_search_results'));
        
        // Admin columns
        add_filter('manage_posts_columns', array($this, 'add_restriction_column'));
        add_filter('manage_pages_columns', array($this, 'add_restriction_column'));
        add_action('manage_posts_custom_column', array($this, 'display_restriction_column'), 10, 2);
        add_action('manage_pages_custom_column', array($this, 'display_restriction_column'), 10, 2);
    }
    
    /**
     * Filter post content based on item requirements
     */
    public function filter_post_content($content) {
        global $post;
        
        if (!$post || is_admin()) {
            return $content;
        }
        
        // Check if content has restrictions
        if (!$this->has_content_restrictions($post->ID)) {
            return $content;
        }
        
        // Check user access
        $access_check = $this->check_user_access($post->ID);
        
        if ($access_check['has_access']) {
            return $content;
        }
        
        // Return restricted content message
        return $this->get_restriction_message($post->ID, $access_check);
    }
    
    /**
     * Filter post excerpt
     */
    public function filter_post_excerpt($excerpt) {
        global $post;
        
        if (!$post || is_admin()) {
            return $excerpt;
        }
        
        if (!$this->has_content_restrictions($post->ID)) {
            return $excerpt;
        }
        
        $access_check = $this->check_user_access($post->ID);
        
        if (!$access_check['has_access']) {
            return $this->get_excerpt_restriction_message($post->ID);
        }
        
        return $excerpt;
    }
    
    /**
     * Check page access and redirect if necessary
     */
    public function check_page_access() {
        if (is_admin() || !is_singular()) {
            return;
        }
        
        global $post;
        
        if (!$post || !$this->has_content_restrictions($post->ID)) {
            return;
        }
        
        $access_check = $this->check_user_access($post->ID);
        
        if (!$access_check['has_access']) {
            $restriction_settings = $this->get_restriction_settings($post->ID);
            
            if (!empty($restriction_settings['redirect_url'])) {
                wp_redirect($restriction_settings['redirect_url']);
                exit;
            } elseif ($restriction_settings['block_access'] === 'redirect_login' && !is_user_logged_in()) {
                wp_redirect(wp_login_url(get_permalink($post->ID)));
                exit;
            } elseif ($restriction_settings['block_access'] === 'show_404') {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                get_template_part(404);
                exit;
            }
        }
    }
    
    /**
     * Check if user has access to content
     */
    public function check_user_access($post_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $restrictions = $this->get_restriction_settings($post_id);
        
        if (empty($restrictions) || !$restrictions['enabled']) {
            return array('has_access' => true);
        }
        
        // Admin override
        if ($user_id && user_can($user_id, 'manage_options')) {
            return array('has_access' => true);
        }
        
        $access_result = array(
            'has_access' => false,
            'missing_requirements' => array(),
            'restriction_type' => $restrictions['restriction_type'] ?? 'all'
        );
        
        // Check item requirements
        if (!empty($restrictions['required_items'])) {
            $item_check = $this->check_item_requirements($user_id, $restrictions['required_items'], $restrictions['restriction_type']);
            if (!$item_check['has_access']) {
                $access_result['missing_requirements']['items'] = $item_check['missing_items'];
            } else {
                if ($restrictions['restriction_type'] === 'any') {
                    return array('has_access' => true);
                }
            }
        }
        
        // Check currency requirements
        if (!empty($restrictions['required_currencies'])) {
            $currency_check = $this->check_currency_requirements($user_id, $restrictions['required_currencies'], $restrictions['restriction_type']);
            if (!$currency_check['has_access']) {
                $access_result['missing_requirements']['currencies'] = $currency_check['missing_currencies'];
            } else {
                if ($restrictions['restriction_type'] === 'any') {
                    return array('has_access' => true);
                }
            }
        }
        
        // Check NFT requirements
        if (!empty($restrictions['required_nfts'])) {
            $nft_check = $this->check_nft_requirements($user_id, $restrictions['required_nfts'], $restrictions['restriction_type']);
            if (!$nft_check['has_access']) {
                $access_result['missing_requirements']['nfts'] = $nft_check['missing_nfts'];
            } else {
                if ($restrictions['restriction_type'] === 'any') {
                    return array('has_access' => true);
                }
            }
        }
        
        // Check level requirements
        if (!empty($restrictions['required_level'])) {
            $level_check = $this->check_level_requirement($user_id, $restrictions['required_level']);
            if (!$level_check['has_access']) {
                $access_result['missing_requirements']['level'] = $level_check['current_level'];
                $access_result['required_level'] = $restrictions['required_level'];
            } else {
                if ($restrictions['restriction_type'] === 'any') {
                    return array('has_access' => true);
                }
            }
        }
        
        // Determine final access based on restriction type
        if ($restrictions['restriction_type'] === 'all') {
            $access_result['has_access'] = empty($access_result['missing_requirements']);
        } else { // 'any'
            $access_result['has_access'] = count($access_result['missing_requirements']) < count(array_filter([
                $restrictions['required_items'],
                $restrictions['required_currencies'],
                $restrictions['required_nfts'],
                $restrictions['required_level']
            ]));
        }
        
        return $access_result;
    }
    
    /**
     * Check item requirements
     */
    private function check_item_requirements($user_id, $required_items, $restriction_type) {
        $missing_items = array();
        $has_any = false;
        
        foreach ($required_items as $requirement) {
            $item_id = $requirement['item_id'];
            $quantity = $requirement['quantity'] ?? 1;
            $rarity = $requirement['rarity'] ?? null;
            
            $user_item = $this->items->get_user_item($user_id, $item_id);
            $has_requirement = false;
            
            if ($user_item) {
                if ($rarity) {
                    // Check if user has item with specific rarity or higher
                    $user_nfts = $this->get_user_nfts_for_item($user_id, $item_id);
                    foreach ($user_nfts as $nft) {
                        if ($this->is_rarity_sufficient($nft->rarity, $rarity)) {
                            $has_requirement = true;
                            break;
                        }
                    }
                } else {
                    $has_requirement = $user_item->quantity >= $quantity;
                }
            }
            
            if ($has_requirement) {
                $has_any = true;
                if ($restriction_type === 'any') {
                    return array('has_access' => true);
                }
            } else {
                $missing_items[] = $requirement;
            }
        }
        
        return array(
            'has_access' => $restriction_type === 'all' ? empty($missing_items) : $has_any,
            'missing_items' => $missing_items
        );
    }
    
    /**
     * Check currency requirements
     */
    private function check_currency_requirements($user_id, $required_currencies, $restriction_type) {
        $missing_currencies = array();
        $has_any = false;
        
        foreach ($required_currencies as $requirement) {
            $currency_id = $requirement['currency_id'];
            $amount = $requirement['amount'];
            
            $user_currency = $this->get_user_currency_amount($user_id, $currency_id);
            $has_requirement = $user_currency >= $amount;
            
            if ($has_requirement) {
                $has_any = true;
                if ($restriction_type === 'any') {
                    return array('has_access' => true);
                }
            } else {
                $missing_currencies[] = $requirement;
            }
        }
        
        return array(
            'has_access' => $restriction_type === 'all' ? empty($missing_currencies) : $has_any,
            'missing_currencies' => $missing_currencies
        );
    }
    
    /**
     * Check NFT requirements
     */
    private function check_nft_requirements($user_id, $required_nfts, $restriction_type) {
        $missing_nfts = array();
        $has_any = false;
        
        foreach ($required_nfts as $requirement) {
            $item_id = $requirement['item_id'];
            $rarity = $requirement['rarity'] ?? null;
            $min_count = $requirement['count'] ?? 1;
            
            $user_nfts = $this->get_user_nfts_for_item($user_id, $item_id);
            $matching_count = 0;
            
            foreach ($user_nfts as $nft) {
                if (!$rarity || $this->is_rarity_sufficient($nft->rarity, $rarity)) {
                    $matching_count++;
                }
            }
            
            $has_requirement = $matching_count >= $min_count;
            
            if ($has_requirement) {
                $has_any = true;
                if ($restriction_type === 'any') {
                    return array('has_access' => true);
                }
            } else {
                $missing_nfts[] = $requirement;
            }
        }
        
        return array(
            'has_access' => $restriction_type === 'all' ? empty($missing_nfts) : $has_any,
            'missing_nfts' => $missing_nfts
        );
    }
    
    /**
     * Check level requirement
     */
    private function check_level_requirement($user_id, $required_level) {
        $current_level = get_user_meta($user_id, 'membershiping_level', true) ?: 1;
        
        return array(
            'has_access' => $current_level >= $required_level,
            'current_level' => $current_level
        );
    }
    
    /**
     * Get restriction message
     */
    private function get_restriction_message($post_id, $access_check) {
        $restrictions = $this->get_restriction_settings($post_id);
        $custom_message = $restrictions['restriction_message'] ?? '';
        
        if ($custom_message) {
            return $this->parse_restriction_message($custom_message, $access_check);
        }
        
        // Generate default message
        $message = '<div class="membershiping-restriction-notice">';
        $message .= '<h3>' . __('Access Restricted', 'membershiping-inventory') . '</h3>';
        $message .= '<p>' . __('You need the following items to access this content:', 'membershiping-inventory') . '</p>';
        
        $message .= $this->generate_requirements_list($access_check['missing_requirements']);
        
        if (!is_user_logged_in()) {
            $message .= '<p><a href="' . wp_login_url(get_permalink($post_id)) . '" class="button">' . __('Login to Continue', 'membershiping-inventory') . '</a></p>';
        }
        
        $message .= '</div>';
        
        return $message;
    }
    
    /**
     * Generate requirements list HTML
     */
    private function generate_requirements_list($missing_requirements) {
        $html = '<ul class="restriction-requirements">';
        
        if (!empty($missing_requirements['items'])) {
            foreach ($missing_requirements['items'] as $item_req) {
                $item = $this->items->get_item($item_req['item_id']);
                if ($item) {
                    $html .= '<li>';
                    $html .= sprintf(__('%s (Quantity: %d)', 'membershiping-inventory'), 
                        esc_html($item->name), 
                        $item_req['quantity'] ?? 1
                    );
                    if ($item_req['rarity'] ?? null) {
                        $html .= ' - ' . sprintf(__('Rarity: %s or higher', 'membershiping-inventory'), esc_html($item_req['rarity']));
                    }
                    $html .= '</li>';
                }
            }
        }
        
        if (!empty($missing_requirements['currencies'])) {
            foreach ($missing_requirements['currencies'] as $currency_req) {
                $currency = $this->get_currency($currency_req['currency_id']);
                if ($currency) {
                    $html .= '<li>';
                    $html .= sprintf(__('%s: %s', 'membershiping-inventory'), 
                        esc_html($currency->name), 
                        number_format($currency_req['amount'])
                    );
                    $html .= '</li>';
                }
            }
        }
        
        if (!empty($missing_requirements['level'])) {
            $html .= '<li>';
            $html .= sprintf(__('Level %d (Current: %d)', 'membershiping-inventory'), 
                $access_check['required_level'] ?? 0,
                $missing_requirements['level']
            );
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        
        return $html;
    }
    
    /**
     * Parse restriction message with placeholders
     */
    private function parse_restriction_message($message, $access_check) {
        // Replace placeholders
        $message = str_replace('{requirements_list}', $this->generate_requirements_list($access_check['missing_requirements']), $message);
        $message = str_replace('{login_link}', wp_login_url(), $message);
        
        return $message;
    }
    
    /**
     * Shortcode: Require item
     */
    public function require_item_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'item_id' => 0,
            'quantity' => 1,
            'rarity' => '',
            'message' => ''
        ), $atts);
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return $atts['message'] ?: __('Please log in to view this content.', 'membershiping-inventory');
        }
        
        $requirements = array(array(
            'item_id' => intval($atts['item_id']),
            'quantity' => intval($atts['quantity']),
            'rarity' => $atts['rarity'] ?: null
        ));
        
        $check = $this->check_item_requirements($user_id, $requirements, 'all');
        
        if ($check['has_access']) {
            return do_shortcode($content);
        }
        
        return $atts['message'] ?: __('You do not have the required items to view this content.', 'membershiping-inventory');
    }
    
    /**
     * Shortcode: If has item
     */
    public function if_has_item_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'item_id' => 0,
            'quantity' => 1,
            'rarity' => ''
        ), $atts);
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return '';
        }
        
        $requirements = array(array(
            'item_id' => intval($atts['item_id']),
            'quantity' => intval($atts['quantity']),
            'rarity' => $atts['rarity'] ?: null
        ));
        
        $check = $this->check_item_requirements($user_id, $requirements, 'all');
        
        return $check['has_access'] ? do_shortcode($content) : '';
    }
    
    /**
     * Shortcode: If has currency
     */
    public function if_has_currency_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'currency_id' => 0,
            'amount' => 1
        ), $atts);
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return '';
        }
        
        $user_currency = $this->get_user_currency_amount($user_id, intval($atts['currency_id']));
        
        return $user_currency >= floatval($atts['amount']) ? do_shortcode($content) : '';
    }
    
    /**
     * Shortcode: Restriction message
     */
    public function restriction_message_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'post_id' => 0
        ), $atts);
        
        $post_id = $atts['post_id'] ?: get_the_ID();
        
        if (!$post_id) {
            return '';
        }
        
        $access_check = $this->check_user_access($post_id);
        
        if ($access_check['has_access']) {
            return '';
        }
        
        return $this->get_restriction_message($post_id, $access_check);
    }
    
    /**
     * Filter widget display
     */
    public function filter_widget_display($params) {
        global $wp_registered_widgets;
        
        $widget_id = $params[0]['widget_id'];
        
        if (!isset($wp_registered_widgets[$widget_id])) {
            return $params;
        }
        
        // Check widget restrictions (would be stored in widget options)
        $widget_restrictions = get_option('widget_membershiping_restrictions_' . $widget_id, array());
        
        if (!empty($widget_restrictions) && $widget_restrictions['enabled']) {
            $access_check = $this->check_widget_access($widget_restrictions);
            
            if (!$access_check['has_access']) {
                // Hide widget by returning empty params
                return array();
            }
        }
        
        return $params;
    }
    
    /**
     * Filter menu items
     */
    public function filter_menu_items($items, $args) {
        if (is_admin()) {
            return $items;
        }
        
        foreach ($items as $key => $item) {
            $restrictions = get_post_meta($item->object_id, '_membershiping_menu_restrictions', true);
            
            if (!empty($restrictions) && $restrictions['enabled']) {
                $access_check = $this->check_user_access($item->object_id);
                
                if (!$access_check['has_access']) {
                    unset($items[$key]);
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Check product access (WooCommerce)
     */
    public function check_product_access() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $restrictions = get_post_meta($product->get_id(), '_membershiping_product_restrictions', true);
        
        if (!empty($restrictions) && $restrictions['enabled']) {
            $access_check = $this->check_user_access($product->get_id());
            
            if (!$access_check['has_access']) {
                echo '<div class="membershiping-product-restriction">';
                echo $this->get_restriction_message($product->get_id(), $access_check);
                echo '</div>';
            }
        }
    }
    
    /**
     * Filter product purchasable status
     */
    public function filter_product_purchasable($purchasable, $product) {
        $restrictions = get_post_meta($product->get_id(), '_membershiping_product_restrictions', true);
        
        if (!empty($restrictions) && $restrictions['enabled'] && $restrictions['block_purchase']) {
            $access_check = $this->check_user_access($product->get_id());
            
            if (!$access_check['has_access']) {
                return false;
            }
        }
        
        return $purchasable;
    }
    
    /**
     * Add restriction meta boxes
     */
    public function add_restriction_meta_boxes() {
        $post_types = array('post', 'page', 'product');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'membershiping_content_restrictions',
                __('Content Restrictions', 'membershiping-inventory'),
                array($this, 'render_restriction_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    /**
     * Render restriction meta box
     */
    public function render_restriction_meta_box($post) {
        wp_nonce_field('membershiping_restrictions_meta', 'membershiping_restrictions_nonce');
        
        $restrictions = $this->get_restriction_settings($post->ID);
        
        ?>
        <div class="membershiping-restrictions-meta">
            <p>
                <label>
                    <input type="checkbox" name="membershiping_restrictions[enabled]" value="1" 
                           <?php checked($restrictions['enabled'] ?? false); ?>>
                    <?php _e('Enable content restrictions', 'membershiping-inventory'); ?>
                </label>
            </p>
            
            <div class="restriction-settings" style="<?php echo ($restrictions['enabled'] ?? false) ? '' : 'display:none;'; ?>">
                <p>
                    <label><?php _e('Restriction Type:', 'membershiping-inventory'); ?></label>
                    <select name="membershiping_restrictions[restriction_type]">
                        <option value="all" <?php selected($restrictions['restriction_type'] ?? 'all', 'all'); ?>>
                            <?php _e('Must have ALL requirements', 'membershiping-inventory'); ?>
                        </option>
                        <option value="any" <?php selected($restrictions['restriction_type'] ?? 'all', 'any'); ?>>
                            <?php _e('Must have ANY requirement', 'membershiping-inventory'); ?>
                        </option>
                    </select>
                </p>
                
                <p>
                    <label><?php _e('When access denied:', 'membershiping-inventory'); ?></label>
                    <select name="membershiping_restrictions[block_access]">
                        <option value="show_message" <?php selected($restrictions['block_access'] ?? 'show_message', 'show_message'); ?>>
                            <?php _e('Show restriction message', 'membershiping-inventory'); ?>
                        </option>
                        <option value="redirect_login" <?php selected($restrictions['block_access'] ?? 'show_message', 'redirect_login'); ?>>
                            <?php _e('Redirect to login', 'membershiping-inventory'); ?>
                        </option>
                        <option value="show_404" <?php selected($restrictions['block_access'] ?? 'show_message', 'show_404'); ?>>
                            <?php _e('Show 404 error', 'membershiping-inventory'); ?>
                        </option>
                        <option value="redirect_custom" <?php selected($restrictions['block_access'] ?? 'show_message', 'redirect_custom'); ?>>
                            <?php _e('Redirect to custom URL', 'membershiping-inventory'); ?>
                        </option>
                    </select>
                </p>
                
                <p class="redirect-url-field" style="<?php echo ($restrictions['block_access'] ?? '') === 'redirect_custom' ? '' : 'display:none;'; ?>">
                    <label><?php _e('Redirect URL:', 'membershiping-inventory'); ?></label>
                    <input type="url" name="membershiping_restrictions[redirect_url]" 
                           value="<?php echo esc_attr($restrictions['redirect_url'] ?? ''); ?>" class="widefat">
                </p>
                
                <p>
                    <label><?php _e('Custom restriction message:', 'membershiping-inventory'); ?></label>
                    <textarea name="membershiping_restrictions[restriction_message]" 
                              class="widefat" rows="3"><?php echo esc_textarea($restrictions['restriction_message'] ?? ''); ?></textarea>
                    <small><?php _e('Use {requirements_list} to show required items, {login_link} for login URL', 'membershiping-inventory'); ?></small>
                </p>
                
                <hr>
                
                <p><strong><?php _e('Requirements Configuration', 'membershiping-inventory'); ?></strong></p>
                <p><em><?php _e('Configure specific requirements in the full editor below.', 'membershiping-inventory'); ?></em></p>
                
                <button type="button" class="button configure-restrictions" data-post-id="<?php echo $post->ID; ?>">
                    <?php _e('Configure Detailed Requirements', 'membershiping-inventory'); ?>
                </button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('input[name="membershiping_restrictions[enabled]"]').change(function() {
                if ($(this).is(':checked')) {
                    $('.restriction-settings').show();
                } else {
                    $('.restriction-settings').hide();
                }
            });
            
            $('select[name="membershiping_restrictions[block_access]"]').change(function() {
                if ($(this).val() === 'redirect_custom') {
                    $('.redirect-url-field').show();
                } else {
                    $('.redirect-url-field').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save restriction meta box
     */
    public function save_restriction_meta_box($post_id) {
        if (!isset($_POST['membershiping_restrictions_nonce']) || 
            !wp_verify_nonce($_POST['membershiping_restrictions_nonce'], 'membershiping_restrictions_meta')) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $restrictions = $_POST['membershiping_restrictions'] ?? array();
        
        // Sanitize data
        $sanitized_restrictions = array(
            'enabled' => !empty($restrictions['enabled']),
            'restriction_type' => sanitize_text_field($restrictions['restriction_type'] ?? 'all'),
            'block_access' => sanitize_text_field($restrictions['block_access'] ?? 'show_message'),
            'redirect_url' => esc_url_raw($restrictions['redirect_url'] ?? ''),
            'restriction_message' => wp_kses_post($restrictions['restriction_message'] ?? ''),
            'required_items' => $restrictions['required_items'] ?? array(),
            'required_currencies' => $restrictions['required_currencies'] ?? array(),
            'required_nfts' => $restrictions['required_nfts'] ?? array(),
            'required_level' => intval($restrictions['required_level'] ?? 0)
        );
        
        update_post_meta($post_id, '_membershiping_content_restrictions', $sanitized_restrictions);
    }
    
    /**
     * Get restriction settings for a post
     */
    private function get_restriction_settings($post_id) {
        return get_post_meta($post_id, '_membershiping_content_restrictions', true) ?: array();
    }
    
    /**
     * Check if content has restrictions
     */
    private function has_content_restrictions($post_id) {
        $restrictions = $this->get_restriction_settings($post_id);
        return !empty($restrictions) && ($restrictions['enabled'] ?? false);
    }
    
    /**
     * Utility functions
     */
    
    private function get_user_currency_amount($user_id, $currency_id) {
        if (!$user_id || !$currency_id) {
            return 0;
        }
        
        return $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT amount FROM " . $this->database->get_table_name('user_currencies') . " 
             WHERE user_id = %d AND currency_id = %d",
            $user_id, $currency_id
        )) ?: 0;
    }
    
    private function get_user_nfts_for_item($user_id, $item_id) {
        if (!$user_id || !$item_id) {
            return array();
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM " . $this->database->get_table_name('nfts') . " 
             WHERE owner_id = %d AND item_id = %d",
            $user_id, $item_id
        ));
    }
    
    private function is_rarity_sufficient($user_rarity, $required_rarity) {
        $rarity_order = array('common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic');
        $user_index = array_search($user_rarity, $rarity_order);
        $required_index = array_search($required_rarity, $rarity_order);
        
        return $user_index !== false && $required_index !== false && $user_index >= $required_index;
    }
    
    private function get_currency($currency_id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM " . $this->database->get_table_name('currencies') . " WHERE id = %d",
            $currency_id
        ));
    }
    
    private function get_excerpt_restriction_message($post_id) {
        return __('This content is restricted. You need specific items to access it.', 'membershiping-inventory');
    }
    
    /**
     * AJAX check content access
     */
    public function ajax_check_content_access() {
        $post_id = intval($_POST['post_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        $access_check = $this->check_user_access($post_id, $user_id);
        
        wp_send_json_success($access_check);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('membershiping-inventory/v1', '/content-access/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_check_content_access'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                )
            )
        ));
    }
    
    /**
     * REST API endpoint for checking content access
     */
    public function rest_check_content_access($request) {
        $post_id = intval($request['id']);
        $user_id = get_current_user_id();
        
        $access_check = $this->check_user_access($post_id, $user_id);
        
        return rest_ensure_response($access_check);
    }
    
    /**
     * Filter comments access
     */
    public function filter_comments_access($open, $post_id) {
        if (!$this->has_content_restrictions($post_id)) {
            return $open;
        }
        
        $restrictions = $this->get_restriction_settings($post_id);
        
        if ($restrictions['restrict_comments'] ?? false) {
            $access_check = $this->check_user_access($post_id);
            return $access_check['has_access'] ? $open : false;
        }
        
        return $open;
    }
    
    /**
     * Filter search results
     */
    public function filter_search_results($query) {
        if (is_admin() || !$query->is_search() || !$query->is_main_query()) {
            return;
        }
        
        // Get posts with restrictions
        $restricted_posts = $this->wpdb->get_col("
            SELECT post_id FROM {$this->wpdb->postmeta} 
            WHERE meta_key = '_membershiping_content_restrictions' 
            AND meta_value LIKE '%\"enabled\";b:1%'
        ");
        
        if (!empty($restricted_posts)) {
            $accessible_posts = array();
            $user_id = get_current_user_id();
            
            foreach ($restricted_posts as $post_id) {
                $access_check = $this->check_user_access($post_id, $user_id);
                if ($access_check['has_access']) {
                    $accessible_posts[] = $post_id;
                }
            }
            
            // Exclude inaccessible posts from search
            $inaccessible_posts = array_diff($restricted_posts, $accessible_posts);
            
            if (!empty($inaccessible_posts)) {
                $query->set('post__not_in', $inaccessible_posts);
            }
        }
    }
    
    /**
     * Add restriction column to admin post lists
     */
    public function add_restriction_column($columns) {
        $columns['membershiping_restrictions'] = __('Restrictions', 'membershiping-inventory');
        return $columns;
    }
    
    /**
     * Display restriction column content
     */
    public function display_restriction_column($column, $post_id) {
        if ($column === 'membershiping_restrictions') {
            if ($this->has_content_restrictions($post_id)) {
                echo '<span class="dashicons dashicons-lock" title="' . __('Has restrictions', 'membershiping-inventory') . '"></span>';
            } else {
                echo '<span class="dashicons dashicons-unlock" title="' . __('No restrictions', 'membershiping-inventory') . '"></span>';
            }
        }
    }
}
