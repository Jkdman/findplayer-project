<?php
if (!defined('ABSPATH')) exit;

/**
 * DASHBOARD COMUNICAZIONI – ADMIN
 */

add_action('admin_menu', function () {

    add_menu_page(
        'Comunicazioni Find Player',
        'Comunicazioni',
        'manage_options',
        'fp-pm-dashboard',
        'fp_pm_render_dashboard',
        'dashicons-email-alt',
        58
    );
});

/**
 * EXPORT CSV – COMUNICAZIONI
 */
add_action('admin_post_fp_pm_export_csv', function () {

    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'fp_pm_mail_log';

    $rows = $wpdb->get_results(
        "SELECT * FROM $table ORDER BY created_at DESC",
        ARRAY_A
    );

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=fp-comunicazioni.csv');

    $out = fopen('php://output', 'w');

    fputcsv($out, ['Data', 'User ID', 'Admin ID', 'Messaggio']);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['created_at'],
            $row['user_id'],
            $row['admin_id'],
            $row['message']
        ]);
    }

    fclose($out);
    exit;
});

function fp_pm_render_dashboard() {
    global $wpdb;

    $table = $wpdb->prefix . 'fp_pm_mail_log';

    $logs = $wpdb->get_results(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT 100"
    );
    ?>
    <div class="wrap">
        <h1>Comunicazioni – Find Player</h1>
<p>
    <a href="<?php echo esc_url(admin_url('admin-post.php?action=fp_pm_export_csv')); ?>"
       class="button button-primary">
       Esporta CSV
    </a>
</p>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Utente</th>
                    <th>Admin</th>
                    <th>Messaggio</th>
                </tr>
            </thead>
            <tbody>

            <?php if (empty($logs)) : ?>
                <tr>
                    <td colspan="4"><em>Nessuna comunicazione trovata.</em></td>
                </tr>
            <?php else : ?>

                <?php foreach ($logs as $log) :
                    $user  = get_userdata($log->user_id);
                    $admin = get_userdata($log->admin_id);
                ?>
                <tr>
                    <td><?php echo esc_html(date('d/m/Y H:i', strtotime($log->created_at))); ?></td>
                    <td><?php echo esc_html($user ? $user->display_name : '—'); ?></td>
                    <td><?php echo esc_html($admin ? $admin->display_name : 'Sistema'); ?></td>
                    <td style="white-space:pre-wrap;"><?php echo esc_html($log->message); ?></td>
                </tr>
                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>
        </table>
    </div>
    <?php
}


add_action('admin_enqueue_scripts', function ($hook) {

    // Carichiamo solo sulle schede fp_giocatore
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }

    global $post;
    if (!$post || $post->post_type !== 'fp_giocatore') {
        return;
    }

    wp_enqueue_script(
        'fp-private-mail-js',
        FP_PM_URL . 'assets/js/fp-private-mail.js',
        ['jquery'],
        '1.0.0',
        true
    );

    wp_localize_script('fp-private-mail-js', 'FP_PM', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('fp_pm_nonce')
    ]);
});