<?php
/**
 * FindPlayer Plugin Configuration
 * 
 * Centralized configuration for Supabase and other settings
 */

if (!defined('ABSPATH')) exit;

// Supabase compatibility layer
if (!defined('FP_SUPABASE_URL') && defined('FPFP_SUPABASE_URL')) {
    define('FP_SUPABASE_URL', FPFP_SUPABASE_URL);
}

if (!defined('FP_SUPABASE_API_KEY') && defined('FPFP_SUPABASE_API_KEY')) {
    define('FP_SUPABASE_API_KEY', FPFP_SUPABASE_API_KEY);
}

// Default Supabase configuration
if (!defined('FPFP_SUPABASE_URL')) {
    define('FPFP_SUPABASE_URL', 'https://wpxnpvsaleswzfagneib.supabase.co');
}

if (!defined('FPFP_SUPABASE_API_KEY')) {
    define('FPFP_SUPABASE_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6IndweG5wdnNhbGVzd3pmYWduZWliIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjA0ODI1MTYsImV4cCI6MjA3NjA1ODUxNn0.F6XXMUfbhUgICN4cieMYIcAgu33Pbbz0YhTSXgw-FQE');
}
