<?php
if (!defined('ABSPATH')) exit;

/**
 * Crea tabella log email
 */
register_activation_hook(
    FP_PM_PATH . 'fp-private-mail.php',
    'fp_pm_create_log_table'
);

function fp_pm_create_log_table() {
    global $wpdb;

    $table = $wpdb->prefix . 'fp_pm_mail_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        admin_id BIGINT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Salva log invio mail
 */
function fp_pm_log_mail($user_id, $admin_id, $message) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'fp_pm_mail_log',
        [
            'user_id'    => $user_id,
            'admin_id'   => $admin_id,
            'message'    => $message,
            'created_at' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s']
    );
}

function fp_pm_get_logs_by_user($user_id) {
    global $wpdb;

    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}fp_pm_mail_log
             WHERE user_id = %d
             ORDER BY created_at DESC",
            $user_id
        )
    );
}