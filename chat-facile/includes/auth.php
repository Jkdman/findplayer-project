<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — AUTH
 * ======================================================
 */

/**
 * ------------------------------------------------------
 * CHECK IF USER CAN ACCESS CHAT
 * ------------------------------------------------------
 */
function cf_chat_user_can_chat() {

    if (!is_user_logged_in()) {
        return false;
    }

    $user_id = get_current_user_id();

    /**
     * Flag base: utente abilitato alla chat
     * (es. dopo verifica mail, approvazione admin, ecc.)
     */
    $enabled = get_user_meta($user_id, '_cf_chat_enabled', true);

    if ($enabled !== 'yes') {
        return false;
    }

    return true;
}

/**
 * ------------------------------------------------------
 * VERIFY CHAT PASSWORD / TOKEN
 * ------------------------------------------------------
 * Password fornita dall'admin, salvata hashata
 */
function cf_chat_verify_chat_password($password) {

    if (!is_user_logged_in()) {
        return false;
    }

    if (empty($password)) {
        return false;
    }

    $user_id = get_current_user_id();
    $hash = get_user_meta($user_id, '_cf_chat_password', true);

    if (!$hash) {
        return false;
    }

    return wp_check_password($password, $hash);
}

/**
 * ------------------------------------------------------
 * ENABLE CHAT FOR USER
 * ------------------------------------------------------
 * Usabile da admin o integrazioni esterne
 */
function cf_chat_enable_user($user_id, $plain_password) {

    if (!$user_id || empty($plain_password)) {
        return false;
    }

    update_user_meta($user_id, '_cf_chat_enabled', 'yes');
    update_user_meta($user_id, '_cf_chat_password', wp_hash_password($plain_password));

    return true;
}

/**
 * ------------------------------------------------------
 * DISABLE CHAT FOR USER
 * ------------------------------------------------------
 */
function cf_chat_disable_user($user_id) {

    if (!$user_id) {
        return false;
    }

    update_user_meta($user_id, '_cf_chat_enabled', 'no');

    return true;
}