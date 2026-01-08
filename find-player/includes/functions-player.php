<?php
if (!defined('ABSPATH')) exit;

/**
 * Ritorna l’ID della scheda giocatore collegata all’utente WP
 */
function fp_get_player_id_by_user($user_id) {
    if (!$user_id) return 0;

    $q = new WP_Query([
        'post_type'      => 'fp_giocatore',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'   => 'fp_wp_user_id',
                'value' => (int)$user_id,
                'compare' => '='
            ]
        ]
    ]);

    if ($q->have_posts()) {
        return (int) $q->posts[0]->ID;
    }

    return 0;
}

/**
 * Recupera l'indirizzo IP reale del client (proxy / CDN safe)
 */
function fp_get_client_ip() {

    $keys = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];

    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            return sanitize_text_field(trim($ip));
        }
    }

    return null;
}
/**
 * Verifica se nickname / email / telefono appartengono a un giocatore registrato
 * Ritorna true se ESISTE (quindi BLOCCO)
 */
function fp_dato_appartiene_a_giocatore_registrato($nickname = '', $email = '', $telefono = '') {

    $meta_query = ['relation' => 'OR'];

    if ($nickname) {
        $meta_query[] = [
            'key'   => 'fp_nickname',
            'value' => $nickname,
            'compare' => '='
        ];
    }

    if ($email) {
        $meta_query[] = [
            'key'   => 'fp_email',
            'value' => $email,
            'compare' => '='
        ];
    }

    if ($telefono) {
        $meta_query[] = [
            'key'   => 'fp_telefono',
            'value' => $telefono,
            'compare' => '='
        ];
    }

    if (count($meta_query) === 1) {
        return false; // nessun dato da controllare
    }

    $q = new WP_Query([
        'post_type'      => 'fp_giocatore',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => $meta_query,
        'fields'         => 'ids'
    ]);

    return $q->have_posts();
}