<?php
if (!defined('ABSPATH')) exit;

function fp_pm_send_email_to_user($user_id, $html_body) {

    $user = get_userdata($user_id);
    if (!$user || empty($user->user_email)) {
        return false;
    }

    add_filter('wp_mail_content_type', function () {
        return 'text/html; charset=UTF-8';
    });

    $sent = wp_mail(
        $user->user_email,
        'Nuovo messaggio su FIND PLAYER',
        $html_body
    );

    remove_filter('wp_mail_content_type', '__return_false');

    return $sent;
}