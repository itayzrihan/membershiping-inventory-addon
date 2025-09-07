<?php
/**
 * Frontend management class for Membershiping Inventory System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Frontend {
    
    private $wpdb;
    private $database;
    private $security;
    private $items;
    private $currencies;
    private $nfts;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = new Membershiping_Inventory_Security();
        $this->items = new Membershiping_Inventory_Items();
        $this->currencies = new Membershiping_Inventory_Currencies();
        $this->nfts = new Membershiping_Inventory_NFTs();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize frontend hooks
     */
    private function init_hooks() {
        // Shortcodes
        add_action('init', array($this, 'register_shortcodes'));
        
        // Scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_membershiping_inventory_use_item', array($this, 'ajax_use_item'));
        add_action('wp_ajax_membershiping_inventory_get_inventory', array($this, 'ajax_get_inventory'));
        add_action('wp_ajax_membershiping_inventory_get_item_details', array($this, 'ajax_get_item_details'));
        add_action('wp_ajax_membershiping_inventory_create_trade', array($this, 'ajax_create_trade'));
        add_action('wp_ajax_membershiping_inventory_accept_trade', array($this, 'ajax_accept_trade'));
        add_action('wp_ajax_membershiping_inventory_decline_trade', array($this, 'ajax_decline_trade'));
        add_action('wp_ajax_membershiping_inventory_cancel_trade', array($this, 'ajax_cancel_trade'));
        add_action('wp_ajax_membershiping_inventory_get_trades', array($this, 'ajax_get_trades'));
        add_action('wp_ajax_membershiping_inventory_search_users', array($this, 'ajax_search_users'));
        
        // My Account integration
        add_action('init', array($this, 'add_my_account_endpoints'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_my_account_menu_items'));
        add_action('woocommerce_account_inventory_endpoint', array($this, 'my_account_inventory_content'));
        add_action('woocommerce_account_nfts_endpoint', array($this, 'my_account_nfts_content'));
        add_action('woocommerce_account_currencies_endpoint', array($this, 'my_account_currencies_content'));
        
        // REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        // Main shortcodes
        add_shortcode('membershiping_inventory', array($this, 'inventory_shortcode'));
        add_shortcode('membershiping_currencies', array($this, 'currencies_shortcode'));
        add_shortcode('membershiping_nfts', array($this, 'nfts_shortcode'));
        add_shortcode('membershiping_trading', array($this, 'trading_shortcode'));
        
        // Alternative names for validator compatibility
        add_shortcode('membershiping_user_inventory', array($this, 'inventory_shortcode'));
        add_shortcode('membershiping_trading_interface', array($this, 'trading_shortcode'));
        add_shortcode('membershiping_nft_gallery', array($this, 'nfts_shortcode'));
        
        // Missing documented shortcodes
        add_shortcode('membershiping_inventory_currencies', array($this, 'currencies_shortcode'));
        
        // Conditional content shortcodes
        add_shortcode('membershiping_require_item', array($this, 'require_item_shortcode'));
        add_shortcode('membershiping_if_has_currency', array($this, 'if_has_currency_shortcode'));
        add_shortcode('membershiping_restriction_message', array($this, 'restriction_message_shortcode'));
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_user_logged_in()) {
            return;
        }
        
        wp_enqueue_style(
            'membershiping-inventory-frontend',
            MEMBERSHIPING_INVENTORY_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            MEMBERSHIPING_INVENTORY_VERSION
        );
        
        wp_enqueue_script(
            'membershiping-inventory-frontend',
            MEMBERSHIPING_INVENTORY_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            MEMBERSHIPING_INVENTORY_VERSION,
            true
        );
        
        wp_localize_script('membershiping-inventory-frontend', 'membershiping_inventory_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('membershiping_inventory_nonce'),
            'strings' => array(
                'confirm_use' => __('Are you sure you want to use this item?', 'membershiping-inventory'),
                'confirm_trade' => __('Are you sure you want to send this trade request?', 'membershiping-inventory'),
                'loading' => __('Loading...', 'membershiping-inventory'),
                'error' => __('An error occurred. Please try again.', 'membershiping-inventory'),
                'success' => __('Action completed successfully!', 'membershiping-inventory')
            )
        ));
    }
    
    /**
     * Inventory shortcode
     */
    public function inventory_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your inventory.', 'membershiping-inventory') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'type' => 'all', // all, consumable, equipment, collectible, etc.
            'columns' => 4,
            'show_stats' => 'yes',
            'show_use_button' => 'yes',
            'show_trade_button' => 'yes'
        ), $atts);
        
        $user_id = get_current_user_id();
        $item_type = $atts['type'] !== 'all' ? $atts['type'] : null;
        
        // Get user items
        $user_items = $this->items->get_user_items($user_id, $item_type);
        $user_nfts = $this->nfts->get_user_nfts($user_id);
        
        ob_start();
        ?>
        <div class="membershiping-inventory-container" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <div class="inventory-header">
                <h3><?php _e('Your Inventory', 'membershiping-inventory'); ?></h3>
                <div class="inventory-filters">
                    <select id="inventory-filter-type" class="inventory-filter">
                        <option value="all"><?php _e('All Items', 'membershiping-inventory'); ?></option>
                        <option value="consumable"><?php _e('Consumables', 'membershiping-inventory'); ?></option>
                        <option value="equipment"><?php _e('Equipment', 'membershiping-inventory'); ?></option>
                        <option value="collectible"><?php _e('Collectibles', 'membershiping-inventory'); ?></option>
                        <option value="gift_box"><?php _e('Gift Boxes', 'membershiping-inventory'); ?></option>
                        <option value="material"><?php _e('Materials', 'membershiping-inventory'); ?></option>
                    </select>
                    <select id="inventory-filter-rarity" class="inventory-filter">
                        <option value="all"><?php _e('All Rarities', 'membershiping-inventory'); ?></option>
                        <option value="common"><?php _e('Common', 'membershiping-inventory'); ?></option>
                        <option value="uncommon"><?php _e('Uncommon', 'membershiping-inventory'); ?></option>
                        <option value="rare"><?php _e('Rare', 'membershiping-inventory'); ?></option>
                        <option value="epic"><?php _e('Epic', 'membershiping-inventory'); ?></option>
                        <option value="legendary"><?php _e('Legendary', 'membershiping-inventory'); ?></option>
                        <option value="mythic"><?php _e('Mythic', 'membershiping-inventory'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="inventory-grid" id="inventory-grid">
                <?php $this->render_inventory_items($user_items, $atts); ?>
                <?php $this->render_inventory_nfts($user_nfts, $atts); ?>
            </div>
            
            <?php if (empty($user_items) && empty($user_nfts)): ?>
                <div class="inventory-empty">
                    <p><?php _e('Your inventory is empty. Purchase items from the shop or earn them through activities!', 'membershiping-inventory'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Item Details Modal -->
        <div id="item-details-modal" class="inventory-modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <div id="item-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render inventory stackable items
     */
    private function render_inventory_items($items, $atts) {
        foreach ($items as $item) {
            $this->render_inventory_item($item, $atts, 'stackable');
        }
    }
    
    /**
     * Render inventory NFTs
     */
    private function render_inventory_nfts($nfts, $atts) {
        foreach ($nfts as $nft) {
            $this->render_inventory_item($nft, $atts, 'nft');
        }
    }
    
    /**
     * Render individual inventory item
     */
    private function render_inventory_item($item, $atts, $type = 'stackable') {
        $rarity_class = 'rarity-' . ($item->rarity ?? 'common');
        $is_nft = $type === 'nft';
        $item_id = $is_nft ? $item->item_id : $item->item_id;
        $quantity = $is_nft ? 1 : $item->quantity;
        $item_name = $is_nft ? $item->item_name : $item->name;
        $description = $is_nft ? $item->item_description : $item->description;
        
        ?>
        <div class="inventory-item <?php echo esc_attr($rarity_class); ?>" 
             data-item-id="<?php echo esc_attr($item_id); ?>" 
             data-type="<?php echo esc_attr($item->item_type ?? ''); ?>"
             data-rarity="<?php echo esc_attr($item->rarity ?? 'common'); ?>"
             data-item-type="<?php echo esc_attr($type); ?>"
             data-nft-id="<?php echo $is_nft ? esc_attr($item->id) : ''; ?>">
            
            <div class="item-image">
                <?php if ($item->base_image ?? $item->custom_image ?? false): ?>
                    <img src="<?php echo esc_url($item->custom_image ?? $item->base_image); ?>" 
                         alt="<?php echo esc_attr($item_name); ?>" 
                         loading="lazy">
                <?php else: ?>
                    <div class="item-placeholder">
                        <span class="item-type-icon"><?php echo $this->get_item_type_icon($item->item_type ?? 'collectible'); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($quantity > 1): ?>
                    <span class="item-quantity"><?php echo esc_html($quantity); ?></span>
                <?php endif; ?>
                
                <?php if ($is_nft && $item->upgrade_level > 0): ?>
                    <span class="upgrade-level">+<?php echo esc_html($item->upgrade_level); ?></span>
                <?php endif; ?>
                
                <div class="rarity-border"></div>
            </div>
            
            <div class="item-details">
                <h4 class="item-name"><?php echo esc_html($item_name); ?></h4>
                <p class="item-rarity"><?php echo esc_html(ucfirst($item->rarity ?? 'common')); ?></p>
                
                <?php if ($atts['show_stats'] === 'yes' && !empty($item->stats)): ?>
                    <div class="item-stats">
                        <?php $this->render_item_stats($item->stats); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($is_nft && !empty($item->custom_stats)): ?>
                    <div class="item-stats">
                        <?php $this->render_item_stats($item->custom_stats); ?>
                    </div>
                <?php endif; ?>
                
                <div class="item-actions">
                    <button class="button item-details-btn" 
                            data-item-id="<?php echo esc_attr($item_id); ?>"
                            data-item-type="<?php echo esc_attr($type); ?>"
                            data-nft-id="<?php echo $is_nft ? esc_attr($item->id) : ''; ?>">
                        <?php _e('Details', 'membershiping-inventory'); ?>
                    </button>
                    
                    <?php if ($atts['show_use_button'] === 'yes' && ($item->is_consumable ?? false)): ?>
                        <button class="button button-primary use-item-btn" 
                                data-item-id="<?php echo esc_attr($item_id); ?>"
                                data-item-type="<?php echo esc_attr($type); ?>"
                                data-nft-id="<?php echo $is_nft ? esc_attr($item->id) : ''; ?>">
                            <?php _e('Use', 'membershiping-inventory'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_trade_button'] === 'yes' && ($item->is_tradeable ?? false)): ?>
                        <button class="button trade-item-btn" 
                                data-item-id="<?php echo esc_attr($item_id); ?>"
                                data-item-type="<?php echo esc_attr($type); ?>"
                                data-nft-id="<?php echo $is_nft ? esc_attr($item->id) : ''; ?>">
                            <?php _e('Trade', 'membershiping-inventory'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render item stats
     */
    private function render_item_stats($stats_json) {
        $stats = is_string($stats_json) ? json_decode($stats_json, true) : $stats_json;
        
        if (!$stats || !is_array($stats)) {
            return;
        }
        
        echo '<div class="stats-grid">';
        foreach ($stats as $stat => $value) {
            if ($stat === 'special_abilities') {
                continue; // Handle separately
            }
            
            echo '<div class="stat-item">';
            echo '<span class="stat-name">' . esc_html(ucfirst($stat)) . ':</span>';
            echo '<span class="stat-value">' . esc_html($value) . '</span>';
            echo '</div>';
        }
        echo '</div>';
        
        // Handle special abilities
        if (isset($stats['special_abilities']) && is_array($stats['special_abilities'])) {
            echo '<div class="special-abilities">';
            echo '<strong>' . __('Special:', 'membershiping-inventory') . '</strong> ';
            $abilities = array();
            foreach ($stats['special_abilities'] as $ability => $enabled) {
                if ($enabled) {
                    $abilities[] = ucfirst(str_replace('_', ' ', $ability));
                }
            }
            echo esc_html(implode(', ', $abilities));
            echo '</div>';
        }
    }
    
    /**
     * Get item type icon
     */
    private function get_item_type_icon($item_type) {
        $icons = array(
            'consumable' => '🍯',
            'equipment' => '⚔️',
            'gift_box' => '🎁',
            'material' => '🔨',
            'collectible' => '💎'
        );
        
        return $icons[$item_type] ?? '📦';
    }
    
    /**
     * Currencies shortcode
     */
    public function currencies_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your currencies.', 'membershiping-inventory') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'show_transactions' => 'no',
            'transaction_limit' => 10
        ), $atts);
        
        $user_id = get_current_user_id();
        $balances = $this->currencies->get_user_balances($user_id);
        
        ob_start();
        ?>
        <div class="membershiping-currencies-container">
            <h3><?php _e('Your Currencies', 'membershiping-inventory'); ?></h3>
            
            <?php if (!empty($balances)): ?>
                <div class="currencies-grid">
                    <?php foreach ($balances as $balance): ?>
                        <div class="currency-item">
                            <div class="currency-header">
                                <span class="currency-symbol"><?php echo esc_html($balance->symbol); ?></span>
                                <h4 class="currency-name"><?php echo esc_html($balance->name); ?></h4>
                            </div>
                            <div class="currency-balance">
                                <span class="balance-amount">
                                    <?php echo esc_html(number_format($balance->balance, $balance->decimal_places)); ?>
                                </span>
                            </div>
                            <div class="currency-stats">
                                <div class="stat">
                                    <span class="label"><?php _e('Earned:', 'membershiping-inventory'); ?></span>
                                    <span class="value"><?php echo esc_html(number_format($balance->total_earned, $balance->decimal_places)); ?></span>
                                </div>
                                <div class="stat">
                                    <span class="label"><?php _e('Spent:', 'membershiping-inventory'); ?></span>
                                    <span class="value"><?php echo esc_html(number_format($balance->total_spent, $balance->decimal_places)); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($atts['show_transactions'] === 'yes'): ?>
                    <div class="currency-transactions">
                        <h4><?php _e('Recent Transactions', 'membershiping-inventory'); ?></h4>
                        <?php $this->render_currency_transactions($user_id, $atts['transaction_limit']); ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <p><?php _e('You have no currencies yet. Start earning by participating in activities!', 'membershiping-inventory'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * NFTs shortcode
     */
    public function nfts_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your NFTs.', 'membershiping-inventory') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'columns' => 3,
            'show_certificate' => 'yes'
        ), $atts);
        
        $user_id = get_current_user_id();
        $nfts = $this->nfts->get_user_nfts($user_id);
        
        ob_start();
        ?>
        <div class="membershiping-nfts-container" data-columns="<?php echo esc_attr($atts['columns']); ?>">
            <h3><?php _e('Your NFT Collection', 'membershiping-inventory'); ?></h3>
            
            <?php if (!empty($nfts)): ?>
                <div class="nfts-grid">
                    <?php foreach ($nfts as $nft): ?>
                        <div class="nft-item rarity-<?php echo esc_attr($nft->rarity); ?>">
                            <div class="nft-image">
                                <?php if ($nft->custom_image ?? $nft->base_image ?? false): ?>
                                    <img src="<?php echo esc_url($nft->custom_image ?? $nft->base_image); ?>" 
                                         alt="<?php echo esc_attr($nft->item_name); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="nft-placeholder">
                                        <span class="nft-icon">🎨</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($nft->upgrade_level > 0): ?>
                                    <span class="upgrade-level">+<?php echo esc_html($nft->upgrade_level); ?></span>
                                <?php endif; ?>
                                
                                <div class="rarity-glow"></div>
                            </div>
                            
                            <div class="nft-details">
                                <h4 class="nft-name"><?php echo esc_html($nft->item_name); ?></h4>
                                <p class="nft-token"><?php echo esc_html($nft->nft_token); ?></p>
                                <p class="nft-rarity"><?php echo esc_html(ucfirst($nft->rarity)); ?></p>
                                
                                <?php if (!empty($nft->custom_stats)): ?>
                                    <div class="nft-stats">
                                        <?php $this->render_item_stats($nft->custom_stats); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="nft-actions">
                                    <?php if ($atts['show_certificate'] === 'yes'): ?>
                                        <button class="button nft-certificate-btn" 
                                                data-nft-id="<?php echo esc_attr($nft->id); ?>">
                                            <?php _e('Certificate', 'membershiping-inventory'); ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($nft->is_tradeable): ?>
                                        <button class="button trade-nft-btn" 
                                                data-nft-id="<?php echo esc_attr($nft->id); ?>">
                                            <?php _e('Trade', 'membershiping-inventory'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php _e('You have no NFTs yet. Purchase non-stackable items to start your collection!', 'membershiping-inventory'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Trading shortcode
     */
    public function trading_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access trading.', 'membershiping-inventory') . '</p>';
        }
        
        $atts = shortcode_atts(array(
            'view' => 'dashboard', // dashboard, create, history
            'show_search' => 'yes'
        ), $atts);
        
        $user_id = get_current_user_id();
        
        ob_start();
        ?>
        <div class="membershiping-trading-container">
            <div class="trading-header">
                <h3><?php _e('Trading Center', 'membershiping-inventory'); ?></h3>
                <div class="trading-nav">
                    <button class="trading-nav-btn active" data-view="dashboard"><?php _e('Dashboard', 'membershiping-inventory'); ?></button>
                    <button class="trading-nav-btn" data-view="create"><?php _e('Create Trade', 'membershiping-inventory'); ?></button>
                    <button class="trading-nav-btn" data-view="history"><?php _e('Trade History', 'membershiping-inventory'); ?></button>
                </div>
            </div>
            
            <div class="trading-content">
                <!-- Dashboard View -->
                <div class="trading-view" id="trading-dashboard" style="display: block;">
                    <?php $this->render_trading_dashboard($user_id); ?>
                </div>
                
                <!-- Create Trade View -->
                <div class="trading-view" id="trading-create" style="display: none;">
                    <?php $this->render_trading_form($user_id); ?>
                </div>
                
                <!-- History View -->
                <div class="trading-view" id="trading-history" style="display: none;">
                    <?php $this->render_trading_history($user_id); ?>
                </div>
            </div>
        </div>
        
        <!-- Trade Details Modal -->
        <div id="trade-details-modal" class="inventory-modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <div id="trade-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render trading dashboard
     */
    private function render_trading_dashboard($user_id) {
        $trading = new Membershiping_Inventory_Trading();
        
        // Get pending trades
        $incoming_trades = $trading->get_user_trades($user_id, 'pending');
        $incoming_trades = array_filter($incoming_trades, function($trade) use ($user_id) {
            return $trade->recipient_id == $user_id;
        });
        
        $outgoing_trades = $trading->get_user_trades($user_id, 'pending');
        $outgoing_trades = array_filter($outgoing_trades, function($trade) use ($user_id) {
            return $trade->requester_id == $user_id;
        });
        
        // Get trade statistics
        $stats = $trading->get_trade_statistics($user_id);
        
        ?>
        <div class="trading-dashboard">
            <!-- Statistics -->
            <div class="trading-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($stats->total_trades ?? 0); ?></div>
                    <div class="stat-label"><?php _e('Total Trades', 'membershiping-inventory'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html($stats->completed_trades ?? 0); ?></div>
                    <div class="stat-label"><?php _e('Completed', 'membershiping-inventory'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html(count($incoming_trades)); ?></div>
                    <div class="stat-label"><?php _e('Pending Incoming', 'membershiping-inventory'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo esc_html(count($outgoing_trades)); ?></div>
                    <div class="stat-label"><?php _e('Pending Outgoing', 'membershiping-inventory'); ?></div>
                </div>
            </div>
            
            <!-- Incoming Trades -->
            <div class="trades-section">
                <h4><?php _e('Incoming Trade Requests', 'membershiping-inventory'); ?></h4>
                <?php if (!empty($incoming_trades)): ?>
                    <div class="trades-list">
                        <?php foreach ($incoming_trades as $trade): ?>
                            <?php $this->render_trade_card($trade, 'incoming'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-trades"><?php _e('No incoming trade requests.', 'membershiping-inventory'); ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Outgoing Trades -->
            <div class="trades-section">
                <h4><?php _e('Your Trade Requests', 'membershiping-inventory'); ?></h4>
                <?php if (!empty($outgoing_trades)): ?>
                    <div class="trades-list">
                        <?php foreach ($outgoing_trades as $trade): ?>
                            <?php $this->render_trade_card($trade, 'outgoing'); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-trades"><?php _e('No outgoing trade requests.', 'membershiping-inventory'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render trading form
     */
    private function render_trading_form($user_id) {
        ?>
        <div class="trading-form">
            <h4><?php _e('Create New Trade', 'membershiping-inventory'); ?></h4>
            
            <form id="create-trade-form">
                <!-- Recipient Selection -->
                <div class="form-section">
                    <label for="trade-recipient"><?php _e('Trade with:', 'membershiping-inventory'); ?></label>
                    <div class="recipient-search">
                        <input type="text" id="trade-recipient-search" placeholder="<?php _e('Search for user...', 'membershiping-inventory'); ?>">
                        <div id="user-search-results" class="search-results"></div>
                        <input type="hidden" id="trade-recipient-id" name="recipient_id">
                        <div id="selected-recipient" class="selected-user" style="display: none;"></div>
                    </div>
                </div>
                
                <!-- Your Offer -->
                <div class="form-section">
                    <h5><?php _e('Your Offer', 'membershiping-inventory'); ?></h5>
                    <div class="trade-items-selection">
                        <div class="items-tabs">
                            <button type="button" class="tab-btn active" data-tab="offer-items"><?php _e('Items', 'membershiping-inventory'); ?></button>
                            <button type="button" class="tab-btn" data-tab="offer-nfts"><?php _e('NFTs', 'membershiping-inventory'); ?></button>
                            <button type="button" class="tab-btn" data-tab="offer-currencies"><?php _e('Currencies', 'membershiping-inventory'); ?></button>
                        </div>
                        
                        <div class="tab-content">
                            <div class="tab-panel active" id="offer-items">
                                <?php $this->render_user_items_selection($user_id, 'offer'); ?>
                            </div>
                            <div class="tab-panel" id="offer-nfts">
                                <?php $this->render_user_nfts_selection($user_id, 'offer'); ?>
                            </div>
                            <div class="tab-panel" id="offer-currencies">
                                <?php $this->render_user_currencies_selection($user_id, 'offer'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="selected-items" id="offer-selected">
                        <h6><?php _e('Selected Items to Offer:', 'membershiping-inventory'); ?></h6>
                        <div class="selected-items-list"></div>
                    </div>
                </div>
                
                <!-- What You Want -->
                <div class="form-section">
                    <h5><?php _e('What You Want', 'membershiping-inventory'); ?></h5>
                    <div class="request-items">
                        <button type="button" class="button add-request-btn"><?php _e('Add Item Request', 'membershiping-inventory'); ?></button>
                        <div class="request-items-list"></div>
                    </div>
                </div>
                
                <!-- Message -->
                <div class="form-section">
                    <label for="trade-message"><?php _e('Message (optional):', 'membershiping-inventory'); ?></label>
                    <textarea id="trade-message" name="message" placeholder="<?php _e('Add a message to your trade request...', 'membershiping-inventory'); ?>"></textarea>
                </div>
                
                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" class="button button-primary"><?php _e('Send Trade Request', 'membershiping-inventory'); ?></button>
                    <button type="button" class="button button-secondary" id="clear-trade-form"><?php _e('Clear Form', 'membershiping-inventory'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render trading history
     */
    private function render_trading_history($user_id) {
        ?>
        <div class="trading-history">
            <div class="history-filters">
                <select id="history-status-filter">
                    <option value=""><?php _e('All Trades', 'membershiping-inventory'); ?></option>
                    <option value="completed"><?php _e('Completed', 'membershiping-inventory'); ?></option>
                    <option value="declined"><?php _e('Declined', 'membershiping-inventory'); ?></option>
                    <option value="cancelled"><?php _e('Cancelled', 'membershiping-inventory'); ?></option>
                    <option value="expired"><?php _e('Expired', 'membershiping-inventory'); ?></option>
                </select>
            </div>
            
            <div id="trade-history-list" class="trades-list">
                <!-- Content loaded via AJAX -->
            </div>
            
            <div class="load-more-trades">
                <button class="button" id="load-more-trades-btn"><?php _e('Load More', 'membershiping-inventory'); ?></button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render trade card
     */
    private function render_trade_card($trade, $type = 'incoming') {
        $is_incoming = $type === 'incoming';
        $other_user_id = $is_incoming ? $trade->requester_id : $trade->recipient_id;
        $other_user_name = $is_incoming ? $trade->requester_name : $trade->recipient_name;
        
        ?>
        <div class="trade-card" data-trade-id="<?php echo esc_attr($trade->id); ?>">
            <div class="trade-header">
                <div class="trade-user">
                    <strong><?php echo esc_html($other_user_name); ?></strong>
                    <span class="trade-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($trade->created_at))); ?></span>
                </div>
                <div class="trade-status status-<?php echo esc_attr($trade->status); ?>">
                    <?php echo esc_html(ucfirst($trade->status)); ?>
                </div>
            </div>
            
            <div class="trade-summary">
                <div class="trade-offer">
                    <h6><?php echo $is_incoming ? __('They Offer:', 'membershiping-inventory') : __('You Offer:', 'membershiping-inventory'); ?></h6>
                    <?php $this->render_trade_items_summary($is_incoming ? $trade->requester_items : $trade->recipient_items); ?>
                </div>
                
                <div class="trade-request">
                    <h6><?php echo $is_incoming ? __('You Give:', 'membershiping-inventory') : __('You Want:', 'membershiping-inventory'); ?></h6>
                    <?php $this->render_trade_items_summary($is_incoming ? $trade->recipient_items : $trade->requester_items); ?>
                </div>
            </div>
            
            <?php if ($trade->message): ?>
                <div class="trade-message">
                    <strong><?php _e('Message:', 'membershiping-inventory'); ?></strong>
                    <p><?php echo esc_html($trade->message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="trade-actions">
                <button class="button view-trade-btn" data-trade-id="<?php echo esc_attr($trade->id); ?>">
                    <?php _e('View Details', 'membershiping-inventory'); ?>
                </button>
                
                <?php if ($is_incoming && $trade->status === 'pending'): ?>
                    <button class="button button-primary accept-trade-btn" data-trade-id="<?php echo esc_attr($trade->id); ?>">
                        <?php _e('Accept', 'membershiping-inventory'); ?>
                    </button>
                    <button class="button decline-trade-btn" data-trade-id="<?php echo esc_attr($trade->id); ?>">
                        <?php _e('Decline', 'membershiping-inventory'); ?>
                    </button>
                <?php elseif (!$is_incoming && $trade->status === 'pending'): ?>
                    <button class="button cancel-trade-btn" data-trade-id="<?php echo esc_attr($trade->id); ?>">
                        <?php _e('Cancel', 'membershiping-inventory'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render trade items summary
     */
    private function render_trade_items_summary($items) {
        if (empty($items)) {
            echo '<p class="no-items">' . __('No items', 'membershiping-inventory') . '</p>';
            return;
        }
        
        echo '<ul class="trade-items-summary">';
        foreach ($items as $item) {
            if ($item['type'] === 'nft') {
                echo '<li class="nft-item">';
                echo '<span class="item-name">' . esc_html($item['name']) . '</span>';
                echo '<span class="item-type">NFT</span>';
                echo '</li>';
            } else {
                echo '<li class="regular-item">';
                echo '<span class="item-name">' . esc_html($item['name']) . '</span>';
                echo '<span class="item-quantity">x' . esc_html($item['quantity']) . '</span>';
                echo '</li>';
            }
        }
        echo '</ul>';
    }
    
    /**
     * Render user items selection
     */
    private function render_user_items_selection($user_id, $prefix) {
        $user_items = $this->items->get_user_items($user_id);
        $tradeable_items = array_filter($user_items, function($item) {
            return $item->is_tradeable;
        });
        
        if (empty($tradeable_items)) {
            echo '<p>' . __('You have no tradeable items.', 'membershiping-inventory') . '</p>';
            return;
        }
        
        echo '<div class="items-selection-grid">';
        foreach ($tradeable_items as $item) {
            ?>
            <div class="selectable-item" data-item-id="<?php echo esc_attr($item->item_id); ?>" data-type="item">
                <div class="item-image">
                    <?php if ($item->base_image): ?>
                        <img src="<?php echo esc_url($item->base_image); ?>" alt="<?php echo esc_attr($item->name); ?>">
                    <?php else: ?>
                        <div class="item-placeholder"><?php echo $this->get_item_type_icon($item->item_type); ?></div>
                    <?php endif; ?>
                </div>
                <div class="item-info">
                    <h6><?php echo esc_html($item->name); ?></h6>
                    <p class="item-quantity">Qty: <?php echo esc_html($item->quantity); ?></p>
                </div>
                <div class="item-select">
                    <input type="number" min="1" max="<?php echo esc_attr($item->quantity); ?>" value="1" class="quantity-input">
                    <button type="button" class="button select-item-btn"><?php _e('Select', 'membershiping-inventory'); ?></button>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
    
    /**
     * Render user NFTs selection
     */
    private function render_user_nfts_selection($user_id, $prefix) {
        $user_nfts = $this->nfts->get_user_nfts($user_id);
        $tradeable_nfts = array_filter($user_nfts, function($nft) {
            return $nft->is_tradeable && !$nft->is_reserved;
        });
        
        if (empty($tradeable_nfts)) {
            echo '<p>' . __('You have no tradeable NFTs.', 'membershiping-inventory') . '</p>';
            return;
        }
        
        echo '<div class="items-selection-grid">';
        foreach ($tradeable_nfts as $nft) {
            ?>
            <div class="selectable-item" data-nft-id="<?php echo esc_attr($nft->id); ?>" data-type="nft">
                <div class="item-image">
                    <?php if ($nft->custom_image ?? $nft->base_image): ?>
                        <img src="<?php echo esc_url($nft->custom_image ?? $nft->base_image); ?>" alt="<?php echo esc_attr($nft->item_name); ?>">
                    <?php else: ?>
                        <div class="item-placeholder">🎨</div>
                    <?php endif; ?>
                </div>
                <div class="item-info">
                    <h6><?php echo esc_html($nft->item_name); ?></h6>
                    <p class="nft-token"><?php echo esc_html($nft->nft_token); ?></p>
                    <p class="nft-rarity"><?php echo esc_html(ucfirst($nft->rarity)); ?></p>
                </div>
                <div class="item-select">
                    <button type="button" class="button select-item-btn"><?php _e('Select', 'membershiping-inventory'); ?></button>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
    
    /**
     * Render user currencies selection
     */
    private function render_user_currencies_selection($user_id, $prefix) {
        $user_balances = $this->currencies->get_user_balances($user_id);
        
        if (empty($user_balances)) {
            echo '<p>' . __('You have no currencies to trade.', 'membershiping-inventory') . '</p>';
            return;
        }
        
        echo '<div class="currencies-selection">';
        foreach ($user_balances as $balance) {
            if ($balance->balance <= 0) continue;
            ?>
            <div class="selectable-currency" data-currency-id="<?php echo esc_attr($balance->currency_id); ?>">
                <div class="currency-info">
                    <h6><?php echo esc_html($balance->name); ?> (<?php echo esc_html($balance->symbol); ?>)</h6>
                    <p class="currency-balance"><?php _e('Available:', 'membershiping-inventory'); ?> <?php echo esc_html(number_format($balance->balance, $balance->decimal_places)); ?></p>
                </div>
                <div class="currency-select">
                    <input type="number" min="0.01" max="<?php echo esc_attr($balance->balance); ?>" step="0.01" value="1" class="amount-input">
                    <button type="button" class="button select-currency-btn"><?php _e('Select', 'membershiping-inventory'); ?></button>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
    
    /**
     * Render currency transactions
     */
    private function render_currency_transactions($user_id, $limit = 10) {
        $transactions = $this->currencies->get_user_transactions($user_id, null, $limit);
        
        if (empty($transactions)) {
            echo '<p>' . __('No transactions yet.', 'membershiping-inventory') . '</p>';
            return;
        }
        
        echo '<div class="transactions-list">';
        foreach ($transactions as $transaction) {
            $amount_class = $transaction->amount >= 0 ? 'positive' : 'negative';
            $amount_prefix = $transaction->amount >= 0 ? '+' : '';
            
            echo '<div class="transaction-item">';
            echo '<div class="transaction-details">';
            echo '<span class="transaction-type">' . esc_html(ucfirst($transaction->transaction_type)) . '</span>';
            if ($transaction->description) {
                echo '<span class="transaction-description">' . esc_html($transaction->description) . '</span>';
            }
            echo '</div>';
            echo '<div class="transaction-amount ' . esc_attr($amount_class) . '">';
            echo '<span class="amount">' . esc_html($amount_prefix . number_format($transaction->amount, 2)) . '</span>';
            echo '<span class="symbol">' . esc_html($transaction->currency_symbol) . '</span>';
            echo '</div>';
            echo '<div class="transaction-date">';
            echo '<span>' . esc_html(date_i18n(get_option('date_format'), strtotime($transaction->created_at))) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    /**
     * AJAX handler for using items
     */
    public function ajax_use_item() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $item_id = intval($_POST['item_id'] ?? 0);
        $item_type = sanitize_text_field($_POST['item_type'] ?? 'stackable');
        $nft_id = intval($_POST['nft_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        $user_id = get_current_user_id();
        
        if ($item_type === 'nft') {
            wp_send_json_error('NFTs cannot be consumed');
            return;
        }
        
        $result = $this->items->use_item($user_id, $item_id, $quantity);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Item used successfully!', 'membershiping-inventory'),
                'effects' => $result
            ));
        }
    }
    
    /**
     * AJAX handler for getting inventory
     */
    public function ajax_get_inventory() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $user_id = get_current_user_id();
        $type_filter = sanitize_text_field($_POST['type'] ?? '');
        $rarity_filter = sanitize_text_field($_POST['rarity'] ?? '');
        
        $user_items = $this->items->get_user_items($user_id, $type_filter ?: null);
        $user_nfts = $this->nfts->get_user_nfts($user_id, null, $rarity_filter ?: null);
        
        // Filter by rarity if specified
        if ($rarity_filter && $rarity_filter !== 'all') {
            $user_items = array_filter($user_items, function($item) use ($rarity_filter) {
                return $item->rarity === $rarity_filter;
            });
        }
        
        ob_start();
        $atts = array('columns' => 4, 'show_stats' => 'yes', 'show_use_button' => 'yes', 'show_trade_button' => 'yes');
        $this->render_inventory_items($user_items, $atts);
        $this->render_inventory_nfts($user_nfts, $atts);
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX handler for getting item details
     */
    public function ajax_get_item_details() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 401);
        }
        
        $item_id = intval($_POST['item_id'] ?? 0);
        $item_type = sanitize_text_field($_POST['item_type'] ?? 'stackable');
        $nft_id = intval($_POST['nft_id'] ?? 0);
        
        if ($item_type === 'nft' && $nft_id) {
            $nft = $this->nfts->get_nft($nft_id);
            $item = $this->items->get_item($item_id);
            
            if (!$nft || !$item) {
                wp_send_json_error('NFT not found');
                return;
            }
            
            $details = array(
                'type' => 'nft',
                'name' => $item->name,
                'description' => $item->description,
                'rarity' => $nft->rarity,
                'token' => $nft->nft_token,
                'upgrade_level' => $nft->upgrade_level,
                'stats' => $nft->custom_stats ? json_decode($nft->custom_stats, true) : null,
                'metadata' => $nft->metadata ? json_decode($nft->metadata, true) : null,
                'is_tradeable' => $nft->is_tradeable,
                'created_at' => $nft->created_at
            );
        } else {
            $user_id = get_current_user_id();
            $user_items = $this->items->get_user_items($user_id);
            $user_item = null;
            
            foreach ($user_items as $ui) {
                if ($ui->item_id == $item_id) {
                    $user_item = $ui;
                    break;
                }
            }
            
            if (!$user_item) {
                wp_send_json_error('Item not found');
                return;
            }
            
            $details = array(
                'type' => 'stackable',
                'name' => $user_item->name,
                'description' => $user_item->description,
                'rarity' => $user_item->rarity,
                'quantity' => $user_item->quantity,
                'item_type' => $user_item->item_type,
                'stats' => $user_item->stats ? json_decode($user_item->stats, true) : null,
                'is_tradeable' => $user_item->is_tradeable,
                'is_consumable' => $user_item->is_consumable,
                'acquired_at' => $user_item->acquired_at,
                'last_used_at' => $user_item->last_used_at
            );
        }
        
        wp_send_json_success($details);
    }
    
    /**
     * Add My Account endpoints
     */
    public function add_my_account_endpoints() {
        add_rewrite_endpoint('inventory', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('nfts', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('currencies', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add My Account menu items
     */
    public function add_my_account_menu_items($items) {
        $new_items = array();
        
        // Insert after orders
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            
            if ($key === 'orders') {
                $new_items['inventory'] = __('Inventory', 'membershiping-inventory');
                $new_items['nfts'] = __('NFT Collection', 'membershiping-inventory');
                $new_items['currencies'] = __('Currencies', 'membershiping-inventory');
            }
        }
        
        return $new_items;
    }
    
    /**
     * My Account inventory content
     */
    public function my_account_inventory_content() {
        echo do_shortcode('[membershiping_inventory columns="3"]');
    }
    
    /**
     * My Account NFTs content
     */
    public function my_account_nfts_content() {
        echo do_shortcode('[membershiping_nfts columns="3"]');
    }
    
    /**
     * My Account currencies content
     */
    public function my_account_currencies_content() {
        echo do_shortcode('[membershiping_currencies show_transactions="yes"]');
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('membershiping-inventory/v1', '/inventory/(?P<user_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_get_inventory'),
            'permission_callback' => array($this, 'rest_permissions_check'),
            'args' => array(
                'user_id' => array(
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ),
            ),
        ));
        
        register_rest_route('membershiping-inventory/v1', '/nft/(?P<token>[A-Z0-9\-]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'rest_verify_nft'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * REST API permissions check
     */
    public function rest_permissions_check($request) {
        return current_user_can('manage_options') || get_current_user_id() == $request['user_id'];
    }
    
    /**
     * REST API get inventory
     */
    public function rest_get_inventory($request) {
        $user_id = $request['user_id'];
        
        $items = $this->items->get_user_items($user_id);
        $nfts = $this->nfts->get_user_nfts($user_id);
        $currencies = $this->currencies->get_user_balances($user_id);
        
        return rest_ensure_response(array(
            'user_id' => $user_id,
            'items' => $items,
            'nfts' => $nfts,
            'currencies' => $currencies,
            'timestamp' => time()
        ));
    }
    
    /**
     * REST API verify NFT
     */
    public function rest_verify_nft($request) {
        $token = $request['token'];
        
        $verification = $this->nfts->verify_nft_authenticity($token);
        
        return rest_ensure_response($verification);
    }
    
    /**
     * Trading AJAX Handlers
     */
    
    public function ajax_create_trade() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $trading = new Membershiping_Inventory_Trading();
        $user_id = get_current_user_id();
        $recipient_id = intval($_POST['recipient_id'] ?? 0);
        
        $trade_data = array(
            'requester_items' => json_decode(stripslashes($_POST['requester_items'] ?? '[]'), true),
            'recipient_items' => json_decode(stripslashes($_POST['recipient_items'] ?? '[]'), true),
            'requester_currencies' => json_decode(stripslashes($_POST['requester_currencies'] ?? '[]'), true),
            'recipient_currencies' => json_decode(stripslashes($_POST['recipient_currencies'] ?? '[]'), true),
            'message' => sanitize_textarea_field($_POST['message'] ?? '')
        );
        
        $result = $trading->create_trade($user_id, $recipient_id, $trade_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'trade_id' => $result,
                'message' => __('Trade request sent successfully!', 'membershiping-inventory')
            ));
        }
    }
    
    public function ajax_accept_trade() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $trading = new Membershiping_Inventory_Trading();
        $trade_id = intval($_POST['trade_id'] ?? 0);
        $user_id = get_current_user_id();
        
        $result = $trading->accept_trade($trade_id, $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Trade completed successfully!', 'membershiping-inventory')
            ));
        }
    }
    
    public function ajax_decline_trade() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $trading = new Membershiping_Inventory_Trading();
        $trade_id = intval($_POST['trade_id'] ?? 0);
        $user_id = get_current_user_id();
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        $result = $trading->decline_trade($trade_id, $user_id, $reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Trade declined.', 'membershiping-inventory')
            ));
        }
    }
    
    public function ajax_cancel_trade() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $trading = new Membershiping_Inventory_Trading();
        $trade_id = intval($_POST['trade_id'] ?? 0);
        $user_id = get_current_user_id();
        
        $result = $trading->cancel_trade($trade_id, $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'message' => __('Trade cancelled.', 'membershiping-inventory')
            ));
        }
    }
    
    public function ajax_get_trades() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $trading = new Membershiping_Inventory_Trading();
        $user_id = get_current_user_id();
        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);
        
        $trades = $trading->get_user_trades($user_id, $status ?: null, $limit, $offset);
        
        wp_send_json_success($trades);
    }
    
    public function ajax_search_users() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        if (strlen($search) < 3) {
            wp_send_json_error('Search term must be at least 3 characters');
        }
        
        $users = get_users(array(
            'search' => '*' . $search . '*',
            'search_columns' => array('user_login', 'user_nicename', 'display_name'),
            'number' => 10,
            'exclude' => array(get_current_user_id()),
            'fields' => array('ID', 'display_name', 'user_login')
        ));
        
        wp_send_json_success($users);
    }
    
    /**
     * Require item shortcode - conditional content display based on item ownership
     */
    public function require_item_shortcode($atts, $content = null) {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'item_id' => 0,
            'quantity' => 1,
            'deny_message' => __('You need a specific item to view this content.', 'membershiping-inventory'),
            'show_deny_message' => 'yes'
        ), $atts);
        
        $user_id = get_current_user_id();
        $item_id = intval($atts['item_id']);
        $required_quantity = intval($atts['quantity']);
        
        if (!$item_id) {
            return '<!-- membershiping_require_item: No item_id specified -->';
        }
        
        // Check if user has the required item
        $user_items = $this->items->get_user_items($user_id);
        $has_item = false;
        $user_quantity = 0;
        
        foreach ($user_items as $item) {
            if ($item->item_id == $item_id) {
                $user_quantity = $item->quantity;
                $has_item = $user_quantity >= $required_quantity;
                break;
            }
        }
        
        if ($has_item) {
            return do_shortcode($content);
        } else {
            if ($atts['show_deny_message'] === 'yes') {
                return '<div class="membershiping-restriction-message">' . esc_html($atts['deny_message']) . '</div>';
            }
            return '';
        }
    }
    
    /**
     * If has currency shortcode - conditional content display based on currency ownership
     */
    public function if_has_currency_shortcode($atts, $content = null) {
        if (!is_user_logged_in()) {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'currency_id' => 0,
            'amount' => 1,
            'deny_message' => __('You need sufficient currency to view this content.', 'membershiping-inventory'),
            'show_deny_message' => 'yes'
        ), $atts);
        
        $user_id = get_current_user_id();
        $currency_id = intval($atts['currency_id']);
        $required_amount = floatval($atts['amount']);
        
        if (!$currency_id) {
            return '<!-- membershiping_if_has_currency: No currency_id specified -->';
        }
        
        // Check if user has the required currency amount
        $user_balance = $this->currencies->get_user_currency($user_id, $currency_id);
        $has_currency = $user_balance >= $required_amount;
        
        if ($has_currency) {
            return do_shortcode($content);
        } else {
            if ($atts['show_deny_message'] === 'yes') {
                return '<div class="membershiping-restriction-message">' . esc_html($atts['deny_message']) . '</div>';
            }
            return '';
        }
    }
    
    /**
     * Restriction message shortcode - displays restriction info for a specific post
     */
    public function restriction_message_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => 0,
            'type' => 'item', // item, currency, level
            'show_requirements' => 'yes'
        ), $atts);
        
        $post_id = intval($atts['post_id']) ?: get_the_ID();
        
        if (!$post_id) {
            return '<!-- membershiping_restriction_message: No post ID found -->';
        }
        
        // Get restriction settings for this post
        $restrictions = get_post_meta($post_id, '_membershiping_restrictions', true);
        
        if (empty($restrictions)) {
            return '<!-- membershiping_restriction_message: No restrictions found -->';
        }
        
        ob_start();
        ?>
        <div class="membershiping-restriction-info">
            <h4><?php _e('Access Requirements', 'membershiping-inventory'); ?></h4>
            
            <?php if ($atts['show_requirements'] === 'yes'): ?>
                <div class="restriction-requirements">
                    <?php
                    if (isset($restrictions['required_items']) && !empty($restrictions['required_items'])) {
                        echo '<div class="required-items">';
                        echo '<h5>' . __('Required Items:', 'membershiping-inventory') . '</h5>';
                        echo '<ul>';
                        foreach ($restrictions['required_items'] as $item_requirement) {
                            $item_id = $item_requirement['item_id'];
                            $quantity = $item_requirement['quantity'];
                            $item_name = $this->items->get_item_name($item_id);
                            echo '<li>' . esc_html($item_name) . ' (x' . esc_html($quantity) . ')</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                    
                    if (isset($restrictions['required_currencies']) && !empty($restrictions['required_currencies'])) {
                        echo '<div class="required-currencies">';
                        echo '<h5>' . __('Required Currencies:', 'membershiping-inventory') . '</h5>';
                        echo '<ul>';
                        foreach ($restrictions['required_currencies'] as $currency_requirement) {
                            $currency_id = $currency_requirement['currency_id'];
                            $amount = $currency_requirement['amount'];
                            $currency_name = $this->currencies->get_currency_name($currency_id);
                            echo '<li>' . esc_html($currency_name) . ' (' . esc_html(number_format($amount, 2)) . ')</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <p class="restriction-message">
                <?php echo esc_html($restrictions['deny_message'] ?? __('You do not meet the requirements to access this content.', 'membershiping-inventory')); ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}
