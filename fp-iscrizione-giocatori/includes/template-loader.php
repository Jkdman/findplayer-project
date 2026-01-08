<?php
if (!defined('ABSPATH')) exit;

// carico la logica rating
require_once __DIR__ . '/rating.php';

// carico il template custom
add_filter('template_include', function($template) {

    if (is_singular('fp_giocatore')) {

        $custom = plugin_dir_path(__FILE__) . '../templates/single-fp_giocatore.php';

        if (file_exists($custom)) {
            return $custom;
        }
    }

    return $template;
});