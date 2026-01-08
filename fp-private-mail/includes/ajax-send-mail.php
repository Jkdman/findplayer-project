<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_fp_pm_send_mail', 'fp_pm_send_mail');

function fp_pm_send_mail() {

    check_ajax_referer('fp_pm_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    $message = sanitize_textarea_field($_POST['message'] ?? '');

    if (!$user_id || !$message) {
        wp_send_json_error('Dati non validi');
    }

    // 🔐 BLOCCO PRIVACY — DEVE STARE QUI
    if (get_user_meta($user_id, 'fp_pm_no_contact', true)) {
        wp_send_json_error('Utente non contattabile per scelta privacy');
    }

    if (!function_exists('fp_pm_send_email_to_user')) {
        wp_send_json_error('Mailer non disponibile');
    }

    $sent = fp_pm_send_email_to_user($user_id, $message);

    if (!$sent) {
        wp_send_json_error('Errore invio email');
    }

    // 🧾 LOG INVIO (SOLO SE MAIL PARTITA)
    fp_pm_log_mail(
        $user_id,
        get_current_user_id(),
        $message
    );

    wp_send_json_success('Email inviata e registrata');
}