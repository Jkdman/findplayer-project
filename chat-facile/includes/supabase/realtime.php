<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — SUPABASE REALTIME
 * ======================================================
 */

/**
 * ------------------------------------------------------
 * GET REALTIME CONFIG
 * ------------------------------------------------------
 * Usato dal frontend JS
 */
function cf_chat_get_realtime_config() {

    $url  = get_option('cf_chat_supabase_url');
    $anon = get_option('cf_chat_supabase_anon_key');

    if (empty($url) || empty($anon)) {
        return null;
    }

    return [
        'supabase_url'      => $url,
        'supabase_anon_key' => $anon,
        'channels' => [
            'room'   => 'chat_room_',   // chat_room_{room_id}
            'global' => 'chat_global',
        ]
    ];
}