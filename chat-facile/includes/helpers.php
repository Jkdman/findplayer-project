<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — HELPERS
 * ======================================================
 */

/**
 * ------------------------------------------------------
 * GET CURRENT CHAT USER ID
 * ------------------------------------------------------
 * Ritorna un ID univoco per la chat.
 * Di default usa l'ID WP.
 * In futuro può essere filtrato (Find Player, Supabase, ecc.)
 */
function cf_chat_get_current_user_id() {

    if (!is_user_logged_in()) {
        return null;
    }

    $user_id = get_current_user_id();

    /**
     * Filter per override ID (Find Player / altri sistemi)
     */
    return apply_filters('cf_chat_user_id', $user_id);
}

/**
 * ------------------------------------------------------
 * GENERATE PRIVATE CHAT ROOM ID
 * ------------------------------------------------------
 * Stanza deterministica 1-to-1
 */
function cf_chat_generate_room_id($user_a, $user_b) {

    if (!$user_a || !$user_b || $user_a === $user_b) {
        return null;
    }

    $users = [$user_a, $user_b];
    sort($users, SORT_NUMERIC);

    return hash('sha256', $users[0] . '_' . $users[1]);
}

/**
 * ------------------------------------------------------
 * SANITIZE CHAT MESSAGE
 * ------------------------------------------------------
 */
function cf_chat_sanitize_message($message) {

    $message = wp_strip_all_tags($message);
    $message = trim($message);

    return $message;
}

/**
 * ------------------------------------------------------
 * CHECK AJAX NONCE
 * ------------------------------------------------------
 */
function cf_chat_verify_nonce() {

    if (
        empty($_POST['nonce']) ||
        !wp_verify_nonce($_POST['nonce'], 'cf_chat_nonce')
    ) {
        wp_send_json_error(['message' => 'Nonce non valido'], 403);
    }
}

/**
 * ------------------------------------------------------
 * JSON RESPONSE HELPERS
 * ------------------------------------------------------
 */
function cf_chat_json_success($data = []) {
    wp_send_json_success($data);
}

function cf_chat_json_error($message = 'Errore generico', $code = 400) {
    wp_send_json_error(['message' => $message], $code);
}