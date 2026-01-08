<?php
/**
 * Plugin Name: Find Player – Messaggi Privati Email
 * Description: Invio messaggi email diretti agli utenti in modalità privacy-safe.
 * Version: 1.0.0
 * Author: Facile PMI
 */
if (!defined('ABSPATH')) exit;

add_filter('wp_mail_content_type', function () {
    return 'text/html; charset=UTF-8';
});

define('FP_PM_PATH', plugin_dir_path(__FILE__));
define('FP_PM_URL', plugin_dir_url(__FILE__));

require_once FP_PM_PATH . 'includes/helpers.php';
require_once FP_PM_PATH . 'includes/permissions.php';
require_once FP_PM_PATH . 'includes/mail-sender.php';
require_once FP_PM_PATH . 'includes/mail-logger.php';
require_once FP_PM_PATH . 'includes/ajax-send-mail.php';

if (is_admin()) {
    require_once FP_PM_PATH . 'admin/admin-hooks.php';
    require_once FP_PM_PATH . 'admin/metabox-mail.php';
}