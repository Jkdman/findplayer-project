<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ======================================================
 * CHAT FACILE — SHORTCODE CHAT BUTTON
 * ======================================================
 * Shortcode: [cf_chat_button user_id="123"]
 */

add_shortcode('cf_chat_button', 'cf_chat_render_button');

function cf_chat_render_button($atts) {

    if (!is_user_logged_in()) {
        return '';
    }

    if (!cf_chat_user_can_chat()) {
        return '';
    }

    $atts = shortcode_atts([
        'user_id' => 0,
        'label'   => 'Chatta',
        'class'   => '',
    ], $atts);

    $other_user_id = (int) $atts['user_id'];
    $me = get_current_user_id();

    if (!$other_user_id || $other_user_id === $me) {
        return '';
    }

    if (!cf_chat_can_open_chat($other_user_id)) {
        return '';
    }

    $online = cf_chat_is_user_online($other_user_id);
    $status = $online ? 'online' : 'offline';

    ob_start();
    ?>
    <button
        class="cf-chat-open cf-chat-<?php echo esc_attr($status); ?> <?php echo esc_attr($atts['class']); ?>"
        data-user-id="<?php echo esc_attr($other_user_id); ?>"
        data-status="<?php echo esc_attr($status); ?>"
        type="button"
    >
        <?php echo esc_html($atts['label']); ?>
    </button>
    <?php

    return ob_get_clean();
}