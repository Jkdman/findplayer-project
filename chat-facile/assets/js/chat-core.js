(function ($) {
  'use strict';

  const ChatFacile = {
    roomId: null,

    init() {
        
      this.bindEvents();

      // Se esiste il bottone, apriamo subito la globale
      if ($('#cf-chat-global').length) {
        this.openGlobal();
      }
        // 🔥 AUTO REFRESH ogni 5 secondi
  this.startAutoRefresh();
},

startAutoRefresh() {
  setInterval(() => {
    if (this.roomId === 'global') {
      this.fetchGlobalMessages();
    }
  }, 5000); // 5 secondi
},


    bindEvents() {

      // CHAT GLOBALE
      $(document).on('click', '#cf-chat-global', (e) => {
        e.preventDefault();
        this.openGlobal();
      });

      // INVIO
      $(document).on('click', '#cf-chat-send', (e) => {
        e.preventDefault();
        this.sendGlobalMessage();
      });

      // ENTER
      $(document).on('keypress', '#cf-chat-text', (e) => {
        if (e.which === 13) {
          e.preventDefault();
          this.sendGlobalMessage();
        }
      });
    },

    openGlobal() {
      this.roomId = 'global';

      $('#cf-chat-global').addClass('active');
      $('.cf-chat-title').text('Chat Globale');
      $('#cf-chat-messages').html('<p class="cf-chat-loading">Caricamento chat globale...</p>');

      this.fetchGlobalMessages();
    },

    fetchGlobalMessages() {
      if (!this.roomId) return;

      $.post(CF_CHAT.ajax_url, {
        action: 'cf_chat_fetch_messages',
        nonce: CF_CHAT.nonce,
        room_id: 'global'
      }).done((response) => {

        if (!response || !response.success) {
          $('#cf-chat-messages').html('<p class="cf-chat-empty">Nessun messaggio.</p>');
          return;
        }

        const messages = response.data.messages || [];
        const box = $('#cf-chat-messages');
        box.empty();

        if (!messages.length) {
          box.html('<p class="cf-chat-empty">Nessun messaggio.</p>');
          return;
        }

        messages.forEach((msg) => {
          const isMe = (String(msg.sender_id) === String(CF_CHAT.user_id));
          const cls = isMe ? 'me' : 'other';
          const nick = msg.display_name || msg.sender_name || 'Utente';

const ts = msg.created_ts ? Number(msg.created_ts) * 1000 : Date.now();
const dateObj = new Date(ts);

const dateStr = new Intl.DateTimeFormat('it-IT', {
  timeZone: 'Europe/Rome',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric'
}).format(dateObj);

const timeStr = new Intl.DateTimeFormat('it-IT', {
  timeZone: 'Europe/Rome',
  hour: '2-digit',
  minute: '2-digit'
}).format(dateObj);

box.append(`
  <div class="cf-chat-message ${cls}">
    <div class="cf-chat-meta">
<a 
  href="/giocatore/${msg.sender_id}" 
  class="cf-chat-nick-link"
>
  ${this.escapeHtml(nick)}
</a>
      <span class="cf-chat-date">${dateStr} · ${timeStr}</span>
    </div>
    ${this.escapeHtml(msg.message || '')}
  </div>
`);
        });

        $(document).trigger('chat:messages:updated');
      });
    },

    sendGlobalMessage() {
      const textEl = $('#cf-chat-text');
      const text = (textEl.val() || '').trim();
      if (!text) return;

      $.post(CF_CHAT.ajax_url, {
        action: 'cf_chat_send_message',
        nonce: CF_CHAT.nonce,
        room_id: 'global',
        message: text
      }).done((response) => {
        if (response && response.success) {
          textEl.val('');
          this.fetchGlobalMessages();
        }
      });
    },

    escapeHtml(str) {
      return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }
  };

  $(document).ready(function () {
    ChatFacile.init();
    window.ChatFacile = ChatFacile;
  });

})(jQuery);