<?php
if (!defined('ABSPATH')) exit;

/**
 * SHORTCODE INBOX – STORICO CONVERSAZIONI
 * Uso: [fp_inbox]
 */

add_shortcode('fp_inbox', function () {
    
if (!is_user_logged_in()) {
    return '<p>Devi essere loggato per vedere i messaggi.</p>';
}

fp_clear_unread_messages(get_current_user_id());

    global $wpdb;
    $user_id = get_current_user_id();
    $table   = $wpdb->prefix . 'fp_user_messages';

    // Recupera ultimo messaggio per conversazione
    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT m.*
        FROM {$table} m
        INNER JOIN (
            SELECT
                CASE
                    WHEN from_user = %d THEN to_user
                    ELSE from_user
                END AS other_user,
                MAX(created_at) AS last_date
            FROM {$table}
            WHERE from_user = %d OR to_user = %d
            GROUP BY other_user
        ) x
        ON (
            (m.from_user = %d AND m.to_user = x.other_user)
            OR
            (m.to_user = %d AND m.from_user = x.other_user)
        )
        AND m.created_at = x.last_date
        ORDER BY m.created_at DESC
    ", $user_id, $user_id, $user_id, $user_id, $user_id));

    if (empty($rows)) {
        return '<p>Nessuna conversazione.</p>';
    }

    ob_start();
    ?>
    <div class="fp-inbox">
        <h2>I tuoi messaggi</h2>
        <ul class="fp-inbox-list">
            <?php foreach ($rows as $r) :
                $other_id = ($r->from_user == $user_id) ? $r->to_user : $r->from_user;
                $other    = get_user_by('ID', $other_id);
                if (!$other) continue;
            ?>
                <li class="fp-inbox-item">
                    <strong><?php echo esc_html($other->display_name); ?></strong><br>
                    <span class="fp-inbox-preview">
                        <?php echo esc_html(wp_trim_words($r->message, 14)); ?>
                    </span>
                    <div class="fp-inbox-actions">
                        <a href="<?php echo esc_url(site_url('/invia-messaggio?to=' . $other->user_login . '&reply=1')); ?>">
                            Rispondi
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php

    return ob_get_clean();
});

/**
 * NOTIFICHE MESSAGGI – BADGE NON LETTI
 */

/* Incrementa contatore messaggi non letti */
function fp_increment_unread_messages($user_id) {
    $count = (int) get_user_meta($user_id, 'fp_unread_messages', true);
    update_user_meta($user_id, 'fp_unread_messages', $count + 1);
}

/* Azzera contatore */
function fp_clear_unread_messages($user_id) {
    update_user_meta($user_id, 'fp_unread_messages', 0);
}

/* Recupera contatore */
function fp_get_unread_messages($user_id) {
    return (int) get_user_meta($user_id, 'fp_unread_messages', true);
}

/**
 * LOG MESSAGGI UTENTE → UTENTE
 */

/* Crea tabella (1 sola volta) */
add_action('plugins_loaded', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'fp_user_messages';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        from_user BIGINT UNSIGNED NOT NULL,
        to_user BIGINT UNSIGNED NOT NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY from_user (from_user),
        KEY to_user (to_user)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

/* Salva messaggio */
function fp_log_user_message($from, $to, $message) {
    global $wpdb;

    $wpdb->insert(
        $wpdb->prefix . 'fp_user_messages',
        [
            'from_user'  => $from,
            'to_user'    => $to,
            'message'    => $message,
            'created_at' => current_time('mysql')
        ],
        ['%d', '%d', '%s', '%s']
    );
}

/**
 * RATE LIMIT – MESSAGGI UTENTE → UTENTE
 * Max 5 messaggi al giorno per utente
 */

function fp_can_send_user_message($user_id) {

    if (current_user_can('manage_options')) {
        return true; // nessun limite in fase di test
    }

    $today = date('Ymd');
    $key   = 'fp_msg_count_' . $today;

    $count = (int) get_user_meta($user_id, $key, true);

    return $count < 35;
}


function fp_increment_user_message_count($user_id) {

    $today = date('Ymd');
    $key   = 'fp_msg_count_' . $today;

    $count = (int) get_user_meta($user_id, $key, true);
    update_user_meta($user_id, $key, $count + 1);
}

/**
 * SHORTCODE FRONTEND – INVIA MESSAGGIO UTENTE → UTENTE
 * Uso: [fp_send_message]
 */

add_shortcode('fp_send_message', function () {

    if (!is_user_logged_in()) {
        return '<p>Devi essere loggato per inviare un messaggio.</p>';
    }

    if (empty($_GET['to'])) {
        return '<p>Destinatario non valido.</p>';
    }

    $to_user = get_user_by('login', sanitize_text_field($_GET['to']));
    if (!$to_user) {
        return '<p>Utente non trovato.</p>';
    }

    $from_user = wp_get_current_user();
    fp_clear_unread_messages($from_user->ID);

    if ($from_user->ID === $to_user->ID) {
        return '<p>Non puoi scrivere a te stesso.</p>';
    }

    ob_start();
    ?>
    <form method="post">
<h3>
    <?php
    if (!empty($_GET['reply'])) {
        echo 'Rispondi a ' . esc_html($to_user->display_name);
    } else {
        echo 'Invia un messaggio a ' . esc_html($to_user->display_name);
    }
    ?>
</h3>
        <textarea name="fp_message" rows="6" required
                  placeholder="Scrivi il tuo messaggio..."
                  style="width:100%;max-width:600px;"></textarea>

        <?php wp_nonce_field('fp_send_message_nonce'); ?>

        <p>
            <button type="submit" class="button button-primary">
                Invia messaggio
            </button>
        </p>
    </form>
    <?php

    if (!empty($_POST['fp_message']) && check_admin_referer('fp_send_message_nonce')) {
        
        if (!fp_can_send_user_message($from_user->ID)) {
    echo '<p style="color:red;">Hai raggiunto il limite giornaliero di messaggi.</p>';
    return ob_get_clean();
}

        $raw_message = wp_strip_all_tags($_POST['fp_message']);
        if (!empty($_GET['reply'])) {
    $raw_message = 'Risposta: ' . $raw_message;
}
        // BLOCCO LINK E NUMERI
        if (preg_match('/(https?:\/\/|www\.|\+?[0-9]{6,})/i', $raw_message)) {
            echo '<p style="color:red;">Messaggio non valido: link o numeri non ammessi.</p>';
            return ob_get_clean();
        }

$player_post = get_posts([
    'post_type'  => 'fp_giocatore',
    'meta_key'   => 'fp_wp_user_id',
    'meta_value' => $from_user->ID,
    'numberposts'=> 1,
]);

$profile_url = $player_post
    ? get_permalink($player_post[0]->ID)
    : site_url('/');

        // EMAIL
        if (function_exists('fp_pm_send_email_to_user')) {

            ob_start();
            $from_nickname = $from_user->display_name;
            $message = $raw_message;
            $profile_url   = $profile_url;

            include FP_PM_PATH . 'templates/mail-user-to-user.php';
            $email_body = ob_get_clean();

fp_pm_send_email_to_user(
    $to_user->ID,
    $email_body,
    'USER-MESSAGE'
);

fp_log_user_message(
    $from_user->ID,
    $to_user->ID,
    $raw_message
);
            fp_increment_unread_messages($to_user->ID);

            fp_increment_user_message_count($from_user->ID);
        }
    }

    return ob_get_clean();
});

/**
 * QUEUE EMAIL AUTOMATICHE – WP CRON
 */

/* 1. SCHEDULER */
add_action('fp_pm_send_queued_mail', function ($user_id, $message, $tag, $attempt = 1) {

    if (get_user_meta($user_id, 'fp_pm_no_contact', true)) return;
    if (!get_option('fp_pm_auto_mail_enabled')) return;

    if (!function_exists('fp_pm_send_email_to_user')) return;

    $sent = fp_pm_send_email_to_user($user_id, $message);

    if ($sent) {
        fp_pm_log_mail($user_id, 0, '[' . $tag . '] ' . $message);
        return;
    }

    // 🔁 RETRY (max 3 tentativi)
    if ($attempt < 3) {

$delay = $attempt * 120; // 2 min, 4 min

wp_schedule_single_event(
    time() + $delay,
    'fp_pm_send_queued_mail',
    [$user_id, $message, $tag, $attempt + 1]
);

    } else {
        // ❌ fallimento definitivo
        fp_pm_log_mail($user_id, 0, '[FAILED-' . $tag . '] ' . $message);
    }

}, 10, 4);

/* 2. FUNZIONE PER METTERE IN CODA */
function fp_pm_queue_email($user_id, $message, $tag = 'AUTO') {

    wp_schedule_single_event(
        time() + 60, // delay 60 secondi
        'fp_pm_send_queued_mail',
        [$user_id, $message, $tag]
    );
}

/**
 * TOGGLE TRIGGER EMAIL AUTOMATICHE
 */

/* 1. Registro opzione */
add_action('admin_init', function () {
    register_setting('fp_pm_settings', 'fp_pm_auto_mail_enabled');
});

/* 2. Aggiungo voce menu */
add_action('admin_menu', function () {

    add_options_page(
        'Find Player – Email automatiche',
        'Find Player – Email',
        'manage_options',
        'fp-pm-settings',
        'fp_pm_render_settings_page'
    );
});

/* 3. Render pagina impostazioni */
function fp_pm_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Email automatiche – Find Player</h1>

        <form method="post" action="options.php">
            <?php settings_fields('fp_pm_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">Email automatiche</th>
                    <td>
                        <input type="checkbox"
                               name="fp_pm_auto_mail_enabled"
                               value="1"
                               <?php checked(get_option('fp_pm_auto_mail_enabled'), 1); ?>>
                        <span class="description">
                            Abilita invio automatico email (creazione / approvazione scheda).
                        </span>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * SMTP CONFIGURAZIONE GLOBALE
 * (vale per tutto WordPress)
 */
add_action('phpmailer_init', function ($phpmailer) {

    $phpmailer->isSMTP();
    $phpmailer->Host       = 'smtp.findplayer.it';
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Port       = 587;
    $phpmailer->Username   = 'noreply@findplayer.it';
    // SECURITY: Password should be stored in wp-config.php or environment variable
    // Define FINDPLAYER_SMTP_PASSWORD in wp-config.php
    $phpmailer->Password   = defined('FINDPLAYER_SMTP_PASSWORD') ? FINDPLAYER_SMTP_PASSWORD : '';
    $phpmailer->SMTPSecure = 'tls';

    $phpmailer->From       = 'noreply@findplayer.it';
    $phpmailer->FromName   = 'Find Player';
});

/**
 * EMAIL AUTOMATICHE – fp_giocatore
 */

/* Creazione scheda */
add_action('wp_insert_post', function ($post_id, $post, $update) {

    if ($update) return;
    if ($post->post_type !== 'fp_giocatore') return;

    $user_id = $post->post_author;
    if (!$user_id) return;

    if (!get_option('fp_pm_auto_mail_enabled')) return;

    if (get_user_meta($user_id, 'fp_pm_no_contact', true)) return;

    $message = "Ciao,\n\nla tua scheda giocatore è stata creata correttamente.\n\n— Team Find Player";

fp_pm_queue_email($user_id, $message, 'AUTO-CREATE');

}, 10, 3);


/* Pubblicazione scheda */
add_action('transition_post_status', function ($new, $old, $post) {

    if ($post->post_type !== 'fp_giocatore') return;
    if ($old === 'publish' || $new !== 'publish') return;

    $user_id = $post->post_author;
    if (!$user_id) return;

    if (!get_option('fp_pm_auto_mail_enabled')) return;

    if (get_user_meta($user_id, 'fp_pm_no_contact', true)) return;

    $message = "Ciao,\n\nla tua scheda giocatore è stata approvata ed è ora visibile.\n\n— Team Find Player";

fp_pm_queue_email($user_id, $message, 'AUTO-PUBLISH');

}, 10, 3);


/**
 * Campo privacy – utente non contattabile
 */
add_action('show_user_profile', 'fp_pm_user_privacy_field');
add_action('edit_user_profile', 'fp_pm_user_privacy_field');

function fp_pm_user_privacy_field($user) {
    ?>
    <h3>Privacy – Comunicazioni</h3>

    <table class="form-table">
        <tr>
            <th>
                <label for="fp_pm_no_contact">Comunicazioni email</label>
            </th>
            <td>
                <input type="checkbox"
                       name="fp_pm_no_contact"
                       id="fp_pm_no_contact"
                       value="1"
                       <?php checked(get_user_meta($user->ID, 'fp_pm_no_contact', true), 1); ?>>
                <span class="description">
                    Impedisce l’invio di messaggi email diretti.
                </span>
            </td>
        </tr>
    </table>
    <?php
}

add_action('personal_options_update', 'fp_pm_save_user_privacy_field');
add_action('edit_user_profile_update', 'fp_pm_save_user_privacy_field');

function fp_pm_save_user_privacy_field($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }

    update_user_meta(
        $user_id,
        'fp_pm_no_contact',
        isset($_POST['fp_pm_no_contact']) ? 1 : 0
    );
}