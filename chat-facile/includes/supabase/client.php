<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — SUPABASE CLIENT
 * ======================================================
 */

/**
 * ------------------------------------------------------
 * CONFIG
 * ------------------------------------------------------
 * Configurazione tramite opzioni WP
 * (Impostazioni → Chat Facile)
 */
function cf_chat_supabase_is_configured() {

    $url = get_option('cf_chat_supabase_url');
    $key = get_option('cf_chat_supabase_api_key');

    return !empty($url) && !empty($key);
}


/**
 * ------------------------------------------------------
 * GENERIC SUPABASE REQUEST
 * ------------------------------------------------------
 */
function cf_chat_supabase_request($endpoint, $method = 'GET', $body = null, $query = []) {

    if (!cf_chat_supabase_is_configured()) {
        return [
            'error' => true,
            'message' => 'Supabase non configurato'
        ];
    }

$supabase_url = get_option('cf_chat_supabase_url');
$url = rtrim($supabase_url, '/') . '/rest/v1/' . ltrim($endpoint, '/');

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

$api_key = get_option('cf_chat_supabase_api_key');

$args = [
    'method'  => $method,
    'headers' => [
        'apikey'        => $api_key,
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
        'Prefer'        => 'return=representation'
    ],
    'timeout' => 15,
];

    if ($body !== null) {
        $args['body'] = wp_json_encode($body);
    }

    $response = wp_remote_request($url, $args);

    if (is_wp_error($response)) {
        return [
            'error' => true,
            'message' => $response->get_error_message()
        ];
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code < 200 || $code >= 300) {
        return [
            'error'   => true,
            'status'  => $code,
            'message' => $data['message'] ?? 'Errore Supabase',
            'raw'     => $raw
        ];
    }

    return [
        'error' => false,
        'data'  => $data
    ];
}