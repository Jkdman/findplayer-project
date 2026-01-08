<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — USER CHAT SETTINGS
 * ======================================================
 */

add_action('show_user_profile', 'cf_chat_user_profile_fields');
add_action('edit_user_profile', 'cf_chat_user_profile_fields');

add_action('personal_options_update', 'cf_chat_save_user_profile_fields');
add_action('edit_user_profile_update', 'cf_chat_save_user_profile_fields');

function cf_chat_user_profile_fields($user) {

    if (!current_user_can('manage_options')) {
        return;
    }

    $enabled = get_user_meta($user->ID, '_cf_chat_enabled', true);
    ?>
    <h2>Chat Facile</h2>

    <table class="form-table">
        <tr>
            <th>
                <label for="cf_chat_enabled">Abilita chat</label>
            </th>
            <td>
                <input type="checkbox" name="cf_chat_enabled" value="yes"
                    <?php checked($enabled, 'yes'); ?> />
                <span class="description">Consenti a questo utente di usare la chat</span>
            </td>
        </tr>

        <tr>
            <th>
                <label for="cf_chat_password">Password Chat</label>
            </th>
            <td>
                <input type="text" name="cf_chat_password" class="regular-text" autocomplete="off" />
                <p class="description">
                    Inserisci una nuova password per la chat (lascia vuoto per non modificare)
                </p>
            </td>
        </tr>
    </table>
    <?php
}

function cf_chat_save_user_profile_fields($user_id) {

    if (!current_user_can('manage_options')) {
        return;
    }

    // 🔒 sicurezza WP standard
    check_admin_referer('update-user_' . $user_id);

    // Abilitazione chat
    if (!empty($_POST['cf_chat_enabled']) && $_POST['cf_chat_enabled'] === 'yes') {
        update_user_meta($user_id, '_cf_chat_enabled', 'yes');
    } else {
        update_user_meta($user_id, '_cf_chat_enabled', 'no');
    }

    // Password chat
    if (!empty($_POST['cf_chat_password'])) {
        update_user_meta(
            $user_id,
            '_cf_chat_password',
            wp_hash_password(sanitize_text_field($_POST['cf_chat_password']))
        );
    }
}