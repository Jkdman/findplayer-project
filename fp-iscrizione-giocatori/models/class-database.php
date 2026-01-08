<?php
/**
 * Database Model
 * 
 * Manages database tables for player registration and voting
 */

if (!defined('ABSPATH')) exit;

class FPIG_Database {
    
    /**
     * Create required database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        self::create_temp_players_table();
        self::create_votes_table();
    }
    
    /**
     * Create temporary players table
     */
    private static function create_temp_players_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_giocatori_temp';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(190) NOT NULL,
            token VARCHAR(64) NOT NULL,
            data LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            confirmed TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY token_idx (token),
            KEY email_idx (email),
            KEY created_idx (created_at)
        ) $charset;";
        
        dbDelta($sql);
    }
    
    /**
     * Create player votes table
     */
    private static function create_votes_table() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_player_votes';
        $charset = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            voter_id BIGINT UNSIGNED NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            sport VARCHAR(100) NOT NULL,
            voto TINYINT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY target_idx (target_id),
            KEY sport_idx (sport)
        ) $charset;";
        
        dbDelta($sql);
    }
    
    /**
     * Schedule cleanup cron job
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('fp_cleanup_giocatori_temp')) {
            wp_schedule_event(time() + 3600, 'daily', 'fp_cleanup_giocatori_temp');
        }
    }
    
    /**
     * Unschedule cleanup cron job
     */
    public static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled('fp_cleanup_giocatori_temp');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fp_cleanup_giocatori_temp');
        }
    }
}
