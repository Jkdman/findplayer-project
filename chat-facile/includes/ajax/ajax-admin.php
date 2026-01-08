<?php
if (!defined('ABSPATH')) exit;

error_log('🔥 AJAX ADMIN FILE CARICATO');

add_action('wp_ajax_cf_chat_clear_all', 'cf_chat_clear_all');

function cf_chat_clear_all() {

    error_log('🔥 AJAX cf_chat_clear_all CHIAMATO');

    if (!current_user_can('administrator')) {
        error_log('❌ NON ADMIN');
        wp_send_json_error('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'cf_chat_messages';

    $res = $wpdb->query("UPDATE $table SET deleted_at = NOW()");
    error_log('✅ QUERY ESEGUITA: ' . $res);

    wp_send_json_success('Chat svuotata');
}