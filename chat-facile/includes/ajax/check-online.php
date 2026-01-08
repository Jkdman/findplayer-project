<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — AJAX CHECK ONLINE
 * ======================================================
 */

add_action('wp_ajax_cf_chat_check_online', 'cf_chat_ajax_check_online');

function cf_chat_ajax_check_online() {

    if (!is_user_logged_in()) {
        cf_chat_json_error('Utente non autenticato', 401);
    }

    cf_chat_verify_nonce();

    if (!cf_chat_user_can_chat()) {
        cf_chat_json_error('Chat non abilitata', 403);
    }

    $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

    if (!$user_id) {
        cf_chat_json_error('Utente non valido');
    }

    $online = cf_chat_is_user_online($user_id);

    cf_chat_json_success([
        'online' => (bool) $online
    ]);
}