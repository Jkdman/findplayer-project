<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — AJAX FETCH MESSAGES
 * ======================================================
 */

add_action('wp_ajax_cf_chat_fetch_messages', 'cf_chat_ajax_fetch_messages');

function cf_chat_ajax_fetch_messages() {

    if (!is_user_logged_in()) {
        cf_chat_json_error('Utente non autenticato', 401);
    }

    cf_chat_verify_nonce();

    if (!cf_chat_user_can_chat()) {
        cf_chat_json_error('Chat non abilitata', 403);
    }

    $room_id = $_POST['room_id'] ?? '';

    /**
     * CHAT GLOBALE
     */
    if ($room_id === 'global') {

        $messages = cf_chat_fetch_global_messages();

        if (!empty($messages['error'])) {
            cf_chat_json_error('Errore nel recupero messaggi globali');
        }

        foreach ($messages['data'] as &$m) {
            $user = get_userdata($m['sender_id']);
            $m['sender_name'] = $user ? $user->display_name : 'Utente';
        }

        cf_chat_json_success([
            'messages' => $messages['data']
        ]);
    }

    /**
     * CHAT PRIVATA
     */
    $user_a = isset($_POST['user_a']) ? (int) $_POST['user_a'] : 0;
    $user_b = isset($_POST['user_b']) ? (int) $_POST['user_b'] : 0;

    if (!$room_id || !$user_a || !$user_b) {
        cf_chat_json_error('Parametri chat privata non validi');
    }

    if (!cf_chat_can_access_room($room_id, $user_a, $user_b)) {
        cf_chat_json_error('Accesso non autorizzato', 403);
    }

    $messages = cf_chat_fetch_messages($room_id);

    if (!empty($messages['error'])) {
        cf_chat_json_error('Errore nel recupero messaggi');
    }

    cf_chat_json_success([
        'messages' => $messages['data']
    ]);
}