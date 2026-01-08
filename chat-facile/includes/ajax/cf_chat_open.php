<?php
if (!defined('ABSPATH')) exit;

/**
 * ======================================================
 * CHAT FACILE — AJAX OPEN CHAT PRIVATA
 * ======================================================
 */

add_action('wp_ajax_cf_chat_open', 'cf_chat_ajax_open_chat');

function cf_chat_ajax_open_chat() {

    cf_chat_verify_nonce();

    if (!is_user_logged_in()) {
        cf_chat_json_error('Utente non autenticato', 401);
    }

    $current_user = get_current_user_id();
    $other_user   = isset($_POST['other_user_id']) ? (int) $_POST['other_user_id'] : 0;

    if (!$other_user || $other_user === $current_user) {
        cf_chat_json_error('Utente non valido');
    }

    // Crea o recupera la stanza
    $room = cf_chat_get_or_create_room($current_user, $other_user);

    if (!$room || empty($room['id'])) {
        cf_chat_json_error('Impossibile creare la chat');
    }

    cf_chat_json_success([
        'room_id' => $room['id'],
        'user_one'=> $room['user_one'],
        'user_two'=> $room['user_two'],
    ]);
}