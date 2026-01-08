<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap Chat Facile
 */

// ADMIN PAGES (cartella admin diretta)
require_once plugin_dir_path(__DIR__) . 'admin/dashboard-widget.php';
require_once plugin_dir_path(__DIR__) . 'admin/chat-admin-page.php';

// AJAX (cartella ajax DENTRO includes)
require_once plugin_dir_path(__DIR__) . 'includes/ajax/ajax-admin.php';

/**
 * ------------------------------------------------------
 * LOAD CORE FILES
 * ------------------------------------------------------
 */
require_once CF_CHAT_PATH . 'includes/helpers.php';
require_once CF_CHAT_PATH . 'includes/auth.php';
require_once CF_CHAT_PATH . 'includes/permissions.php';

/**
 * Supabase layer
 */
require_once CF_CHAT_PATH . 'includes/supabase/client.php';
require_once CF_CHAT_PATH . 'includes/supabase/queries.php';
require_once CF_CHAT_PATH . 'includes/supabase/realtime.php';

/**
 * Chat logic
 */
require_once CF_CHAT_PATH . 'includes/chat/chat-room.php';
require_once CF_CHAT_PATH . 'includes/chat/chat-messages.php';
require_once CF_CHAT_PATH . 'includes/chat/chat-status.php';
require_once CF_CHAT_PATH . 'includes/chat/chat-notify.php';
require_once CF_CHAT_PATH . 'includes/chat/cleanup/cleanup.php';


/**
 * AJAX endpoints
 */
require_once CF_CHAT_PATH . 'includes/ajax/open-chat.php';
require_once CF_CHAT_PATH . 'includes/ajax/send-message.php';
require_once CF_CHAT_PATH . 'includes/ajax/fetch-messages.php';
require_once CF_CHAT_PATH . 'includes/ajax/check-online.php';

/**
 * Shortcodes
 */
require_once CF_CHAT_PATH . 'includes/shortcodes/chat-window.php';
require_once CF_CHAT_PATH . 'includes/shortcodes/chat-button.php';
require_once CF_CHAT_PATH . 'includes/admin/user-metabox.php';
require_once CF_CHAT_PATH . 'includes/ajax/set-online.php';
require_once CF_CHAT_PATH . 'includes/ajax/set-offline.php';

/**
 * ------------------------------------------------------
 * ENQUEUE ASSETS
 * ------------------------------------------------------
 */
add_action('wp_enqueue_scripts', function () {

    wp_enqueue_style(
        'chat-facile-css',
        CF_CHAT_URL . 'assets/css/chat.css',
        [],
        CF_CHAT_VERSION
    );

    wp_enqueue_script(
        'chat-facile-core',
        CF_CHAT_URL . 'assets/js/chat-core.js',
        ['jquery'],
        time(),
        true
    );

    wp_enqueue_script(
        'chat-facile-realtime',
        CF_CHAT_URL . 'assets/js/chat-realtime.js',
        ['chat-facile-core'],
        CF_CHAT_VERSION,
        true
    );

    wp_enqueue_script(
        'chat-facile-ui',
        CF_CHAT_URL . 'assets/js/chat-ui.js',
        ['chat-facile-core'],
        CF_CHAT_VERSION,
        true
    );

wp_localize_script('chat-facile-core', 'CF_CHAT', [
    'ajax_url'            => admin_url('admin-ajax.php'),
    'nonce'               => wp_create_nonce('cf_chat_nonce'),
    'user_id'             => get_current_user_id(),
    'supabase_url'        => get_option('cf_chat_supabase_url'),
    'supabase_anon_key'   => get_option('cf_chat_supabase_anon_key'),
]);
});