<?php
/**
 * Player Post Type Model
 * 
 * Registers and manages the fp_giocatore custom post type
 */

if (!defined('ABSPATH')) exit;

class FPIG_Player_Post_Type {
    
    /**
     * Initialize player post type
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
    }
    
    /**
     * Register fp_giocatore custom post type
     */
    public static function register_post_type() {
        register_post_type('fp_giocatore', [
            'labels' => [
                'name' => 'Giocatori',
                'singular_name' => 'Giocatore',
                'add_new' => 'Aggiungi giocatore',
                'add_new_item' => 'Nuovo giocatore',
                'edit_item' => 'Modifica giocatore',
                'view_item' => 'Vedi giocatore',
            ],
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'has_archive' => false,
            'rewrite' => ['slug' => 'giocatori', 'with_front' => false],
        ]);
    }
}
