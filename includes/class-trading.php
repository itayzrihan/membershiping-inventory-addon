<?php
/**
 * Trading system for Membershiping Inventory
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_Trading {
    
    private $wpdb;
    private $database;
    private $security;
    private $items;
    private $nfts;
    private $currencies;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = new Membershiping_Inventory_Security();
        $this->items = new Membershiping_Inventory_Items();
        $this->nfts = new Membershiping_Inventory_NFTs();
        $this->currencies = new Membershiping_Inventory_Currencies();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers for trading
        add_action('wp_ajax_membershiping_inventory_create_trade', array($this, 'ajax_create_trade'));
        add_action('wp_ajax_membershiping_inventory_accept_trade', array($this, 'ajax_accept_trade'));
        add_action('wp_ajax_membershiping_inventory_decline_trade', array($this, 'ajax_decline_trade'));
        add_action('wp_ajax_membershiping_inventory_cancel_trade', array($this, 'ajax_cancel_trade'));
        add_action('wp_ajax_membershiping_inventory_get_trades', array($this, 'ajax_get_trades'));
        add_action('wp_ajax_membershiping_inventory_search_users', array($this, 'ajax_search_users'));
        
        // Scheduled cleanup of expired trades
        add_action('membershiping_inventory_cleanup_trades', array($this, 'cleanup_expired_trades'));
        if (!wp_next_scheduled('membershiping_inventory_cleanup_trades')) {
            wp_schedule_event(time(), 'hourly', 'membershiping_inventory_cleanup_trades');
        }
    }
    
    /**
     * Create a trade request
     */
    public function create_trade($requester_id, $recipient_id, $trade_data) {
        // Validate users
        if (!$this->security->validate_user_exists($requester_id) || 
            !$this->security->validate_user_exists($recipient_id)) {
            return new WP_Error('invalid_users', 'Invalid users specified');
        }
        
        if ($requester_id === $recipient_id) {
            return new WP_Error('self_trade', 'Cannot trade with yourself');
        }
        
        // Rate limiting
        if (!$this->security->check_rate_limit('trade_creation', $requester_id, 10, 3600)) {
            return new WP_Error('rate_limit', 'Too many trade requests. Please wait before creating another.');
        }
        
        // Validate trade data
        $validation = $this->validate_trade_data($trade_data, $requester_id);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Check if users haven't blocked each other
        if ($this->is_user_blocked($requester_id, $recipient_id) || 
            $this->is_user_blocked($recipient_id, $requester_id)) {
            return new WP_Error('user_blocked', 'Trading with this user is not allowed');
        }
        
        // Check for existing pending trades between users
        $existing_trade = $this->get_pending_trade_between_users($requester_id, $recipient_id);
        if ($existing_trade) {
            return new WP_Error('trade_exists', 'A pending trade already exists between these users');
        }
        
        // Calculate trade value for fairness check
        $requester_value = $this->calculate_trade_value($trade_data['requester_items'], $trade_data['requester_currencies']);
        $recipient_value = $this->calculate_trade_value($trade_data['recipient_items'], $trade_data['recipient_currencies']);
        
        // Create trade record
        $trade_id = $this->wpdb->insert(
            $this->database->get_table_name('trades'),
            array(
                'requester_id' => $requester_id,
                'recipient_id' => $recipient_id,
                'requester_items' => json_encode($trade_data['requester_items']),
                'recipient_items' => json_encode($trade_data['recipient_items']),
                'requester_currencies' => json_encode($trade_data['requester_currencies']),
                'recipient_currencies' => json_encode($trade_data['recipient_currencies']),
                'requester_value' => $requester_value,
                'recipient_value' => $recipient_value,
                'message' => sanitize_textarea_field($trade_data['message'] ?? ''),
                'status' => 'pending',
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s')
        );
        
        if (!$trade_id) {
            return new WP_Error('db_error', 'Failed to create trade');
        }
        
        // Reserve items to prevent double-trading
        $this->reserve_trade_items($trade_id, $requester_id, $trade_data['requester_items']);
        
        // Log the trade creation
        $this->security->log_security_event('trade_created', $requester_id, array(
            'trade_id' => $trade_id,
            'recipient_id' => $recipient_id,
            'requester_value' => $requester_value,
            'recipient_value' => $recipient_value
        ));
        
        // Send notification to recipient
        $this->send_trade_notification($trade_id, 'new_trade');
        
        return $trade_id;
    }
    
    /**
     * Accept a trade
     */
    public function accept_trade($trade_id, $user_id) {
        $trade = $this->get_trade($trade_id);
        
        if (!$trade) {
            return new WP_Error('trade_not_found', 'Trade not found');
        }
        
        if ($trade->recipient_id != $user_id) {
            return new WP_Error('permission_denied', 'You cannot accept this trade');
        }
        
        if ($trade->status !== 'pending') {
            return new WP_Error('invalid_status', 'Trade is no longer pending');
        }
        
        if (strtotime($trade->expires_at) < time()) {
            $this->update_trade_status($trade_id, 'expired');
            return new WP_Error('trade_expired', 'Trade has expired');
        }
        
        // Rate limiting
        if (!$this->security->check_rate_limit('trade_acceptance', $user_id, 20, 3600)) {
            return new WP_Error('rate_limit', 'Too many trade acceptances. Please wait.');
        }
        
        // Validate that both users still have the required items/currencies
        $validation = $this->validate_trade_execution($trade);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Execute the trade
        $result = $this->execute_trade($trade);
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update trade status
        $this->update_trade_status($trade_id, 'completed');
        
        // Log the trade acceptance
        $this->security->log_security_event('trade_accepted', $user_id, array(
            'trade_id' => $trade_id,
            'requester_id' => $trade->requester_id
        ));
        
        // Send notifications
        $this->send_trade_notification($trade_id, 'trade_completed');
        
        return true;
    }
    
    /**
     * Decline a trade
     */
    public function decline_trade($trade_id, $user_id, $reason = '') {
        $trade = $this->get_trade($trade_id);
        
        if (!$trade) {
            return new WP_Error('trade_not_found', 'Trade not found');
        }
        
        if ($trade->recipient_id != $user_id) {
            return new WP_Error('permission_denied', 'You cannot decline this trade');
        }
        
        if ($trade->status !== 'pending') {
            return new WP_Error('invalid_status', 'Trade is no longer pending');
        }
        
        // Update trade status
        $this->update_trade_status($trade_id, 'declined', $reason);
        
        // Release reserved items
        $this->release_trade_items($trade_id);
        
        // Log the trade decline
        $this->security->log_security_event('trade_declined', $user_id, array(
            'trade_id' => $trade_id,
            'requester_id' => $trade->requester_id,
            'reason' => $reason
        ));
        
        // Send notification to requester
        $this->send_trade_notification($trade_id, 'trade_declined');
        
        return true;
    }
    
    /**
     * Cancel a trade (by requester)
     */
    public function cancel_trade($trade_id, $user_id) {
        $trade = $this->get_trade($trade_id);
        
        if (!$trade) {
            return new WP_Error('trade_not_found', 'Trade not found');
        }
        
        if ($trade->requester_id != $user_id) {
            return new WP_Error('permission_denied', 'You cannot cancel this trade');
        }
        
        if ($trade->status !== 'pending') {
            return new WP_Error('invalid_status', 'Trade is no longer pending');
        }
        
        // Update trade status
        $this->update_trade_status($trade_id, 'cancelled');
        
        // Release reserved items
        $this->release_trade_items($trade_id);
        
        // Log the trade cancellation
        $this->security->log_security_event('trade_cancelled', $user_id, array(
            'trade_id' => $trade_id,
            'recipient_id' => $trade->recipient_id
        ));
        
        // Send notification to recipient
        $this->send_trade_notification($trade_id, 'trade_cancelled');
        
        return true;
    }
    
    /**
     * Get trade by ID
     */
    public function get_trade($trade_id) {
        $table_name = $this->database->get_table_name('trades');
        
        $trade = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $trade_id
            )
        );
        
        if ($trade) {
            // Decode JSON fields
            $trade->requester_items = json_decode($trade->requester_items, true);
            $trade->recipient_items = json_decode($trade->recipient_items, true);
            $trade->requester_currencies = json_decode($trade->requester_currencies, true);
            $trade->recipient_currencies = json_decode($trade->recipient_currencies, true);
        }
        
        return $trade;
    }
    
    /**
     * Get user trades
     */
    public function get_user_trades($user_id, $status = null, $limit = 20, $offset = 0) {
        $table_name = $this->database->get_table_name('trades');
        
        $where_conditions = array(
            $this->wpdb->prepare("(requester_id = %d OR recipient_id = %d)", $user_id, $user_id)
        );
        
        if ($status) {
            $where_conditions[] = $this->wpdb->prepare("status = %s", $status);
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $trades = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT t.*, 
                        r.display_name as requester_name, 
                        rec.display_name as recipient_name
                 FROM {$table_name} t
                 LEFT JOIN {$this->wpdb->users} r ON t.requester_id = r.ID
                 LEFT JOIN {$this->wpdb->users} rec ON t.recipient_id = rec.ID
                 {$where_clause}
                 ORDER BY t.created_at DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
        
        // Decode JSON fields for each trade
        foreach ($trades as $trade) {
            $trade->requester_items = json_decode($trade->requester_items, true);
            $trade->recipient_items = json_decode($trade->recipient_items, true);
            $trade->requester_currencies = json_decode($trade->requester_currencies, true);
            $trade->recipient_currencies = json_decode($trade->recipient_currencies, true);
        }
        
        return $trades;
    }
    
    /**
     * Validate trade data
     */
    private function validate_trade_data($trade_data, $user_id) {
        // Check required fields
        if (empty($trade_data['requester_items']) && empty($trade_data['requester_currencies'])) {
            return new WP_Error('empty_offer', 'You must offer at least one item or currency');
        }
        
        if (empty($trade_data['recipient_items']) && empty($trade_data['recipient_currencies'])) {
            return new WP_Error('empty_request', 'You must request at least one item or currency');
        }
        
        // Validate requester owns the items they're offering
        if (!empty($trade_data['requester_items'])) {
            foreach ($trade_data['requester_items'] as $item) {
                if ($item['type'] === 'nft') {
                    $nft = $this->nfts->get_nft($item['nft_id']);
                    if (!$nft || $nft->owner_id != $user_id || !$nft->is_tradeable) {
                        return new WP_Error('invalid_nft', 'Invalid or non-tradeable NFT specified');
                    }
                } else {
                    $user_item = $this->items->get_user_item($user_id, $item['item_id']);
                    if (!$user_item || $user_item->quantity < $item['quantity']) {
                        return new WP_Error('insufficient_items', 'Insufficient item quantity');
                    }
                    
                    $item_details = $this->items->get_item($item['item_id']);
                    if (!$item_details || !$item_details->is_tradeable) {
                        return new WP_Error('non_tradeable', 'One or more items cannot be traded');
                    }
                }
            }
        }
        
        // Validate requester has the currencies they're offering
        if (!empty($trade_data['requester_currencies'])) {
            foreach ($trade_data['requester_currencies'] as $currency) {
                $balance = $this->currencies->get_user_balance($user_id, $currency['currency_id']);
                if ($balance < $currency['amount']) {
                    return new WP_Error('insufficient_currency', 'Insufficient currency balance');
                }
            }
        }
        
        return true;
    }
    
    /**
     * Validate trade can be executed
     */
    private function validate_trade_execution($trade) {
        // Check requester still has offered items
        if (!empty($trade->requester_items)) {
            foreach ($trade->requester_items as $item) {
                if ($item['type'] === 'nft') {
                    $nft = $this->nfts->get_nft($item['nft_id']);
                    if (!$nft || $nft->owner_id != $trade->requester_id) {
                        return new WP_Error('item_unavailable', 'Requester no longer owns offered NFT');
                    }
                } else {
                    $user_item = $this->items->get_user_item($trade->requester_id, $item['item_id']);
                    if (!$user_item || $user_item->quantity < $item['quantity']) {
                        return new WP_Error('item_unavailable', 'Requester no longer has sufficient item quantity');
                    }
                }
            }
        }
        
        // Check requester still has offered currencies
        if (!empty($trade->requester_currencies)) {
            foreach ($trade->requester_currencies as $currency) {
                $balance = $this->currencies->get_user_balance($trade->requester_id, $currency['currency_id']);
                if ($balance < $currency['amount']) {
                    return new WP_Error('currency_unavailable', 'Requester no longer has sufficient currency');
                }
            }
        }
        
        // Check recipient has space/ability to receive items
        // This could include inventory limits, etc.
        
        return true;
    }
    
    /**
     * Execute the actual trade
     */
    private function execute_trade($trade) {
        // Start database transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Transfer items from requester to recipient
            if (!empty($trade->requester_items)) {
                foreach ($trade->requester_items as $item) {
                    if ($item['type'] === 'nft') {
                        $result = $this->nfts->transfer_nft($item['nft_id'], $trade->recipient_id);
                        if (is_wp_error($result)) {
                            throw new Exception($result->get_error_message());
                        }
                    } else {
                        // Remove from requester
                        $result = $this->items->remove_user_item($trade->requester_id, $item['item_id'], $item['quantity']);
                        if (is_wp_error($result)) {
                            throw new Exception($result->get_error_message());
                        }
                        
                        // Add to recipient
                        $result = $this->items->add_user_item($trade->recipient_id, $item['item_id'], $item['quantity']);
                        if (is_wp_error($result)) {
                            throw new Exception($result->get_error_message());
                        }
                    }
                }
            }
            
            // Transfer items from recipient to requester
            if (!empty($trade->recipient_items)) {
                foreach ($trade->recipient_items as $item) {
                    if ($item['type'] === 'nft') {
                        $result = $this->nfts->transfer_nft($item['nft_id'], $trade->requester_id);
                        if (is_wp_error($result)) {
                            throw new Exception($result->get_error_message());
                        }
                    } else {
                        // Remove from recipient
                        $result = $this->items->remove_user_item($trade->recipient_id, $item['item_id'], $item['quantity']);
                        if (is_wp_error($result)) {
                            throw new Exception($result->get_error_message());
                        }
                        
                        // Add to requester
                        $result = $this->items->add_user_item($trade->requester_id, $item['item_id'], $item['quantity']);
                        if (is_wp_error($result)) {
                            throw new Exception($result->get_error_message());
                        }
                    }
                }
            }
            
            // Transfer currencies from requester to recipient
            if (!empty($trade->requester_currencies)) {
                foreach ($trade->requester_currencies as $currency) {
                    $result = $this->currencies->transfer_currency(
                        $trade->requester_id,
                        $trade->recipient_id,
                        $currency['currency_id'],
                        $currency['amount'],
                        'trade',
                        "Trade #{$trade->id}"
                    );
                    if (is_wp_error($result)) {
                        throw new Exception($result->get_error_message());
                    }
                }
            }
            
            // Transfer currencies from recipient to requester
            if (!empty($trade->recipient_currencies)) {
                foreach ($trade->recipient_currencies as $currency) {
                    $result = $this->currencies->transfer_currency(
                        $trade->recipient_id,
                        $trade->requester_id,
                        $currency['currency_id'],
                        $currency['amount'],
                        'trade',
                        "Trade #{$trade->id}"
                    );
                    if (is_wp_error($result)) {
                        throw new Exception($result->get_error_message());
                    }
                }
            }
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            
            // Release reserved items
            $this->release_trade_items($trade->id);
            
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->wpdb->query('ROLLBACK');
            return new WP_Error('trade_execution_failed', $e->getMessage());
        }
    }
    
    /**
     * Calculate trade value for fairness assessment
     */
    private function calculate_trade_value($items, $currencies) {
        $total_value = 0;
        
        // Calculate item values
        if (!empty($items)) {
            foreach ($items as $item) {
                if ($item['type'] === 'nft') {
                    $nft = $this->nfts->get_nft($item['nft_id']);
                    if ($nft) {
                        // NFT value could be based on rarity, upgrade level, etc.
                        $base_value = $this->get_rarity_value($nft->rarity);
                        $upgrade_multiplier = 1 + ($nft->upgrade_level * 0.2);
                        $total_value += $base_value * $upgrade_multiplier;
                    }
                } else {
                    $item_details = $this->items->get_item($item['item_id']);
                    if ($item_details) {
                        $item_value = $this->get_item_base_value($item_details) * $item['quantity'];
                        $total_value += $item_value;
                    }
                }
            }
        }
        
        // Calculate currency values
        if (!empty($currencies)) {
            foreach ($currencies as $currency) {
                $currency_details = $this->currencies->get_currency($currency['currency_id']);
                if ($currency_details) {
                    // Convert to base value using exchange rate
                    $total_value += $currency['amount'] * $currency_details->exchange_rate;
                }
            }
        }
        
        return $total_value;
    }
    
    /**
     * Get rarity base value
     */
    private function get_rarity_value($rarity) {
        $values = array(
            'common' => 10,
            'uncommon' => 25,
            'rare' => 50,
            'epic' => 100,
            'legendary' => 250,
            'mythic' => 500
        );
        
        return $values[$rarity] ?? 10;
    }
    
    /**
     * Get item base value
     */
    private function get_item_base_value($item) {
        // This could be enhanced with more sophisticated valuation
        $base_value = $this->get_rarity_value($item->rarity);
        
        // Adjust for item type
        $type_multipliers = array(
            'consumable' => 0.5,
            'equipment' => 2.0,
            'collectible' => 1.5,
            'material' => 0.8,
            'gift_box' => 1.2
        );
        
        $multiplier = $type_multipliers[$item->item_type] ?? 1.0;
        return $base_value * $multiplier;
    }
    
    /**
     * Reserve items for trading
     */
    private function reserve_trade_items($trade_id, $user_id, $items) {
        if (empty($items)) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item['type'] === 'nft') {
                // Mark NFT as reserved
                $this->wpdb->update(
                    $this->database->get_table_name('user_nfts'),
                    array('is_reserved' => 1, 'reserved_for_trade' => $trade_id),
                    array('id' => $item['nft_id']),
                    array('%d', '%d'),
                    array('%d')
                );
            } else {
                // Create or update reservation record
                $table_name = $this->database->get_table_name('item_reservations');
                $this->wpdb->replace(
                    $table_name,
                    array(
                        'user_id' => $user_id,
                        'item_id' => $item['item_id'],
                        'quantity' => $item['quantity'],
                        'trade_id' => $trade_id,
                        'reserved_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%d', '%s')
                );
            }
        }
    }
    
    /**
     * Release reserved items
     */
    private function release_trade_items($trade_id) {
        // Release NFT reservations
        $this->wpdb->update(
            $this->database->get_table_name('user_nfts'),
            array('is_reserved' => 0, 'reserved_for_trade' => null),
            array('reserved_for_trade' => $trade_id),
            array('%d', '%s'),
            array('%d')
        );
        
        // Release item reservations
        $this->wpdb->delete(
            $this->database->get_table_name('item_reservations'),
            array('trade_id' => $trade_id),
            array('%d')
        );
    }
    
    /**
     * Update trade status
     */
    private function update_trade_status($trade_id, $status, $reason = '') {
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($reason) {
            $update_data['decline_reason'] = $reason;
        }
        
        if ($status === 'completed') {
            $update_data['completed_at'] = current_time('mysql');
        }
        
        $this->wpdb->update(
            $this->database->get_table_name('trades'),
            $update_data,
            array('id' => $trade_id),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Check if user is blocked
     */
    private function is_user_blocked($user_id, $blocked_user_id) {
        // This could be integrated with a user blocking system
        // For now, return false
        return false;
    }
    
    /**
     * Get pending trade between users
     */
    private function get_pending_trade_between_users($user1, $user2) {
        $table_name = $this->database->get_table_name('trades');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$table_name} 
                 WHERE ((requester_id = %d AND recipient_id = %d) OR (requester_id = %d AND recipient_id = %d))
                 AND status = 'pending'
                 LIMIT 1",
                $user1, $user2, $user2, $user1
            )
        );
    }
    
    /**
     * Send trade notification
     */
    private function send_trade_notification($trade_id, $type) {
        $trade = $this->get_trade($trade_id);
        if (!$trade) {
            return;
        }
        
        switch ($type) {
            case 'new_trade':
                $this->send_email_notification($trade->recipient_id, 'new_trade', $trade);
                break;
            case 'trade_completed':
                $this->send_email_notification($trade->requester_id, 'trade_completed', $trade);
                $this->send_email_notification($trade->recipient_id, 'trade_completed', $trade);
                break;
            case 'trade_declined':
                $this->send_email_notification($trade->requester_id, 'trade_declined', $trade);
                break;
            case 'trade_cancelled':
                $this->send_email_notification($trade->recipient_id, 'trade_cancelled', $trade);
                break;
        }
    }
    
    /**
     * Send email notification
     */
    private function send_email_notification($user_id, $type, $trade) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $requester = get_user_by('id', $trade->requester_id);
        $recipient = get_user_by('id', $trade->recipient_id);
        
        $subject = '';
        $message = '';
        
        switch ($type) {
            case 'new_trade':
                $subject = sprintf(__('New Trade Request from %s', 'membershiping-inventory'), $requester->display_name);
                $message = sprintf(
                    __('You have received a new trade request from %s. Please log in to your account to review and respond to this trade.', 'membershiping-inventory'),
                    $requester->display_name
                );
                break;
                
            case 'trade_completed':
                $subject = __('Trade Completed Successfully', 'membershiping-inventory');
                $message = sprintf(
                    __('Your trade with %s has been completed successfully. Check your inventory to see your new items!', 'membershiping-inventory'),
                    $user_id === $trade->requester_id ? $recipient->display_name : $requester->display_name
                );
                break;
                
            case 'trade_declined':
                $subject = __('Trade Request Declined', 'membershiping-inventory');
                $message = sprintf(
                    __('Your trade request to %s has been declined.', 'membershiping-inventory'),
                    $recipient->display_name
                );
                break;
                
            case 'trade_cancelled':
                $subject = __('Trade Request Cancelled', 'membershiping-inventory');
                $message = sprintf(
                    __('The trade request from %s has been cancelled.', 'membershiping-inventory'),
                    $requester->display_name
                );
                break;
        }
        
        if ($subject && $message) {
            wp_mail($user->user_email, $subject, $message);
        }
    }
    
    /**
     * Cleanup expired trades
     */
    public function cleanup_expired_trades() {
        $table_name = $this->database->get_table_name('trades');
        
        // Get expired trades
        $expired_trades = $this->wpdb->get_results(
            "SELECT id FROM {$table_name} 
             WHERE status = 'pending' AND expires_at < NOW()"
        );
        
        foreach ($expired_trades as $trade) {
            $this->update_trade_status($trade->id, 'expired');
            $this->release_trade_items($trade->id);
        }
        
        // Log cleanup
        if (!empty($expired_trades)) {
            $this->security->log_security_event('trades_expired', 0, array(
                'count' => count($expired_trades),
                'trade_ids' => wp_list_pluck($expired_trades, 'id')
            ));
        }
    }
    
    /**
     * Get trade statistics
     */
    public function get_trade_statistics($user_id = null) {
        $table_name = $this->database->get_table_name('trades');
        $where = $user_id ? $this->wpdb->prepare("WHERE (requester_id = %d OR recipient_id = %d)", $user_id, $user_id) : '';
        
        $stats = $this->wpdb->get_row(
            "SELECT 
                COUNT(*) as total_trades,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_trades,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_trades,
                SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined_trades,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_trades,
                AVG(requester_value + recipient_value) as avg_trade_value
             FROM {$table_name} {$where}"
        );
        
        return $stats;
    }
    
    /**
     * AJAX Handlers
     */
    
    public function ajax_create_trade() {
        check_ajax_referer('membershiping_inventory_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $user_id = get_current_user_id();
        $recipient_id = intval($_POST['recipient_id'] ?? 0);
        
        $trade_data = array(
            'requester_items' => json_decode(stripslashes($_POST['requester_items'] ?? '[]'), true),
            'recipient_items' => json_decode(stripslashes($_POST['recipient_items'] ?? '[]'), true),
            'requester_currencies' => json_decode(stripslashes($_POST['requester_currencies'] ?? '[]'), true),
            'recipient_currencies' => json_decode(stripslashes($_POST['recipient_currencies'] ?? '[]'), true),
            'message' => sanitize_textarea_field($_POST['message'] ?? '')
        );
        
        $result = $this->create_trade($user_id, $recipient_id, $trade_data);
        
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
        
        $trade_id = intval($_POST['trade_id'] ?? 0);
        $user_id = get_current_user_id();
        
        $result = $this->accept_trade($trade_id, $user_id);
        
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
        
        $trade_id = intval($_POST['trade_id'] ?? 0);
        $user_id = get_current_user_id();
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        
        $result = $this->decline_trade($trade_id, $user_id, $reason);
        
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
        
        $trade_id = intval($_POST['trade_id'] ?? 0);
        $user_id = get_current_user_id();
        
        $result = $this->cancel_trade($trade_id, $user_id);
        
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
        
        $user_id = get_current_user_id();
        $status = sanitize_text_field($_POST['status'] ?? '');
        $limit = intval($_POST['limit'] ?? 20);
        $offset = intval($_POST['offset'] ?? 0);
        
        $trades = $this->get_user_trades($user_id, $status ?: null, $limit, $offset);
        
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
}
