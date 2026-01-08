<?php
/**
 * Assets Helper
 * 
 * Handles enqueuing of scripts and styles
 */

if (!defined('ABSPATH')) exit;

class FP_Assets_Helper {
    
    /**
     * Initialize asset enqueuing
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_leaflet']);
    }
    
    /**
     * Enqueue Leaflet library for maps
     */
    public static function enqueue_leaflet() {
        wp_enqueue_style(
            'leaflet-css',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
        );
        
        wp_enqueue_script(
            'leaflet-js',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            [],
            null,
            true
        );
    }
}
