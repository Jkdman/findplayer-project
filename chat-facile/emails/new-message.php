<?php
/**
 * ======================================================
 * CHAT FACILE — EMAIL NUOVO MESSAGGIO
 * ======================================================
 *
 * Variabili disponibili:
 * $recipient_email
 * $recipient_name
 * $sender_name
 * $message_text
 * $chat_link
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<p>Ciao <?php echo esc_html($recipient_name); ?>,</p>

<p>
Hai ricevuto un nuovo messaggio su <strong>Chat Facile</strong> da
<strong><?php echo esc_html($sender_name); ?></strong>.
</p>

<blockquote style="border-left:4px solid #4CAF50;padding-left:10px;color:#333;">
<?php echo nl2br(esc_html($message_text)); ?>
</blockquote>

<p>
Per rispondere, accedi alla chat:
</p>

<p>
<a href="<?php echo esc_url($chat_link); ?>" style="background:#4CAF50;color:#fff;padding:10px 14px;border-radius:6px;text-decoration:none;">
Apri la chat
</a>
</p>

<p style="font-size:12px;color:#777;">
Non rispondere a questa email.<br>
Il messaggio è stato inviato tramite Chat Facile.
</p>