<?php
/**
 * Calendar Integration Helper
 * 
 * Exports FindPlayer events to "Calendario Eventi Universale" plugin
 */

if (!defined('ABSPATH')) exit;

class FP_Calendar_Integration {
    
    /**
     * Initialize calendar integration
     */
    public static function init() {
        add_filter('ce_get_events', [__CLASS__, 'export_events_to_calendar'], 10, 2);
    }
    
    /**
     * Export FindPlayer events to the universal calendar plugin
     * 
     * @param array $events Existing events array
     * @param array $args   Query arguments (start_date, end_date)
     * @return array Modified events array
     */
    public static function export_events_to_calendar($events, $args) {
        $start_date = $args['start_date'];
        $end_date = $args['end_date'];
        
        // Build Supabase query URL
        $url = FPFP_SUPABASE_URL . '/rest/v1/allenamenti'
             . '?select=id,disciplina,data_evento,wp_post_id,citta'
             . '&data_evento=gte.' . $start_date
             . '&data_evento=lte.' . $end_date
             . '&evento_confermato=eq.true';
        
        $response = wp_remote_get($url, [
            'headers' => [
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            ],
            'timeout' => 20,
        ]);
        
        if (is_wp_error($response)) {
            return $events;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return $events;
        }
        
        $eventi_gia_aggiunti = [];
        
        foreach ($data as $row) {
            $wp_post_id = intval($row['wp_post_id'] ?? 0);
            
            // Skip events without WordPress post
            if (!$wp_post_id) {
                continue;
            }
            
            // Only include published posts
            if (get_post_status($wp_post_id) !== 'publish') {
                continue;
            }
            
            // Create unique key to avoid duplicates
            $unique_key = ($row['data_evento'] ?? '') . '|' .
                          ($row['disciplina'] ?? '') . '|' .
                          ($row['citta'] ?? '');
            
            // Skip duplicate events
            if (isset($eventi_gia_aggiunti[$unique_key])) {
                continue;
            }
            
            $eventi_gia_aggiunti[$unique_key] = true;
            
            $events[] = [
                'date'       => $row['data_evento'],
                'title'      => $row['disciplina'],
                'url'        => get_permalink($wp_post_id),
                'discipline' => $row['disciplina'],
            ];
        }
        
        return $events;
    }
}
