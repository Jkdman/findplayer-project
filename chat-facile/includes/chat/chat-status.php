<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — USER STATUS & PERMISSIONS
 * ======================================================
 */

/**
 * Crea tabella status utenti se non esiste
 */
function cf_chat_create_status_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'cf_chat_user_status';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        user_id BIGINT UNSIGNED NOT NULL,
        is_online TINYINT(1) NOT NULL DEFAULT 0,
        last_seen DATETIME NOT NULL,
        PRIMARY KEY (user_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Segna utente ONLINE
 */
function cf_chat_set_user_online($user_id = null) {
    global $wpdb;

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) return;

    $table = $wpdb->prefix . 'cf_chat_user_status';

    $wpdb->replace(
        $table,
        [
            'user_id'   => intval($user_id),
            'is_online' => 1,
            'last_seen' => current_time('mysql'),
        ],
        ['%d', '%d', '%s']
    );
}


/**
 * Segna utente OFFLINE
 */
function cf_chat_set_user_offline($user_id = null) {
    global $wpdb;

    if (!$user_id) {
        $user_id = get_current_user_id();
    }

    if (!$user_id) return;

    $table = $wpdb->prefix . 'cf_chat_user_status';

    $wpdb->update(
        $table,
        [
            'is_online' => 0,
            'last_seen' => current_time('mysql'),
        ],
        ['user_id' => intval($user_id)],
        ['%d', '%s'],
        ['%d']
    );
}


/**
 * Verifica se un utente è online
 */
function cf_chat_is_user_online($user_id) {
    global $wpdb;

    if (!$user_id) return false;

    $table = $wpdb->prefix . 'cf_chat_user_status';

    $online = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT is_online FROM $table WHERE user_id = %d",
            intval($user_id)
        )
    );

    return intval($online) === 1;
}