<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — CHAT ROOM LOGIC
 * ======================================================
 */

/**
 * Crea la tabella delle chat room se non esiste
 */
function cf_chat_create_rooms_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'cf_chat_rooms';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_one BIGINT UNSIGNED NOT NULL,
        user_two BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY unique_room (user_one, user_two),
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Hook di sicurezza (attivazione plugin)
 */
register_activation_hook(__FILE__, 'cf_chat_create_rooms_table');

/**
 * Normalizza l'ordine utenti (A < B)
 */
function cf_chat_normalize_users($user_a, $user_b) {
    return ($user_a < $user_b)
        ? [$user_a, $user_b]
        : [$user_b, $user_a];
}


/**
 * Recupera o crea una stanza di chat 1-to-1
 */
function cf_chat_get_or_create_room($user_a, $user_b) {
    global $wpdb;

    if (!$user_a || !$user_b || $user_a === $user_b) {
        return false;
    }

    [$u1, $u2] = cf_chat_normalize_users($user_a, $user_b);

    $table = $wpdb->prefix . 'cf_chat_rooms';

    // 1️⃣ Cerco stanza esistente
    $room_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM $table WHERE user_one = %d AND user_two = %d",
            $u1,
            $u2
        )
    );

    if ($room_id) {
        return [
            'id'       => (string) $room_id,
            'user_one' => $u1,
            'user_two' => $u2,
        ];
    }

    // 2️⃣ Creo nuova stanza
    $inserted = $wpdb->insert(
        $table,
        [
            'user_one'   => $u1,
            'user_two'   => $u2,
            'created_at'=> current_time('mysql'),
        ],
        ['%d', '%d', '%s']
    );

    if (!$inserted) {
        return false;
    }

    return [
        'id'       => (string) $wpdb->insert_id,
        'user_one' => $u1,
        'user_two' => $u2,
    ];
}