<?php
/**
 * Plugin Name: Find Player - Iscrizione Giocatori (Supabase + Approve)
 * Description: Form frontend → token email → scheda WP pending → approvazione admin → invio a Supabase (giocatori + discipline).
 * Version: 1.1.0
 * Author: Facile PMI
 * 
 * Refactored version with improved structure
 */

if (!defined('ABSPATH')) exit;

// Load configuration
require_once plugin_dir_path(__FILE__) . 'helpers/config.php';

// Load models
require_once plugin_dir_path(__FILE__) . 'models/class-database.php';
require_once plugin_dir_path(__FILE__) . 'models/class-player-post-type.php';

// Load existing includes (already modularized)
require_once plugin_dir_path(__FILE__) . 'includes/template-loader.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-check-giocatore.php';
require_once plugin_dir_path(__FILE__) . 'includes/sports/sports-cpt.php';
require_once plugin_dir_path(__FILE__) . 'includes/sports/sports-helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/metaboxes/fp-giocatore-sport-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions-eventi.php';

// Initialize components
fpig_configure_mail();
FPIG_Player_Post_Type::init();

/**
 * Plugin activation
 */
register_activation_hook(__FILE__, function() {
    // Create database tables
    FPIG_Database::create_tables();
    
    // Schedule cleanup cron
    FPIG_Database::schedule_cleanup();
    
    // Register CPT and flush rewrite rules
    FPIG_Player_Post_Type::register_post_type();
    flush_rewrite_rules();
});

/**
 * Plugin deactivation
 */
register_deactivation_hook(__FILE__, function() {
    // Unschedule cleanup cron
    FPIG_Database::unschedule_cleanup();
    
    flush_rewrite_rules();
});

// Load legacy features (to be further refactored)
require_once plugin_dir_path(__FILE__) . 'includes/legacy-player-features.php';
