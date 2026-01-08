<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — PERMISSIONS
 * ======================================================
 */

/**
 * ------------------------------------------------------
 * CAN USER OPEN CHAT WITH OTHER USER?
 * ------------------------------------------------------
 */
function cf_chat_can_open_chat($other_user_id) {

    if (!cf_chat_user_can_chat()) {
        return false;
    }

    $me = get_current_user_id();

    if (!$other_user_id || (int)$other_user_id === (int)$me) {
        return false;
    }

    /**
     * Hook per regole business (Find Player: solo se approvati,
     * solo se hanno partecipato ad attività, ecc.)
     */
    $allowed = apply_filters('cf_chat_can_open_chat', true, $me, (int)$other_user_id);

    return (bool) $allowed;
}

/**
 * ------------------------------------------------------
 * CAN USER ACCESS A ROOM?
 * ------------------------------------------------------
 * Room deterministica: hash(min_id . '_' . max_id)
 * Per validare, ricostruiamo l'hash e confrontiamo.
 */
function cf_chat_can_access_room($room_id, $user_a, $user_b) {

    if ($room_id === 'global') {
        return cf_chat_user_can_chat();
    }

    if (!cf_chat_user_can_chat()) {
        return false;
    }

    $me = get_current_user_id();

    $user_a = (int) $user_a;
    $user_b = (int) $user_b;

    if (!$room_id || !$user_a || !$user_b) {
        return false;
    }

    // L'utente deve essere uno dei due
    if ((int)$me !== $user_a && (int)$me !== $user_b) {
        return false;
    }

    // Room ID deve combaciare con quello deterministico
    $expected = cf_chat_generate_room_id($user_a, $user_b);
    if (!$expected || $expected !== $room_id) {
        return false;
    }

    /**
     * Hook per policy extra (es: blocchi utenti, ban, ecc.)
     */
    $allowed = apply_filters('cf_chat_can_access_room', true, $me, $room_id, $user_a, $user_b);

    return (bool) $allowed;
}

/**
 * ------------------------------------------------------
 * CAN USER SEND MESSAGE?
 * ------------------------------------------------------
 */
function cf_chat_can_send_message($room_id, $user_a, $user_b) {

    // Per ora stessa logica dell’accesso.
    // In futuro puoi mettere rate-limit, mute, ecc.
    $allowed = cf_chat_can_access_room($room_id, $user_a, $user_b);

    $allowed = apply_filters('cf_chat_can_send_message', $allowed, get_current_user_id(), $room_id, $user_a, $user_b);

    return (bool) $allowed;
}