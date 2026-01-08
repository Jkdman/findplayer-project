<?php
if (!defined('ABSPATH')) exit;

/**
 * CHAT FACILE — CHAT MESSAGES LOGIC
 */

function cf_chat_insert_message($room_id, $sender_id, $message) {
    global $wpdb;

    if (!$room_id || !$sender_id || $message === '') {
        return ['error' => true];
    }

    $table = $wpdb->prefix . 'cf_chat_messages';

    $ok = $wpdb->insert(
        $table,
        [
            'room_id'    => $room_id,
            'sender_id'  => $sender_id,
            'message'    => wp_kses_post($message),
            'created_at' => current_time('mysql', true) // UTC
        ],
        ['%s', '%d', '%s', '%s']
    );

    return ['error' => !$ok];
}

function cf_chat_fetch_global_messages($limit = 50) {
    global $wpdb;

    $table = $wpdb->prefix . 'cf_chat_messages';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT m.sender_id,
                    m.message,
                    m.created_at,
                    u.display_name
             FROM $table m
             LEFT JOIN {$wpdb->users} u ON u.ID = m.sender_id
             WHERE m.room_id = 'global'
               AND m.deleted_at IS NULL
             ORDER BY m.created_at ASC
             LIMIT %d",
            $limit
        ),
        ARRAY_A
    );

    foreach ($rows as &$r) {
        $r['created_ts'] = strtotime($r['created_at']);
    }
    unset($r);

    return [
        'error' => false,
        'data'  => $rows ?: []
    ];
}