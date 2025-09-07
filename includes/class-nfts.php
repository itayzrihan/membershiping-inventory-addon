<?php
/**
 * NFT management class for Membershiping Inventory System
 */

if (!defined('ABSPATH')) {
    exit;
}

class Membershiping_Inventory_NFTs {
    
    private $wpdb;
    private $database;
    private $security;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->database = new Membershiping_Inventory_Database();
        $this->security = new Membershiping_Inventory_Security();
    }
    
    /**
     * Mint a new NFT
     */
    public function mint_nft($item_id, $owner_id, $rarity = 'common', $metadata = array()) {
        $items_table = $this->database->get_table('items');
        $nfts_table = $this->database->get_table('nfts');
        
        // Verify item exists
        $item = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $items_table WHERE id = %d AND status = 'active'",
                $item_id
            )
        );
        
        if (!$item) {
            return new WP_Error('item_not_found', 'Item not found or inactive');
        }
        
        // Verify user exists
        $user = get_user_by('id', $owner_id);
        if (!$user) {
            return new WP_Error('user_not_found', 'User not found');
        }
        
        // Check quantity limits
        if ($item->quantity_limit && $item->current_quantity >= $item->quantity_limit) {
            return new WP_Error('quantity_limit', 'Item quantity limit reached');
        }
        
        // Generate unique identifiers
        $nft_hash = $this->generate_unique_hash($item_id, $owner_id);
        $nft_token = $this->generate_unique_token($item_id, $owner_id);
        $mint_transaction_id = $this->generate_mint_transaction_id();
        
        // Prepare metadata
        $nft_metadata = array_merge(array(
            'mint_timestamp' => time(),
            'mint_block' => $this->get_current_block(),
            'original_rarity' => $rarity,
            'generation' => 1,
            'authenticity_score' => $this->calculate_authenticity_score($item_id, $owner_id),
            'creation_method' => 'purchase'
        ), $metadata);
        
        // Mint the NFT
        $result = $this->wpdb->insert(
            $nfts_table,
            array(
                'item_id' => $item_id,
                'nft_hash' => $nft_hash,
                'nft_token' => $nft_token,
                'owner_id' => $owner_id,
                'original_owner_id' => $owner_id,
                'rarity' => $rarity,
                'upgrade_level' => 0,
                'custom_stats' => null,
                'custom_image' => null,
                'metadata' => wp_json_encode($nft_metadata),
                'is_tradeable' => $item->is_tradeable,
                'mint_transaction_id' => $mint_transaction_id
            ),
            array('%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('mint_failed', 'Failed to mint NFT');
        }
        
        $nft_id = $this->wpdb->insert_id;
        
        // Update item current quantity
        $this->wpdb->update(
            $items_table,
            array('current_quantity' => $item->current_quantity + 1),
            array('id' => $item_id),
            array('%d'),
            array('%d')
        );
        
        // Log the minting
        $this->security->log_security_event('nft_minted', $owner_id, array(
            'nft_id' => $nft_id,
            'item_id' => $item_id,
            'nft_token' => $nft_token,
            'rarity' => $rarity,
            'mint_transaction_id' => $mint_transaction_id
        ));
        
        do_action('membershiping_inventory_nft_minted', $nft_id, $item_id, $owner_id, $nft_token);
        
        return $nft_id;
    }
    
    /**
     * Transfer NFT ownership
     */
    public function transfer_nft($nft_id, $from_user_id, $to_user_id, $transfer_type = 'trade') {
        $nfts_table = $this->database->get_table('nfts');
        
        // Verify NFT exists and ownership
        $nft = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $nfts_table WHERE id = %d AND owner_id = %d",
                $nft_id,
                $from_user_id
            )
        );
        
        if (!$nft) {
            return new WP_Error('nft_not_found', 'NFT not found or not owned by user');
        }
        
        // Check if tradeable
        if (!$nft->is_tradeable) {
            return new WP_Error('not_tradeable', 'This NFT is not tradeable');
        }
        
        // Verify target user exists
        $to_user = get_user_by('id', $to_user_id);
        if (!$to_user) {
            return new WP_Error('user_not_found', 'Target user not found');
        }
        
        // Update ownership
        $result = $this->wpdb->update(
            $nfts_table,
            array('owner_id' => $to_user_id),
            array('id' => $nft_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('transfer_failed', 'Failed to transfer NFT');
        }
        
        // Log the transfer
        $this->security->log_security_event('nft_transferred', $from_user_id, array(
            'nft_id' => $nft_id,
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'transfer_type' => $transfer_type,
            'nft_token' => $nft->nft_token
        ));
        
        do_action('membershiping_inventory_nft_transferred', $nft_id, $from_user_id, $to_user_id, $transfer_type);
        
        return true;
    }
    
    /**
     * Upgrade NFT rarity
     */
    public function upgrade_nft($nft_id, $new_rarity, $custom_stats = null, $custom_image = null) {
        $nfts_table = $this->database->get_table('nfts');
        
        $nft = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $nfts_table WHERE id = %d",
                $nft_id
            )
        );
        
        if (!$nft) {
            return new WP_Error('nft_not_found', 'NFT not found');
        }
        
        // Validate rarity progression
        $rarity_levels = array('common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic');
        $current_level = array_search($nft->rarity, $rarity_levels);
        $new_level = array_search($new_rarity, $rarity_levels);
        
        if ($new_level === false || $new_level <= $current_level) {
            return new WP_Error('invalid_upgrade', 'Invalid rarity upgrade');
        }
        
        // Update NFT
        $update_data = array(
            'rarity' => $new_rarity,
            'upgrade_level' => $nft->upgrade_level + 1,
            'is_tradeable' => 0 // Upgraded items become untradeable
        );
        
        if ($custom_stats) {
            $update_data['custom_stats'] = wp_json_encode($custom_stats);
        }
        
        if ($custom_image) {
            $update_data['custom_image'] = $custom_image;
        }
        
        // Update metadata
        $metadata = json_decode($nft->metadata, true) ?: array();
        $metadata['upgrade_history'][] = array(
            'timestamp' => time(),
            'from_rarity' => $nft->rarity,
            'to_rarity' => $new_rarity,
            'upgrade_level' => $nft->upgrade_level + 1
        );
        $update_data['metadata'] = wp_json_encode($metadata);
        
        $result = $this->wpdb->update(
            $nfts_table,
            $update_data,
            array('id' => $nft_id)
        );
        
        if ($result === false) {
            return new WP_Error('upgrade_failed', 'Failed to upgrade NFT');
        }
        
        // Log the upgrade
        $this->security->log_security_event('nft_upgraded', $nft->owner_id, array(
            'nft_id' => $nft_id,
            'from_rarity' => $nft->rarity,
            'to_rarity' => $new_rarity,
            'upgrade_level' => $nft->upgrade_level + 1
        ));
        
        do_action('membershiping_inventory_nft_upgraded', $nft_id, $nft->rarity, $new_rarity);
        
        return true;
    }
    
    /**
     * Upgrade random item for user
     */
    public function upgrade_random_item($user_id, $item_id) {
        $nfts_table = $this->database->get_table('nfts');
        
        // Get all user's NFTs for this item
        $user_nfts = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM $nfts_table WHERE owner_id = %d AND item_id = %d AND rarity != 'mythic'",
                $user_id,
                $item_id
            )
        );
        
        if (empty($user_nfts)) {
            return new WP_Error('no_items', 'User has no upgradeable items of this type');
        }
        
        // Select random NFT
        $random_nft = $user_nfts[array_rand($user_nfts)];
        
        // Determine next rarity level
        $rarity_levels = array('common', 'uncommon', 'rare', 'epic', 'legendary', 'mythic');
        $current_level = array_search($random_nft->rarity, $rarity_levels);
        $next_rarity = $rarity_levels[$current_level + 1] ?? 'mythic';
        
        // Generate custom stats for upgraded item
        $custom_stats = $this->generate_upgrade_stats($random_nft, $next_rarity);
        
        return $this->upgrade_nft($random_nft->id, $next_rarity, $custom_stats);
    }
    
    /**
     * Get NFT by ID
     */
    public function get_nft($nft_id) {
        $nfts_table = $this->database->get_table('nfts');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $nfts_table WHERE id = %d",
                $nft_id
            )
        );
    }
    
    /**
     * Get NFT by token
     */
    public function get_nft_by_token($nft_token) {
        $nfts_table = $this->database->get_table('nfts');
        
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $nfts_table WHERE nft_token = %s",
                $nft_token
            )
        );
    }
    
    /**
     * Get user NFTs
     */
    public function get_user_nfts($user_id, $item_id = null, $rarity = null) {
        $nfts_table = $this->database->get_table('nfts');
        $items_table = $this->database->get_table('items');
        
        $where = "WHERE n.owner_id = %d";
        $params = array($user_id);
        
        if ($item_id) {
            $where .= " AND n.item_id = %d";
            $params[] = $item_id;
        }
        
        if ($rarity) {
            $where .= " AND n.rarity = %s";
            $params[] = $rarity;
        }
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT n.*, i.name as item_name, i.description as item_description, 
                        i.base_image, i.item_type
                FROM $nfts_table n 
                INNER JOIN $items_table i ON n.item_id = i.id 
                $where 
                ORDER BY n.created_at DESC",
                ...$params
            )
        );
    }
    
    /**
     * Verify NFT authenticity
     */
    public function verify_nft_authenticity($nft_token) {
        $nft = $this->get_nft_by_token($nft_token);
        
        if (!$nft) {
            return array(
                'valid' => false,
                'error' => 'NFT not found'
            );
        }
        
        // Verify hash integrity
        $expected_hash = $this->generate_unique_hash($nft->item_id, $nft->original_owner_id, strtotime($nft->created_at));
        
        if ($nft->nft_hash !== $expected_hash) {
            return array(
                'valid' => false,
                'error' => 'Hash verification failed'
            );
        }
        
        // Verify token format
        if (!$this->verify_token_format($nft_token)) {
            return array(
                'valid' => false,
                'error' => 'Invalid token format'
            );
        }
        
        return array(
            'valid' => true,
            'nft' => $nft,
            'authenticity_score' => $this->calculate_current_authenticity_score($nft)
        );
    }
    
    /**
     * Get NFT ownership history
     */
    public function get_nft_ownership_history($nft_id) {
        $logs_table = $this->database->get_table('logs');
        
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT l.*, u.display_name 
                FROM $logs_table l 
                LEFT JOIN {$this->wpdb->users} u ON l.user_id = u.ID 
                WHERE l.action IN ('nft_minted', 'nft_transferred') 
                AND JSON_EXTRACT(l.details, '$.nft_id') = %d 
                ORDER BY l.created_at ASC",
                $nft_id
            )
        );
    }
    
    /**
     * Generate NFT certificate
     */
    public function generate_nft_certificate($nft_id) {
        $nft = $this->get_nft($nft_id);
        if (!$nft) {
            return false;
        }
        
        $items_table = $this->database->get_table('items');
        $item = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $items_table WHERE id = %d",
                $nft->item_id
            )
        );
        
        $owner = get_user_by('id', $nft->owner_id);
        $original_owner = get_user_by('id', $nft->original_owner_id);
        
        $certificate = array(
            'certificate_id' => 'CERT-' . $nft->nft_token,
            'nft_token' => $nft->nft_token,
            'nft_hash' => $nft->nft_hash,
            'item_name' => $item->name,
            'item_type' => $item->item_type,
            'rarity' => $nft->rarity,
            'upgrade_level' => $nft->upgrade_level,
            'mint_date' => $nft->created_at,
            'current_owner' => $owner->display_name,
            'original_owner' => $original_owner->display_name,
            'authenticity_verified' => true,
            'generation_timestamp' => time(),
            'certificate_hash' => $this->generate_certificate_hash($nft)
        );
        
        return $certificate;
    }
    
    /**
     * Burn NFT (permanently destroy)
     */
    public function burn_nft($nft_id, $reason = 'user_request') {
        $nfts_table = $this->database->get_table('nfts');
        
        $nft = $this->get_nft($nft_id);
        if (!$nft) {
            return new WP_Error('nft_not_found', 'NFT not found');
        }
        
        // Create burn record in metadata
        $burn_record = array(
            'burn_timestamp' => time(),
            'burn_reason' => $reason,
            'burn_hash' => hash('sha256', $nft->nft_token . time()),
            'original_owner_id' => $nft->owner_id
        );
        
        // Archive NFT data before deletion
        $archive_data = array(
            'nft_id' => $nft_id,
            'nft_token' => $nft->nft_token,
            'nft_hash' => $nft->nft_hash,
            'item_id' => $nft->item_id,
            'original_data' => $nft,
            'burn_record' => $burn_record
        );
        
        // Store in options table as burned NFT record
        $burned_nfts = get_option('membershiping_inventory_burned_nfts', array());
        $burned_nfts[$nft->nft_token] = $archive_data;
        update_option('membershiping_inventory_burned_nfts', $burned_nfts);
        
        // Delete NFT
        $result = $this->wpdb->delete(
            $nfts_table,
            array('id' => $nft_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('burn_failed', 'Failed to burn NFT');
        }
        
        // Log the burn
        $this->security->log_security_event('nft_burned', $nft->owner_id, array(
            'nft_id' => $nft_id,
            'nft_token' => $nft->nft_token,
            'reason' => $reason,
            'burn_hash' => $burn_record['burn_hash']
        ));
        
        do_action('membershiping_inventory_nft_burned', $nft_id, $nft, $reason);
        
        return $burn_record['burn_hash'];
    }
    
    /**
     * Generate unique hash for NFT
     */
    private function generate_unique_hash($item_id, $user_id, $timestamp = null) {
        if (!$timestamp) {
            $timestamp = time();
        }
        
        $data = sprintf(
            '%d:%d:%d:%s:%s:%s',
            $item_id,
            $user_id,
            $timestamp,
            wp_generate_uuid4(),
            $this->security->generate_secure_token(16),
            get_option('membershiping_inventory_salt', wp_generate_password(32, false))
        );
        
        return hash('sha256', $data);
    }
    
    /**
     * Generate unique token for NFT
     */
    private function generate_unique_token($item_id, $user_id) {
        $timestamp = time();
        $random = bin2hex(random_bytes(4));
        
        return sprintf(
            'MINV-%04d-%04d-%010d-%s',
            $item_id,
            $user_id,
            $timestamp,
            strtoupper($random)
        );
    }
    
    /**
     * Generate mint transaction ID
     */
    private function generate_mint_transaction_id() {
        return 'MINT-' . time() . '-' . bin2hex(random_bytes(8));
    }
    
    /**
     * Get current "block" number (simulated blockchain concept)
     */
    private function get_current_block() {
        // Simple implementation: use day of year + year for pseudo-block concept
        return intval(date('Y') . str_pad(date('z'), 3, '0', STR_PAD_LEFT));
    }
    
    /**
     * Calculate authenticity score
     */
    private function calculate_authenticity_score($item_id, $user_id) {
        // Complex algorithm to generate authenticity score
        $factors = array(
            'user_reputation' => $this->get_user_reputation($user_id),
            'item_rarity' => $this->get_item_rarity_score($item_id),
            'timestamp_entropy' => $this->calculate_timestamp_entropy(),
            'random_factor' => mt_rand(85, 100)
        );
        
        $score = 0;
        foreach ($factors as $factor => $value) {
            $score += $value;
        }
        
        return min(100, max(50, intval($score / count($factors))));
    }
    
    /**
     * Calculate current authenticity score for verification
     */
    private function calculate_current_authenticity_score($nft) {
        $metadata = json_decode($nft->metadata, true) ?: array();
        $base_score = $metadata['authenticity_score'] ?? 75;
        
        // Factor in age and transfers
        $age_days = (time() - strtotime($nft->created_at)) / DAY_IN_SECONDS;
        $age_bonus = min(10, $age_days * 0.1); // Older NFTs get slight bonus
        
        $upgrade_bonus = $nft->upgrade_level * 2; // Upgraded items get bonus
        
        return min(100, $base_score + $age_bonus + $upgrade_bonus);
    }
    
    /**
     * Get user reputation score
     */
    private function get_user_reputation($user_id) {
        // Simple reputation based on account age and activity
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return 50;
        }
        
        $account_age_days = (time() - strtotime($user->user_registered)) / DAY_IN_SECONDS;
        $age_score = min(30, $account_age_days * 0.1);
        
        // Add points system integration if available
        if (class_exists('Membershiping_Points_System')) {
            $points_system = new Membershiping_Points_System();
            $user_points = $points_system->get_user_total_points($user_id);
            $points_score = min(20, $user_points * 0.01);
        } else {
            $points_score = 10;
        }
        
        return $age_score + $points_score + 50; // Base 50 + bonuses
    }
    
    /**
     * Get item rarity score
     */
    private function get_item_rarity_score($item_id) {
        $items_table = $this->database->get_table('items');
        $item = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT rarity FROM $items_table WHERE id = %d",
                $item_id
            )
        );
        
        if (!$item) {
            return 50;
        }
        
        $rarity_scores = array(
            'common' => 60,
            'uncommon' => 70,
            'rare' => 80,
            'epic' => 90,
            'legendary' => 95,
            'mythic' => 100
        );
        
        return $rarity_scores[$item->rarity] ?? 60;
    }
    
    /**
     * Calculate timestamp entropy
     */
    private function calculate_timestamp_entropy() {
        // Generate entropy based on current timestamp
        $timestamp = time();
        $entropy = 0;
        
        // Use various timestamp factors
        $entropy += ($timestamp % 100); // Last 2 digits
        $entropy += (($timestamp / 60) % 60); // Minutes
        $entropy += (($timestamp / 3600) % 24); // Hours
        
        return min(100, max(70, $entropy));
    }
    
    /**
     * Verify token format
     */
    private function verify_token_format($token) {
        // Verify MINV-####-####-##########-######## format
        return preg_match('/^MINV-\d{4}-\d{4}-\d{10}-[A-F0-9]{8}$/', $token);
    }
    
    /**
     * Generate upgrade stats
     */
    private function generate_upgrade_stats($nft, $new_rarity) {
        $base_stats = json_decode($nft->custom_stats, true) ?: array();
        
        // Rarity multipliers
        $multipliers = array(
            'common' => 1.0,
            'uncommon' => 1.2,
            'rare' => 1.5,
            'epic' => 2.0,
            'legendary' => 3.0,
            'mythic' => 5.0
        );
        
        $multiplier = $multipliers[$new_rarity] ?? 1.0;
        
        $upgraded_stats = array(
            'attack' => intval(($base_stats['attack'] ?? 10) * $multiplier),
            'defense' => intval(($base_stats['defense'] ?? 10) * $multiplier),
            'magic' => intval(($base_stats['magic'] ?? 5) * $multiplier),
            'luck' => intval(($base_stats['luck'] ?? 5) * $multiplier),
            'durability' => intval(($base_stats['durability'] ?? 100) * $multiplier)
        );
        
        // Add special properties for higher rarities
        if (in_array($new_rarity, array('legendary', 'mythic'))) {
            $upgraded_stats['special_abilities'] = array(
                'glow_effect' => true,
                'particle_effects' => $new_rarity === 'mythic',
                'unique_sound' => true
            );
        }
        
        return $upgraded_stats;
    }
    
    /**
     * Generate certificate hash
     */
    private function generate_certificate_hash($nft) {
        $data = sprintf(
            '%s:%s:%s:%d',
            $nft->nft_token,
            $nft->nft_hash,
            $nft->created_at,
            time()
        );
        
        return hash('sha256', $data);
    }
    
    /**
     * Get NFT statistics
     */
    public function get_nft_statistics() {
        $nfts_table = $this->database->get_table('nfts');
        
        return array(
            'total_nfts' => $this->wpdb->get_var("SELECT COUNT(*) FROM $nfts_table"),
            'by_rarity' => $this->wpdb->get_results(
                "SELECT rarity, COUNT(*) as count FROM $nfts_table GROUP BY rarity ORDER BY 
                CASE rarity 
                    WHEN 'common' THEN 1 
                    WHEN 'uncommon' THEN 2 
                    WHEN 'rare' THEN 3 
                    WHEN 'epic' THEN 4 
                    WHEN 'legendary' THEN 5 
                    WHEN 'mythic' THEN 6 
                END"
            ),
            'upgraded_nfts' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM $nfts_table WHERE upgrade_level > 0"
            ),
            'tradeable_nfts' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM $nfts_table WHERE is_tradeable = 1"
            )
        );
    }
}
