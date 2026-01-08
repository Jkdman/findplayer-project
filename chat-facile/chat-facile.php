<?php
/**
 * Plugin Name: Chat Facile
 * Description: Sistema di chat privata 1-to-1 modulare e riutilizzabile per WordPress. Realtime, notifiche offline, integrazione Supabase.
 * Version: 0.1.0
 * Author: Facile PMI
 * Text Domain: chat-facile
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * COSTANTI GLOBALI
 * ======================================================
 */
define('CF_CHAT_VERSION', '0.1.0');
define('CF_CHAT_PATH', plugin_dir_path(__FILE__));
define('CF_CHAT_URL', plugin_dir_url(__FILE__));

/**
 * ======================================================
 * BOOTSTRAP
 * ======================================================
 */
require_once CF_CHAT_PATH . 'includes/bootstrap.php';
require_once CF_CHAT_PATH . 'includes/ajax/open-chat.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax/ajax-admin.php';

register_deactivation_hook(__FILE__, 'cf_chat_unschedule_cleanup');