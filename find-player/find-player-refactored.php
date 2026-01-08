<?php
/**
 * Plugin Name: Find Player - Calendario Allenamenti
 * Description: Creazione attività, con elenco, creaz. pagine dedicate, prenotazioni e salvataggio su Supabase.
 * Version: 2.5.0
 * Author: Facile PMI
 * 
 * This is the refactored main plugin file with MVC structure
 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('FP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load configuration
require_once FP_PLUGIN_DIR . 'helpers/config.php';

// Load helper functions
require_once FP_PLUGIN_DIR . 'includes/functions-nickname.php';
require_once FP_PLUGIN_DIR . 'includes/functions-player.php';

// Load helpers
require_once FP_PLUGIN_DIR . 'helpers/class-assets-helper.php';
require_once FP_PLUGIN_DIR . 'helpers/class-calendar-integration.php';

// Load models
require_once FP_PLUGIN_DIR . 'models/class-event-post-type.php';
require_once FP_PLUGIN_DIR . 'models/class-user-sync.php';

// Load controllers
require_once FP_PLUGIN_DIR . 'controllers/class-event-token-controller.php';

// Initialize components
FP_Assets_Helper::init();
FP_Calendar_Integration::init();
FP_Event_Post_Type::init();
FP_User_Sync::init();
FP_Event_Token_Controller::init();

/**
 * Flush permalinks on plugin activation
 */
register_activation_hook(__FILE__, function() {
    FP_Event_Post_Type::register_post_type();
    flush_rewrite_rules();
});

/**
 * Flush permalinks on plugin deactivation
 */
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Load legacy code (to be refactored in future iterations)
// This includes shortcodes, calendar integration, and other complex features
require_once FP_PLUGIN_DIR . 'includes/legacy-features.php';
