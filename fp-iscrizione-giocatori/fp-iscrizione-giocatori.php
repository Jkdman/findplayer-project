<?php
/**
 * Plugin Name: Find Player - Iscrizione Giocatori (Supabase + Approve)
 * Description: Form frontend → token email → scheda WP pending → approvazione admin → invio a Supabase (giocatori + discipline).
 * Version: 1.0.0
 * Author: Facile PMI
 */

if (!defined('ABSPATH')) exit;

// =========================================================
// TEMPLATE LOADER (single fp_giocatore)
// =========================================================
require_once plugin_dir_path(__FILE__) . 'includes/template-loader.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-check-giocatore.php';

/* =========================================================
   CONFIG — SUPABASE (USA COSTANTI GLOBALI)
========================================================= */

if (!defined('FP_SUPABASE_URL') || !defined('FP_SUPABASE_API_KEY')) {
    wp_die('Configurazione Supabase mancante');
}

define('FP_MAIL_FROM_NAME', 'Find Player');
define('FP_MAIL_FROM_EMAIL', 'no-reply@findplayer.it'); // se non esiste sul server, WP userà fallback ma manteniamo brand
define('FP_MAIL_REPLYTO', 'findplayeritaly@gmail.com'); // <- risposte qui
define('FP_ADMIN_EMAIL', 'findplayeritaly@gmail.com');

define('FP_TOKEN_TTL_HOURS', 48);

register_activation_hook(__FILE__, function () {
    global $wpdb;

    $table = $wpdb->prefix . 'fp_player_votes';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        voter_id BIGINT UNSIGNED NOT NULL,
        target_id BIGINT UNSIGNED NOT NULL,
        sport VARCHAR(100) NOT NULL,
        voto TINYINT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY target_idx (target_id),
        KEY sport_idx (sport)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});

// ===============================
// MODULO SPORT (BASE UNICA)
// ===============================
require_once plugin_dir_path(__FILE__) . 'includes/sports/sports-cpt.php';
require_once plugin_dir_path(__FILE__) . 'includes/sports/sports-helpers.php';

require_once plugin_dir_path(__FILE__) . 'includes/metaboxes/fp-giocatore-sport-metabox.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions-eventi.php';


/* =========================================================
   INSTALL: tabella temp + cron cleanup
========================================================= */
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'fp_giocatori_temp';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        token VARCHAR(64) NOT NULL,
        data LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        confirmed TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY token_idx (token),
        KEY email_idx (email),
        KEY created_idx (created_at)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    if (!wp_next_scheduled('fp_cleanup_giocatori_temp')) {
        wp_schedule_event(time() + 3600, 'daily', 'fp_cleanup_giocatori_temp');
    }

    // CPT
    fp_register_cpt_giocatore();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('fp_cleanup_giocatori_temp');
    if ($timestamp) wp_unschedule_event($timestamp, 'fp_cleanup_giocatori_temp');
    flush_rewrite_rules();
});

add_action('fp_cleanup_giocatori_temp', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'fp_giocatori_temp';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table WHERE confirmed = 0 AND created_at < %s",
        gmdate('Y-m-d H:i:s', time() - (FP_TOKEN_TTL_HOURS * 3600))
    ));
});

/* =========================================================
   CPT: fp_giocatore (scheda in WP)
========================================================= */
add_action('init', 'fp_register_cpt_giocatore');
function fp_register_cpt_giocatore() {
    register_post_type('fp_giocatore', [
        'labels' => [
            'name' => 'Giocatori',
            'singular_name' => 'Giocatore',
            'add_new' => 'Aggiungi giocatore',
            'add_new_item' => 'Nuovo giocatore',
            'edit_item' => 'Modifica giocatore',
            'view_item' => 'Vedi giocatore',
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'supports' => ['title','editor','thumbnail'],
        'has_archive' => false,
        'rewrite' => ['slug' => 'giocatori', 'with_front' => false],
    ]);
}

/* =========================================================
   EMAIL HEADERS (From + Reply-To)
========================================================= */
add_filter('wp_mail_from', function($from){ return FP_MAIL_FROM_EMAIL; });
add_filter('wp_mail_from_name', function($name){ return FP_MAIL_FROM_NAME; });

function fp_mail_headers() {
    return [
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . FP_MAIL_FROM_NAME . ' <' . FP_MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . FP_MAIL_FROM_NAME . ' <' . FP_MAIL_REPLYTO . '>',
    ];
}

/* =========================================================
   CONFERMA TOKEN (link email)
   URL: https://tuosito.it/?fp_confirm_player_token=XXXX
========================================================= */
add_action('init', function() {
    if (!empty($_GET['fp_confirm_player_token'])) {
        fp_handle_player_confirmation(sanitize_text_field($_GET['fp_confirm_player_token']));
        exit;
    }
});

function fp_handle_player_confirmation($token) {
    global $wpdb;
    $table = $wpdb->prefix . 'fp_giocatori_temp';

    $token_hashed = hash('sha256', $token . NONCE_SALT);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token = %s", $token_hashed));
    if (!$row) {
        wp_die('Token non valido o già utilizzato.', 'Conferma iscrizione', ['response'=>400]);
    }

    $created_ts = strtotime($row->created_at . ' UTC');
    if ($created_ts < time() - (FP_TOKEN_TTL_HOURS * 3600)) {
        $wpdb->delete($table, ['id' => $row->id], ['%d']);
        wp_die('Link scaduto. Compila di nuovo il modulo.', 'Conferma iscrizione', ['response'=>410]);
    }

    if (intval($row->confirmed) === 1) {
        wp_die('Email già confermata.', 'Conferma iscrizione', ['response'=>200]);
    }

    $data = json_decode($row->data, true);
    if (!is_array($data)) {
        wp_die('Dati non disponibili. Ricompila il modulo.', 'Conferma iscrizione', ['response'=>500]);
    }

    // Crea scheda giocatore in WP come "pending" (attesa approvazione)
    $nickname = sanitize_text_field($data['nickname'] ?? '');
    if (!empty($nickname)) {
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $nickname,
        'nickname'     => $nickname,
        'first_name'   => sanitize_text_field($data['nome'] ?? ''),
        'last_name'    => sanitize_text_field($data['cognome'] ?? ''),
    ]);
}
    if (!$nickname) {
        wp_die('Nickname mancante.', 'Conferma iscrizione', ['response'=>422]);
    }

    // blocco duplicati base lato WP
    $existing = get_posts([
        'post_type' => 'fp_giocatore',
        'post_status' => ['publish','pending','draft','private'],
        'meta_key' => 'fp_email',
        'meta_value' => sanitize_email($data['email'] ?? ''),
        'fields' => 'ids',
        'numberposts' => 1
    ]);
    if (!empty($existing)) {
        wp_die('Esiste già una scheda con questa email. Contatta assistenza.', 'Conferma iscrizione', ['response'=>409]);
    }

$post_id = wp_insert_post([
    'post_type' => 'fp_giocatore',
    'post_title' => $nickname,
    'post_content' => sanitize_textarea_field($data['descrizione'] ?? ''),
    'post_status' => 'publish',
], true);

// =======================================
// CREA / COLLEGA UTENTE WP DA ISCRIZIONE
// =======================================

$email = sanitize_email($data['email'] ?? '');

if ($email && is_email($email)) {

    $user = get_user_by('email', $email);

    if ($user) {
        $user_id = $user->ID;
    } else {

        $username = sanitize_user(current(explode('@', $email)), true);
        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        $password = wp_generate_password(12, true);

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_pass'  => $password,
            'user_email' => $email,
            'role'       => 'subscriber',
        ]);

        if (is_wp_error($user_id)) {
            return;
        }
    }

    // 👉 QUI ESATTAMENTE (DOPO user_id SICURO)
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $nickname,
        'nickname'     => $nickname,
        'first_name'   => sanitize_text_field($data['nome'] ?? ''),
        'last_name'    => sanitize_text_field($data['cognome'] ?? ''),
    ]);

    update_post_meta($post_id, 'fp_wp_user_id', $user_id);

    if (function_exists('cf_chat_enable_user')) {
        cf_chat_enable_user($user_id, wp_generate_password(10, false));
    }
}
    if (is_wp_error($post_id)) {
        wp_die('Errore creazione scheda. Riprova.', 'Conferma iscrizione', ['response'=>500]);
    }

    // Salva metadati (questa è la "single source of truth" per l’admin)
    update_post_meta($post_id, 'fp_nickname', $nickname);
    update_post_meta($post_id, 'fp_nome', sanitize_text_field($data['nome'] ?? ''));
    update_post_meta($post_id, 'fp_cognome', sanitize_text_field($data['cognome'] ?? ''));
    update_post_meta($post_id, 'fp_data_nascita', sanitize_text_field($data['data_nascita'] ?? ''));
    update_post_meta($post_id, 'fp_luogo_nascita', sanitize_text_field($data['luogo_nascita'] ?? ''));
    update_post_meta($post_id, 'fp_luogo_residenza', sanitize_text_field($data['luogo_residenza'] ?? ''));
    update_post_meta($post_id, 'fp_sesso', sanitize_text_field($data['sesso'] ?? ''));
    update_post_meta($post_id, 'fp_email', sanitize_email($data['email'] ?? ''));
    update_post_meta($post_id, 'fp_telefono', sanitize_text_field($data['telefono'] ?? ''));
$mostra = ($data['mostra_dati_pubblici'] ?? '1') === '0' ? '0' : '1';
update_post_meta($post_id, 'fp_mostra_dati_pubblici', $mostra);

    update_post_meta($post_id, 'fp_instagram', esc_url_raw($data['instagram'] ?? ''));
    update_post_meta($post_id, 'fp_facebook', esc_url_raw($data['facebook'] ?? ''));
    update_post_meta($post_id, 'fp_tiktok', esc_url_raw($data['tiktok'] ?? ''));
    update_post_meta($post_id, 'fp_linkedin', esc_url_raw($data['linkedin'] ?? ''));

    // Foto (salviamo URL se presente; upload lo facciamo nella V1.1)
    update_post_meta($post_id, 'fp_foto_url', esc_url_raw($data['foto_url'] ?? ''));

    // Discipline (array)
    $discipline = $data['discipline'] ?? [];
    if (!is_array($discipline)) $discipline = [];
    update_post_meta($post_id, 'fp_discipline', $discipline);
    
    // ✅ Versione B: invio immediato a Supabase (ora che i meta sono già salvati)
$res = fp_push_player_to_supabase($post_id);

if (is_wp_error($res)) {
    // NON blocchiamo la pubblicazione, ma ci salviamo l'errore per debug
    update_post_meta($post_id, 'fp_supabase_error', $res->get_error_message());
}


    // Marca temp come confermato
    $wpdb->update($table, ['confirmed' => 1], ['id' => $row->id], ['%d'], ['%d']);

    // Notifica admin
    $msg_admin = "Nuova richiesta giocatore (IN ATTESA DI APPROVAZIONE)\n\n"
        . "Nickname: {$nickname}\n"
        . "Email: " . (sanitize_email($data['email'] ?? '')) . "\n"
        . "Città: " . (sanitize_text_field($data['luogo_residenza'] ?? '')) . "\n\n"
        . "Vai in WP → Giocatori → In attesa di revisione.\n";
    wp_mail(FP_ADMIN_EMAIL, 'Find Player — Nuovo giocatore da approvare', $msg_admin, fp_mail_headers());

    // Pagina di successo
    wp_die(
        "<h2>✅ Email confermata!</h2>
        <p>Perfetto. La tua scheda è ora <strong>in attesa di approvazione</strong>.</p>
        <p>Appena validata, verrà pubblicata e indicizzata in ricerca/mappa.</p>",
        'Conferma completata',
        ['response'=>200]
    );
}

function fp_push_player_to_supabase($post_id){
    $nickname = get_post_meta($post_id, 'fp_nickname', true);
    $email    = get_post_meta($post_id, 'fp_email', true);

    // 1) Inserisci giocatore
$payload_player = [
    'nickname' => get_post_meta($post_id, 'fp_nickname', true),
    'nome' => get_post_meta($post_id, 'fp_nome', true),
    'cognome' => get_post_meta($post_id, 'fp_cognome', true),
    'data_nascita' => get_post_meta($post_id, 'fp_data_nascita', true) ?: null,
    'luogo_nascita' => get_post_meta($post_id, 'fp_luogo_nascita', true),
    'luogo_residenza' => get_post_meta($post_id, 'fp_luogo_residenza', true),
    'sesso' => get_post_meta($post_id, 'fp_sesso', true),
    'email' => strtolower(get_post_meta($post_id, 'fp_email', true)),
    'telefono' => get_post_meta($post_id, 'fp_telefono', true),
    'descrizione' => wp_strip_all_tags(get_post_field('post_content', $post_id)),
    'foto_url' => get_post_meta($post_id, 'fp_foto_url', true),
    'instagram' => get_post_meta($post_id, 'fp_instagram', true),
    'facebook' => get_post_meta($post_id, 'fp_facebook', true),
    'tiktok' => get_post_meta($post_id, 'fp_tiktok', true),
    'linkedin' => get_post_meta($post_id, 'fp_linkedin', true),
'mostra_dati_pubblici' => get_post_meta($post_id, 'fp_mostra_dati_pubblici', true),

    // 🔥 QUESTO SERVE PER IL CHECK CONSTRAINT
    'status' => 'pending'
];


    $resp = wp_remote_post(FP_SUPABASE_URL . '/rest/v1/giocatori', [
        'headers' => [
            'apikey' => FP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ],
        'body' => wp_json_encode($payload_player),
        'timeout' => 20
    ]);

    if (is_wp_error($resp)) return $resp;
    $code = wp_remote_retrieve_response_code($resp);
    $body = wp_remote_retrieve_body($resp);

    if (!in_array($code, [200,201], true)) {
        return new WP_Error('supabase_player_error', "Errore Supabase giocatori (HTTP $code): $body");
    }

    $json = json_decode($body, true);
    $supabase_id = $json[0]['id'] ?? null;
    if (!$supabase_id) {
        return new WP_Error('supabase_player_noid', "Giocatore inserito ma id non restituito: $body");
    }

    update_post_meta($post_id, 'fp_supabase_id', intval($supabase_id));



    // 2) Inserisci discipline
    $discipline = get_post_meta($post_id, 'fp_discipline', true);
    if (!is_array($discipline)) $discipline = [];

    $rows = [];
    foreach ($discipline as $d){
        $sport = sanitize_text_field($d['sport'] ?? '');
        $liv   = intval($d['livello'] ?? 0);
        if (!$sport || $liv < 1 || $liv > 10) continue;
        $rows[] = [
            'giocatore_id' => intval($supabase_id),
            'sport' => $sport,
            'livello' => $liv,
        ];
    }

// ================================
// INSERIMENTO IN iscritti_findplayer
// ================================

// prendo la prima disciplina, se esiste
$discipline = get_post_meta($post_id, 'fp_discipline', true);
$disciplina = 'generico';

if (is_array($discipline) && !empty($discipline)) {
    $disciplina = sanitize_text_field($discipline[0]['sport'] ?? 'generico');
}

$payload_iscritto = [
    'allenamento_id' => 0, // placeholder tecnico
    'ruolo'          => 'partecipante',
    'disciplina'     => $disciplina,
    'citta'          => get_post_meta($post_id, 'fp_luogo_residenza', true),
    'nickname'       => get_post_meta($post_id, 'fp_nickname', true),
    'nome'           => trim(
        get_post_meta($post_id, 'fp_nome', true) . ' ' .
        get_post_meta($post_id, 'fp_cognome', true)
    ),
    'email'          => strtolower(get_post_meta($post_id, 'fp_email', true)),
    'telefono'       => get_post_meta($post_id, 'fp_telefono', true),
];

$resp_iscritto = wp_remote_post(
    FP_SUPABASE_URL . '/rest/v1/iscritti_findplayer',
    [
        'headers' => [
            'apikey'        => FP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=minimal',
        ],
        'body' => wp_json_encode($payload_iscritto),
        'timeout' => 20
    ]
);

if (is_wp_error($resp_iscritto)) {
    return new WP_Error(
        'supabase_iscritto_error',
        'Errore Supabase iscritti_findplayer: ' . $resp_iscritto->get_error_message()
    );
}

$code_i = wp_remote_retrieve_response_code($resp_iscritto);
if (!in_array($code_i, [200, 201, 204], true)) {
    $body_i = wp_remote_retrieve_body($resp_iscritto);
    return new WP_Error(
        'supabase_iscritto_http',
        "Errore Supabase iscritti_findplayer (HTTP $code_i): $body_i"
    );
}


    if (!empty($rows)) {
        $resp2 = wp_remote_post(FP_SUPABASE_URL . '/rest/v1/giocatori_discipline', [
            'headers' => [
                'apikey' => FP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=minimal',
            ],
            'body' => wp_json_encode($rows),
            'timeout' => 20
        ]);

        if (is_wp_error($resp2)) return $resp2;
        $code2 = wp_remote_retrieve_response_code($resp2);
        $body2 = wp_remote_retrieve_body($resp2);

        if (!in_array($code2, [200,201,204], true)) {
            return new WP_Error('supabase_disc_error', "Errore Supabase discipline (HTTP $code2): $body2");
        }
    }

    // Email admin “approvato”
    wp_mail(FP_ADMIN_EMAIL, 'Find Player — Giocatore approvato e inviato a Supabase', "OK: $nickname ($email)", fp_mail_headers());

    return true;
}

/*delete giocatore da supabase se cancellato da wp*/
function fp_delete_player_from_supabase($post_id) {

    // Prendiamo ID e/o email come chiavi di business
    $supabase_id = get_post_meta($post_id, 'fp_supabase_id', true);
    $email = strtolower(trim((string) get_post_meta($post_id, 'fp_email', true)));

    // Se non ho né id né email, non posso fare nulla
    if (!$supabase_id && !$email) {
        error_log("[FP] DELETE: stop, no supabase_id and no email for post_id={$post_id}");
        return;
    }

    // Base headers
    $headers = [
        'apikey'        => FP_SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
        'Accept'        => 'application/json',
    ];

    // Helper per chiamate e log
    $call = function($url) use ($headers) {
        $resp = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => $headers,
            'timeout' => 20
        ]);

        if (is_wp_error($resp)) {
            error_log("[FP] DELETE ERROR: " . $resp->get_error_message() . " URL={$url}");
            return $resp;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        error_log("[FP] DELETE HTTP {$code} URL={$url} BODY=" . substr($body, 0, 300));

        // 200/204 ok. 404 ok-ish (già cancellato). Il resto è problema.
        if (!in_array($code, [200, 204, 404], true)) {
            return new WP_Error('supabase_delete_failed', "HTTP {$code}: {$body}");
        }

        return true;
    };

    // 1) Cancella discipline
    if ($supabase_id) {
        $r1 = $call(FP_SUPABASE_URL . '/rest/v1/giocatori_discipline?giocatore_id=eq.' . intval($supabase_id));
        if (is_wp_error($r1)) {
            update_post_meta($post_id, 'fp_supabase_delete_error', $r1->get_error_message());
            // continuiamo comunque col giocatore
        }
    } else {
        // se non ho id, non posso fare delete discipline per FK -> la gestiamo dopo con cascade o query alternative
        error_log("[FP] DELETE: no supabase_id, skipping discipline delete (post_id={$post_id})");
    }

    // 2) Cancella giocatore (priorità: id, fallback: email)
    if ($supabase_id) {
        $r2 = $call(FP_SUPABASE_URL . '/rest/v1/giocatori?id=eq.' . intval($supabase_id));
        if (is_wp_error($r2)) {
            update_post_meta($post_id, 'fp_supabase_delete_error', $r2->get_error_message());
            return;
        }
        // se ok, pulizia meta
        delete_post_meta($post_id, 'fp_supabase_delete_error');
        return;
    }

    // Fallback per email (funziona anche se fp_supabase_id non era mai stato salvato)
    if ($email) {
        // prima recupero id da supabase
        $lookup = wp_remote_get(
            FP_SUPABASE_URL . '/rest/v1/giocatori?select=id&email=eq.' . rawurlencode($email),
            ['headers' => $headers, 'timeout' => 20]
        );

        if (is_wp_error($lookup)) {
            update_post_meta($post_id, 'fp_supabase_delete_error', $lookup->get_error_message());
            error_log("[FP] LOOKUP ERROR: " . $lookup->get_error_message());
            return;
        }

        $codeL = wp_remote_retrieve_response_code($lookup);
        $bodyL = wp_remote_retrieve_body($lookup);
        error_log("[FP] LOOKUP HTTP {$codeL} BODY=" . substr($bodyL, 0, 300));

        $json = json_decode($bodyL, true);
        $found_id = $json[0]['id'] ?? null;

        if (!$found_id) {
            error_log("[FP] DELETE: no record in Supabase for email={$email} post_id={$post_id}");
            return;
        }

        // ora cancello discipline e giocatore
        $call(FP_SUPABASE_URL . '/rest/v1/giocatori_discipline?giocatore_id=eq.' . intval($found_id));
        $r3 = $call(FP_SUPABASE_URL . '/rest/v1/giocatori?id=eq.' . intval($found_id));

        if (is_wp_error($r3)) {
            update_post_meta($post_id, 'fp_supabase_delete_error', $r3->get_error_message());
            return;
        }

        delete_post_meta($post_id, 'fp_supabase_delete_error');
    }
}
add_action('wp_trash_post', function ($post_id) {
    if (get_post_type($post_id) !== 'fp_giocatore') return;
    if (wp_is_post_revision($post_id)) return;

    fp_delete_player_from_supabase($post_id);
});

/*fine funzione*/

add_action('wp_delete_post', function ($post_id) {

    if (get_post_type($post_id) !== 'fp_giocatore') return;
    if (wp_is_post_revision($post_id)) return;

    fp_delete_player_from_supabase($post_id);

});

/* =========================================================
   SHORTCODE: [fp_iscrizione_giocatore]
========================================================= */
add_shortcode('fp_iscrizione_giocatore', function(){
    ob_start();

    // --- submit form ---
    if (!empty($_POST['fp_player_submit'])) {

        // nonce
        if (empty($_POST['fp_player_nonce']) || !wp_verify_nonce($_POST['fp_player_nonce'], 'fp_player_submit')) {
            echo '<div style="color:#b00;">⚠️ Sessione non valida. Ricarica e riprova.</div>';
            return ob_get_clean();
        }

        // antispam
        $risposta_utente   = strtolower(trim($_POST['fp_antispam'] ?? ''));
        $risposta_corretta = strtolower(trim($_POST['fp_risp'] ?? ''));
        if ($risposta_utente !== $risposta_corretta) {
            echo '<div style="color:#b00;">⚠️ Risposta di verifica errata.</div>';
            return ob_get_clean();
        }

// privacy
if (empty($_POST['fp_privacy'])) {
    echo '<div style="color:#b00;">⚠️ Devi accettare la privacy per proseguire.</div>';
    return ob_get_clean();
}

// dati base (DEVONO STARE QUI)
$nickname = sanitize_text_field($_POST['nickname'] ?? '');
$email = strtolower(sanitize_email($_POST['email'] ?? ''));
$telefono = sanitize_text_field($_POST['telefono'] ?? '');

if (!$nickname || !$email) {
    echo '<div style="color:#b00;">⚠️ Nickname ed Email sono obbligatori.</div>';
    return ob_get_clean();
}

// sicurezza duplicati (ultima difesa)
$check_url = FP_SUPABASE_URL . '/rest/v1/giocatori?or=' .
    '(nickname.eq.' . urlencode($nickname) .
    ',email.eq.' . urlencode($email) .
    ',telefono.eq.' . urlencode($telefono) . ')' .
    '&select=id';

$check = wp_remote_get($check_url, [
    'headers' => [
        'apikey'        => FP_SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY
    ]
]);

$exists = json_decode(wp_remote_retrieve_body($check), true);
if (!empty($exists)) {
    echo '<div style="color:#b00;">⚠️ Sei già registrato con questi dati.</div>';
    return ob_get_clean();
}


        // dati base
        $nickname = sanitize_text_field($_POST['nickname'] ?? '');
        $email    = sanitize_email($_POST['email'] ?? '');

        if (!$nickname || !$email) {
            echo '<div style="color:#b00;">⚠️ Nickname ed Email sono obbligatori.</div>';
            return ob_get_clean();
        }

        // discipline array
        $disc = [];
        if (!empty($_POST['sport']) && is_array($_POST['sport'])) {
            foreach ($_POST['sport'] as $i => $sport) {
                $sport = sanitize_text_field($sport);
                $liv = intval($_POST['livello'][$i] ?? 0);
                if ($sport && $liv >= 1 && $liv <= 10) {
                    $disc[] = ['sport'=>$sport, 'livello'=>$liv];
                }
            }
        }

        // payload temp
        $data = [
            'mostra_dati_pubblici' => isset($_POST['fp_mostra_dati_pubblici']) && $_POST['fp_mostra_dati_pubblici'] === '0'
    ? '0'
    : '1',

            'nickname' => $nickname,
            'nome' => sanitize_text_field($_POST['nome'] ?? ''),
            'cognome' => sanitize_text_field($_POST['cognome'] ?? ''),
            'data_nascita' => sanitize_text_field($_POST['data_nascita'] ?? ''),
            'luogo_nascita' => sanitize_text_field($_POST['luogo_nascita'] ?? ''),
            'luogo_residenza' => sanitize_text_field($_POST['luogo_residenza'] ?? ''),
            'sesso' => sanitize_text_field($_POST['sesso'] ?? ''),
            'email' => $email,
            'telefono' => sanitize_text_field($_POST['telefono'] ?? ''),
            'descrizione' => sanitize_textarea_field($_POST['descrizione'] ?? ''),
            'instagram' => esc_url_raw($_POST['instagram'] ?? ''),
            'facebook' => esc_url_raw($_POST['facebook'] ?? ''),
            'tiktok' => esc_url_raw($_POST['tiktok'] ?? ''),
            'linkedin' => esc_url_raw($_POST['linkedin'] ?? ''),
            'foto_url' => esc_url_raw($_POST['foto_url'] ?? ''),
            'discipline' => $disc,
            'created_at' => date('c'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        // salva temp + invia email token
        global $wpdb;
        $table = $wpdb->prefix . 'fp_giocatori_temp';

        $token = wp_generate_password(40, false, false);

        $wpdb->insert($table, [
            'email' => $email,
            'token' => hash('sha256', $token . NONCE_SALT),
            'data' => wp_json_encode($data),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'confirmed' => 0
        ], ['%s','%s','%s','%s','%d']);

        $confirm_url = add_query_arg(['fp_confirm_player_token' => $token], home_url('/'));

        $msg = "Ciao $nickname,\n\n"
             . "per completare l’iscrizione a Find Player conferma la tua email cliccando qui:\n\n"
             . $confirm_url . "\n\n"
             . "Il link scade tra " . FP_TOKEN_TTL_HOURS . " ore.\n\n"
             . "Se non hai richiesto tu l’iscrizione, ignora questa email.\n";

        wp_mail($email, 'Find Player — Conferma la tua email', $msg, fp_mail_headers());

        echo '<div style="padding:12px;border:1px solid #ddd;border-radius:10px;background:#f7fff7;">
                ✅ Ti abbiamo inviato una mail con il link di conferma. Controlla anche SPAM.
              </div>';

        return ob_get_clean();
    }

    // domanda antispam
    $domande = [
        ['q'=>'Quanto fa 8 + 80?', 'a'=>'88'],
        ['q'=>'Quanto fa 10 + 90?', 'a'=>'100'],
        ['q'=>'Quanto fa 50 diviso 10?', 'a'=>'5'],
        ['q'=>'Qual è il risultato di 40 + 4?', 'a'=>'44'],
        ['q'=>'Scrivi il numero 55 al contrario', 'a'=>'55'],
    ];
    $scelta = $domande[array_rand($domande)];
    $q = $scelta['q'];
    $a = strtolower(trim($scelta['a']));

    ?>
<style>
.fp-box{max-width:980px;margin:0 auto}
.fp-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.fp-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.fp-field label{display:block;font-weight:700;margin:0 0 6px}
.fp-field input,.fp-field select,.fp-field textarea{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
.fp-card{background:#fff;padding:16px;border-radius:12px;box-shadow:0 0 10px rgba(0,0,0,0.06);margin:14px 0}
.fp-btn{padding:12px 18px;border:0;border-radius:10px;cursor:pointer;background:#0078ff;color:#fff;font-weight:800}
.fp-btn2{padding:10px 14px;border:1px solid #ccc;border-radius:10px;cursor:pointer;background:#fff}

.fp-add-sport,
.fp-remove-sport {
  color: #1f7a4d;
  font-weight: 600;
  cursor: pointer;
}
.fp-add-sport:hover,
.fp-remove-sport:hover {
  color: #3cb371;
  text-decoration: underline;
}

.fp-check {
  position:absolute;
  right:10px;
  top:36px;
  font-size:18px;
  font-weight:800;
}
.fp-check.ok{color:#2ecc71}
.fp-check.ko{color:#e74c3c}

.fp-disc{display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-top:10px}
@media(max-width:820px){.fp-grid,.fp-grid-2,.fp-disc{grid-template-columns:1fr}}
</style>


    <div class="fp-box">
      <div class="fp-card">
        <h2 style="margin:0 0 10px">Iscrizione giocatore Find Player</h2>
<form id="fp-player-form" method="post" enctype="multipart/form-data">
          <?php wp_nonce_field('fp_player_submit', 'fp_player_nonce'); ?>

          <div class="fp-grid">
<div class="fp-field">
  <label>*Nickname</label>
  <div style="position:relative">
    <input name="nickname" id="fp-nickname" required>
    <span class="fp-check" id="check-nickname"></span>
  </div>
</div>

<div class="fp-field">
  <label>*Email</label>
  <div style="position:relative">
    <input type="email" name="email" id="fp-email" required>
    <span class="fp-check" id="check-email"></span>
  </div>
</div>

<div class="fp-field">
  <label>Telefono</label>
  <div style="position:relative">
    <input name="telefono" id="fp-telefono">
    <span class="fp-check" id="check-telefono"></span>
  </div>
</div>

          </div>

          <div class="fp-grid">
            <div class="fp-field"><label>Nome</label><input name="nome"></div>
            <div class="fp-field"><label>Cognome</label><input name="cognome"></div>
            <div class="fp-field">
              <label>Sesso</label>
              <select name="sesso">
                <option value="">Seleziona...</option>
                <option value="Maschio">Maschio</option>
                <option value="Femmina">Femmina</option>
                <option value="Altro">Altro</option>
              </select>
            </div>
          </div>

          <div class="fp-grid">
            <div class="fp-field"><label>Data di nascita</label><input type="date" name="data_nascita"></div>
            <div class="fp-field"><label>Luogo di nascita</label><input name="luogo_nascita"></div>
            <div class="fp-field"><label>*Città (residenza)</label><input name="luogo_residenza" required></div>
          </div>

          <div class="fp-field" style="margin-top:12px">
            <label>Descrizione</label>
            <textarea name="descrizione" rows="4"></textarea>
          </div>

          <div class="fp-card" style="padding:14px;margin-top:14px">
            <h3 style="margin:0 0 8px">Sport e livello</h3>

            <div id="fp-disc-wrapper"></div>

            <div style="margin-top:10px" class="fp-row">
<button type="button" class="fp-btn2 fp-add-sport" id="fp-add-disc">
  + Aggiungi sport
</button>
              <span style="color:#666;font-size:13px">Livello da 1 a 10</span>
            </div>
          </div>

          <div class="fp-grid-2">
            <div class="fp-field"><label>Instagram (link)</label><input name="instagram"></div>
            <div class="fp-field"><label>Facebook (link)</label><input name="facebook"></div>
          </div>
          <div class="fp-grid-2">
            <div class="fp-field"><label>TikTok (link)</label><input name="tiktok"></div>
            <div class="fp-field"><label>LinkedIn (link)</label><input name="linkedin"></div>
          </div>
          
          <div class="fp-card" style="padding:14px">
            <div class="fp-field">
              <label><?php echo esc_html($q); ?> (verifica)</label>
              <input name="fp_antispam" required>
              <input type="hidden" name="fp_risp" value="<?php echo esc_attr($a); ?>">
            </div>

<div class="fp-card" style="padding:14px;margin-top:14px">
  <h3 style="margin:0 0 8px">Privacy profilo pubblico</h3>

  <label style="display:block;margin-bottom:6px">
    <input type="radio" name="fp_mostra_dati_pubblici" value="1" checked>
    Mostra i miei contatti (email, telefono, social)
  </label>

  <label style="display:block">
    <input type="radio" name="fp_mostra_dati_pubblici" value="0">
    Mostra solo nickname, sport e descrizione
  </label>

  <p style="font-size:12px;color:#666;margin-top:6px">
    Potrai modificare questa scelta in qualsiasi momento dalla tua scheda.
  </p>
</div>

            <label style="display:flex;gap:10px;align-items:flex-start;margin-top:10px">
              <input type="checkbox" name="fp_privacy" value="1" required style="margin-top:3px">
              <span>Accetto l’informativa privacy e il trattamento dei dati per le finalità del servizio Find Player.</span>
            </label>
          </div>

          <button class="fp-btn" type="submit" name="fp_player_submit" value="1">Invia richiesta</button>
        </form>
      </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const wrap = document.getElementById('fp-disc-wrapper');
  const btn  = document.getElementById('fp-add-disc');

  if (!wrap || !btn) return;

  function row(){
    const div = document.createElement('div');
    div.className = 'fp-disc';
    div.innerHTML = `
      <div class="fp-field">
        <label>Sport</label>
        <select name="sport[]" required>
          <option value="">Seleziona sport</option>
          <?php
            $sports = fp_get_all_sports();
            usort($sports, function($a, $b){
              return strcasecmp($a->post_title, $b->post_title);
            });
            foreach ($sports as $sport):
          ?>
            <option value="<?php echo esc_attr($sport->post_title); ?>">
              <?php echo esc_html($sport->post_title); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fp-field">
        <label>Livello</label>
        <select name="livello[]" required>
          ${Array.from({length:10},(_,i)=>`<option value="${i+1}">${i+1}</option>`).join('')}
        </select>
      </div>

      <div class="fp-field">
        <label>&nbsp;</label>
        <button type="button" class="fp-btn2 fp-remove-sport">Rimuovi</button>
      </div>
    `;

    div.querySelector('.fp-remove-sport')
       .addEventListener('click', () => div.remove());

    return div;
  }

  // 👉 bottone aggiungi
  btn.addEventListener('click', () => {
    wrap.appendChild(row());
  });

  // 👉 PRIMA RIGA AUTOMATICA (QUESTA MANCAVA)
  wrap.appendChild(row());

});
</script>

    <script>
    window.fpErrori = { nickname:false, email:false, telefono:false };
</script>

<script>
function aggiornaSubmit() {
  const btn = document.querySelector('#fp-player-form button[name="fp_player_submit"]');
  if (!btn) return;

  const hasError = Object.values(window.fpErrori).includes(true);
  btn.disabled = hasError;
  btn.style.opacity = hasError ? '0.5' : '1';
  btn.style.cursor  = hasError ? 'not-allowed' : 'pointer';
}

function checkCampo(campo, valore, el) {
  if (!el) return;

  if (!valore) {
    el.textContent = '';
    el.classList.remove('ok','ko');
    window.fpErrori[campo] = false;
    aggiornaSubmit();
    return;
  }

  fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      action: "fp_check_giocatore",
      campo,
      valore
    })
  })
  .then(r => r.json())
  .then(r => {
    el.classList.remove('ok','ko');

    if (r.success) {
      el.textContent = '✔';
      el.classList.add('ok');
      window.fpErrori[campo] = false;
    } else {
      el.textContent = '✖';
      el.classList.add('ko');
      window.fpErrori[campo] = true;
    }

    aggiornaSubmit();
  });
}

document.getElementById('fp-nickname')?.addEventListener('blur', e =>
  checkCampo('nickname', e.target.value, document.getElementById('check-nickname'))
);

document.getElementById('fp-email')?.addEventListener('blur', e => {
  e.target.value = e.target.value.trim().toLowerCase();
  checkCampo('email', e.target.value, document.getElementById('check-email'));
});

document.getElementById('fp-telefono')?.addEventListener('blur', e =>
  checkCampo('telefono', e.target.value, document.getElementById('check-telefono'))
);
</script>
<script>
document.getElementById('fp-player-form')?.addEventListener('submit', function(e){

  if (Object.values(window.fpErrori).includes(true)) {
    e.preventDefault();

    let box = document.getElementById('fp-form-error');
    if (!box) {
      box = document.createElement('div');
      box.id = 'fp-form-error';
      box.style.color = '#b00';
      box.style.marginBottom = '10px';
      box.textContent = '⚠️ Correggi i campi evidenziati prima di proseguire.';
      this.prepend(box);
    }

    return false;
  }
});
</script>

    <?php

    return ob_get_clean();
});

function fp_render_risultati_giocatori() {

  $sport   = sanitize_text_field($_GET['sport'] ?? '');
  $livello = intval($_GET['livello'] ?? 0);

  $args = [
    'post_type'      => 'fp_giocatore',
    'post_status'    => 'publish',
    'posts_per_page' => 50,
    'meta_query'     => []
  ];

  // filtro città (DB)
  if (!empty($_GET['citta'])) {
    $args['meta_query'][] = [
      'key'     => 'fp_luogo_residenza',
      'value'   => sanitize_text_field($_GET['citta']),
      'compare' => 'LIKE'
    ];
  }

  $q = new WP_Query($args);

  if (!$q->have_posts()) {
    echo '<p>Nessun giocatore trovato.</p>';
    return;
  }

  echo '<div class="fp-results">';

  while ($q->have_posts()) {
    $q->the_post();

    $disc = get_post_meta(get_the_ID(), 'fp_discipline', true);
    if (!is_array($disc)) continue;

    $match = false;

    foreach ($disc as $d) {
      $s = strtolower($d['sport'] ?? '');
      $l = intval($d['livello_real'] ?? $d['livello'] ?? $d['livello_user'] ?? 0);

      if (
        (!$sport   || str_contains($s, strtolower($sport))) &&
        (!$livello || $l >= $livello)
      ) {
        $match = true;
        break;
      }
    }

    if ($match || (!$sport && !$livello)) {
      fp_render_player_card(get_the_ID());
    }
  }

  echo '</div>';
  wp_reset_postdata();
}


function fp_render_player_card($post_id) {

  $nickname = get_post_meta($post_id,'fp_nickname',true);
  $citta    = get_post_meta($post_id,'fp_luogo_residenza',true);
  $disc     = get_post_meta($post_id,'fp_discipline',true);

  ?>
  <div class="fp-player-card">
    <strong><?= esc_html($nickname) ?></strong><br>
    <small><?= esc_html($citta) ?></small>

    <?php if (is_array($disc)): ?>
      <div class="fp-card-sport">
        <?php foreach ($disc as $d): ?>
<?php
$liv = intval($d['livello_real'] ?? $d['livello'] ?? $d['livello_user'] ?? 0);
?>
<span>
  <?= esc_html($d['sport']) ?>
  (<?= $liv > 0 ? $liv : '—' ?>)
</span>

        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <a href="<?= esc_url(get_permalink($post_id)) ?>">Vedi scheda</a>
  </div>
  <?php
}

/* =========================================================
   ADMIN: colonna “Supabase” e info errore
========================================================= */
add_filter('manage_fp_giocatore_posts_columns', function($cols){
    $cols['fp_supabase'] = 'Supabase';
    return $cols;
});
add_action('manage_fp_giocatore_posts_custom_column', function($col, $post_id){
    if ($col !== 'fp_supabase') return;
    $sid = get_post_meta($post_id, 'fp_supabase_id', true);
    $err = get_post_meta($post_id, 'fp_supabase_error', true);
    if ($sid) echo '✅ ID: ' . intval($sid);
    elseif ($err) echo '❌ ' . esc_html($err);
    else echo '—';
}, 10, 2);

add_shortcode('fp_cerca_giocatori', function () {
  ob_start(); ?>
<style>
.fp-search-grid { display:grid; grid-template-columns: repeat(4,1fr); gap:10px; }
.fp-results { margin-top:20px; }
.fp-player-card { padding:14px; border-radius:12px; background:#fff; margin-bottom:12px; box-shadow:0 4px 10px rgba(0,0,0,.08); }
@media (max-width: 900px){ .fp-search-grid{ grid-template-columns:1fr; } }
</style>

<form class="fp-search-box" method="get">
  <div class="fp-search-grid">

    <input type="text" name="citta" placeholder="Città">

    <input type="text" name="sport" placeholder="Sport (es. Padel)">

    <select name="livello">
      <option value="">Livello minimo</option>
      <?php for ($i=1;$i<=10;$i++): ?>
        <option value="<?= $i ?>"><?= $i ?></option>
      <?php endfor; ?>
    </select>

    <button type="submit">Cerca giocatori</button>

  </div>
</form>
<?php
  fp_render_risultati_giocatori();
  return ob_get_clean();
});

add_shortcode('fp_completa_profilo', function () {

  if (!is_user_logged_in()) {
    return '<p>Devi essere autenticato per completare il profilo.</p>';
  }

  $player_id = intval($_GET['player'] ?? 0);
  if (!$player_id || get_post_type($player_id) !== 'fp_giocatore') {
    return '<p>Profilo non valido.</p>';
  }

  // SUBMIT
  if (!empty($_POST['fp_gallery_submit'])) {

    if (!wp_verify_nonce($_POST['fp_gallery_nonce'], 'fp_gallery_upload')) {
      return '<p>Sessione non valida.</p>';
    }

    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/media.php';
    require_once ABSPATH.'wp-admin/includes/image.php';
require_once __DIR__ . '/includes/metaboxes/fp-evento-chiusura-metabox.php';
require_once __DIR__ . '/includes/cron-eventi.php';

    $gallery_ids = [];

    if (!empty($_FILES['fp_gallery']['name'][0])) {
      foreach ($_FILES['fp_gallery']['name'] as $i => $name) {

        if ($_FILES['fp_gallery']['error'][$i] !== UPLOAD_ERR_OK) continue;

        $_FILES['fp_single'] = [
          'name'     => $_FILES['fp_gallery']['name'][$i],
          'type'     => $_FILES['fp_gallery']['type'][$i],
          'tmp_name' => $_FILES['fp_gallery']['tmp_name'][$i],
          'error'    => $_FILES['fp_gallery']['error'][$i],
          'size'     => $_FILES['fp_gallery']['size'][$i],
        ];

        $attach_id = media_handle_upload('fp_single', $player_id);
        if (!is_wp_error($attach_id)) {
          $gallery_ids[] = $attach_id;
        }
      }
    }

    if ($gallery_ids) {
      update_post_meta($player_id, 'fp_gallery_ids', $gallery_ids);
      set_post_thumbnail($player_id, $gallery_ids[0]);
    }

    return '<p>✅ Profilo completato correttamente.</p>';
  }

  // FORM
  ob_start(); ?>
  <form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('fp_gallery_upload','fp_gallery_nonce'); ?>

    <h3>Completa il tuo profilo</h3>

    <input type="file" name="fp_gallery[]" multiple accept="image/*">

    <p style="font-size:13px;color:#666">
      La prima immagine diventerà la foto principale.
    </p>

    <button type="submit" name="fp_gallery_submit">
      Salva immagini
    </button>
  </form>
  <?php
  return ob_get_clean();
});

/* =========================================================
   METABOX GALLERIA GIOCATORE (BACKEND)
========================================================= */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'fp_gallery_metabox',
        'Galleria giocatore',
        'fp_render_gallery_metabox',
        'fp_giocatore',
        'normal',
        'high'
    );
});

function fp_render_gallery_metabox($post) {

    wp_nonce_field('fp_gallery_metabox_save', 'fp_gallery_metabox_nonce');

    $gallery_ids = get_post_meta($post->ID, 'fp_gallery_ids', true);
    if (!is_array($gallery_ids)) $gallery_ids = [];

    ?>
    <div id="fp-gallery-wrapper" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px">
        <?php foreach ($gallery_ids as $img_id): 
            $thumb = wp_get_attachment_image_url($img_id, 'thumbnail');
            if (!$thumb) continue;
        ?>
            <div class="fp-gallery-item" data-id="<?php echo esc_attr($img_id); ?>">
                <img src="<?php echo esc_url($thumb); ?>" style="width:80px;height:auto;border-radius:6px">
            </div>
        <?php endforeach; ?>
    </div>

    <input type="hidden" id="fp_gallery_ids" name="fp_gallery_ids"
           value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>">

    <button type="button" class="button button-primary" id="fp-gallery-add">
        Aggiungi / Modifica immagini
    </button>

    <p style="margin-top:8px;color:#666;font-size:13px">
        La prima immagine verrà usata come immagine principale.
    </p>

    <?php
}

add_action('save_post_fp_giocatore', function ($post_id) {
add_action('save_post_fp_giocatore', 'fp_auto_link_user_on_approve', 20, 3);
function fp_auto_link_user_on_approve($post_id, $post, $update) {

    // Solo backend
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if ($post->post_status !== 'publish') return;

    // Evita doppio collegamento
    if (get_post_meta($post_id, 'fp_wp_user_id', true)) {
        return;
    }

    // Recupero email giocatore
    $email = get_post_meta($post_id, 'fp_email', true);
    if (!$email || !is_email($email)) return;

    // Controlla se esiste già un utente WP
    $user = get_user_by('email', $email);

    if ($user) {
        $user_id = $user->ID;
    } else {

        // Crea nuovo utente WP
        $username = sanitize_user(current(explode('@', $email)), true);

        // Evita collisioni username
        if (username_exists($username)) {
            $username .= '_' . wp_generate_password(4, false);
        }

        $password = wp_generate_password(12, true);

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return;
        }

        // Ruolo minimo
        wp_update_user([
            'ID' => $user_id,
            'role' => 'subscriber'
        ]);
    }

    // 🔗 COLLEGAMENTO DEFINITIVO
    update_post_meta($post_id, 'fp_wp_user_id', $user_id);

    // ✅ Abilita Chat Facile automaticamente
    if (function_exists('cf_chat_enable_user')) {
        cf_chat_enable_user($user_id, wp_generate_password(10, false));
    }
}

    if (!isset($_POST['fp_gallery_metabox_nonce'])) return;
    if (!wp_verify_nonce($_POST['fp_gallery_metabox_nonce'], 'fp_gallery_metabox_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['fp_gallery_ids'])) {

        $ids = array_filter(array_map('intval', explode(',', $_POST['fp_gallery_ids'])));
        update_post_meta($post_id, 'fp_gallery_ids', $ids);

        if (!empty($ids)) {
            set_post_thumbnail($post_id, $ids[0]);
        }
    }
});

add_shortcode('fp_vota_giocatore', function () {

    if (!is_user_logged_in()) {
        return '<p>Devi essere autenticato per votare.</p>';
    }

    $target_id = intval($_GET['player'] ?? 0);
    if (!$target_id || get_post_type($target_id) !== 'fp_giocatore') {
        return '<p>Giocatore non valido.</p>';
    }

    $current_user = get_current_user_id();
    $voter_player = get_user_meta($current_user, 'fp_player_id', true);

    if (!$voter_player || $voter_player == $target_id) {
        return '<p>Non puoi votare questo profilo.</p>';
    }

    $discipline = get_post_meta($target_id, 'fp_discipline', true);
    if (!is_array($discipline) || empty($discipline)) {
        return '<p>Nessuno sport da votare.</p>';
    }

    ob_start(); ?>

    <form method="post">
        <h3>Valuta il giocatore</h3>

        <select name="sport" required>
            <option value="">Seleziona sport</option>
            <?php foreach ($discipline as $d): ?>
                <option value="<?php echo esc_attr($d['sport']); ?>">
                    <?php echo esc_html($d['sport']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="voto" required>
            <?php for ($i=1;$i<=10;$i++): ?>
                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit" name="fp_submit_vote">Invia voto</button>
    </form>

    <?php
    return ob_get_clean();
});

add_action('init', function () {

    if (empty($_POST['fp_submit_vote'])) return;

    global $wpdb;
    $table = $wpdb->prefix . 'fp_player_votes';

    $sport = sanitize_text_field($_POST['sport']);
    $voto  = intval($_POST['voto']);
    $target_id = intval($_GET['player']);

    if ($voto < 1 || $voto > 10) return;

    $voter_player = get_user_meta(get_current_user_id(), 'fp_player_id', true);
    if (!$voter_player || $voter_player == $target_id) return;

    // salva voto
    $wpdb->insert($table, [
        'voter_id' => $voter_player,
        'target_id' => $target_id,
        'sport' => $sport,
        'voto' => $voto,
        'created_at' => current_time('mysql')
    ]);

    // ricalcola media
    $avg = $wpdb->get_var($wpdb->prepare(
        "SELECT AVG(voto) FROM $table WHERE target_id = %d AND sport = %s",
        $target_id, $sport
    ));

    // aggiorna livello_real
    $disc = get_post_meta($target_id, 'fp_discipline', true);
    foreach ($disc as &$d) {
        if ($d['sport'] === $sport) {
            $d['livello_real'] = round($avg, 1);
        }
    }

    update_post_meta($target_id, 'fp_discipline', $disc);
});