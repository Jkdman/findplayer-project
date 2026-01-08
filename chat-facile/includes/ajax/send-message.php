<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — AJAX SEND MESSAGE
 * ======================================================
 */

add_action('wp_ajax_cf_chat_send_message', 'cf_chat_ajax_send_message');

function cf_chat_ajax_send_message() {

    if (!is_user_logged_in()) {
        cf_chat_json_error('Utente non autenticato', 401);
    }

    cf_chat_verify_nonce();

    if (!cf_chat_user_can_chat()) {
        cf_chat_json_error('Chat non abilitata', 403);
    }

    $room_id = $_POST['room_id'] ?? '';
    $user_a  = isset($_POST['user_a']) ? (int) $_POST['user_a'] : 0;
    $user_b  = isset($_POST['user_b']) ? (int) $_POST['user_b'] : 0;
    $message = $_POST['message'] ?? '';

    $message = cf_chat_sanitize_message($message);

    if ($message === '') {
        cf_chat_json_error('Messaggio vuoto');
    }

    $sender_id = get_current_user_id();

    /**
     * CHAT GLOBALE
     */
    if ($room_id === 'global') {

$insert = cf_chat_insert_message('global', $sender_id, $message);

if (!empty($insert['error'])) {
    cf_chat_json_error('Errore invio messaggio');
}

cf_chat_cleanup_old_messages(); // ✅ QUI ESATTO

cf_chat_json_success([
    'message' => 'Messaggio inviato'
]);

    }

    /**
     * CHAT PRIVATA
     */
    if (!$room_id || !$user_a || !$user_b) {
        cf_chat_json_error('Dati chat non validi');
    }

    if (!cf_chat_can_send_message($room_id, $user_a, $user_b)) {
        cf_chat_json_error('Non hai i permessi per scrivere in questa chat', 403);
    }
$insert = cf_chat_insert_message($room_id, $sender_id, $message);

if (!empty($insert['error'])) {
    cf_chat_json_error('Errore durante l’invio del messaggio');
}

cf_chat_cleanup_old_messages(); // ✅ QUI ESATTO

do_action(
    'cf_chat_message_sent',
    $room_id,
    $sender_id,
    $user_a,
    $user_b,
    $message
);

cf_chat_json_success([
    'message' => 'Messaggio inviato'
]);

}