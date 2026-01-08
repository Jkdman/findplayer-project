<?php
/**
 * Plugin Name: Find Player – Scheda Giocatore
 * Description: Modulo di registrazione giocatore con selezione discipline e livelli.
 * Version: 1.1.1
 * Author: Facile PMI
 */

if (!defined('ABSPATH')) exit;
function fp_enqueue_scripts_conditionally() {

    // Carica lo script SOLO se siamo nella pagina giusta del form
    if (is_page('iscrizione') || is_page('crea-scheda') || get_post_type() === 'fp_scheda') {

        wp_enqueue_script(
            'fp-form',
            plugin_dir_url(__FILE__) . 'js/fp-form.js',
            array('jquery'),
            '1.0',
            true
        );
    }
}

add_action('wp_enqueue_scripts', 'fp_enqueue_scripts_conditionally');

function fp_scheda_enqueue_assets() {
    wp_enqueue_style('fp-scheda-style', plugin_dir_url(__FILE__) . 'assets/css/fp-style.css');
    wp_enqueue_script('fp-sports-list', plugin_dir_url(__FILE__) . 'assets/js/sports.js', [], null, true);
    wp_enqueue_script('fp-form-js', plugin_dir_url(__FILE__) . 'assets/js/fp-form.js', ['fp-sports-list'], null, true);
}
add_action('wp_enqueue_scripts', 'fp_scheda_enqueue_assets');

function fp_render_form_giocatore() {
    ob_start();
    include plugin_dir_path(__FILE__) . "templates/form-giocatore.php";
    return ob_get_clean();
}
add_shortcode("fp_form_giocatore", "fp_render_form_giocatore");