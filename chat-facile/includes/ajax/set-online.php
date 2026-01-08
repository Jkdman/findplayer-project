<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — AJAX SET ONLINE
 * ======================================================
 */

add_action('wp_ajax_cf_chat_set_online', 'cf_chat_ajax_set_online');

function cf_chat_ajax_set_online() {

    if (!is_user_logged_in()) {
        wp_die();
    }

    cf_chat_verify_nonce();

    cf_chat_set_user_online(get_current_user_id());

    wp_die();
}