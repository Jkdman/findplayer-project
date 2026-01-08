<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — AJAX OPEN CHAT
 * ======================================================
 */

add_action('wp_ajax_cf_chat_open', 'cf_chat_ajax_open_chat');

function cf_chat_ajax_open_chat() {

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged'], 401);
    }

    cf_chat_verify_nonce();

    $other_user_id = intval($_POST['other_user_id'] ?? 0);
    if (!$other_user_id) {
        wp_send_json_error(['message' => 'User invalid'], 400);
    }

    $me = get_current_user_id();

    if ($me === $other_user_id) {
        wp_send_json_error(['message' => 'Cannot chat with yourself'], 400);
    }

    if (!cf_chat_can_open_chat($other_user_id)) {
        wp_send_json_error(['message' => 'Access denied'], 403);
    }

    $room = cf_chat_get_or_create_room($me, $other_user_id);

    if (!$room || empty($room['id'])) {
        wp_send_json_error(['message' => 'Room error'], 500);
    }

    $other_user = get_user_by('id', $other_user_id);

    wp_send_json_success([
        'room_id'          => $room['id'],
        'room_type'        => 'private',
        'user_one'         => $room['user_one'],
        'user_two'         => $room['user_two'],
        'other_user_id'    => $other_user_id,
        'other_user_name'  => $other_user ? $other_user->display_name : 'Utente'
    ]);
}