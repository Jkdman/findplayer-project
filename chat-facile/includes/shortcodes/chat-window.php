<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — SHORTCODE CHAT WINDOW
 * ======================================================
 * Shortcode: [cf_chat]
 */
/*ATTENZIONE!!! per cambiare altezza chat non basta sostituire i valori di cf-chat-app qui sotto ma bisogna anche cambiare quello in chat.css*/

add_shortcode('cf_chat', 'cf_chat_render_window');

function cf_chat_render_window() {

    if (!is_user_logged_in()) {
        return '<p>Devi effettuare l’accesso per usare la chat.</p>';
    }

    if (!cf_chat_user_can_chat()) {
        return '<p>La chat non è abilitata per il tuo profilo.</p>';
    }

    ob_start();
    ?>

<div id="cf-chat-app" class="cf-chat-app" style="height:500px;max-height:700px;overflow:hidden;display:flex;flex-direction:column;">
        <input type="hidden" id="cf-chat-nonce" value="<?php echo wp_create_nonce('cf_chat_nonce'); ?>">

        <div class="cf-chat-header">
            <button id="cf-chat-global" type="button">Chat Globale</button>
            <span class="cf-chat-title">Chat Facile</span>
        </div>

        <div id="cf-chat-messages" class="cf-chat-messages">
            <p class="cf-chat-placeholder">Apri una chat per iniziare a scrivere.</p>
        </div>

        <div class="cf-chat-input">
            <input
                type="text"
                id="cf-chat-text"
                placeholder="Scrivi un messaggio..."
                autocomplete="off"
            />
            <button id="cf-chat-send">Invia</button>
        </div>

    </div>

    <?php
    return ob_get_clean();
}