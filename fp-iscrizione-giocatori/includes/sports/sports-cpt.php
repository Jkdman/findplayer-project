<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {

    register_post_type('fp_sport', [
        'labels' => [
            'name'          => 'Sport',
            'singular_name' => 'Sport',
            'add_new'       => 'Aggiungi sport',
            'add_new_item'  => 'Nuovo sport',
            'edit_item'     => 'Modifica sport',
            'menu_name'     => 'Sport Find Player',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-universal-access',
        'supports'     => ['title'],
    ]);

});