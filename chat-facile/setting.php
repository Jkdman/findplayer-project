<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — ADMIN SETTINGS
 * ======================================================
 */

add_action('admin_menu', 'cf_chat_admin_menu');
add_action('admin_init', 'cf_chat_register_settings');

function cf_chat_admin_menu() {

    add_options_page(
        'Chat Facile',
        'Chat Facile',
        'manage_options',
        'chat-facile',
        'cf_chat_settings_page'
    );
}

function cf_chat_register_settings() {

register_setting('cf_chat_settings', 'cf_chat_supabase_url', [
    'sanitize_callback' => 'esc_url_raw'
]);

register_setting('cf_chat_settings', 'cf_chat_supabase_api_key', [
    'sanitize_callback' => 'sanitize_text_field'
]);

register_setting('cf_chat_settings', 'cf_chat_supabase_anon_key', [
    'sanitize_callback' => 'sanitize_text_field'
]);


function cf_chat_settings_page() {
    ?>
    <div class="wrap">
        <h1>Chat Facile — Impostazioni</h1>

        <form method="post" action="options.php">
            <?php settings_fields('cf_chat_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Supabase URL</th>
                    <td>
                        <input type="text" name="cf_chat_supabase_url" class="regular-text"
                               value="<?php echo esc_attr(get_option('cf_chat_supabase_url')); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">Supabase API Key</th>
                    <td>
                        <input type="password" name="cf_chat_supabase_api_key" class="regular-text"
                               value="<?php echo esc_attr(get_option('cf_chat_supabase_api_key')); ?>">
                    </td>
                </tr>

                <tr>
                    <th scope="row">Supabase Anon Public Key (Realtime)</th>
                    <td>
                        <input type="password" name="cf_chat_supabase_anon_key" class="regular-text"
                               value="<?php echo esc_attr(get_option('cf_chat_supabase_anon_key')); ?>">
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}