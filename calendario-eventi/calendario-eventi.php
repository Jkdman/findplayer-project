<?php
/**
 * Plugin Name: Calendario Eventi Universale
 * Description: Calendario mensile per visualizzare eventi WordPress tramite filtro unificato.
 * Version: 1.1.0
 * Author: Facile PMI
 */

if (!defined('ABSPATH')) exit;

/**
 * Costanti plugin
 */
define('CE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CE_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once CE_PLUGIN_DIR . 'includes/class-ce-calendar.php';

/**
 * Enqueue CSS e JS solo se lo shortcode è presente
 */
add_action('wp_enqueue_scripts', function () {
    global $post;

    if (!$post || strpos($post->post_content, '[calendario_eventi]') === false) {
        return;
    }

    wp_enqueue_style(
        'ce-calendar-css',
        CE_PLUGIN_URL . 'assets/calendar.css',
        [],
        '1.1.0'
    );

    wp_enqueue_script(
        'ce-calendar-js',
        CE_PLUGIN_URL . 'assets/calendar.js',
        ['jquery'],
        '1.1.0',
        true
    );
});

/**
 * Shortcode principale
 */
add_shortcode('calendario_eventi', function ($atts) {

    $atts = shortcode_atts([
        'month' => '',
        'year'  => '',
    ], $atts, 'calendario_eventi');

    $month = !empty($_GET['ce_month'])
        ? intval($_GET['ce_month'])
        : (int) date('n');

    $year = !empty($_GET['ce_year'])
        ? intval($_GET['ce_year'])
        : (int) date('Y');

    if (!empty($atts['month'])) $month = intval($atts['month']);
    if (!empty($atts['year']))  $year  = intval($atts['year']);

    $calendar = new CE_Calendar($month, $year);

    ob_start();
    echo $calendar->render();
    return ob_get_clean();
});

/**
 * 🔗 INTEGRAZIONE FIND PLAYER
 * ⚠️ SOLO LETTURA CPT WORDPRESS
 * ❌ NIENTE SUPABASE
 * ❌ NIENTE CREAZIONI
 */
add_filter('ce_get_events', function ($events, $args) {

    if (empty($args['start_date']) || empty($args['end_date'])) {
        return $events;
    }

    $posts = get_posts([
        'post_type'      => 'findplayer_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => '_findplayer_allenamento_id',
                'compare' => 'EXISTS',
            ]
        ],
        'date_query' => [
            [
                'after'     => $args['start_date'],
                'before'    => $args['end_date'],
                'inclusive' => true,
            ]
        ],
    ]);

   foreach ($posts as $post) {

    $data_evento = get_post_meta($post->ID, 'fp_data_evento', true);

    // ❌ se non c’è la data evento reale, NON MOSTRARE l’evento
    if (!$data_evento) {
        continue;
    }

    $events[] = [
        'date'       => $data_evento,
        'title'      => $post->post_title,
        'discipline' => get_post_meta($post->ID, 'fp_disciplina_evento', true),
        'url'        => get_permalink($post->ID),
    ];
}

    return $events;

}, 10, 2);