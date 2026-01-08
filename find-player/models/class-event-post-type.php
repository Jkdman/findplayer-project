<?php
/**
 * Event Post Type Model
 * 
 * Registers and manages the FindPlayer Event custom post type
 */

if (!defined('ABSPATH')) exit;

class FP_Event_Post_Type {
    
    /**
     * Initialize post type registration
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('init', [__CLASS__, 'cleanup_old_events']);
    }
    
    /**
     * Register FindPlayer Event custom post type
     */
    public static function register_post_type() {
        $labels = [
            'name'               => 'Eventi Find Player',
            'singular_name'      => 'Evento Find Player',
            'add_new'            => 'Aggiungi Evento',
            'add_new_item'       => 'Aggiungi nuovo Evento Find Player',
            'edit_item'          => 'Modifica Evento',
            'new_item'           => 'Nuovo Evento',
            'view_item'          => 'Vedi Evento',
            'search_items'       => 'Cerca Eventi',
            'not_found'          => 'Nessun evento trovato',
            'not_found_in_trash' => 'Nessun evento nel cestino',
            'menu_name'          => 'Find Player Eventi',
        ];
        
        register_post_type('findplayer_event', [
            'labels'       => $labels,
            'public'       => true,
            'has_archive'  => false,
            'rewrite'      => ['slug' => 'find-player', 'with_front' => false],
            'supports'     => ['title', 'editor'],
            'show_in_menu' => true,
            'show_in_rest' => false,
        ]);
    }
    
    /**
     * Cleanup old events from Supabase (older than 7 days)
     */
    public static function cleanup_old_events() {
        $sette_giorni_fa = gmdate('Y-m-d', strtotime('-7 days'));
        
        $url = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?data_evento=lt.' . $sette_giorni_fa;
        
        $response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            ],
            'timeout' => 20,
        ]);
        
        if (!is_wp_error($response)) {
            error_log('🗑️ Eventi Find Player cancellati automaticamente (più vecchi di 7 giorni).');
        }
    }
}
