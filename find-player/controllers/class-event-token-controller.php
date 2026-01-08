<?php
/**
 * Event Token Controller
 * 
 * Handles token-based event confirmation (guest users)
 */

if (!defined('ABSPATH')) exit;

class FP_Event_Token_Controller {
    
    /**
     * Initialize token handlers
     */
    public static function init() {
        add_action('init', [__CLASS__, 'handle_event_confirmation']);
    }
    
    /**
     * Handle event confirmation via token (guest users)
     */
    public static function handle_event_confirmation() {
        if (empty($_GET['fp_confirm_event']) || empty($_GET['event_id'])) {
            return;
        }
        
        $token = sanitize_text_field($_GET['fp_confirm_event']);
        $post_id = intval($_GET['event_id']);
        
        if (!$post_id || !$token) {
            wp_die('Link di conferma non valido.');
        }
        
        // Verify saved token
        $saved_token = get_post_meta($post_id, 'fp_event_token', true);
        
        if (!$saved_token || !hash_equals($saved_token, $token)) {
            wp_die('Token non valido o già utilizzato.');
        }
        
        // Publish event on WordPress
        wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'publish',
        ]);
        
        // Get Supabase event ID
        $allenamento_id = get_post_meta($post_id, '_findplayer_allenamento_id', true);
        
        if ($allenamento_id) {
            // Confirm event on Supabase
            self::confirm_event_on_supabase($allenamento_id);
        }
        
        // Remove token (single use)
        delete_post_meta($post_id, 'fp_event_token');
        
        // Success message
        wp_die(
            '<h2>✅ Evento confermato con successo</h2>
             <p>L'evento è ora pubblico e visibile nell'elenco.</p>
             <p><a href="' . esc_url(get_permalink($post_id)) . '">Vai all'evento</a></p>',
            'Evento confermato',
            ['response' => 200]
        );
    }
    
    /**
     * Confirm event on Supabase
     * 
     * @param int $allenamento_id Supabase allenamento ID
     */
    private static function confirm_event_on_supabase($allenamento_id) {
        wp_remote_request(
            FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . intval($allenamento_id),
            [
                'method'  => 'PATCH',
                'headers' => [
                    'apikey'        => FPFP_SUPABASE_API_KEY,
                    'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'evento_confermato' => true,
                ]),
                'timeout' => 15,
            ]
        );
    }
}
