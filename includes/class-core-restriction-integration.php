<?php
/**
 * Core Plugin Restriction Integration for Membershiping Inventory
 * Integrates inventory items into the core plugin's existing restriction system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Core_Restriction_Integration {
    
    private $wpdb;
    private $database;
    private $items;
    private $currencies;
    private $nfts;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->items = new Membershiping_Inventory_Items();
        $this->currencies = new Membershiping_Inventory_Currencies();
        $this->nfts = new Membershiping_Inventory_NFTs();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks to integrate with core plugin
     */
    private function init_hooks() {
        // Hook into core plugin's restriction system
        add_filter('membershiping_restriction_types', array($this, 'add_inventory_restriction_types'));
        add_filter('membershiping_restriction_options', array($this, 'add_inventory_restriction_options'), 10, 2);
        add_filter('membershiping_check_user_restrictions', array($this, 'check_inventory_restrictions'), 10, 3);
        add_action('membershiping_restriction_admin_fields', array($this, 'render_inventory_restriction_fields'), 10, 2);
        add_action('membershiping_save_restriction_settings', array($this, 'save_inventory_restriction_settings'), 10, 2);
        
        // Add inventory items to core restriction interface
        add_action('admin_enqueue_scripts', array($this, 'enqueue_restriction_assets'));
        add_action('wp_ajax_membershiping_get_inventory_items', array($this, 'ajax_get_inventory_items'));
        add_action('wp_ajax_membershiping_get_user_items_for_restriction', array($this, 'ajax_get_user_items'));
        
        // Hook into core plugin's content filtering
        add_filter('membershiping_content_access_check', array($this, 'validate_inventory_access'), 10, 4);
        
        // Integration with core plugin's shortcodes
        add_filter('membershiping_shortcode_restrictions', array($this, 'add_inventory_shortcode_support'));
        
        // Hook into core plugin's user capability checks
        add_filter('membershiping_user_can_access', array($this, 'check_user_inventory_access'), 10, 3);
    }
    
    /**
     * Add inventory restriction types to core plugin
     */
    public function add_inventory_restriction_types($types) {
        $types['inventory_items'] = array(
            'label' => __('Inventory Items', 'membershiping-inventory'),
            'description' => __('Restrict based on owned inventory items', 'membershiping-inventory'),
            'icon' => 'dashicons-archive',
            'priority' => 15
        );
        
        $types['inventory_currencies'] = array(
            'label' => __('Virtual Currencies', 'membershiping-inventory'),
            'description' => __('Restrict based on currency amounts', 'membershiping-inventory'),
            'icon' => 'dashicons-money-alt',
            'priority' => 16
        );
        
        $types['inventory_nfts'] = array(
            'label' => __('NFT Ownership', 'membershiping-inventory'),
            'description' => __('Restrict based on owned NFTs', 'membershiping-inventory'),
            'icon' => 'dashicons-images-alt2',
            'priority' => 17
        );
        
        $types['inventory_level'] = array(
            'label' => __('User Level', 'membershiping-inventory'),
            'description' => __('Restrict based on user level/experience', 'membershiping-inventory'),
            'icon' => 'dashicons-chart-line',
            'priority' => 18
        );
        
        return $types;
    }
    
    /**
     * Add inventory restriction options to core plugin interface
     */
    public function add_inventory_restriction_options($options, $restriction_type) {
        switch ($restriction_type) {
            case 'inventory_items':
                $options = $this->get_item_restriction_options();
                break;
            case 'inventory_currencies':
                $options = $this->get_currency_restriction_options();
                break;
            case 'inventory_nfts':
                $options = $this->get_nft_restriction_options();
                break;
            case 'inventory_level':
                $options = $this->get_level_restriction_options();
                break;
        }
        
        return $options;
    }
    
    /**
     * Get item restriction options
     */
    private function get_item_restriction_options() {
        $items = $this->items->get_all_items();
        $options = array();
        
        foreach ($items as $item) {
            $options[] = array(
                'value' => $item->id,
                'label' => $item->name,
                'description' => $item->description,
                'rarity' => $item->rarity,
                'type' => $item->item_type,
                'fields' => array(
                    'quantity' => array(
                        'type' => 'number',
                        'label' => __('Required Quantity', 'membershiping-inventory'),
                        'default' => 1,
                        'min' => 1
                    ),
                    'min_rarity' => array(
                        'type' => 'select',
                        'label' => __('Minimum Rarity', 'membershiping-inventory'),
                        'options' => array(
                            'common' => __('Common', 'membershiping-inventory'),
                            'uncommon' => __('Uncommon', 'membershiping-inventory'),
                            'rare' => __('Rare', 'membershiping-inventory'),
                            'epic' => __('Epic', 'membershiping-inventory'),
                            'legendary' => __('Legendary', 'membershiping-inventory'),
                            'mythic' => __('Mythic', 'membershiping-inventory')
                        ),
                        'default' => 'common'
                    ),
                    'consume_on_access' => array(
                        'type' => 'checkbox',
                        'label' => __('Consume item when accessing content', 'membershiping-inventory'),
                        'default' => false
                    )
                )
            );
        }
        
        return $options;
    }
    
    /**
     * Get currency restriction options
     */
    private function get_currency_restriction_options() {
        $currencies = $this->currencies->get_all_currencies();
        $options = array();
        
        foreach ($currencies as $currency) {
            $options[] = array(
                'value' => $currency->id,
                'label' => $currency->name . ' (' . $currency->symbol . ')',
                'description' => $currency->description,
                'fields' => array(
                    'amount' => array(
                        'type' => 'number',
                        'label' => __('Required Amount', 'membershiping-inventory'),
                        'default' => 1,
                        'min' => 0,
                        'step' => 0.01
                    ),
                    'deduct_on_access' => array(
                        'type' => 'checkbox',
                        'label' => __('Deduct currency when accessing content', 'membershiping-inventory'),
                        'default' => false
                    )
                )
            );
        }
        
        return $options;
    }
    
    /**
     * Get NFT restriction options
     */
    private function get_nft_restriction_options() {
        $items = $this->items->get_all_items();
        $options = array();
        
        foreach ($items as $item) {
            if ($item->mint_nft) {
                $options[] = array(
                    'value' => $item->id,
                    'label' => $item->name . ' (NFT)',
                    'description' => __('Must own NFT version of this item', 'membershiping-inventory'),
                    'fields' => array(
                        'min_rarity' => array(
                            'type' => 'select',
                            'label' => __('Minimum NFT Rarity', 'membershiping-inventory'),
                            'options' => array(
                                'common' => __('Common', 'membershiping-inventory'),
                                'uncommon' => __('Uncommon', 'membershiping-inventory'),
                                'rare' => __('Rare', 'membershiping-inventory'),
                                'epic' => __('Epic', 'membershiping-inventory'),
                                'legendary' => __('Legendary', 'membershiping-inventory'),
                                'mythic' => __('Mythic', 'membershiping-inventory')
                            ),
                            'default' => 'common'
                        ),
                        'count' => array(
                            'type' => 'number',
                            'label' => __('Required NFT Count', 'membershiping-inventory'),
                            'default' => 1,
                            'min' => 1
                        )
                    )
                );
            }
        }
        
        return $options;
    }
    
    /**
     * Get level restriction options
     */
    private function get_level_restriction_options() {
        return array(
            array(
                'value' => 'user_level',
                'label' => __('User Level Requirement', 'membershiping-inventory'),
                'description' => __('Restrict based on user level/experience', 'membershiping-inventory'),
                'fields' => array(
                    'level' => array(
                        'type' => 'number',
                        'label' => __('Minimum Level', 'membershiping-inventory'),
                        'default' => 1,
                        'min' => 1,
                        'max' => 100
                    ),
                    'experience' => array(
                        'type' => 'number',
                        'label' => __('Minimum Experience Points', 'membershiping-inventory'),
                        'default' => 0,
                        'min' => 0
                    )
                )
            )
        );
    }
    
    /**
     * Check inventory restrictions for core plugin
     */
    public function check_inventory_restrictions($has_access, $restrictions, $user_id) {
        // If user already doesn't have access, don't override
        if (!$has_access) {
            return $has_access;
        }
        
        // Check each inventory restriction type
        foreach ($restrictions as $restriction) {
            switch ($restriction['type']) {
                case 'inventory_items':
                    if (!$this->check_item_restriction($user_id, $restriction)) {
                        return false;
                    }
                    break;
                    
                case 'inventory_currencies':
                    if (!$this->check_currency_restriction($user_id, $restriction)) {
                        return false;
                    }
                    break;
                    
                case 'inventory_nfts':
                    if (!$this->check_nft_restriction($user_id, $restriction)) {
                        return false;
                    }
                    break;
                    
                case 'inventory_level':
                    if (!$this->check_level_restriction($user_id, $restriction)) {
                        return false;
                    }
                    break;
            }
        }
        
        return $has_access;
    }
    
    /**
     * Check item restriction
     */
    private function check_item_restriction($user_id, $restriction) {
        $item_id = $restriction['item_id'];
        $required_quantity = $restriction['quantity'] ?? 1;
        $min_rarity = $restriction['min_rarity'] ?? 'common';
        $consume_on_access = $restriction['consume_on_access'] ?? false;
        
        $user_item = $this->items->get_user_item($user_id, $item_id);
        
        if (!$user_item || $user_item->quantity < $required_quantity) {
            return false;
        }
        
        // Check rarity if specified
        if ($min_rarity && $min_rarity !== 'common') {
            $user_nfts = $this->get_user_nfts_for_item($user_id, $item_id);
            $has_rarity = false;
            
            foreach ($user_nfts as $nft) {
                if ($this->is_rarity_sufficient($nft->rarity, $min_rarity)) {
                    $has_rarity = true;
                    break;
                }
            }
            
            if (!$has_rarity) {
                return false;
            }
        }
        
        // Consume item if required
        if ($consume_on_access) {
            $this->items->remove_user_item($user_id, $item_id, $required_quantity);
            
            // Log consumption
            $this->log_restriction_consumption($user_id, 'item', $item_id, $required_quantity);
        }
        
        return true;
    }
    
    /**
     * Check currency restriction
     */
    private function check_currency_restriction($user_id, $restriction) {
        $currency_id = $restriction['currency_id'];
        $required_amount = $restriction['amount'] ?? 1;
        $deduct_on_access = $restriction['deduct_on_access'] ?? false;
        
        $user_currency = $this->currencies->get_user_currency($user_id, $currency_id);
        
        if (!$user_currency || $user_currency->amount < $required_amount) {
            return false;
        }
        
        // Deduct currency if required
        if ($deduct_on_access) {
            $this->currencies->remove_user_currency($user_id, $currency_id, $required_amount, 'content_access', 'Content access payment');
            
            // Log deduction
            $this->log_restriction_consumption($user_id, 'currency', $currency_id, $required_amount);
        }
        
        return true;
    }
    
    /**
     * Check NFT restriction
     */
    private function check_nft_restriction($user_id, $restriction) {
        $item_id = $restriction['item_id'];
        $min_rarity = $restriction['min_rarity'] ?? 'common';
        $required_count = $restriction['count'] ?? 1;
        
        $user_nfts = $this->get_user_nfts_for_item($user_id, $item_id);
        $matching_count = 0;
        
        foreach ($user_nfts as $nft) {
            if ($this->is_rarity_sufficient($nft->rarity, $min_rarity)) {
                $matching_count++;
            }
        }
        
        return $matching_count >= $required_count;
    }
    
    /**
     * Check level restriction
     */
    private function check_level_restriction($user_id, $restriction) {
        $required_level = $restriction['level'] ?? 1;
        $required_experience = $restriction['experience'] ?? 0;
        
        $user_level = get_user_meta($user_id, 'membershiping_level', true) ?: 1;
        $user_experience = get_user_meta($user_id, 'membershiping_experience', true) ?: 0;
        
        return $user_level >= $required_level && $user_experience >= $required_experience;
    }
    
    /**
     * Render inventory restriction fields in core plugin admin
     */
    public function render_inventory_restriction_fields($restriction_type, $post_id) {
        if (!in_array($restriction_type, array('inventory_items', 'inventory_currencies', 'inventory_nfts', 'inventory_level'))) {
            return;
        }
        
        $current_restrictions = get_post_meta($post_id, '_membershiping_inventory_restrictions', true) ?: array();
        
        ?>
        <div class="membershiping-inventory-restriction-fields" data-type="<?php echo esc_attr($restriction_type); ?>">
            <?php
            switch ($restriction_type) {
                case 'inventory_items':
                    $this->render_item_restriction_fields($current_restrictions);
                    break;
                case 'inventory_currencies':
                    $this->render_currency_restriction_fields($current_restrictions);
                    break;
                case 'inventory_nfts':
                    $this->render_nft_restriction_fields($current_restrictions);
                    break;
                case 'inventory_level':
                    $this->render_level_restriction_fields($current_restrictions);
                    break;
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Render item restriction fields
     */
    private function render_item_restriction_fields($current_restrictions) {
        $items = $this->items->get_all_items();
        ?>
        <div class="inventory-item-restrictions">
            <h4><?php _e('Required Items', 'membershiping-inventory'); ?></h4>
            <p class="description"><?php _e('Users must own these items to access the content.', 'membershiping-inventory'); ?></p>
            
            <div class="restriction-items-list">
                <div class="restriction-item-template" style="display: none;">
                    <div class="restriction-item">
                        <select name="membershiping_inventory_restrictions[items][]" class="item-select">
                            <option value=""><?php _e('Select an item...', 'membershiping-inventory'); ?></option>
                            <?php foreach ($items as $item): ?>
                            <option value="<?php echo $item->id; ?>" 
                                    data-rarity="<?php echo $item->rarity; ?>"
                                    data-type="<?php echo $item->item_type; ?>">
                                <?php echo esc_html($item->name); ?> (<?php echo esc_html(ucfirst($item->rarity)); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>
                            <?php _e('Quantity:', 'membershiping-inventory'); ?>
                            <input type="number" name="membershiping_inventory_restrictions[quantities][]" 
                                   value="1" min="1" class="small-text">
                        </label>
                        
                        <label>
                            <?php _e('Min Rarity:', 'membershiping-inventory'); ?>
                            <select name="membershiping_inventory_restrictions[rarities][]">
                                <option value="common"><?php _e('Common', 'membershiping-inventory'); ?></option>
                                <option value="uncommon"><?php _e('Uncommon', 'membershiping-inventory'); ?></option>
                                <option value="rare"><?php _e('Rare', 'membershiping-inventory'); ?></option>
                                <option value="epic"><?php _e('Epic', 'membershiping-inventory'); ?></option>
                                <option value="legendary"><?php _e('Legendary', 'membershiping-inventory'); ?></option>
                                <option value="mythic"><?php _e('Mythic', 'membershiping-inventory'); ?></option>
                            </select>
                        </label>
                        
                        <label>
                            <input type="checkbox" name="membershiping_inventory_restrictions[consume][]" value="1">
                            <?php _e('Consume on access', 'membershiping-inventory'); ?>
                        </label>
                        
                        <button type="button" class="button remove-restriction-item"><?php _e('Remove', 'membershiping-inventory'); ?></button>
                    </div>
                </div>
                
                <?php if (!empty($current_restrictions['items'])): ?>
                    <?php foreach ($current_restrictions['items'] as $index => $item_restriction): ?>
                    <div class="restriction-item">
                        <!-- Render existing restrictions -->
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" class="button add-restriction-item"><?php _e('Add Item Requirement', 'membershiping-inventory'); ?></button>
        </div>
        <?php
    }
    
    /**
     * Render currency restriction fields
     */
    private function render_currency_restriction_fields($current_restrictions) {
        $currencies = $this->currencies->get_all_currencies();
        ?>
        <div class="inventory-currency-restrictions">
            <h4><?php _e('Required Currencies', 'membershiping-inventory'); ?></h4>
            <p class="description"><?php _e('Users must have these currency amounts to access the content.', 'membershiping-inventory'); ?></p>
            
            <div class="restriction-currencies-list">
                <?php foreach ($currencies as $currency): ?>
                <div class="restriction-currency">
                    <label>
                        <input type="checkbox" name="membershiping_inventory_restrictions[currencies][<?php echo $currency->id; ?>][enabled]" value="1"
                               <?php checked(!empty($current_restrictions['currencies'][$currency->id]['enabled'])); ?>>
                        <?php echo esc_html($currency->name . ' (' . $currency->symbol . ')'); ?>
                    </label>
                    
                    <div class="currency-fields" style="margin-left: 25px;">
                        <label>
                            <?php _e('Required Amount:', 'membershiping-inventory'); ?>
                            <input type="number" name="membershiping_inventory_restrictions[currencies][<?php echo $currency->id; ?>][amount]" 
                                   value="<?php echo esc_attr($current_restrictions['currencies'][$currency->id]['amount'] ?? 1); ?>" 
                                   min="0" step="0.01" class="small-text">
                        </label>
                        
                        <label>
                            <input type="checkbox" name="membershiping_inventory_restrictions[currencies][<?php echo $currency->id; ?>][deduct]" value="1"
                                   <?php checked(!empty($current_restrictions['currencies'][$currency->id]['deduct'])); ?>>
                            <?php _e('Deduct currency on access', 'membershiping-inventory'); ?>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render NFT restriction fields
     */
    private function render_nft_restriction_fields($current_restrictions) {
        $nft_items = $this->items->get_items_by_criteria(array('mint_nft' => true));
        ?>
        <div class="inventory-nft-restrictions">
            <h4><?php _e('Required NFTs', 'membershiping-inventory'); ?></h4>
            <p class="description"><?php _e('Users must own NFT versions of these items to access the content.', 'membershiping-inventory'); ?></p>
            
            <div class="restriction-nfts-list">
                <?php foreach ($nft_items as $item): ?>
                <div class="restriction-nft">
                    <label>
                        <input type="checkbox" name="membershiping_inventory_restrictions[nfts][<?php echo $item->id; ?>][enabled]" value="1"
                               <?php checked(!empty($current_restrictions['nfts'][$item->id]['enabled'])); ?>>
                        <?php echo esc_html($item->name); ?> NFT
                    </label>
                    
                    <div class="nft-fields" style="margin-left: 25px;">
                        <label>
                            <?php _e('Minimum Rarity:', 'membershiping-inventory'); ?>
                            <select name="membershiping_inventory_restrictions[nfts][<?php echo $item->id; ?>][rarity]">
                                <option value="common" <?php selected($current_restrictions['nfts'][$item->id]['rarity'] ?? 'common', 'common'); ?>><?php _e('Common', 'membershiping-inventory'); ?></option>
                                <option value="uncommon" <?php selected($current_restrictions['nfts'][$item->id]['rarity'] ?? 'common', 'uncommon'); ?>><?php _e('Uncommon', 'membershiping-inventory'); ?></option>
                                <option value="rare" <?php selected($current_restrictions['nfts'][$item->id]['rarity'] ?? 'common', 'rare'); ?>><?php _e('Rare', 'membershiping-inventory'); ?></option>
                                <option value="epic" <?php selected($current_restrictions['nfts'][$item->id]['rarity'] ?? 'common', 'epic'); ?>><?php _e('Epic', 'membershiping-inventory'); ?></option>
                                <option value="legendary" <?php selected($current_restrictions['nfts'][$item->id]['rarity'] ?? 'common', 'legendary'); ?>><?php _e('Legendary', 'membershiping-inventory'); ?></option>
                                <option value="mythic" <?php selected($current_restrictions['nfts'][$item->id]['rarity'] ?? 'common', 'mythic'); ?>><?php _e('Mythic', 'membershiping-inventory'); ?></option>
                            </select>
                        </label>
                        
                        <label>
                            <?php _e('Required Count:', 'membershiping-inventory'); ?>
                            <input type="number" name="membershiping_inventory_restrictions[nfts][<?php echo $item->id; ?>][count]" 
                                   value="<?php echo esc_attr($current_restrictions['nfts'][$item->id]['count'] ?? 1); ?>" 
                                   min="1" class="small-text">
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render level restriction fields
     */
    private function render_level_restriction_fields($current_restrictions) {
        ?>
        <div class="inventory-level-restrictions">
            <h4><?php _e('Level Requirements', 'membershiping-inventory'); ?></h4>
            <p class="description"><?php _e('Users must meet these level/experience requirements to access the content.', 'membershiping-inventory'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th><?php _e('Minimum Level', 'membershiping-inventory'); ?></th>
                    <td>
                        <input type="number" name="membershiping_inventory_restrictions[level][min_level]" 
                               value="<?php echo esc_attr($current_restrictions['level']['min_level'] ?? 1); ?>" 
                               min="1" max="100" class="small-text">
                        <p class="description"><?php _e('User must be at least this level.', 'membershiping-inventory'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Minimum Experience', 'membershiping-inventory'); ?></th>
                    <td>
                        <input type="number" name="membershiping_inventory_restrictions[level][min_experience]" 
                               value="<?php echo esc_attr($current_restrictions['level']['min_experience'] ?? 0); ?>" 
                               min="0" class="regular-text">
                        <p class="description"><?php _e('User must have at least this much experience points.', 'membershiping-inventory'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Save inventory restriction settings
     */
    public function save_inventory_restriction_settings($post_id, $restrictions_data) {
        if (isset($restrictions_data['membershiping_inventory_restrictions'])) {
            $inventory_restrictions = $restrictions_data['membershiping_inventory_restrictions'];
            
            // Sanitize and save inventory restrictions
            $sanitized_restrictions = $this->sanitize_inventory_restrictions($inventory_restrictions);
            update_post_meta($post_id, '_membershiping_inventory_restrictions', $sanitized_restrictions);
        }
    }
    
    /**
     * Sanitize inventory restrictions data
     */
    private function sanitize_inventory_restrictions($restrictions) {
        $sanitized = array();
        
        // Sanitize item restrictions
        if (!empty($restrictions['items'])) {
            $sanitized['items'] = array();
            foreach ($restrictions['items'] as $index => $item_id) {
                if ($item_id) {
                    $sanitized['items'][] = array(
                        'item_id' => intval($item_id),
                        'quantity' => intval($restrictions['quantities'][$index] ?? 1),
                        'rarity' => sanitize_text_field($restrictions['rarities'][$index] ?? 'common'),
                        'consume' => !empty($restrictions['consume'][$index])
                    );
                }
            }
        }
        
        // Sanitize currency restrictions
        if (!empty($restrictions['currencies'])) {
            $sanitized['currencies'] = array();
            foreach ($restrictions['currencies'] as $currency_id => $currency_data) {
                if (!empty($currency_data['enabled'])) {
                    $sanitized['currencies'][$currency_id] = array(
                        'amount' => floatval($currency_data['amount'] ?? 1),
                        'deduct' => !empty($currency_data['deduct'])
                    );
                }
            }
        }
        
        // Sanitize NFT restrictions
        if (!empty($restrictions['nfts'])) {
            $sanitized['nfts'] = array();
            foreach ($restrictions['nfts'] as $item_id => $nft_data) {
                if (!empty($nft_data['enabled'])) {
                    $sanitized['nfts'][$item_id] = array(
                        'rarity' => sanitize_text_field($nft_data['rarity'] ?? 'common'),
                        'count' => intval($nft_data['count'] ?? 1)
                    );
                }
            }
        }
        
        // Sanitize level restrictions
        if (!empty($restrictions['level'])) {
            $sanitized['level'] = array(
                'min_level' => intval($restrictions['level']['min_level'] ?? 1),
                'min_experience' => intval($restrictions['level']['min_experience'] ?? 0)
            );
        }
        
        return $sanitized;
    }
    
    /**
     * Enqueue restriction admin assets
     */
    public function enqueue_restriction_assets($hook) {
        if (strpos($hook, 'membershiping') === false) {
            return;
        }
        
        // Safety check for constant
        $plugin_url = defined('MEMBERSHIPING_INVENTORY_URL') ? MEMBERSHIPING_INVENTORY_URL : MEMBERSHIPING_INVENTORY_PLUGIN_URL;
        $version = defined('MEMBERSHIPING_INVENTORY_VERSION') ? MEMBERSHIPING_INVENTORY_VERSION : '1.0.0';
        
        wp_enqueue_script(
            'membershiping-inventory-restrictions',
            $plugin_url . 'assets/js/restrictions-admin.js',
            array('jquery'),
            $version,
            true
        );
        
        wp_localize_script('membershiping-inventory-restrictions', 'membershipingInventoryRestrictions', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('membershiping_inventory_restrictions_nonce'),
            'strings' => array(
                'selectItem' => __('Select an item...', 'membershiping-inventory'),
                'removeItem' => __('Remove', 'membershiping-inventory'),
                'addItem' => __('Add Item Requirement', 'membershiping-inventory')
            )
        ));
    }
    
    /**
     * AJAX: Get inventory items for restriction interface
     */
    public function ajax_get_inventory_items() {
        check_ajax_referer('membershiping_inventory_restrictions_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $items = $this->items->get_all_items();
        $formatted_items = array();
        
        foreach ($items as $item) {
            $formatted_items[] = array(
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'rarity' => $item->rarity,
                'type' => $item->item_type,
                'mint_nft' => $item->mint_nft
            );
        }
        
        wp_send_json_success($formatted_items);
    }
    
    /**
     * AJAX: Get user items for restriction validation
     */
    public function ajax_get_user_items() {
        check_ajax_referer('membershiping_inventory_restrictions_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID');
        }
        
        $user_items = $this->items->get_user_items($user_id);
        $user_currencies = $this->currencies->get_user_currencies($user_id);
        $user_nfts = $this->nfts->get_user_nfts($user_id);
        
        wp_send_json_success(array(
            'items' => $user_items,
            'currencies' => $user_currencies,
            'nfts' => $user_nfts
        ));
    }
    
    /**
     * Validate inventory access for core plugin content filtering
     */
    public function validate_inventory_access($has_access, $post_id, $user_id, $content_type) {
        $inventory_restrictions = get_post_meta($post_id, '_membershiping_inventory_restrictions', true);
        
        if (empty($inventory_restrictions)) {
            return $has_access;
        }
        
        // Check each type of inventory restriction
        if (!empty($inventory_restrictions['items'])) {
            foreach ($inventory_restrictions['items'] as $item_restriction) {
                if (!$this->check_item_restriction($user_id, $item_restriction)) {
                    return false;
                }
            }
        }
        
        if (!empty($inventory_restrictions['currencies'])) {
            foreach ($inventory_restrictions['currencies'] as $currency_id => $currency_restriction) {
                $restriction_data = array_merge($currency_restriction, array('currency_id' => $currency_id));
                if (!$this->check_currency_restriction($user_id, $restriction_data)) {
                    return false;
                }
            }
        }
        
        if (!empty($inventory_restrictions['nfts'])) {
            foreach ($inventory_restrictions['nfts'] as $item_id => $nft_restriction) {
                $restriction_data = array_merge($nft_restriction, array('item_id' => $item_id));
                if (!$this->check_nft_restriction($user_id, $restriction_data)) {
                    return false;
                }
            }
        }
        
        if (!empty($inventory_restrictions['level'])) {
            if (!$this->check_level_restriction($user_id, $inventory_restrictions['level'])) {
                return false;
            }
        }
        
        return $has_access;
    }
    
    /**
     * Add inventory shortcode support to core plugin
     */
    public function add_inventory_shortcode_support($shortcodes) {
        $shortcodes['membershiping_require_item'] = array(
            'callback' => array($this, 'require_item_shortcode'),
            'description' => __('Show content only if user owns specific item', 'membershiping-inventory')
        );
        
        $shortcodes['membershiping_require_currency'] = array(
            'callback' => array($this, 'require_currency_shortcode'),
            'description' => __('Show content only if user has currency amount', 'membershiping-inventory')
        );
        
        $shortcodes['membershiping_require_nft'] = array(
            'callback' => array($this, 'require_nft_shortcode'),
            'description' => __('Show content only if user owns specific NFT', 'membershiping-inventory')
        );
        
        return $shortcodes;
    }
    
    /**
     * Check user inventory access for core plugin capability system
     */
    public function check_user_inventory_access($can_access, $capability, $user_id) {
        // This integrates with the core plugin's user capability checking
        // Add inventory-based capability checks here if needed
        
        return $can_access;
    }
    
    /**
     * Utility functions
     */
    
    private function get_user_nfts_for_item($user_id, $item_id) {
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
    
    private function log_restriction_consumption($user_id, $type, $item_id, $amount) {
        $this->wpdb->insert(
            $this->database->get_table_name('audit_logs'),
            array(
                'user_id' => $user_id,
                'action' => 'restriction_consumption',
                'object_type' => $type,
                'object_id' => $item_id,
                'details' => json_encode(array(
                    'amount' => $amount,
                    'consumed_for' => 'content_access'
                )),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Shortcode implementations
     */
    
    public function require_item_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'item_id' => 0,
            'quantity' => 1,
            'rarity' => 'common',
            'message' => ''
        ), $atts);
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $atts['message'] ?: __('Please log in to view this content.', 'membershiping-inventory');
        }
        
        $restriction = array(
            'item_id' => intval($atts['item_id']),
            'quantity' => intval($atts['quantity']),
            'min_rarity' => $atts['rarity']
        );
        
        if ($this->check_item_restriction($user_id, $restriction)) {
            return do_shortcode($content);
        }
        
        return $atts['message'] ?: __('You do not have the required items to view this content.', 'membershiping-inventory');
    }
    
    public function require_currency_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'currency_id' => 0,
            'amount' => 1,
            'message' => ''
        ), $atts);
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $atts['message'] ?: __('Please log in to view this content.', 'membershiping-inventory');
        }
        
        $restriction = array(
            'currency_id' => intval($atts['currency_id']),
            'amount' => floatval($atts['amount'])
        );
        
        if ($this->check_currency_restriction($user_id, $restriction)) {
            return do_shortcode($content);
        }
        
        return $atts['message'] ?: __('You do not have enough currency to view this content.', 'membershiping-inventory');
    }
    
    public function require_nft_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'item_id' => 0,
            'rarity' => 'common',
            'count' => 1,
            'message' => ''
        ), $atts);
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return $atts['message'] ?: __('Please log in to view this content.', 'membershiping-inventory');
        }
        
        $restriction = array(
            'item_id' => intval($atts['item_id']),
            'min_rarity' => $atts['rarity'],
            'count' => intval($atts['count'])
        );
        
        if ($this->check_nft_restriction($user_id, $restriction)) {
            return do_shortcode($content);
        }
        
        return $atts['message'] ?: __('You do not have the required NFTs to view this content.', 'membershiping-inventory');
    }
}
