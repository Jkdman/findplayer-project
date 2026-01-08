(function ($) {
    'use strict';

    /**
     * ======================================================
     * CHAT FACILE — UI
     * ======================================================
     */

    const ChatUI = {

        init() {
            this.bindUI();

            // UTENTE ONLINE
            $.post(CF_CHAT.ajax_url, {
                action: 'cf_chat_set_online',
                nonce: CF_CHAT.nonce
            });

            // UTENTE OFFLINE (uscita pagina)
            window.addEventListener('beforeunload', function () {

                const data = new FormData();
                data.append('action', 'cf_chat_set_offline');
                data.append('nonce', CF_CHAT.nonce);

                navigator.sendBeacon(CF_CHAT.ajax_url, data);
            });
        },

        bindUI() {

            // Focus automatico input quando si apre una chat
            $(document).on('chat:opened', function () {
                $('#cf-chat-text').focus();
            });

            // Scroll automatico su nuovi messaggi
            $(document).on('chat:messages:updated', function () {
                const box = $('#cf-chat-messages');
                if (box.length) {
                    box.scrollTop(box[0].scrollHeight);
                }
            });
        }
    };

    $(document).ready(function () {
        ChatUI.init();
        window.ChatUI = ChatUI;
    });

})(jQuery);