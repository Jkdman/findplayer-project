<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — NOTIFY
 * ======================================================
 */

/**
 * Invia email se il destinatario è offline
 */
add_action('cf_chat_message_sent', 'cf_chat_maybe_notify_user', 10, 5);

function cf_chat_maybe_notify_user($room_id, $sender_id, $user_a, $user_b, $message) {

    // Determina destinatario
    $receiver_id = ($sender_id == $user_a) ? $user_b : $user_a;

    // Se online, non inviamo nulla
if (cf_chat_is_user_online($receiver_id)) {
    return;
}
    $receiver = get_userdata($receiver_id);
    $sender   = get_userdata($sender_id);

    if (!$receiver || !$sender) {
        return;
    }

    $recipient_email = $receiver->user_email;
    $recipient_name  = $receiver->display_name;
    $sender_name     = $sender->display_name;
    $message_text    = $message;

    // Link alla pagina chat (può essere filtrato per progetto)
    $chat_link = apply_filters(
        'cf_chat_link',
        site_url('/chat'),
        $room_id,
        $receiver_id
    );

    // Render template
    ob_start();
    include CF_CHAT_PATH . 'emails/new-message.php';
    $email_body = ob_get_clean();

    wp_mail(
        $recipient_email,
        'Nuovo messaggio su Chat Facile',
        $email_body,
        ['Content-Type: text/html; charset=UTF-8']
    );
}