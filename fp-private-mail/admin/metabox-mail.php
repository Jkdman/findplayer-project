<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {

    add_meta_box(
        'fp_pm_metabox',
        'Messaggio privato (Email)',
        'fp_pm_render_metabox',
        'fp_giocatore',
        'normal',
        'high'
    );

});

function fp_pm_render_metabox($post) {

    if (!current_user_can('manage_options')) {
        echo '<p>Permessi insufficienti.</p>';
        return;
    }

    $user_id    = $post->post_author;
    $no_contact = get_user_meta($user_id, 'fp_pm_no_contact', true);
    ?>

    <!-- ============================= -->
    <!-- INVIO MESSAGGIO -->
    <!-- ============================= -->

    <p>
        <strong>Invia un messaggio diretto all’utente</strong><br>
        <small>Comunicazione di servizio – email privacy-safe</small>
    </p>

    <textarea
        id="fp-pm-message"
        style="width:100%;min-height:120px;"
        placeholder="Scrivi qui il messaggio da inviare via email..."
        <?php disabled($no_contact, 1); ?>
    ></textarea>

    <p style="margin-top:10px;">
        <button
            type="button"
            class="button button-primary"
            id="fp-pm-send"
            data-user-id="<?php echo esc_attr($user_id); ?>"
            <?php disabled($no_contact, 1); ?>>
            Invia messaggio
        </button>
    </p>

    <?php if ($no_contact) : ?>
        <p style="color:#b32d2e;">
            ⚠️ L’utente ha disabilitato le comunicazioni email.
        </p>
    <?php endif; ?>

    <!-- ============================= -->
    <!-- STORICO MESSAGGI -->
    <!-- ============================= -->

    <?php
    $logs = fp_pm_get_logs_by_user($user_id);
    ?>

    <hr>

    <h4>Storico comunicazioni</h4>

    <?php if (empty($logs)) : ?>

        <p><em>Nessun messaggio inviato.</em></p>

    <?php else : ?>

        <ul style="margin-left:0;">

            <?php foreach ($logs as $log) :
                $admin = get_userdata($log->admin_id);
            ?>

            <li style="margin-bottom:12px;padding:10px;border:1px solid #ddd;border-radius:6px;background:#fafafa;">
                <strong>Data:</strong>
                <?php echo esc_html(date('d/m/Y H:i', strtotime($log->created_at))); ?><br>

                <strong>Da:</strong>
                <?php echo esc_html($admin ? $admin->display_name : 'Admin'); ?><br>

                <strong>Messaggio:</strong><br>

                <div style="margin-top:5px;white-space:pre-wrap;">
                    <?php echo esc_html($log->message); ?>
                </div>
            </li>

            <?php endforeach; ?>

        </ul>

    <?php endif; ?>
    <?php
}