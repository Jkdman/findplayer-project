<?php
/**
 * User Sync Model
 * 
 * Handles synchronization between WordPress users and FindPlayer custom user types
 * Fixes duplicate user creation issue
 */

if (!defined('ABSPATH')) exit;

class FP_User_Sync {
    
    /**
     * Initialize user synchronization hooks
     */
    public static function init() {
        add_action('profile_update', [__CLASS__, 'sync_wp_user_with_player'], 10, 2);
        add_action('user_register', [__CLASS__, 'sync_wp_user_with_player'], 10, 1);
        add_filter('author_link', [__CLASS__, 'author_link_to_player'], 20, 3);
    }
    
    /**
     * Synchronize WordPress user data with FindPlayer player card
     * Updates display_name, nickname, and user_nicename to match player card
     * 
     * @param int $user_id WordPress user ID
     */
    public static function sync_wp_user_with_player($user_id) {
        // Get connected player card
        $player = get_posts([
            'post_type'   => 'fp_giocatore',
            'post_status' => 'publish',
            'meta_key'    => 'fp_wp_user_id',
            'meta_value'  => $user_id,
            'numberposts' => 1,
            'fields'      => 'ids'
        ]);
        
        if (empty($player)) {
            return;
        }
        
        $player_id = $player[0];
        
        // Get nickname from player card
        $nickname = get_post_meta($player_id, 'fp_nickname', true);
        if (!$nickname) {
            $nickname = get_the_title($player_id);
        }
        
        if (!$nickname) {
            return;
        }
        
        // Update WordPress user to match player card
        wp_update_user([
            'ID'            => $user_id,
            'display_name'  => $nickname,
            'nickname'      => $nickname,
            'user_nicename' => sanitize_title($nickname)
        ]);
    }
    
    /**
     * Override author link to point to player card instead of author archive
     * 
     * @param string $link            Author link URL
     * @param int    $author_id       Author user ID
     * @param string $author_nicename Author nicename
     * @return string Modified link pointing to player card
     */
    public static function author_link_to_player($link, $author_id, $author_nicename) {
        // Find player card connected to WordPress user
        $player = get_posts([
            'post_type'   => 'fp_giocatore',
            'post_status' => 'publish',
            'meta_key'    => 'fp_wp_user_id',
            'meta_value'  => $author_id,
            'numberposts' => 1,
            'fields'      => 'ids'
        ]);
        
        if (empty($player)) {
            return $link;
        }
        
        return get_permalink($player[0]);
    }
    
    /**
     * Get player ID for a given WordPress user
     * 
     * @param int $user_id WordPress user ID
     * @return int|null Player post ID or null if not found
     */
    public static function get_player_id_for_user($user_id) {
        $player = get_posts([
            'post_type'   => 'fp_giocatore',
            'post_status' => 'publish',
            'meta_key'    => 'fp_wp_user_id',
            'meta_value'  => $user_id,
            'numberposts' => 1,
            'fields'      => 'ids'
        ]);
        
        return !empty($player) ? $player[0] : null;
    }
}
