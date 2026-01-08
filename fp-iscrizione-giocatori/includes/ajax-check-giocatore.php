<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_fp_check_giocatore', 'fp_ajax_check_giocatore');
add_action('wp_ajax_nopriv_fp_check_giocatore', 'fp_ajax_check_giocatore');

function fp_ajax_check_giocatore() {

    if (empty($_POST['campo']) || empty($_POST['valore'])) {
        wp_send_json_error();
    }

    $campo  = sanitize_text_field($_POST['campo']);
    $valore = sanitize_text_field($_POST['valore']);

    // normalizzazione email
    if ($campo === 'email') {
        $valore = strtolower($valore);
    }

    // 🔒 CONSENTIAMO SOLO QUESTI CAMPI
    if (!in_array($campo, ['nickname', 'email', 'telefono'], true)) {
        wp_send_json_error();
    }

    // 🔥 QUERY SOLO SU "giocatori"
    $url = FP_SUPABASE_URL . '/rest/v1/giocatori?' .
        $campo . '=eq.' . urlencode($valore) .
        '&select=id&limit=1';

    $resp = wp_remote_get($url, [
        'headers' => [
            'apikey'        => FP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
        ],
        'timeout' => 10
    ]);

    if (is_wp_error($resp)) {
        wp_send_json_error();
    }

    $body = json_decode(wp_remote_retrieve_body($resp), true);

    // ✅ SUCCESS = disponibile
    if (empty($body)) {
        wp_send_json_success();
    }

    // ❌ ESISTE GIÀ IN giocatori
    wp_send_json_error();
}