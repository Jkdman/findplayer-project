(function ($) {
    'use strict';

    /**
     * ======================================================
     * CHAT FACILE — REALTIME (Supabase)
     * ======================================================
     * Richiede:
     *  - CF_CHAT.supabase_url
     *  - CF_CHAT.supabase_anon_key
     */

    const ChatRealtime = {
        client: null,
        channel: null,

        init() {
            // Se mancano config realtime, non blocchiamo nulla: fallback su fetchMessages()
            if (!window.CF_CHAT || !CF_CHAT.supabase_url || !CF_CHAT.supabase_anon_key) {
                return;
            }

            // Carica Supabase JS SDK da CDN (lazy, solo se serve)
            this.loadSupabaseSDK()
                .then(() => {
                    this.createClient();
                    this.bindRoomSubscription();
                })
                .catch(() => {
                    // fallback silenzioso
                });
        },

        loadSupabaseSDK() {
            return new Promise((resolve, reject) => {

                if (window.supabase && window.supabase.createClient) {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.min.js';
                script.async = true;

                script.onload = () => resolve();
                script.onerror = () => reject();

                document.head.appendChild(script);
            });
        },

        createClient() {
            this.client = window.supabase.createClient(CF_CHAT.supabase_url, CF_CHAT.supabase_anon_key);
        },

        bindRoomSubscription() {
            // Quando cambia stanza, ri-sottoscriviamo
            $(document).on('chat:room:ready', () => {
                this.subscribeToActiveRoom();
            });
        },

subscribeToActiveRoom() {
    if (!this.client || !window.ChatFacile) return;
    if (!window.ChatFacile.roomId) return;

    const roomId = window.ChatFacile.roomId;

    // Chiudi eventuale channel precedente
    if (this.channel) {
        this.client.removeChannel(this.channel);
        this.channel = null;
    }

    // Subscribe realtime Supabase
    this.channel = this.client
        .channel('chat_room_' + roomId)
        .on(
            'postgres_changes',
            {
                event: 'INSERT',
                schema: 'public',
                table: 'cf_chat_messages', // ✅ NOME TABELLA CORRETTO
                filter: 'room_id=eq.' + roomId
            },
            () => {
                if (window.ChatFacile && typeof window.ChatFacile.fetchMessages === 'function') {
                    window.ChatFacile.fetchMessages();
                }
            }
        )
        .subscribe();
}

    };

    $(document).ready(function () {
        ChatRealtime.init();
        window.ChatRealtime = ChatRealtime;
    });

})(jQuery);