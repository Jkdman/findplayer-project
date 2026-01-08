<?php
/**
 * FP Iscrizione Giocatori - Configuration
 * 
 * Centralized configuration for the player registration plugin
 */

if (!defined('ABSPATH')) exit;

// Supabase configuration check
if (!defined('FP_SUPABASE_URL') || !defined('FP_SUPABASE_API_KEY')) {
    wp_die('Configurazione Supabase mancante');
}

// Mail configuration
if (!defined('FP_MAIL_FROM_NAME')) {
    define('FP_MAIL_FROM_NAME', 'Find Player');
}

if (!defined('FP_MAIL_FROM_EMAIL')) {
    define('FP_MAIL_FROM_EMAIL', 'no-reply@findplayer.it');
}

if (!defined('FP_MAIL_REPLYTO')) {
    define('FP_MAIL_REPLYTO', 'findplayeritaly@gmail.com');
}

if (!defined('FP_ADMIN_EMAIL')) {
    define('FP_ADMIN_EMAIL', 'findplayeritaly@gmail.com');
}

// Token configuration
if (!defined('FP_TOKEN_TTL_HOURS')) {
    define('FP_TOKEN_TTL_HOURS', 48);
}

// Plugin constants
define('FPIG_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
define('FPIG_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));

/**
 * Configure mail filters
 */
function fpig_configure_mail() {
    add_filter('wp_mail_from', function($from) { 
        return FP_MAIL_FROM_EMAIL; 
    });
    
    add_filter('wp_mail_from_name', function($name) { 
        return FP_MAIL_FROM_NAME; 
    });
}

/**
 * Get mail headers for player emails
 * 
 * @return array Mail headers
 */
function fp_mail_headers() {
    return [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . FP_MAIL_FROM_NAME . ' <' . FP_MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . FP_MAIL_REPLYTO
    ];
}
