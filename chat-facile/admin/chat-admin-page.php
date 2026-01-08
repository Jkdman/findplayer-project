<?php
if (!defined('ABSPATH')) exit;

/**
 * ======================================================
 * PAGINA ADMIN — CHAT FACILE
 * ======================================================
 */

add_action('admin_menu', 'cf_chat_register_admin_page');

function cf_chat_register_admin_page() {

    if (!current_user_can('administrator')) return;

    add_menu_page(
        'Chat Facile',
        'Chat Facile',
        'administrator',
        'cf-chat-admin',
        'cf_chat_admin_page_render',
        'dashicons-format-chat',
        56
    );
}

function cf_chat_admin_page_render() {
    ?>
    <div class="wrap">
        <h1>Chat Facile – Controllo Admin</h1>

        <p>
            <button id="cf-clear-chat" class="button button-primary">
                🧹 Svuota TUTTE le chat
            </button>
        </p>
    </div>

<script>
document.getElementById('cf-clear-chat').addEventListener('click', () => {

    console.log('CLICK');

    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=cf_chat_clear_all'
    })
    .then(r => r.text())
    .then(t => {
        console.log('RISPOSTA:', t);
        alert(t);
    });

});
</script>

    <?php
}