<?php
if (!defined('ABSPATH')) exit;

/**
 * Restituisce tutti gli sport disponibili
 */
function fp_get_all_sports() {
    return get_posts([
        'post_type'      => 'fp_sport',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
}

/**
 * Restituisce il nome sport da ID
 */
function fp_get_sport_name($sport_id) {
    $p = get_post($sport_id);
    return $p ? $p->post_title : '';
}