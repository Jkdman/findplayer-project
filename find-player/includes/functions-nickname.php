<?php
if (!defined('ABSPATH')) exit;

function fp_nickname_disponibile($nickname) {
    global $wpdb;

    $nickname = sanitize_text_field($nickname);

    if (!$nickname) return false;

    // 1. Nickname già usato in schede giocatore
    $exists_player = $wpdb->get_var($wpdb->prepare("
        SELECT post_id
        FROM {$wpdb->postmeta}
        WHERE meta_key = 'fp_nickname'
        AND meta_value = %s
        LIMIT 1
    ", $nickname));

    if ($exists_player) return false;

    // 2. Nickname usato in eventi negli ultimi 30 giorni
    $exists_event = $wpdb->get_var($wpdb->prepare("
        SELECT pm.post_id
        FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = 'fp_evento_nickname'
        AND pm.meta_value = %s
        AND p.post_type = 'fp_evento'
        AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LIMIT 1
    ", $nickname));

    return !$exists_event;
}
/**
 * Verifica se nickname è bloccato (lock 6 mesi)
 */
function fp_nickname_locked($nickname) {

    $url = FPFP_SUPABASE_URL . '/rest/v1/nickname_registry'
         . '?nickname=eq.' . rawurlencode($nickname)
         . '&expires_at=gt.' . rawurlencode(gmdate('c'))
         . '&select=id';

    $r = wp_remote_get($url, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($r)) return false;

    $rows = json_decode(wp_remote_retrieve_body($r), true);
    return !empty($rows);
}

/**
 * Registra o rinnova nickname per 6 mesi (post-token)
 */
function fp_nickname_touch($nickname, $email = '', $telefono = '') {

    $now = gmdate('c');
    $exp = gmdate('c', strtotime('+6 months'));

    // upsert manuale
    $check = FPFP_SUPABASE_URL . '/rest/v1/nickname_registry'
           . '?nickname=eq.' . rawurlencode($nickname)
           . '&select=id';

    $r = wp_remote_get($check, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 15,
    ]);

    $rows = json_decode(wp_remote_retrieve_body($r), true);

    if (!empty($rows[0]['id'])) {
        // UPDATE
        wp_remote_request(
            FPFP_SUPABASE_URL . '/rest/v1/nickname_registry?id=eq.' . intval($rows[0]['id']),
            [
                'method'  => 'PATCH',
                'headers' => [
                    'apikey'        => FPFP_SUPABASE_API_KEY,
                    'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'last_used_at'   => $now,
                    'expires_at'     => $exp,
                    'token_confirmed'=> true
                ]),
            ]
        );
    } else {
        // INSERT
        wp_remote_post(
            FPFP_SUPABASE_URL . '/rest/v1/nickname_registry',
            [
                'headers' => [
                    'apikey'        => FPFP_SUPABASE_API_KEY,
                    'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'nickname'        => $nickname,
                    'email'           => $email,
                    'telefono'        => $telefono,
                    'last_used_at'    => $now,
                    'expires_at'      => $exp,
                    'token_confirmed' => true
                ]),
            ]
        );
    }
}