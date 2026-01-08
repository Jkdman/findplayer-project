<?php
if (!defined('ABSPATH')) exit;

/**
 * ======================================================
 * DASHBOARD WIDGET — CHAT FACILE (ADMIN)
 * ======================================================
 */

add_action('wp_dashboard_setup', 'cf_chat_register_dashboard_widget');

function cf_chat_register_dashboard_widget() {

    if (!current_user_can('administrator')) return;

    wp_add_dashboard_widget(
        'cf_chat_admin_widget',
        'Chat Facile – Controllo Admin',
        'cf_chat_admin_widget_render'
    );
}

function cf_chat_admin_widget_render() {
    ?>
    <h3>TEST WIDGET</h3>

    <button id="cf-clear-chat" class="button button-secondary">
        🧹 Svuota TUTTE le chat
    </button>

    <script>
    document.getElementById('cf-clear-chat').addEventListener('click', () => {

        if (!confirm('ATTENZIONE: cancellare TUTTI i messaggi?')) return;

        fetch(ajaxurl, {
            method: 'POST',
            body: new URLSearchParams({
                action: 'cf_chat_clear_all'
            })
        }).then(() => alert('Chat svuotata'));

    });
    </script>
    <?php
}