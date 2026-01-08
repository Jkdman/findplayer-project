<?php
if (!defined('ABSPATH')) exit;

/**
 * CHAT FACILE — CLEANUP MESSAGGI
 */

// ⏱ retention in secondi
define('CF_CHAT_RETENTION_SECONDS', 5760); // 1 minuto

function cf_chat_cleanup_old_messages() {
    global $wpdb;

    $table = $wpdb->prefix . 'cf_chat_messages';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table
             WHERE created_at < (UTC_TIMESTAMP() - INTERVAL %d SECOND)",
            CF_CHAT_RETENTION_SECONDS
        )
    );
}

// cron ogni minuto
add_action('cf_chat_cleanup_cron', 'cf_chat_cleanup_old_messages');

// scheduler
add_action('plugins_loaded', function () {
    if (!wp_next_scheduled('cf_chat_cleanup_cron')) {
        wp_schedule_event(time(), 'minute', 'cf_chat_cleanup_cron');
    }
});

// intervallo 1 minuto
add_filter('cron_schedules', function ($schedules) {
    $schedules['minute'] = [
        'interval' => 60,
        'display'  => 'Ogni minuto'
    ];
    return $schedules;
});

// unschedule su disattivazione
register_deactivation_hook(
    dirname(__DIR__, 3) . '/chat-facile.php',
    function () {
        $ts = wp_next_scheduled('cf_chat_cleanup_cron');
        if ($ts) {
            wp_unschedule_event($ts, 'cf_chat_cleanup_cron');
        }
    }
);