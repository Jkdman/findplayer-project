<?php
if (!defined('ABSPATH')) exit;

/**
 * Recupera eventi attivi del giocatore (CPT findplayer_event)
 */
function fp_get_eventi_attivi_giocatore($giocatore_post_id) {

    if (!$giocatore_post_id) return [];

    $today = date('Y-m-d');

    $q = new WP_Query([
        'post_type'      => 'findplayer_event',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'meta_query'     => [
            [
                'key'   => 'fp_giocatore_id',
                'value' => (int) $giocatore_post_id,
            ],
            [
                'key'     => 'data_evento',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE'
            ]
        ],
        'orderby'  => 'meta_value',
        'meta_key' => 'data_evento',
        'order'    => 'ASC'
    ]);

    $out = [];

    while ($q->have_posts()) {
        $q->the_post();
        $out[] = [
            'titolo'      => get_the_title(),
            'citta'       => get_post_meta(get_the_ID(), 'citta', true),
            'data_evento' => get_post_meta(get_the_ID(), 'data_evento', true),
            'url'         => get_permalink()
        ];
    }

    wp_reset_postdata();
    return $out;
}