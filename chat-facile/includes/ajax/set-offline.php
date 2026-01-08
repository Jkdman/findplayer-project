<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — AJAX SET OFFLINE
 * ======================================================
 */

add_action('wp_ajax_cf_chat_set_offline', 'cf_chat_ajax_set_offline');

function cf_chat_ajax_set_offline() {

    if (!is_user_logged_in()) {
        wp_die();
    }

    cf_chat_verify_nonce();

    cf_chat_set_user_offline(get_current_user_id());

    wp_die();
}