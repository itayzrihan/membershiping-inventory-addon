<?php
/**
 * Consumable Items & Gift Boxes System for Membershiping Inventory
 * Handles random rewards, rarity upgrades, and gift box mechanics
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Consumables {
    
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
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_membershiping_inventory_consume_item', array($this, 'ajax_consume_item'));
        add_action('wp_ajax_membershiping_inventory_open_gift_box', array($this, 'ajax_open_gift_box'));
        add_action('wp_ajax_membershiping_inventory_upgrade_item', array($this, 'ajax_upgrade_item'));
        add_action('wp_ajax_membershiping_inventory_get_consumable_effects', array($this, 'ajax_get_consumable_effects'));
        
        // Product configuration hooks
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_consumable_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_consumable_product_fields'));
        
        // Admin meta boxes
        add_action('add_meta_boxes', array($this, 'add_consumable_meta_boxes'));
        
        // Custom item effects
        add_filter('membershiping_inventory_item_effects', array($this, 'apply_custom_item_effects'), 10, 3);
    }
    
    /**
     * Consume an item and apply its effects
     */
    public function consume_item($user_id, $item_id, $quantity = 1) {
        // Validate user has the item
        $user_item = $this->items->get_user_item($user_id, $item_id);
        if (!$user_item || $user_item->quantity < $quantity) {
            return new WP_Error('insufficient_items', 'Not enough items to consume');
        }
        
        // Get item details
        $item = $this->items->get_item($item_id);
        if (!$item || !$item->is_consumable) {
            return new WP_Error('not_consumable', 'Item is not consumable');
        }
        
        // Rate limiting
        if (!$this->security->check_rate_limit('item_consumption', $user_id, 50, 3600)) {
            return new WP_Error('rate_limit', 'Too many items consumed. Please wait.');
        }
        
        $total_effects = array();
        
        // Apply effects for each quantity consumed
        for ($i = 0; $i < $quantity; $i++) {
            $effects = $this->apply_consumable_effects($user_id, $item);
            $total_effects = $this->merge_effects($total_effects, $effects);
        }
        
        // Remove consumed items
        $result = $this->items->remove_user_item($user_id, $item_id, $quantity);
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Log consumption
        $this->security->log_security_event('item_consumed', $user_id, array(
            'item_id' => $item_id,
            'quantity' => $quantity,
            'effects' => $total_effects
        ));
        
        return $total_effects;
    }
    
    /**
     * Apply consumable item effects
     */
    private function apply_consumable_effects($user_id, $item) {
        $effects = array();
        $consumable_config = $this->get_consumable_config($item->id);
        
        if (empty($consumable_config)) {
            return $effects;
        }
        
        // Apply currency rewards
        if (!empty($consumable_config['currency_rewards'])) {
            foreach ($consumable_config['currency_rewards'] as $reward) {
                $amount = $this->calculate_random_amount($reward);
                if ($amount > 0) {
                    $result = $this->currencies->add_user_currency($user_id, $reward['currency_id'], $amount, 'item_consumption', "Consumed {$item->name}");
                    if (!is_wp_error($result)) {
                        $effects['currencies'][$reward['currency_name']] = ($effects['currencies'][$reward['currency_name']] ?? 0) + $amount;
                    }
                }
            }
        }
        
        // Apply item rewards
        if (!empty($consumable_config['item_rewards'])) {
            foreach ($consumable_config['item_rewards'] as $reward) {
                if ($this->chance_succeeds($reward['chance'])) {
                    $amount = $this->calculate_random_amount($reward);
                    if ($amount > 0) {
                        $result = $this->items->add_user_item($user_id, $reward['item_id'], $amount);
                        if (!is_wp_error($result)) {
                            $effects['items'][$reward['item_name']] = ($effects['items'][$reward['item_name']] ?? 0) + $amount;
                        }
                    }
                }
            }
        }
        
        // Apply flag rewards
        if (!empty($consumable_config['flag_rewards'])) {
            foreach ($consumable_config['flag_rewards'] as $reward) {
                if ($this->chance_succeeds($reward['chance'])) {
                    $amount = $this->calculate_random_amount($reward);
                    if ($amount > 0) {
                        $this->award_flag($user_id, $reward['flag_name'], $amount);
                        $effects['flags'][] = $reward['flag_name'] . ' +' . $amount;
                    }
                }
            }
        }
        
        // Apply stat boosts (temporary)
        if (!empty($consumable_config['stat_boosts'])) {
            foreach ($consumable_config['stat_boosts'] as $boost) {
                if ($this->chance_succeeds($boost['chance'])) {
                    $this->apply_temporary_stat_boost($user_id, $boost);
                    $effects['stat_boosts'][] = $boost['stat_name'] . ' +' . $boost['amount'] . ' (' . $boost['duration'] . 's)';
                }
            }
        }
        
        // Apply special effects
        $special_effects = $this->apply_special_consumable_effects($user_id, $item, $consumable_config);
        $effects = $this->merge_effects($effects, $special_effects);
        
        return $effects;
    }
    
    /**
     * Open a gift box and reveal rewards
     */
    public function open_gift_box($user_id, $item_id, $quantity = 1) {
        // Validate user has the gift box
        $user_item = $this->items->get_user_item($user_id, $item_id);
        if (!$user_item || $user_item->quantity < $quantity) {
            return new WP_Error('insufficient_items', 'Not enough gift boxes to open');
        }
        
        // Get gift box details
        $item = $this->items->get_item($item_id);
        if (!$item || $item->item_type !== 'gift_box') {
            return new WP_Error('not_gift_box', 'Item is not a gift box');
        }
        
        // Rate limiting
        if (!$this->security->check_rate_limit('gift_box_opening', $user_id, 20, 3600)) {
            return new WP_Error('rate_limit', 'Too many gift boxes opened. Please wait.');
        }
        
        $total_rewards = array();
        
        // Open each gift box
        for ($i = 0; $i < $quantity; $i++) {
            $rewards = $this->generate_gift_box_rewards($user_id, $item);
            $total_rewards = $this->merge_effects($total_rewards, $rewards);
        }
        
        // Remove opened gift boxes
        $result = $this->items->remove_user_item($user_id, $item_id, $quantity);
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Apply the rewards
        $this->apply_gift_box_rewards($user_id, $total_rewards);
        
        // Log gift box opening
        $this->security->log_security_event('gift_box_opened', $user_id, array(
            'item_id' => $item_id,
            'quantity' => $quantity,
            'rewards' => $total_rewards
        ));
        
        return $total_rewards;
    }
    
    /**
     * Generate gift box rewards
     */
    private function generate_gift_box_rewards($user_id, $gift_box) {
        $rewards = array();
        $gift_box_config = $this->get_gift_box_config($gift_box->id);
        
        if (empty($gift_box_config)) {
            return $rewards;
        }
        
        $rarity_multiplier = $this->get_rarity_multiplier($gift_box->rarity);
        
        // Determine number of rewards based on rarity
        $reward_count = $this->calculate_reward_count($gift_box->rarity);
        
        // Pool all possible rewards
        $reward_pool = array();
        
        // Add currency rewards to pool
        if (!empty($gift_box_config['currency_rewards'])) {
            foreach ($gift_box_config['currency_rewards'] as $reward) {
                $reward['type'] = 'currency';
                $reward_pool[] = $reward;
            }
        }
        
        // Add item rewards to pool
        if (!empty($gift_box_config['item_rewards'])) {
            foreach ($gift_box_config['item_rewards'] as $reward) {
                $reward['type'] = 'item';
                $reward_pool[] = $reward;
            }
        }
        
        // Add rare rewards to pool
        if (!empty($gift_box_config['rare_rewards'])) {
            foreach ($gift_box_config['rare_rewards'] as $reward) {
                $reward['type'] = 'rare';
                $reward['weight'] = $reward['weight'] * $rarity_multiplier;
                $reward_pool[] = $reward;
            }
        }
        
        // Select rewards based on weighted random
        for ($i = 0; $i < $reward_count; $i++) {
            $selected_reward = $this->weighted_random_selection($reward_pool);
            if ($selected_reward) {
                $rewards[] = $selected_reward;
                
                // Remove selected reward if it's unique
                if ($selected_reward['unique'] ?? false) {
                    $reward_pool = array_filter($reward_pool, function($r) use ($selected_reward) {
                        return $r !== $selected_reward;
                    });
                }
            }
        }
        
        return $this->format_gift_box_rewards($rewards);
    }
    
    /**
     * Apply gift box rewards to user
     */
    private function apply_gift_box_rewards($user_id, $rewards) {
        // Apply currency rewards
        if (!empty($rewards['currencies'])) {
            foreach ($rewards['currencies'] as $currency_name => $amount) {
                $currency = $this->currencies->get_currency_by_name($currency_name);
                if ($currency) {
                    $this->currencies->add_user_currency($user_id, $currency->id, $amount, 'gift_box', 'Gift box reward');
                }
            }
        }
        
        // Apply item rewards
        if (!empty($rewards['items'])) {
            foreach ($rewards['items'] as $item_name => $quantity) {
                $item = $this->items->get_item_by_name($item_name);
                if ($item) {
                    $this->items->add_user_item($user_id, $item->id, $quantity);
                    
                    // Check if NFT should be minted for non-stackable items
                    if (!$item->is_stackable && $item->mint_nft) {
                        for ($i = 0; $i < $quantity; $i++) {
                            $this->nfts->mint_nft($user_id, $item->id, $this->determine_nft_rarity(), null, array(
                                'acquisition_method' => 'gift_box',
                                'gift_box_date' => current_time('mysql')
                            ));
                        }
                    }
                }
            }
        }
        
        // Apply flag rewards
        if (!empty($rewards['flags'])) {
            foreach ($rewards['flags'] as $flag_data) {
                $this->award_flag($user_id, $flag_data['name'], $flag_data['amount']);
            }
        }
    }
    
    /**
     * Upgrade item rarity
     */
    public function upgrade_item_rarity($user_id, $nft_id, $materials = array()) {
        // Get NFT
        $nft = $this->nfts->get_nft($nft_id);
        if (!$nft || $nft->owner_id != $user_id) {
            return new WP_Error('invalid_nft', 'NFT not found or not owned');
        }
        
        // Check if upgrade is possible
        $next_rarity = $this->get_next_rarity($nft->rarity);
        if (!$next_rarity) {
            return new WP_Error('max_rarity', 'Item is already at maximum rarity');
        }
        
        // Validate materials
        $required_materials = $this->get_upgrade_requirements($nft->rarity, $next_rarity);
        $validation = $this->validate_upgrade_materials($user_id, $required_materials, $materials);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Calculate upgrade chance
        $upgrade_chance = $this->calculate_upgrade_chance($nft->rarity, $materials);
        
        // Rate limiting
        if (!$this->security->check_rate_limit('item_upgrade', $user_id, 10, 3600)) {
            return new WP_Error('rate_limit', 'Too many upgrade attempts. Please wait.');
        }
        
        // Consume materials
        foreach ($required_materials as $material) {
            $this->items->remove_user_item($user_id, $material['item_id'], $material['quantity']);
        }
        
        // Attempt upgrade
        $upgrade_successful = $this->chance_succeeds($upgrade_chance);
        
        if ($upgrade_successful) {
            // Upgrade the NFT
            $result = $this->nfts->upgrade_nft_rarity($nft_id, $next_rarity);
            if (is_wp_error($result)) {
                return $result;
            }
            
            // Log successful upgrade
            $this->security->log_security_event('item_upgrade_success', $user_id, array(
                'nft_id' => $nft_id,
                'old_rarity' => $nft->rarity,
                'new_rarity' => $next_rarity,
                'chance' => $upgrade_chance
            ));
            
            return array(
                'success' => true,
                'new_rarity' => $next_rarity,
                'message' => 'Upgrade successful!'
            );
        } else {
            // Log failed upgrade
            $this->security->log_security_event('item_upgrade_failed', $user_id, array(
                'nft_id' => $nft_id,
                'rarity' => $nft->rarity,
                'chance' => $upgrade_chance
            ));
            
            return array(
                'success' => false,
                'message' => 'Upgrade failed! Materials were consumed.'
            );
        }
    }
    
    /**
     * Get consumable configuration
     */
    private function get_consumable_config($item_id) {
        return get_post_meta($this->get_product_id_by_item($item_id), '_membershiping_consumable_config', true) ?: array();
    }
    
    /**
     * Get gift box configuration
     */
    private function get_gift_box_config($item_id) {
        return get_post_meta($this->get_product_id_by_item($item_id), '_membershiping_gift_box_config', true) ?: array();
    }
    
    /**
     * Calculate random amount within range
     */
    private function calculate_random_amount($reward) {
        $min = $reward['min_amount'] ?? $reward['amount'] ?? 1;
        $max = $reward['max_amount'] ?? $reward['amount'] ?? 1;
        
        return wp_rand($min, $max);
    }
    
    /**
     * Check if chance succeeds
     */
    private function chance_succeeds($chance) {
        return (wp_rand(1, 100) <= $chance);
    }
    
    /**
     * Get rarity multiplier
     */
    private function get_rarity_multiplier($rarity) {
        $multipliers = array(
            'common' => 1.0,
            'uncommon' => 1.2,
            'rare' => 1.5,
            'epic' => 2.0,
            'legendary' => 3.0,
            'mythic' => 5.0
        );
        
        return $multipliers[$rarity] ?? 1.0;
    }
    
    /**
     * Calculate reward count based on rarity
     */
    private function calculate_reward_count($rarity) {
        $base_counts = array(
            'common' => array(1, 2),
            'uncommon' => array(1, 3),
            'rare' => array(2, 4),
            'epic' => array(2, 5),
            'legendary' => array(3, 6),
            'mythic' => array(4, 8)
        );
        
        $range = $base_counts[$rarity] ?? array(1, 2);
        return wp_rand($range[0], $range[1]);
    }
    
    /**
     * Weighted random selection
     */
    private function weighted_random_selection($items) {
        if (empty($items)) {
            return null;
        }
        
        $total_weight = array_sum(array_column($items, 'weight'));
        $random = wp_rand(1, $total_weight);
        
        $current_weight = 0;
        foreach ($items as $item) {
            $current_weight += $item['weight'] ?? 1;
            if ($random <= $current_weight) {
                return $item;
            }
        }
        
        return $items[0]; // Fallback
    }
    
    /**
     * Format gift box rewards
     */
    private function format_gift_box_rewards($raw_rewards) {
        $formatted = array();
        
        foreach ($raw_rewards as $reward) {
            $amount = $this->calculate_random_amount($reward);
            
            switch ($reward['type']) {
                case 'currency':
                    $formatted['currencies'][$reward['currency_name']] = ($formatted['currencies'][$reward['currency_name']] ?? 0) + $amount;
                    break;
                case 'item':
                    $formatted['items'][$reward['item_name']] = ($formatted['items'][$reward['item_name']] ?? 0) + $amount;
                    break;
                case 'rare':
                    if ($reward['reward_type'] === 'flag') {
                        $formatted['flags'][] = array('name' => $reward['flag_name'], 'amount' => $amount);
                    } elseif ($reward['reward_type'] === 'item') {
                        $formatted['items'][$reward['item_name']] = ($formatted['items'][$reward['item_name']] ?? 0) + $amount;
                    }
                    break;
            }
        }
        
        return $formatted;
    }
    
    /**
     * Get next rarity level
     */
    private function get_next_rarity($current_rarity) {
        $rarities = array('common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic');
        $current_index = array_search($current_rarity, $rarities);
        
        if ($current_index === false || $current_index >= count($rarities) - 1) {
            return null;
        }
        
        return $rarities[$current_index + 1];
    }
    
    /**
     * Get upgrade requirements
     */
    private function get_upgrade_requirements($current_rarity, $next_rarity) {
        // This would typically be configured in admin, but here's a default structure
        $requirements = array(
            'common_to_uncommon' => array(
                array('item_name' => 'Upgrade Crystal', 'quantity' => 1),
                array('item_name' => 'Magic Essence', 'quantity' => 5)
            ),
            'uncommon_to_rare' => array(
                array('item_name' => 'Upgrade Crystal', 'quantity' => 3),
                array('item_name' => 'Magic Essence', 'quantity' => 15),
                array('item_name' => 'Rare Powder', 'quantity' => 1)
            ),
            // Add more upgrade paths...
        );
        
        $key = $current_rarity . '_to_' . $next_rarity;
        return $requirements[$key] ?? array();
    }
    
    /**
     * Validate upgrade materials
     */
    private function validate_upgrade_materials($user_id, $required_materials, $provided_materials) {
        foreach ($required_materials as $required) {
            $item = $this->items->get_item_by_name($required['item_name']);
            if (!$item) {
                continue;
            }
            
            $user_item = $this->items->get_user_item($user_id, $item->id);
            if (!$user_item || $user_item->quantity < $required['quantity']) {
                return new WP_Error('insufficient_materials', 'Not enough ' . $required['item_name']);
            }
        }
        
        return true;
    }
    
    /**
     * Calculate upgrade chance
     */
    private function calculate_upgrade_chance($current_rarity, $materials = array()) {
        $base_chances = array(
            'common' => 80,
            'uncommon' => 60,
            'rare' => 40,
            'epic' => 25,
            'legendary' => 15
        );
        
        $base_chance = $base_chances[$current_rarity] ?? 10;
        
        // Apply material bonuses
        $bonus_chance = 0;
        foreach ($materials as $material) {
            if ($material['boost_chance'] ?? false) {
                $bonus_chance += $material['bonus_amount'] ?? 0;
            }
        }
        
        return min(95, $base_chance + $bonus_chance); // Cap at 95%
    }
    
    /**
     * Determine NFT rarity for gift box rewards
     */
    private function determine_nft_rarity() {
        $chances = array(
            'common' => 50,
            'uncommon' => 30,
            'rare' => 15,
            'epic' => 4,
            'legendary' => 1
        );
        
        $random = wp_rand(1, 100);
        $cumulative = 0;
        
        foreach ($chances as $rarity => $chance) {
            $cumulative += $chance;
            if ($random <= $cumulative) {
                return $rarity;
            }
        }
        
        return 'common';
    }
    
    /**
     * Apply temporary stat boost
     */
    private function apply_temporary_stat_boost($user_id, $boost) {
        $boosts_table = $this->database->get_table_name('user_stat_boosts');
        
        $expires_at = date('Y-m-d H:i:s', time() + $boost['duration']);
        
        $this->wpdb->insert(
            $boosts_table,
            array(
                'user_id' => $user_id,
                'stat_name' => $boost['stat_name'],
                'boost_amount' => $boost['amount'],
                'boost_type' => $boost['type'] ?? 'add',
                'source' => 'consumable',
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%f', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Apply special consumable effects
     */
    private function apply_special_consumable_effects($user_id, $item, $config) {
        $effects = array();
        
        // Health/Mana restoration
        if (!empty($config['health_restore'])) {
            $amount = $this->calculate_random_amount($config['health_restore']);
            $this->update_user_stat($user_id, 'health', $amount, 'add');
            $effects['health_restored'] = $amount;
        }
        
        if (!empty($config['mana_restore'])) {
            $amount = $this->calculate_random_amount($config['mana_restore']);
            $this->update_user_stat($user_id, 'mana', $amount, 'add');
            $effects['mana_restored'] = $amount;
        }
        
        // Experience gain
        if (!empty($config['experience_gain'])) {
            $amount = $this->calculate_random_amount($config['experience_gain']);
            $this->award_experience($user_id, $amount);
            $effects['experience_gained'] = $amount;
        }
        
        // Teleportation effects
        if (!empty($config['teleport_location'])) {
            $effects['teleport'] = $config['teleport_location'];
        }
        
        return $effects;
    }
    
    /**
     * Merge multiple effect arrays
     */
    private function merge_effects($effects1, $effects2) {
        foreach ($effects2 as $type => $values) {
            if (is_array($values)) {
                if (isset($effects1[$type])) {
                    if (is_array($effects1[$type])) {
                        $effects1[$type] = array_merge_recursive($effects1[$type], $values);
                    } else {
                        $effects1[$type] = $values;
                    }
                } else {
                    $effects1[$type] = $values;
                }
            } else {
                $effects1[$type] = $values;
            }
        }
        
        return $effects1;
    }
    
    /**
     * Award flag using available systems
     */
    private function award_flag($user_id, $flag_name, $amount) {
        // Use Membershiping Core if available
        if (function_exists('membershiping_award_flag')) {
            membershiping_award_flag($user_id, $flag_name, $amount);
        } elseif (class_exists('Membershiping_Flags')) {
            $flags = new Membershiping_Flags();
            $flags->award_flag($user_id, $flag_name, $amount);
        } else {
            // Fallback to our system
            $this->store_flag_award($user_id, $flag_name, $amount);
        }
    }
    
    /**
     * Store flag award in our system
     */
    private function store_flag_award($user_id, $flag_name, $amount) {
        $table_name = $this->database->get_table_name('user_flags');
        
        $current_value = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT value FROM {$table_name} WHERE user_id = %d AND flag_name = %s",
                $user_id, $flag_name
            )
        );
        
        $new_value = ($current_value ? intval($current_value) : 0) + $amount;
        
        $this->wpdb->replace(
            $table_name,
            array(
                'user_id' => $user_id,
                'flag_name' => $flag_name,
                'value' => $new_value,
                'last_updated' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s')
        );
    }
    
    /**
     * Update user stat
     */
    private function update_user_stat($user_id, $stat_name, $amount, $type = 'add') {
        // This would integrate with a character stats system
        // For now, we'll store in user meta
        $current_value = get_user_meta($user_id, 'membershiping_' . $stat_name, true) ?: 0;
        
        switch ($type) {
            case 'set':
                $new_value = $amount;
                break;
            case 'multiply':
                $new_value = $current_value * $amount;
                break;
            case 'add':
            default:
                $new_value = $current_value + $amount;
                break;
        }
        
        update_user_meta($user_id, 'membershiping_' . $stat_name, $new_value);
    }
    
    /**
     * Award experience
     */
    private function award_experience($user_id, $amount) {
        $current_exp = get_user_meta($user_id, 'membershiping_experience', true) ?: 0;
        $new_exp = $current_exp + $amount;
        
        update_user_meta($user_id, 'membershiping_experience', $new_exp);
        
        // Check for level up
        $this->check_level_up($user_id, $new_exp);
    }
    
    /**
     * Check for level up
     */
    private function check_level_up($user_id, $experience) {
        $current_level = get_user_meta($user_id, 'membershiping_level', true) ?: 1;
        $required_exp = $this->calculate_required_experience($current_level + 1);
        
        if ($experience >= $required_exp) {
            $new_level = $current_level + 1;
            update_user_meta($user_id, 'membershiping_level', $new_level);
            
            // Level up rewards
            $this->apply_level_up_rewards($user_id, $new_level);
            
            // Log level up
            $this->security->log_security_event('level_up', $user_id, array(
                'old_level' => $current_level,
                'new_level' => $new_level,
                'experience' => $experience
            ));
        }
    }
    
    /**
     * Calculate required experience for level
     */
    private function calculate_required_experience($level) {
        return $level * 100 + ($level - 1) * 50; // Example formula
    }
    
    /**
     * Apply level up rewards
     */
    private function apply_level_up_rewards($user_id, $level) {
        // Award currency based on level
        $currency_reward = $level * 10;
        $currencies = $this->currencies->get_all_currencies();
        
        if (!empty($currencies)) {
            $primary_currency = $currencies[0];
            $this->currencies->add_user_currency($user_id, $primary_currency->id, $currency_reward, 'level_up', "Level $level reward");
        }
        
        // Award items every 5 levels
        if ($level % 5 === 0) {
            $reward_items = $this->get_level_reward_items($level);
            foreach ($reward_items as $item_id => $quantity) {
                $this->items->add_user_item($user_id, $item_id, $quantity);
            }
        }
    }
    
    /**
     * Get level reward items
     */
    private function get_level_reward_items($level) {
        // This would be configurable in admin
        $rewards = array();
        
        if ($level >= 5) {
            $rewards[1] = 1; // Example: Health Potion
        }
        
        if ($level >= 10) {
            $rewards[2] = 1; // Example: Mana Potion
        }
        
        return $rewards;
    }
    
    /**
     * Get product ID by item ID
     */
    private function get_product_id_by_item($item_id) {
        global $wpdb;
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_membershiping_inventory_item_id' AND meta_value = %d",
                $item_id
            )
        );
    }
    
    /**
     * AJAX Handlers
     */
    
    public function ajax_consume_item() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $user_id = get_current_user_id();
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        $result = $this->consume_item($user_id, $item_id, $quantity);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'effects' => $result,
                'message' => __('Item consumed successfully!', 'membershiping-inventory')
            ));
        }
    }
    
    public function ajax_open_gift_box() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $user_id = get_current_user_id();
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        
        $result = $this->open_gift_box($user_id, $item_id, $quantity);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'rewards' => $result,
                'message' => __('Gift box opened successfully!', 'membershiping-inventory')
            ));
        }
    }
    
    public function ajax_upgrade_item() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $user_id = get_current_user_id();
        $nft_id = intval($_POST['nft_id'] ?? 0);
        $materials = json_decode(stripslashes($_POST['materials'] ?? '[]'), true);
        
        $result = $this->upgrade_item_rarity($user_id, $nft_id, $materials);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_get_consumable_effects() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $item_id = intval($_POST['item_id'] ?? 0);
        $config = $this->get_consumable_config($item_id);
        
        wp_send_json_success($config);
    }
    
    /**
     * Add consumable product fields
     */
    public function add_consumable_product_fields() {
        global $post;
        
        echo '<div class="options_group">';
        
        woocommerce_wp_checkbox(array(
            'id' => '_membershiping_is_consumable',
            'label' => __('Is Consumable', 'membershiping-inventory'),
            'description' => __('This item can be consumed for effects', 'membershiping-inventory')
        ));
        
        woocommerce_wp_checkbox(array(
            'id' => '_membershiping_is_gift_box',
            'label' => __('Is Gift Box', 'membershiping-inventory'),
            'description' => __('This item is a gift box with random rewards', 'membershiping-inventory')
        ));
        
        echo '</div>';
    }
    
    /**
     * Save consumable product fields
     */
    public function save_consumable_product_fields($post_id) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $is_consumable = isset($_POST['_membershiping_is_consumable']) ? 'yes' : 'no';
        $is_gift_box = isset($_POST['_membershiping_is_gift_box']) ? 'yes' : 'no';
        
        update_post_meta($post_id, '_membershiping_is_consumable', $is_consumable);
        update_post_meta($post_id, '_membershiping_is_gift_box', $is_gift_box);
    }
    
    /**
     * Add consumable meta boxes
     */
    public function add_consumable_meta_boxes() {
        add_meta_box(
            'membershiping_consumable_settings',
            __('Consumable & Gift Box Settings', 'membershiping-inventory'),
            array($this, 'render_consumable_meta_box'),
            'product',
            'normal',
            'default'
        );
    }
    
    /**
     * Render consumable meta box
     */
    public function render_consumable_meta_box($post) {
        wp_nonce_field('membershiping_consumable_meta', 'membershiping_consumable_nonce');
        
        $is_consumable = get_post_meta($post->ID, '_membershiping_is_consumable', true);
        $is_gift_box = get_post_meta($post->ID, '_membershiping_is_gift_box', true);
        $consumable_config = get_post_meta($post->ID, '_membershiping_consumable_config', true) ?: array();
        $gift_box_config = get_post_meta($post->ID, '_membershiping_gift_box_config', true) ?: array();
        
        ?>
        <div class="consumable-settings">
            <h4><?php _e('Consumable Effects', 'membershiping-inventory'); ?></h4>
            <p><?php _e('Configure what happens when this item is consumed.', 'membershiping-inventory'); ?></p>
            
            <!-- Consumable configuration would go here -->
            <div id="consumable-config" style="<?php echo $is_consumable === 'yes' ? '' : 'display:none;'; ?>">
                <p><em><?php _e('Consumable configuration interface would be implemented here.', 'membershiping-inventory'); ?></em></p>
            </div>
            
            <h4><?php _e('Gift Box Rewards', 'membershiping-inventory'); ?></h4>
            <p><?php _e('Configure what rewards this gift box can contain.', 'membershiping-inventory'); ?></p>
            
            <!-- Gift box configuration would go here -->
            <div id="gift-box-config" style="<?php echo $is_gift_box === 'yes' ? '' : 'display:none;'; ?>">
                <p><em><?php _e('Gift box configuration interface would be implemented here.', 'membershiping-inventory'); ?></em></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#_membershiping_is_consumable').change(function() {
                if ($(this).is(':checked')) {
                    $('#consumable-config').show();
                } else {
                    $('#consumable-config').hide();
                }
            });
            
            $('#_membershiping_is_gift_box').change(function() {
                if ($(this).is(':checked')) {
                    $('#gift-box-config').show();
                } else {
                    $('#gift-box-config').hide();
                }
            });
        });
        </script>
        <?php
    }
}
