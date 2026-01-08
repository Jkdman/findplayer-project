<?php
/**
 * Plugin Name: Find Player - Calendario Allenamenti
 * Description: Creazione attività, con elenco, creaz. pagine dedicate, prenotazioni e salvataggio su Supabase.
 * 2.4.0 mail gest cancella, accettaz utenti, tooltip i, calendario mensile, rifiuta già prenotati 
 * Author: Facile pmi
 */

if (!defined('ABSPATH')) exit;
// ==================================================
// CONFERMA EVENTO VIA TOKEN (GUEST)
// ==================================================
add_action('init', function () {

    if (empty($_GET['fp_confirm_event']) || empty($_GET['event_id'])) {
        return;
    }

    $token    = sanitize_text_field($_GET['fp_confirm_event']);
    $post_id  = intval($_GET['event_id']);

    if (!$post_id || !$token) {
        wp_die('Link di conferma non valido.');
    }

    // 🔎 Verifica token salvato su WP
    $saved_token = get_post_meta($post_id, 'fp_event_token', true);

    if (!$saved_token || !hash_equals($saved_token, $token)) {
        wp_die('Token non valido o già utilizzato.');
    }

    // 🔁 PUBBLICA EVENTO SU WORDPRESS
    wp_update_post([
        'ID'          => $post_id,
        'post_status' => 'publish',
    ]);

    // 🔁 RECUPERA ID EVENTO SUPABASE
    $allenamento_id = get_post_meta($post_id, '_findplayer_allenamento_id', true);

    if ($allenamento_id) {

        // 🔁 CONFERMA EVENTO SU SUPABASE
        wp_remote_request(
            FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . intval($allenamento_id),
            [
                'method'  => 'PATCH',
                'headers' => [
                    'apikey'        => FPFP_SUPABASE_API_KEY,
                    'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'evento_confermato' => true,
                ]),
                'timeout' => 15,
            ]
        );
    }

    // 🧹 RIMUOVE TOKEN (USO SINGOLO)
    delete_post_meta($post_id, 'fp_event_token');

    // ✅ REDIRECT FINALE
    wp_die(
        '<h2>✅ Evento confermato con successo</h2>
         <p>L’evento è ora pubblico e visibile nell’elenco.</p>
         <p><a href="' . esc_url(get_permalink($post_id)) . '">Vai all’evento</a></p>',
        'Evento confermato',
        ['response' => 200]
    );

});

// =====================================================
// INCLUDE FUNZIONI GLOBALI
// =====================================================
require_once plugin_dir_path(__FILE__) . 'includes/functions-nickname.php';
require_once plugin_dir_path(__FILE__) . 'includes/functions-player.php'; // 👈 lo creiamo ora


/* -------------------------------------------------------------------------- */
/* ENQUEUE LEAFLET (MAPPA FIND PLAYER)                                        */
/* -------------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    );
    wp_enqueue_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        null,
        true
    );
});

// =========================================================
// SUPABASE COMPATIBILITY LAYER
// =========================================================

// Se il plugin preiscrizione definisce FP_SUPABASE_* senza controllo,
// noi ci agganciamo e basta, senza ridefinire nulla.

if (!defined('FP_SUPABASE_URL') && defined('FPFP_SUPABASE_URL')) {
    define('FP_SUPABASE_URL', FPFP_SUPABASE_URL);
}

if (!defined('FP_SUPABASE_API_KEY') && defined('FPFP_SUPABASE_API_KEY')) {
    define('FP_SUPABASE_API_KEY', FPFP_SUPABASE_API_KEY);
}

/* -------------------------------------------------------------------------- */
/* CONFIGURAZIONE SUPABASE                                                    */
/* -------------------------------------------------------------------------- */

if (!defined('FPFP_SUPABASE_URL')) {
    define('FPFP_SUPABASE_URL', 'https://wpxnpvsaleswzfagneib.supabase.co');
}
if (!defined('FPFP_SUPABASE_API_KEY')) {
    define('FPFP_SUPABASE_API_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6IndweG5wdnNhbGVzd3pmYWduZWliIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjA0ODI1MTYsImV4cCI6MjA3NjA1ODUxNn0.F6XXMUfbhUgICN4cieMYIcAgu33Pbbz0YhTSXgw-FQE');
}

/**-----------------------------------------------------------------
 * Esporta eventi verso il plugin "Calendario Eventi Universale"
 * Filtro: ce_get_events
 ----------------------------------------------------------------*/
add_filter('ce_get_events', 'fpfp_export_events_to_ce_calendar', 10, 2);

function fpfp_export_events_to_ce_calendar($events, $args) {

    $start_date = $args['start_date']; 
    $end_date   = $args['end_date'];

    $supabase_url = FPFP_SUPABASE_URL . '/rest/v1/allenamenti';
    $api_key      = FPFP_SUPABASE_API_KEY;

    // Query: da data a data
$url = $supabase_url . '?select=id,disciplina,data_evento,wp_post_id'
     . '&data_evento=gte.' . $start_date
     . '&data_evento=lte.' . $end_date
     . '&evento_confermato=eq.true';

    $response = wp_remote_get($url, [
        'headers' => [
            'apikey'        => $api_key,
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) return $events;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) return $events;

$eventi_gia_aggiunti = [];

foreach ($data as $row) {

    $wp_post_id = intval($row['wp_post_id'] ?? 0);
    if (!$wp_post_id) {
        continue; // ❌ niente WP = niente calendario
    }

    // sicurezza: solo post pubblicati
    if (get_post_status($wp_post_id) !== 'publish') {
        continue;
    }

    // chiave unica evento
$unique_key =
    ($row['data_evento'] ?? '') . '|' .
    ($row['disciplina'] ?? '') . '|' .
    ($row['citta'] ?? '');

    // ❌ evita doppioni
    if (isset($eventi_gia_aggiunti[$unique_key])) {
        continue;
    }

    $eventi_gia_aggiunti[$unique_key] = true;

    $events[] = [
        'date'       => $row['data_evento'],
        'title'      => $row['disciplina'],
        'url'        => get_permalink($wp_post_id),
        'discipline' => $row['disciplina'],
    ];
}


    return $events;
}

/* -------------------------------------------------------------------------- */
/* CUSTOM POST TYPE: PAGINE EVENTO FIND PLAYER                                */
/* -------------------------------------------------------------------------- */

function fpfp_register_post_type_findplayer() {
    $labels = array(
        'name'               => 'Eventi Find Player',
        'singular_name'      => 'Evento Find Player',
        'add_new'            => 'Aggiungi Evento',
        'add_new_item'       => 'Aggiungi nuovo Evento Find Player',
        'edit_item'          => 'Modifica Evento',
        'new_item'           => 'Nuovo Evento',
        'view_item'          => 'Vedi Evento',
        'search_items'       => 'Cerca Eventi',
        'not_found'          => 'Nessun evento trovato',
        'not_found_in_trash' => 'Nessun evento nel cestino',
        'menu_name'          => 'Find Player Eventi',
    );

    register_post_type('findplayer_event', array(
        'labels'      => $labels,
        'public'      => true,
        'has_archive' => false,
        'rewrite'     => array('slug' => 'find-player', 'with_front' => false),
        'supports'    => array('title', 'editor'),
        'show_in_menu'=> true,
        'show_in_rest'=> false,
    ));
}
add_action('init', 'fpfp_register_post_type_findplayer');

add_action('init', function() {
    $oggi = gmdate('Y-m-d');
    $sette_giorni_fa = gmdate('Y-m-d', strtotime('-7 days'));

    $url = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?data_evento=lt.' . $sette_giorni_fa;
    $resp_del = wp_remote_request($url, [
        'method'  => 'DELETE',
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 20,
    ]);

    if (!is_wp_error($resp_del)) {
        error_log('🗑️ Eventi Find Player cancellati automaticamente (più vecchi di 7 giorni).');
    }
});

/* -------------------------------------------------------------------------- */
/* FLUSH AUTOMATICO DEI PERMALINK ALL'ATTIVAZIONE                             */
/* -------------------------------------------------------------------------- */

register_activation_hook(__FILE__, function() {
    // Registra il CPT e poi aggiorna le rewrite rules
    fpfp_register_post_type_findplayer();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    // Pulizia delle rewrite rules alla disattivazione
    flush_rewrite_rules();
});
/* -------------------------------------------------------------------------- */
/* HANDLER APPROVAZIONE PARTECIPAZIONI VIA TOKEN                              */
/* -------------------------------------------------------------------------- */

add_action('init', function () {
    if (!empty($_GET['approve_token'])) {
        $token = sanitize_text_field($_GET['approve_token']);
        fpfp_handle_approve_token($token);
        exit;
    }
});

function fpfp_handle_cancel_token($token) {
    // 1) Recupera prenotazione via cancel_token
    $url = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?cancel_token=eq.' . rawurlencode($token) . '&select=*';
    $resp = wp_remote_get($url, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        wp_die('<h2>Errore</h2><p>Impossibile contattare il database.</p>');
    }

    $rows = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($rows)) {
        wp_die('<h2>Token non valido</h2><p>Questa richiesta non è più attiva o è già stata cancellata.</p>');
    }

    $pren      = $rows[0];
    $pren_id   = intval($pren['id']);
    $allen_id  = intval($pren['allenamento_id']);
    $email_user = sanitize_email($pren['email'] ?? '');
    $nick_user  = sanitize_text_field($pren['nickname'] ?? '');
    $approvato  = !empty($pren['approvato']);

    // 2) Recupera l'evento per dettagli (disciplina/data/città)
    $disciplina = 'Evento';
    $data_ev    = '';
    $citta      = '';

    if ($allen_id) {
        $url_ev = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allen_id . '&select=disciplina,data_evento,citta';
        $resp_ev = wp_remote_get($url_ev, [
            'headers' => [
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            ],
            'timeout' => 20,
        ]);
        if (!is_wp_error($resp_ev) && wp_remote_retrieve_response_code($resp_ev) === 200) {
            $ev = json_decode(wp_remote_retrieve_body($resp_ev), true);
            if (!empty($ev[0])) {
                $disciplina = esc_html($ev[0]['disciplina'] ?? $disciplina);
                $data_ev    = esc_html($ev[0]['data_evento'] ?? $data_ev);
                $citta      = esc_html($ev[0]['citta'] ?? $citta);
            }
        }
    }

    // 3) Conferma/cancella
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {

        // 3.1 — Cancella la prenotazione
        $url_del = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?id=eq.' . $pren_id;
        $del = wp_remote_request($url_del, [
            'method'  => 'DELETE',
            'headers' => [
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            ],
            'timeout' => 20,
        ]);

        if (!is_wp_error($del) && wp_remote_retrieve_response_code($del) < 300) {

            // 3.2 — Se era approvato → liberiamo un posto
            if ($approvato && $allen_id > 0) {
                $url_posti = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allen_id . '&select=posti_liberi';
                $res_posti = wp_remote_get($url_posti, [
                    'headers' => [
                        'apikey'        => FPFP_SUPABASE_API_KEY,
                        'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                    ],
                    'timeout' => 20,
                ]);

                $data_posti = json_decode(wp_remote_retrieve_body($res_posti), true);
                if (!empty($data_posti) && isset($data_posti[0]['posti_liberi'])) {
                    $posti_attuali = intval($data_posti[0]['posti_liberi']);
                    $posti_agg     = $posti_attuali + 1;

                    wp_remote_post(
                        FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allen_id,
                        [
                            'method'  => 'PATCH',
                            'headers' => [
                                'apikey'        => FPFP_SUPABASE_API_KEY,
                                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                                'Content-Type'  => 'application/json',
                            ],
                            'body'    => json_encode([
                                'posti_liberi' => $posti_agg
                            ]),
                            'timeout' => 20,
                        ]
                    );
                }
            }

            // 3.3 — Email di conferma all’utente
            if ($email_user) {
                wp_mail(
                    $email_user,
                    "Conferma annullamento - $disciplina",
                    "Ciao $nick_user,\n\nHai annullato la tua partecipazione all'evento $disciplina del $data_ev $citta.\n\nCi vediamo presto su Find Player!",
                    ['Content-Type: text/plain; charset=UTF-8']
                );
            }

            wp_die('
                <div style="font-family:Arial,sans-serif;max-width:600px;margin:60px auto;text-align:center;">
                    <h2>Partecipazione annullata</h2>
                    <p>Hai annullato la tua iscrizione all\'evento <strong>' . $disciplina . '</strong> del <strong>' . $data_ev . '</strong>.</p>
                    <p><a href="' . esc_url(home_url('/home-attivita-sportive/')) . '">Torna alla Home Attività</a></p>
                </div>
            ');
        }

        wp_die('<h2>Errore</h2><p>Impossibile completare l\'operazione.</p>');
    }

    // 4) Pagina di conferma
    wp_die('
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:60px auto;text-align:center;">
            <h2>Annulla partecipazione</h2>
            <p>Vuoi davvero cancellare la tua partecipazione all\'evento <strong>' . $disciplina . '</strong>' . ($data_ev ? ' del <strong>' . $data_ev . '</strong>' : '') . '?</p>
            <p>
                <a href="' . esc_url(add_query_arg(['cancel_token' => $token, 'confirm' => 'yes'], home_url('/'))) . '" style="background:#c62828;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">❌ Conferma annullamento</a>
                <a href="' . esc_url(home_url('/home-attivita-sportive/')) . '" style="background:#ccc;color:black;padding:10px 20px;text-decoration:none;border-radius:4px;margin-left:10px;">Annulla</a>
            </p>
        </div>
    ');
}


/* -------------------------------------------------------------------------- */
/* HANDLER GESTIONE PARTECIPAZIONE UTENTE (token_manage_part)                 */
/* -------------------------------------------------------------------------- */
add_action('init', function () {
    if (!empty($_GET['manage_part_token']) && !is_admin()) {
        $token = sanitize_text_field($_GET['manage_part_token']);
        fpfp_handle_manage_part_token($token);
        exit;
    }
});

function fpfp_handle_manage_part_token($token) {
    // 1️⃣ Recupera la prenotazione con token_manage_part
    $url = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?token_manage_part=eq.' . rawurlencode($token) . '&select=*';
    $resp = wp_remote_get($url, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        wp_die('<h2>Errore</h2><p>Impossibile contattare il database.</p>');
    }

    $rows = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($rows)) {
        wp_die('<h2>Token non valido</h2><p>Il link di gestione non è più valido o è già stato usato.</p>');
    }

    $pren = $rows[0];
    $pren_id  = intval($pren['id']);
    $email    = sanitize_email($pren['email']);
    $nick     = sanitize_text_field($pren['nickname']);
    $created  = strtotime($pren['created_at'] ?? 'now');
    $now      = time();

    // 2️⃣ Scadenza 96 ore
    if (($now - $created) > 96 * 3600) {
        wp_die('<h2>Link scaduto</h2><p>Il link di gestione è scaduto (valido 96 ore). Contatta l\'organizzatore per modifiche.</p>');
    }

    $allen_id = intval($pren['allenamento_id']);
    $disciplina = '';
    $data_ev = '';
    $citta = '';
    $orario = '';
    $creatore_email = '';
    $nick_org = '';

    // 3️⃣ Recupera dati evento (anche per mail all'organizzatore)
    if ($allen_id) {
        $url_ev = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allen_id . '&select=disciplina,data_evento,citta,orario,creatore_email,nickname_creatore,creatore_nome';
        $resp_ev = wp_remote_get($url_ev, [
            'headers' => [
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            ],
        ]);
        if (!is_wp_error($resp_ev) && wp_remote_retrieve_response_code($resp_ev) === 200) {
            $ev = json_decode(wp_remote_retrieve_body($resp_ev), true);
            if (!empty($ev[0])) {
                $disciplina = esc_html($ev[0]['disciplina']);
                $data_ev    = esc_html($ev[0]['data_evento']);
                $citta      = esc_html($ev[0]['citta']);
                $orario     = esc_html($ev[0]['orario'] ?? '');
                $creatore_email = sanitize_email($ev[0]['creatore_email'] ?? '');
                $nick_org  = sanitize_text_field($ev[0]['nickname_creatore'] ?? $ev[0]['creatore_nome'] ?? '');
            }
        }
    }


    // 4️⃣ Conferma annullamento
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {

        $approvato = !empty($pren['approvato']);

        $url_del = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?id=eq.' . $pren_id;
        $del = wp_remote_request($url_del, [
            'method'  => 'DELETE',
            'headers' => [
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            ],
        ]);

        if (!is_wp_error($del) && wp_remote_retrieve_response_code($del) < 300) {

            // Se era approvato, liberiamo di nuovo 1 posto
            if ($approvato && $allen_id > 0) {
                $url_posti = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allen_id . '&select=posti_liberi';
                $res_posti = wp_remote_get($url_posti, [
                    'headers' => [
                        'apikey'        => FPFP_SUPABASE_API_KEY,
                        'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                    ],
                ]);

                $data_posti = json_decode(wp_remote_retrieve_body($res_posti), true);
                if (!empty($data_posti) && isset($data_posti[0]['posti_liberi'])) {
                    $posti_attuali = intval($data_posti[0]['posti_liberi']);
                    $posti_agg     = $posti_attuali + 1;

                    wp_remote_post(
                        FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allen_id,
                        [
                            'method'  => 'PATCH',
                            'headers' => [
                                'apikey'        => FPFP_SUPABASE_API_KEY,
                                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                                'Content-Type'  => 'application/json',
                            ],
                            'body'    => json_encode([
                                'posti_liberi' => $posti_agg
                            ]),
                        ]
                    );
                }
            }

            wp_mail($email, "Conferma annullamento evento $disciplina", 
                "Ciao $nick,\n\nHai annullato la tua partecipazione all'evento $disciplina del $data_ev a $citta.\n\nA presto su Find Player!",
                ['Content-Type: text/plain; charset=UTF-8']
            );
            // 📧 Notifica all'organizzatore della cancellazione
            if ($creatore_email) {
                $headers_org = [
                    'Content-Type: text/plain; charset=UTF-8',
                    'From: ASD Oltrecity <no-reply@oltrecity.com>'
                ];

                $msg_org = "Ciao {$nick_org},\n\n"
                         . "ti informiamo che un partecipante ha ANNULLATO la sua partecipazione all'evento:\n\n"
                         . "Disciplina: {$disciplina}\n"
                         . "Data: {$data_ev}\n"
                         . "Orario: {$orario}\n"
                         . "Città: {$citta}\n\n"
                         . "Dettagli partecipante:\n"
                         . "- Nickname: {$nick}\n"
                         . "- Email: {$email}\n\n"
                         . "Questa persona non parteciperà più all'attività.\n\n"
                         . "A presto,\n"
                         . "Find Player – ASD Oltrecity";

                wp_mail(
                    $creatore_email,
                    "Un partecipante ha annullato la sua partecipazione",
                    $msg_org,
                    $headers_org
                );
            }

			
            wp_die("<h2>Partecipazione annullata</h2><p>Hai annullato la tua iscrizione a $disciplina del $data_ev.</p>");
        }

        wp_die('<h2>Errore</h2><p>Impossibile completare l\'operazione.</p>');
    }

    // 5️⃣ Pagina gestione
    wp_die('
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:60px auto;text-align:center;">
            <h2>Gestione iscrizione</h2>
            <p>Ciao <strong>' . esc_html($nick) . '</strong>,<br>sei iscritto all\'evento <strong>' . esc_html($disciplina) . '</strong> del <strong>' . esc_html($data_ev) . '</strong> a <strong>' . esc_html($citta) . '</strong>.</p>
            <p>Vuoi annullare la tua partecipazione?</p>
            <p>
                <a href="' . esc_url(add_query_arg(['manage_part_token' => $token, 'confirm' => 'yes'], home_url('/'))) . '" style="background:#c62828;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">❌ Annulla partecipazione</a>
                <a href="' . esc_url(home_url('/home-attivita-sportive/')) . '" style="background:#ccc;color:black;padding:10px 20px;text-decoration:none;border-radius:4px;margin-left:10px;">🔙 Torna alla Home</a>
            </p>
        </div>
    ');
}


function fpfp_handle_approve_token($token) {
    // 1) Recupera prenotazione da Supabase
    $url = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?token_app=eq.' . rawurlencode($token) . '&select=*';
    $response = wp_remote_get($url, array(
        'headers' => array(
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ),
        'timeout' => 20,
    ));

    if (is_wp_error($response)) {
        wp_die('<h2>Errore</h2><p>Impossibile contattare il database. Riprova più tardi.</p>', 'Errore approvazione', array('response' => 500));
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        wp_die('<h2>Errore</h2><p>Risposta non valida dal database (codice ' . intval($code) . ').</p>', 'Errore approvazione', array('response' => 500));
    }

    $rows = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($rows) || empty($rows)) {
        wp_die('<h2>Token non valido</h2><p>La richiesta non esiste o è già stata gestita.</p>', 'Token non valido', array('response' => 400));
    }

    $pren = $rows[0];
    $pren_id = intval($pren['id']);
    $allenamento_id = intval($pren['allenamento_id']);
    $gia_approvato = !empty($pren['approvato']);
    $email_partecipante = sanitize_email($pren['email']);
    $nickname_partecipante = sanitize_text_field($pren['nickname']);

    if ($gia_approvato) {
        wp_die('<h2>Già approvato</h2><p>Questa richiesta di partecipazione è già stata approvata.</p>', 'Già approvato', array('response' => 200));
    }

    // 2) Recupera evento corrispondente
    $url_ev = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allenamento_id . '&select=*';
    $resp_ev = wp_remote_get($url_ev, array(
        'headers' => array(
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ),
        'timeout' => 20,
    ));

    if (is_wp_error($resp_ev) || wp_remote_retrieve_response_code($resp_ev) !== 200) {
        wp_die('<h2>Errore evento</h2><p>Impossibile recuperare i dati dell\'evento.</p>', 'Errore evento', array('response' => 500));
    }

    $ev_rows = json_decode(wp_remote_retrieve_body($resp_ev), true);
    if (!is_array($ev_rows) || empty($ev_rows)) {
        wp_die('<h2>Evento non trovato</h2><p>L\'evento associato non esiste più.</p>', 'Evento non trovato', array('response' => 404));
    }

    $evento = $ev_rows[0];
    $disciplina   = sanitize_text_field($evento['disciplina'] ?? '');
    $data_evento  = sanitize_text_field($evento['data_evento'] ?? '');
    $orario       = sanitize_text_field($evento['orario'] ?? '');
    $citta        = sanitize_text_field($evento['citta'] ?? '');
    $posti_liberi = isset($evento['posti_liberi']) ? intval($evento['posti_liberi']) : 0;

    $nuovi_posti = max(0, $posti_liberi - 1);

    // 3) Aggiorna prenotazione come approvata
    $url_pren_update = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?id=eq.' . $pren_id;
    $resp_up_pren = wp_remote_request($url_pren_update, array(
        'method'  => 'PATCH',
        'headers' => array(
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ),
        'body'    => wp_json_encode(array('approvato' => true)),
        'timeout' => 20,
    ));

    if (is_wp_error($resp_up_pren) || !in_array(wp_remote_retrieve_response_code($resp_up_pren), array(200,204))) {
        wp_die('<h2>Errore aggiornamento</h2><p>Non è stato possibile aggiornare la prenotazione.</p>', 'Errore aggiornamento', array('response' => 500));
    }

    // 4) Aggiorna posti liberi nell'evento
    $url_ev_update = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allenamento_id;
    $resp_up_ev = wp_remote_request($url_ev_update, array(
        'method'  => 'PATCH',
        'headers' => array(
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ),
        'body'    => wp_json_encode(array('posti_liberi' => $nuovi_posti)),
        'timeout' => 20,
    ));

    if (is_wp_error($resp_up_ev) || !in_array(wp_remote_retrieve_response_code($resp_up_ev), array(200,204))) {
        wp_die('<h2>Errore posti</h2><p>La richiesta è stata approvata ma non è stato possibile aggiornare i posti liberi.</p>', 'Errore posti', array('response' => 500));
    }
	
    // 🔐 CREA TOKEN DI GESTIONE PERSONALE (per il partecipante approvato)
    $token_manage_part = wp_generate_password(40, false, false);

    // Aggiorna la prenotazione su Supabase con il token di gestione
    $url_upd_manage = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?id=eq.' . $pren_id;
    $resp_upd_manage = wp_remote_request($url_upd_manage, [
        'method'  => 'PATCH',
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ],
        'body'    => wp_json_encode([
            'token_manage_part' => $token_manage_part,
        ]),
        'timeout' => 20,
    ]);

    if (is_wp_error($resp_upd_manage)) {
        error_log('⚠️ Errore nel salvataggio del token di gestione partecipante: ' . $resp_upd_manage->get_error_message());
    }

    // Costruisci il link per la gestione della presenza
    $manage_url = add_query_arg('manage_part_token', $token_manage_part, home_url('/'));

	
    // 5) Email al partecipante
    if (!empty($email_partecipante)) {
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ASD Oltrecity <no-reply@oltrecity.com>'
        ];

        $msg = "Ciao $nickname_partecipante,\n\n"
             . "la tua richiesta di partecipazione all'evento:\n\n"
             . "🏅 Disciplina: $disciplina\n"
             . "📅 Data: $data_evento\n"
             . "🕒 Orario: $orario\n"
             . "📍 Città: $citta\n\n"
             . "è stata APPROVATA! 🎉\n\n"
             . "Ti aspettiamo all'appuntamento.\n"
             . "Sii sempre corretto, se non parteciperai all'attività informa l'organizzatore o lo staff.\n\n"

             . "Per gestire la tua iscrizione (es. annullare la partecipazione entro 96 ore), clicca qui:\n"
             . "👉 $manage_url\n\n"
             . "Ricorda: dopo 96 ore il link non sarà più valido.\n\n"
             . "A presto da tutto lo staff Find Player!";
             
        wp_mail($email_partecipante, 'Partecipazione approvata - Find Player', $msg, $headers);
    }


// 🛡️ FIX doppia iscrizione: blocca qualunque inserimento nella tabella iscritti_findplayer
return wp_die(
    '<h2>Partecipazione approvata ✅</h2>'
    . '<p>La richiesta di <strong>' . esc_html($nickname_partecipante) . '</strong> è stata approvata.</p>'
    . '<p>Posti liberi rimanenti: <strong>' . intval($nuovi_posti) . '</strong></p>',
    'Partecipazione approvata',
    array('response' => 200)
);
}
/* -------------------------------------------------------------------------- */
/* HANDLER GESTIONE EVENTO VIA TOKEN GESTORE (ANNULLAMENTO / CANCELLAZIONE)   */
/* -------------------------------------------------------------------------- */

add_action('init', function () {
    if (isset($_GET['manage_token']) && !empty($_GET['manage_token']) && !is_admin()) {
        $token = sanitize_text_field($_GET['manage_token']);
        fpfp_handle_manage_token($token);
        exit;
    }
});

function fpfp_handle_manage_token($token) {
    $url = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?token_gestore=eq.' . rawurlencode($token) . '&select=*';
    $resp = wp_remote_get($url, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        wp_die('<h2>Errore</h2><p>Connessione fallita con il database.</p>');
    }

    $rows = json_decode(wp_remote_retrieve_body($resp), true);
    if (empty($rows)) {
        wp_die('<h2>Token non valido</h2><p>Questo link non è più attivo o l\'evento è già stato eliminato.</p>');
    }

    $ev = $rows[0];
    $allenamento_id = intval($ev['id']);
    $disciplina     = esc_html($ev['disciplina']);
    $data_evento    = esc_html($ev['data_evento']);
    $citta          = esc_html($ev['citta']);

    /* ---------------------------------------------------------------------- */
    /* AZIONE: ELIMINAZIONE EVENTO                                            */
    /* ---------------------------------------------------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'delete') {

    // 1️⃣ Prima recupera i partecipanti
    $url_pren = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?allenamento_id=eq.' . $allenamento_id;
    $resp_pren = wp_remote_get($url_pren, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 20,
    ]);

    $partecipanti = [];
    if (!is_wp_error($resp_pren) && wp_remote_retrieve_response_code($resp_pren) === 200) {
        $partecipanti = json_decode(wp_remote_retrieve_body($resp_pren), true);
    }

    // 2️⃣ Poi elimina l’evento da Supabase
    $url_del = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allenamento_id;
    $del = wp_remote_request($url_del, [
        'method'  => 'DELETE',
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
    ]);

    if (!is_wp_error($del)) {

        // 3️⃣ Ora invia le mail ai partecipanti recuperati
        if (!empty($partecipanti)) {
            foreach ($partecipanti as $p) {
                $email = sanitize_email($p['email']);
                $nick  = sanitize_text_field($p['nickname']);
                if (!$email) continue;

                $subject = "Evento annullato - $disciplina ($data_evento)";
                $msg = "Ciao $nick,\n\n"
                     . "L'evento \"$disciplina\" del $data_evento a $citta è stato annullato dall'organizzatore.\n"
                     . "Ti ringraziamo per aver partecipato a Find Player e speriamo di rivederti presto!\n\n"
                     . "ASD Oltrecity - Find Player - contattaci pure se hai necessità al +393805140047";

                error_log("📧 Invio mail annullamento evento a: $email ($nick)");
                wp_mail($email, $subject, $msg, ['Content-Type: text/plain; charset=UTF-8']);
            }
        } else {
            error_log("⚠️ Nessun partecipante trovato per l'evento ID $allenamento_id");
        }

        // 4️⃣ Mostra la conferma
        wp_die('
            <div style="font-family:Arial,sans-serif;max-width:600px;margin:60px auto;text-align:center;">
                <h2>✅ Evento eliminato correttamente</h2>
                <p>L\'evento <strong>' . $disciplina . '</strong> del <strong>' . $data_evento . '</strong> a <strong>' . $citta . '</strong> è stato rimosso dal calendario.</p>
                <p>Verrai reindirizzato alla home in 5 secondi...</p>
                <p><a href="' . home_url('/home-attivita-sportive/') . '" style="background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">🏠 Torna subito</a></p>
                <script>setTimeout(function(){window.location.href="' . home_url('/home-attivita-sportive/') . '";},5000);</script>
            </div>
        ');
        exit;
    } else {
        wp_die('<h2>Errore</h2><p>Non è stato possibile eliminare l\'evento.</p>');
    }
}
/* ---------------------------------------------------------------------- */
/* PAGINA DI CONFERMA ELIMINAZIONE                                        */
/* ---------------------------------------------------------------------- */
    wp_die('
        <div style="font-family:Arial,sans-serif;max-width:600px;margin:60px auto;text-align:center;">
            <h2>Gestione evento</h2>
            <p><strong>Disciplina:</strong> ' . $disciplina . '<br>
            <strong>Data:</strong> ' . $data_evento . '<br>
            <strong>Città:</strong> ' . $citta . '</p>
            <p>Vuoi davvero <strong>annullare</strong> questo evento?</p>
            <p>
                <a href="' . esc_url(add_query_arg(['manage_token' => $token, 'action' => 'delete'], home_url('/'))) . '" style="background:#c62828;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">❌ Conferma eliminazione</a>
                <a href="' . home_url('/home-attivita-sportive/') . '" style="background:#ccc;color:black;padding:10px 20px;text-decoration:none;border-radius:4px;margin-left:10px;">Annulla</a>
            </p>
        </div>
    ');
}

/* -------------------------------------------------------------------------- */
/* SHORTCODE: FORM CREAZIONE PARTITA/ALLENAMENTO                              */
/* -------------------------------------------------------------------------- */

add_shortcode('find_player_form', function () {
    ob_start();
    
// =====================================================
// PREFILL DATI ORGANIZZATORE EVENTO
// =====================================================
$nickname_creatore = '';
$nome_cognome      = '';
$email             = '';
$telefono          = '';

if (is_user_logged_in()) {

    $user_id = get_current_user_id();
    $user    = wp_get_current_user();

    // Email SEMPRE da WP
    $email = $user->user_email;

    // Recupero scheda giocatore collegata all’utente
    $player_id = 0;

    // 🔴 QUESTA FUNZIONE DEVE ESISTERE
    if (function_exists('fp_get_player_id_by_user')) {
        $player_id = fp_get_player_id_by_user($user_id);
    }

    if ($player_id) {
        $nickname_creatore = get_post_meta($player_id, 'fp_nickname', true);

        $nome    = get_post_meta($player_id, 'fp_nome', true);
        $cognome = get_post_meta($player_id, 'fp_cognome', true);
        $nome_cognome = trim($nome . ' ' . $cognome);

        $telefono = get_post_meta($player_id, 'fp_telefono', true);
    }
}

    
    ?>
<style>
@media(min-width: 768px){
    .fp-row { 
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 15px;
    }
    .fp-row-3 {
        grid-template-columns: 1fr 1fr 1fr;
    }
    .fp-full { 
        grid-column: 1 / 3; 
    }
}

.fp-field label { font-weight: bold; }
.fp-field input,
.fp-field textarea,
.fp-field select {
    width: 100%;
    padding: 6px;
}

/* 👇 Giorni settimana tutti in linea */
#giorni_settimana_box label {
    display: inline-block;
    margin-right: 10px;
    font-weight: normal; /* opzionale, per non averli in grassetto */
}
</style>

    <?php
/* ---------------------------------------------------------------------- */

    if (!empty($_POST['fpfp_invio_evento'])) {

        // Anti-spam
        $risposta_utente   = strtolower(trim($_POST['antispam'] ?? ''));
        $risposta_corretta = strtolower(trim($_POST['risposta_corretta'] ?? ''));

        if ($risposta_utente !== $risposta_corretta) {
            echo '<p style="color:red;">⚠️ Risposta anti-spam errata. Riprova.</p>';
            return ob_get_clean();
        }

        // Privacy
        $privacy = !empty($_POST['privacy']);
        if (!$privacy) {
            echo '<div class="notice notice-error" style="color:red;">⚠️ Devi accettare la Privacy Policy per creare una partita.</div>';
            return ob_get_clean();
        }

        // Dati evento
        $data_evento  = sanitize_text_field($_POST['data_evento'] ?? '');
        $orario       = sanitize_text_field($_POST['orario'] ?? '');
        $citta        = sanitize_text_field($_POST['citta'] ?? '');
		$disciplina = sanitize_text_field($_POST['disciplina'] ?? '');
        $luogo        = sanitize_text_field($_POST['luogo_descrizione'] ?? '');
        $tipo_campo   = sanitize_text_field($_POST['tipo_campo'] ?? '');
        $ruolo_personale = sanitize_text_field($_POST['ruolo_personale'] ?? '');
        $ruoli_cercati   = sanitize_textarea_field($_POST['ruoli_cercati'] ?? '');
        $livello_personale = intval($_POST['livello_personale'] ?? 1);
        $livello_richiesto = intval($_POST['livello_richiesto'] ?? 1);
        $num_giocatori    = intval($_POST['num_giocatori'] ?? 0);
        $note_extra       = sanitize_textarea_field($_POST['note_extra'] ?? '');

$player_id = 0;

if (is_user_logged_in()) {

    $user_id = get_current_user_id();

    // recupera scheda giocatore collegata all’utente
    $player_id = fp_get_player_id_by_user($user_id);

    if ($player_id) {
        $nickname_creatore = get_post_meta($player_id, 'fp_nickname', true);
        $creatore_nome     = trim(
            get_post_meta($player_id, 'fp_nome', true) . ' ' .
            get_post_meta($player_id, 'fp_cognome', true)
        );
        $email    = wp_get_current_user()->user_email;
        $telefono = get_post_meta($player_id, 'fp_telefono', true);
    }
}
        // Dati organizzatore
        $nickname_creatore   = sanitize_text_field($_POST['nickname_creatore'] ?? '');
        $creatore_nome       = sanitize_text_field($_POST['nome_cognome'] ?? '');
        $email      = sanitize_email($_POST['email'] ?? '');
        $telefono   = sanitize_text_field($_POST['telefono'] ?? '');
        
// ===============================
// VALIDAZIONE NICKNAME (SOLO GUEST)
// ===============================
if (!is_user_logged_in()) {

    if (!function_exists('fp_nickname_disponibile')) {
        wp_die('Errore di sistema: funzione nickname mancante.');
    }

    // 🔒 BLOCCO USO DATI GIOCATORI REGISTRATI
    if (fp_dato_appartiene_a_giocatore_registrato(
        $nickname_creatore,
        $email,
        $telefono
    )) {
        echo '<p style="color:red;font-weight:bold;">
        ❌ Non puoi utilizzare nickname, email o telefono
        appartenenti a un giocatore già registrato su Find Player.
        </p>';
        return;
    }

    // 🔒 LOCK 6 MESI NICKNAME
    if (fp_nickname_locked($nickname_creatore)) {
        echo '<p style="color:red;font-weight:bold;">
        ❌ Nickname già utilizzato di recente.
        Potrà tornare disponibile solo dopo 6 mesi di inattività.
        </p>';
        return;
    }

    // 🔒 FALLBACK (se rimane ancora fp_nickname_disponibile)
    if (!fp_nickname_disponibile($nickname_creatore)) {
        echo '<p style="color:red;font-weight:bold;">
        ❌ Nickname non disponibile.
        </p>';
        return;
    }
}
        if (empty($disciplina) || empty($data_evento) || empty($orario) || empty($citta) || empty($email)) {
            echo '<p style="color:red;">⚠️ Disciplina, data, orario, città ed email sono obbligatori.</p>';
            return ob_get_clean();
        }

        // Token gestore per usi futuri
        $token_gestore = wp_generate_password(40, false, false);

/* -------------------------------------------------
   GEO-CODING AUTOMATICO EVENTO (LAT / LNG) — OSM FIX
--------------------------------------------------*/

$lat = null;
$lng = null;
$geo_error_message = null;

$indirizzo_geocode = trim(
    ($luogo ? $luogo . ', ' : '') .
    $citta . ', Sardegna, Italia'
);

$geo_url = 'https://nominatim.openstreetmap.org/search'
    . '?format=json'
    . '&limit=1'
    . '&addressdetails=0'
    . '&q=' . urlencode($indirizzo_geocode);

$geo_resp = wp_remote_get($geo_url, [
    'timeout' => 20,
    'headers' => [
        // 🔥 USER-AGENT VALIDO (obbligatorio per Nominatim)
        'User-Agent' => 'Mozilla/5.0 (FindPlayer.it; +https://www.findplayer.it; info@findplayer.it)'
    ]
]);

if (!is_wp_error($geo_resp)) {
    $geo_body = wp_remote_retrieve_body($geo_resp);
    $geo_data = json_decode($geo_body, true);

    error_log('📍 GEOCODE QUERY: ' . $indirizzo_geocode);
    error_log('📍 GEOCODE RESPONSE: ' . $geo_body);

    if (!empty($geo_data[0]['lat']) && !empty($geo_data[0]['lon'])) {
        $lat = floatval($geo_data[0]['lat']);
        $lng = floatval($geo_data[0]['lon']);
    } else {
        // ⚠️ geocoding fallito, ma NON blocchiamo
        $geo_error_message = 'Luogo non trovato';
    }
} else {
    // ⚠️ errore di rete / timeout
    $geo_error_message = 'Luogo non trovato';
}

if (!is_wp_error($geo_resp)) {
    $geo_body = wp_remote_retrieve_body($geo_resp);
    $geo_data = json_decode($geo_body, true);

    // 🔍 DEBUG TEMPORANEO (TI CONSIGLIO DI LASCIARLO ORA)
    error_log('📍 GEOCODE QUERY: ' . $indirizzo_geocode);
    error_log('📍 GEOCODE RESPONSE: ' . $geo_body);

    if (!empty($geo_data[0]['lat']) && !empty($geo_data[0]['lon'])) {
        $lat = floatval($geo_data[0]['lat']);
        $lng = floatval($geo_data[0]['lon']);
    }
}

if ($geo_error_message) {
    error_log('⚠️ FIND PLAYER: ' . $geo_error_message . ' → evento salvato senza coordinate.');
}


        // Dati per Supabase (tabella: allenamenti)
        $body = array(
            'disciplina'                 => $disciplina,
            'data_evento'                => $data_evento,
            'orario'                     => $orario,
            'citta'                      => $citta,
            'luogo_descrizione'          => $luogo,
            'tipo_campo'                 => $tipo_campo,
            'ruolo_personale'            => $ruolo_personale,
            'ruoli_cercati'              => $ruoli_cercati,
            'livello_personale'          => $livello_personale,
            'livello_richiesto_compagni' => $livello_richiesto,
            'num_giocatori_richiesti'    => $num_giocatori,
            'posti_liberi'               => $num_giocatori,
            'nickname_creatore'          => $nickname_creatore,
            'creatore_nome'              => $creatore_nome,
            'creatore_email'             => $email,
            'creatore_tel'               => $telefono,
            'note_extra'                 => $note_extra,
            'token_gestore'              => $token_gestore,
            'created_at'                 => gmdate('Y-m-d\TH:i:s\Z'),
			'lat'              			 => $lat,
			'lng'             			 => $lng,
			'evento_confermato'          => is_user_logged_in(),
        );

        $response = wp_remote_post(FPFP_SUPABASE_URL . '/rest/v1/allenamenti', array(
            'headers' => array(
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ),
            'body'    => wp_json_encode($body),
            'method'  => 'POST',
            'timeout' => 20,
        ));

        if (is_wp_error($response)) {
            echo '<p style="color:red;">❌ Errore di connessione con il database (Supabase). Riprova tra qualche minuto.</p>';
            return ob_get_clean();
        }

        $code = wp_remote_retrieve_response_code($response);
        if (!in_array($code, array(200, 201), true)) {
            $body_resp = esc_html(wp_remote_retrieve_body($response));
            echo '<p style="color:red;">❌ Errore salvataggio evento su Supabase. Codice: ' . intval($code) . '</p>';
            echo '<pre style="white-space:pre-wrap;font-size:11px;background:#f8f8f8;padding:6px;border:1px solid #eee;">' . $body_resp . '</pre>';
            return ob_get_clean();
        }

        $inserted = json_decode(wp_remote_retrieve_body($response), true);
        $allenamento_id = null;
        if (is_array($inserted) && !empty($inserted[0]['id'])) {
            $allenamento_id = intval($inserted[0]['id']);
        }

        // CREA LA PAGINA EVENTO (CPT) SOLO SE ABBIAMO L'ID
        if ($allenamento_id) {
            $title = $disciplina . ' – ' . date_i18n('d/m/Y', strtotime($data_evento)) . ' – ' . $citta;
            $slug_base = sanitize_title($disciplina . '-' . $data_evento . '-' . $allenamento_id);
            $token_evento = '';

        if (!is_user_logged_in()) {
              $token_evento = wp_generate_password(40, false, false);
}
$post_id = wp_insert_post(array(
    'post_title'   => $title,
    'post_name'    => $slug_base,
    'post_status' => is_user_logged_in() ? 'publish' : 'pending',
    'post_type'    => 'findplayer_event',
'post_content' => '[find_player_event_details post_id="' . $post_id . '"]',
    'post_author'  => is_user_logged_in() ? get_current_user_id() : 0,
));
// 🔗 COLLEGA EVENTO ALLA SCHEDA GIOCATORE (LEGAME FORTE)
if (!empty($player_id)) {
    update_post_meta($post_id, 'fp_creatore_giocatore', (int) $player_id);
}

            // 📌 Dati creatore evento (per token / lock / audit)
update_post_meta($post_id, 'fp_nickname_creatore', $nickname_creatore);
update_post_meta($post_id, 'fp_email_creatore', $email);
update_post_meta($post_id, 'fp_telefono_creatore', $telefono);

            // 🎟️ TOKEN CONFERMA EVENTO (solo guest)
if (!is_user_logged_in() && $post_id && $token_evento) {
    update_post_meta($post_id, 'fp_event_token', $token_evento);
}
// 📧 INVIO MAIL CON TOKEN (solo guest)
if (!is_user_logged_in() && $token_evento && !empty($email)) {

    $link_conferma = add_query_arg([
        'fp_confirm_event' => $token_evento,
        'event_id'         => $post_id,
    ], site_url('/'));

    wp_mail(
        $email,
        'Conferma creazione evento – Find Player',
        "Ciao,\n\nPer confermare la creazione dell’evento clicca qui:\n\n$link_conferma\n\nGrazie\nFind Player"
    );
}

            if ($player_id) {
    update_post_meta($post_id, 'fp_giocatore_id', $player_id);
}
            if ($post_id && !is_wp_error($post_id)) {
                update_post_meta($post_id, '_findplayer_allenamento_id', $allenamento_id);
            }
            // 🔗 Salva ID CPT WordPress in Supabase
wp_remote_request(
    FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . intval($allenamento_id),
    [
        'method'  => 'PATCH',
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode([
            'wp_post_id' => (int) $post_id,
        ]),
    ]
);

        }

// --------------------------------------------------------------------------
// CREAZIONE EVENTI RIPETUTI
// --------------------------------------------------------------------------

$ripetizione = sanitize_text_field($_POST['ripetizione_tipo'] ?? 'singolo');
$settimane   = max(1, intval($_POST['ripetizione_settimane'] ?? 1));
$giorni_raw  = $_POST['giorni'] ?? [];

// Normalizza i giorni come array di interi
$giorni = array_map('intval', (array) $giorni_raw);

$eventi_da_creare = [];

// ✅ Se è evento singolo NON creiamo duplicati: abbiamo già l'evento principale
if ($ripetizione === 'giorni_settimana' && !empty($giorni)) {

    $inizio_ts = strtotime($data_evento);
    if ($inizio_ts !== false) {

        // Monday della settimana della data iniziale (ancora nella stessa settimana logica)
        $monday_start_ts = strtotime('monday this week', $inizio_ts);
        $data_iniziale_ymd = date('Y-m-d', $inizio_ts);

        // Converte i giorni: 0 (Domenica) -> 7, 1..6 restano uguali (Lun=1,...,Sab=6)
        $giorni_norm = [];
        foreach ($giorni as $g) {
            if ($g === 0) {
                $giorni_norm[] = 7; // Domenica alla fine
            } else {
                $giorni_norm[] = $g;
            }
        }
        // Evita duplicati nei giorni
        $giorni_norm = array_unique($giorni_norm);

        for ($s = 0; $s < $settimane; $s++) {

            // Monday della settimana s-esima
            $week_monday_ts = strtotime("+{$s} week", $monday_start_ts);

            foreach ($giorni_norm as $dow) {
                // dow: 1=Lun ... 7=Dom
                $candidate_ts  = $week_monday_ts + ($dow - 1) * DAY_IN_SECONDS;
                $candidate_ymd = date('Y-m-d', $candidate_ts);

                // ❌ Non creare eventi prima della data iniziale
                if ($candidate_ymd < $data_iniziale_ymd) {
                    continue;
                }

                // ❌ Non ricreare la data iniziale (già salvata come evento principale)
                if ($candidate_ymd === $data_iniziale_ymd) {
                    continue;
                }

                $eventi_da_creare[] = $candidate_ymd;
            }
        }

        // Evita doppi anche tra settimane diverse per sicurezza
        $eventi_da_creare = array_values(array_unique($eventi_da_creare));
    }
}

// --------------------------------------------------------------------------
// CREA SU SUPABASE TUTTI GLI EVENTI GENERATI + EMAIL SINGOLA
// --------------------------------------------------------------------------
if (!empty($eventi_da_creare)) {
    $lista_eventi = [];      // raccoglie i link singoli
    $riepilogo_eventi = '';  // testo riepilogo da aggiungere alle mail finali
    // Header email per i duplicati (stesso mittente “bello”)
    $headers_clone = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ASD Oltrecity <no-reply@oltrecity.com>'
    );
    foreach ($eventi_da_creare as $data_generata) {

        $body_clone = $body;
        $body_clone['data_evento']   = $data_generata;
        $body_clone['token_gestore'] = wp_generate_password(40, false, false);
        $body_clone['created_at']    = gmdate('Y-m-d\TH:i:s\Z');
        $body_clone['evento_confermato'] = false;
        $resp_clone = wp_remote_post(FPFP_SUPABASE_URL . '/rest/v1/allenamenti', [
            'headers' => [
                'apikey'        => FPFP_SUPABASE_API_KEY,
                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                'Content-Type'  => 'application/json',
                'Prefer'        => 'return=representation',
            ],
            'body'    => wp_json_encode($body_clone),
            'timeout' => 20,
        ]);

        if (is_wp_error($resp_clone)) {
            error_log("❌ ERRORE: duplicazione evento fallita per $data_generata");
            continue;
        }

        $inserted = json_decode(wp_remote_retrieve_body($resp_clone), true);

        if (empty($inserted[0]['id'])) {
            error_log("❌ ERRORE: Supabase non restituisce ID per $data_generata");
            continue;
        }

        $new_event_id = intval($inserted[0]['id']);

        // CREA CPT ASSOCIATO
        $title_clone = $disciplina . ' – ' . date_i18n('d/m/Y', strtotime($data_generata)) . ' – ' . $citta;
        $slug_clone  = sanitize_title($disciplina . '-' . $data_generata . '-' . $new_event_id);

        $post_clone = wp_insert_post([
            'post_title'   => $title_clone,
            'post_name'    => $slug_clone,
            'post_status'  => is_user_logged_in() ? 'publish' : 'pending',
            'post_type'    => 'findplayer_event',
            'post_content' => '[find_player_event_details id="' . $new_event_id . '"]',
        ]);

        if (!is_wp_error($post_clone)) {
            update_post_meta($post_clone, '_findplayer_allenamento_id', $new_event_id);
        }

        error_log("✅ Creato evento duplicato per data $data_generata — ID $new_event_id");

// 🔄 Raccogliamo i link da mandare in UNICA mail
$lista_eventi[] = [
    'data'  => $data_generata,
            'url'  => site_url("/gestisci-evento/?token={$body_clone['token_gestore']}&id={$new_event_id}")
];
    // 📋 Costruiamo il riepilogo testuale degli eventi ripetuti
    if (!empty($lista_eventi)) {
        $riepilogo_eventi = "\n\n---------------------------------------\n"
                          . "EVENTI RIPETUTI CREATI\n"
                          . "---------------------------------------\n\n";

        foreach ($lista_eventi as $ev) {
            $riepilogo_eventi .= "- {$ev['data']} → {$ev['url']}\n";
           }
        }
      } // <= fine if (!empty($eventi_da_creare))
    }

        // Salva anche l'organizzatore nella tabella iscritti_findplayer (CRM)
        if ($allenamento_id) {
            $body_iscritto = array(
                'allenamento_id' => $allenamento_id,
                'ruolo'          => 'organizzatore',
                'disciplina'     => $disciplina,
                'citta'          => $citta,
                'nickname'       => $nickname_creatore,
                'nome'           => $creatore_nome,
                'email'          => $email,
                'telefono'       => $telefono,
                'ip_address'     => fp_get_client_ip(),
                'created_at'     => gmdate('Y-m-d\TH:i:s\Z'),
            );

            $resp_iscritto = wp_remote_post(FPFP_SUPABASE_URL . '/rest/v1/iscritti_findplayer', array(
                'headers' => array(
                    'apikey'        => FPFP_SUPABASE_API_KEY,
                    'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                    'Content-Type'  => 'application/json',
                    'Prefer'        => 'return=representation',
                ),
                'body'    => wp_json_encode($body_iscritto),
                'timeout' => 20,
            ));

            if (is_wp_error($resp_iscritto)) {
                error_log('❌ Errore inserimento organizzatore in iscritti_findplayer: ' . $resp_iscritto->get_error_message());
            }
        }
			
        // Email riepilogo + link gestione (UNA SOLA MAIL)
        $headers = array('Content-Type: text/plain; charset=UTF-8', 'From: ASD Oltrecity <no-reply@oltrecity.com>');

        $gestione_url = site_url("/gestisci-evento/?token={$token_gestore}&id={$allenamento_id}");

        $msg = "Ciao $creatore_nome,\n\n"
             . "la tua attività è stata creata correttamente.\n\n"
             . "Dettagli evento:\n"
             . "- Disciplina: $disciplina\n"
             . "- Data: $data_evento\n"
             . "- Orario: $orario\n"
             . "- Città: $citta\n"
             . "- Luogo: $luogo\n"
             . "- Tipo campo: $tipo_campo\n"
             . "- Ruolo in cui vuoi giocare: $ruolo_personale\n"
             . "- Ruoli che cerchi: $ruoli_cercati\n"
             . "- Tuo livello: $livello_personale / 5\n"
             . "- Livello minimo richiesto ai partecipanti: $livello_richiesto / 5\n"
             . "- N° partecipanti oltre te: $num_giocatori\n"
             . "- Note: $note_extra\n\n"
             . "Puoi annullare il tuo evento da questo link personale:\n"
             . $gestione_url . "\n\n"
             . "Grazie per aver usato Find Player!";
        // Se ci sono stati eventi ripetuti, aggiungiamo il riepilogo qui sotto
        if (!empty($riepilogo_eventi)) {
            $msg .= $riepilogo_eventi;
        }

        // UNA SOLA mail all'organizzatore (con anche il token)
        wp_mail($email, 'Evento creato - Find Player', $msg, $headers);

        // Email all'amministratore principale
        $msg_admin = "Nuovo evento creato su Find Player:\n\n"
                   . $msg . "\n\nOrganizzatore: ($creatore_nome) - $email - $telefono";
        wp_mail('findplayeritaly@gmail.com', 'Copia - Nuovo evento Find Player', $msg_admin, $headers);

        echo '<div class="notice notice-success" style="color:green;">✅ Ben venuto in Find Player! Evento creato correttamente, controlla la mail con il riepilogo, potresti essere contattato dal 3805140047.</div>';
        return ob_get_clean();
    }

    // FORM CREAZIONE EVENTO con campi affiancati
    ?>
<style>
/* -------------------------------------------------
   Tooltip HELP Find Player
--------------------------------------------------*/
.fpfp-help {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    margin-left: 6px;
    border-radius: 50%;
    border: 1px solid #999;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    position: relative;
    line-height: 1;
    background: #f5f5f5;
    color: #555;
}

.fpfp-help::before {
    content: "i";
}

/* Box del tooltip */
.fpfp-help-tooltip {
    position: absolute;
    left: 50%;
    top: 130%;
    transform: translateX(-50%);
    background: #111;
    color: #fff;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.4;
	width: 250px;
    max-width: none;     
    white-space: normal;   /* va a capo in modo normale */
    box-shadow: 0 8px 20px rgba(0,0,0,0.25);
    z-index: 999;
    display: none;
}

/* Freccetta sotto il tooltip */
.fpfp-help-tooltip::after {
    content: "";
    position: absolute;
    top: -6px;
    left: 50%;
    transform: translateX(-50%);
    border-width: 0 6px 6px 6px;
    border-style: solid;
    border-color: transparent transparent #111 transparent;
}

/* Mostra il tooltip al passaggio */
.fpfp-help:hover .fpfp-help-tooltip {
    display: block;
}
</style>

    <form method="post" id="find-player-form"> 
<h3>Crea una nuova attività</h3>
		<!-- 1) DISCIPLINA (riga singola full width) -->
<div class="fp-row fp-full">
    <div class="fp-field">
<label>*Attività</label>
		<!--inizio tooltip -->
			<span class="fpfp-help">
            <span class="fpfp-help-tooltip">
			Seleziona un attività tra quelle presenti, se non la trovi metti altro e poi in descrizioni indica il nome, la troverai aggiunta prestissimo tra quelle selezionabili
            </span>
    </span>
<!--fine tooltip -->
        <select name="disciplina" required style="width:100%;">
            <option value="">Seleziona attività...</option>
<option value="Aikido">Aikido</option>
<option value="Arti Marziali">Arti Marziali</option>
<option value="Atletica">Atletica</option>
<option value="Ballo">Ballo</option>
<option value="Balli di Gruppo">Balli di Gruppo</option>
<option value="Baseball">Baseball</option>
<option value="Basket">Basket</option>
<option value="Beach Volley">Beach Volley</option>
<option value="Body Building">Body Building</option>
<option value="Boxe">Boxe</option>
<option value="Calcetto">Calcetto</option>
<option value="Calcio">Calcio</option>
<option value="Canoa">Canoa</option>
<option value="Ciclismo">Ciclismo</option>
<option value="Corsa Libera">Corsa Libera</option>
<option value="Crossfit">Crossfit</option>
<option value="Danza">Danza</option>
<option value="Difesa Personale">Difesa Personale</option>
<option value="Enduro">Enduro</option>
<option value="Escursionismo">Escursionismo</option>
<option value="Fitness">Fitness</option>
<option value="Ginnastica Libera">Ginnastica Libera</option>
<option value="Giochi da Tavolo">Giochi da Tavolo</option>
<option value="Giochi di Ruolo">Giochi di Ruolo</option>
<option value="Hiking">Hiking</option>
<option value="Hip Hop">Hip Hop</option>
<option value="Immersione">Immersione</option>
<option value="Jeet Kune Do">Jeet Kune Do</option>
<option value="Judo">Judo</option>
<option value="Karate">Karate</option>
<option value="Kayak">Kayak</option>
<option value="Kickboxing">Kickboxing</option>
<option value="Lasertag">Lasertag</option>
<option value="M.M.A.">M.M.A.</option>
<option value="Motocross">Motocross</option>
<option value="Mountain Bike">Mountain Bike</option>
<option value="Mototurismo">Mototurismo</option>
<option value="Muay Thai">Muay Thai</option>
<option value="Nordic Walking">Nordic Walking</option>
<option value="Nuoto">Nuoto</option>
<option value="Orienteering">Orienteering</option>
<option value="Paddle">Paddle</option>
<option value="Pallanuoto">Pallanuoto</option>
<option value="Pallavolo">Pallavolo</option>
<option value="Paintball">Paintball</option>
<option value="Ping Pong">Ping Pong</option>
<option value="Pugilato">Pugilato</option>
<option value="Running">Running</option>
<option value="Rugby">Rugby</option>
<option value="Skateboard">Skateboard</option>
<option value="Snorkeling">Snorkeling</option>
<option value="Softair">Softair</option>
<option value="Sparatutto-Online">Sparatutto-Online</option>
<option value="Sup">Sup</option>
<option value="Trekking">Trekking</option>
<option value="Volleyball">Volleyball</option>
<option value="Wing Chun">Wing Chun</option>
<option value="Yoga">Yoga</option>
<option value="Zumba">Zumba</option>
            <option value="Altro">Altro... specifica in note</option>
        </select>
    </div>
</div>
		
<!-- 2) Città + Indirizzo + Tipo -->
<div class="fp-row fp-row-3">
    <div class="fp-field">
        <label>*Città</label>
        <input type="text" name="citta" required>
    </div>
    <div class="fp-field">
        <label>*Indirizzo del ritrovo</label>
        <input type="text" name="luogo_descrizione" required>
    </div>
    <div class="fp-field">
        <label>Caratteristiche luogo</label>
<!--inizio tooltip -->
			<span class="fpfp-help">
            <span class="fpfp-help-tooltip">
                Quali caratteristiche ha il posto dove si svolge l'attvità (es, è all'aperto, campo in erba, al coperto, ecc) 
            </span>
    </span>
<!--fine tooltip -->
		<textarea name="tipo_campo"></textarea>
    </div>
</div>


<!-- 3) Ruolo + Livello -->
<div class="fp-row">
    <div class="fp-field">
        <label>Il Tuo ruolo (es.portiere)</label>
<!--inizio tooltip -->
			<span class="fpfp-help">
            <span class="fpfp-help-tooltip">
                In che ruolo partecipi all'attività (es.Portiere, Attaccante, ecc) 
            </span>
    </span>
<!--fine tooltip -->
        <input type="text" name="ruolo_personale">
    </div>
    <div class="fp-field">
        <label>Il Tuo livello</label>
				    <br>
        <select name="livello_personale">
            <option value="1">1 - Base</option>
            <option value="2">2 - Principiante</option>
            <option value="3" selected>3 - Intermedio</option>
            <option value="4">4 - Avanzato</option>
            <option value="5">5 - Agonistico</option>
            <option value="6">6 - Istruttore</option>
        </select>
    </div>
</div>

<!-- 4) Ruoli, livello minimo, N° giocatori -->
<div class="fp-row fp-row-3">
    <div class="fp-field">
        <label>Ruoli dei partecipanti</label>
        <textarea name="ruoli_cercati"></textarea>
    </div>
    <div class="fp-field">
        <label>Loro Livello minimo</label>
        <select name="livello_richiesto">
            <option value="1">1 - Va bene chiunque</option>
            <option value="2">2 - Principiante</option>
            <option value="3" selected>3 - Intermedio</option>
            <option value="4">4 - Avanzato</option>
            <option value="5">5 - Agonistico</option>
            <option value="6">6 - Istruttore</option>
        </select>
    </div>
    <div class="fp-field">
        <label>*N° partecipanti</label>
		<!--inizio tooltip -->
			<span class="fpfp-help">
            <span class="fpfp-help-tooltip">
                Ovviamente si intende oltre Te quante persone cerchi 
            </span>
    </span>
<!--fine tooltip -->
		    <br>
        <input type="number" name="num_giocatori" min="0" style="max-width:100px;">
    </div>
</div>


<!-- 5) Note -->
<div class="fp-row fp-full">
    <div class="fp-field">
        <label>Note / informazioni</label>
<!--inizio tooltip -->
			<span class="fpfp-help">
            <span class="fpfp-help-tooltip">
        Inserisci tutte le info che pensi siano utili per descrivere l'attività.<br><br>
		NON CHIEDERE DENARO ONLINE IN ALCUN MODO (pena bannato a vita)!<br><br>
		Le quote vengono versate sul posto e dietro regolare ricevuta.
            </span>
    </span>
<!--fine tooltip -->
        <textarea name="note_extra"></textarea>
    </div>
</div>
<!-- 6) Data + Orario -->
<div class="fp-row fp-row-3">
    <div class="fp-field">
        <label>*Data attività</label>
        <input type="date" name="data_evento" required>
    </div>
    <div class="fp-field">
        <label>*Orario</label>
<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        Indica l'ora di inizio e di fine nel formato hh:mm - hh:mm (es. 18:10)  
        </span>
    </span>
<!--fine tooltip -->
        <input type="text" name="orario" placeholder="es. 18:00" required>
    </div>
<!-- 6b) Ripetizione evento -->
    <div class="fp-field">
        <label>*Ogni quanto si ripete?</label>
<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        Se prevedi che ci siano altre attività uguali, <br>
	    puoi indicare i giorni della settimana e il numero <br>
		di settimane. ATTENZIONE! Se indichi 1 settimana <br>
	    è inteso che parli della prima indicata, 2 vuol dire <br>
	    la prima più la seconda ecc.
        </span>
    </span>
<!--fine tooltip -->
        <select name="ripetizione_tipo" id="ripetizione_tipo">
            <option value="singolo" selected>Evento singolo</option>
            <option value="giorni_settimana">Giorni specifici</option>
        </select>
    </div>

    <div class="fp-field" id="settimane_box" style="display:none;">
        <label>Per quante settimane?</label>
<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        Se selezioni 1 allora non si ripete nelle prossime settimane
        </span>
    </span>
<!--fine tooltip -->
        <input type="number" name="ripetizione_settimane" min="1" value="1">
    </div>
</div>
    <div class="fp-field" id="giorni_settimana_box" style="display:none;">
    <div class="fp-field">
        <label>Seleziona i giorni:</label>
		    </div>
        <label><input type="checkbox" name="giorni[]" value="1"> Lun</label>
        <label><input type="checkbox" name="giorni[]" value="2"> Mar</label>
        <label><input type="checkbox" name="giorni[]" value="3"> Mer</label>
        <label><input type="checkbox" name="giorni[]" value="4"> Gio</label>
        <label><input type="checkbox" name="giorni[]" value="5"> Ven</label>
        <label><input type="checkbox" name="giorni[]" value="6"> Sab</label>
        <label><input type="checkbox" name="giorni[]" value="0"> Dom</label>
    </div>
<script>
document.getElementById("ripetizione_tipo").addEventListener("change", function () {
    const tipo = this.value;
    document.getElementById("giorni_settimana_box").style.display =
        (tipo === "giorni_settimana") ? "block" : "none";

    document.getElementById("settimane_box").style.display =
        (tipo === "giorni_settimana") ? "block" : "none";
});
</script>
<hr>

<h4>I tuoi dati</h4>

<!-- 7) Nick + Nome -->
<div class="fp-row">
    <div class="fp-field">
        <label>*Nickname (come appari agli altri)</label>
		<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        Questo sarà il solo nome visibile online <br>
	    e comunicato agli utenti
        </span>
    </span>
<!--fine tooltip -->
<input type="text" name="nickname_creatore"
       value="<?php echo esc_attr($nickname_creatore ?? ''); ?>"
       <?php echo is_user_logged_in() ? 'readonly' : ''; ?>
       required>
    </div>
    <div class="fp-field">
        <label>*Nome e cognome (vero)</label>
		<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        Inserisci i dati veri, verranno tenuti in archivio solo per archivio
        </span>
    </span>
<!--fine tooltip -->
        <input type="text" name="nome_cognome"
       value="<?php echo esc_attr($nome_cognome ?? ''); ?>"
       <?php echo is_user_logged_in() ? 'readonly' : ''; ?>
       required>

    </div>
</div>

<!-- 8) Email + Telefono -->
<div class="fp-row">
    <div class="fp-field">
        <label>*Email</label>
		<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        Inserisci la mail corretta o non potrai autorizzare e gestire l'evento<br>
controlla anche in spam e nel caso inseriscila tra le attendibili.
        </span>
    </span>
<!--fine tooltip -->
<input type="text" name="email"
       value="<?php echo esc_attr($email ?? ''); ?>"
       <?php echo is_user_logged_in() ? 'readonly' : ''; ?>
       required>
        
    </div>
    <div class="fp-field">
        <label>*Telefono</label>
		<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        E' fondamentale per fini di cotrollo e verifica<br>
	    oltre che per la tua sicurezza e quella altrui.
        </span>
    </span>
<!--fine tooltip -->
<input type="text" name="telefono"
       value="<?php echo esc_attr($telefono ?? ''); ?>"
       <?php echo is_user_logged_in() ? 'readonly' : ''; ?>
       required>
    </div>
</div>

		        <?php
	
        // Domanda anti-spam
        $domande_antispam = [
            ['q' => 'Quanto fa 8 + 80?', 'a' => 88],
            ['q' => 'Quanto fa 10 + 90?', 'a' => 100],
            ['q' => 'Scrivi il numero 55 al contrario', 'a' => 55],
            ['q' => 'Quanto fa 50 diviso 10?', 'a' => 5],
            ['q' => 'Qual è il risultato di 40 + 4?', 'a' => 44],
            ['q' => 'Se oggi è venerdì, che giorno viene dopo?', 'a' => 'Sabato'],
        ];
        $scelta = $domande_antispam[array_rand($domande_antispam)];
        $domanda_antispam = $scelta['q'];
        $risposta_attesa = strtolower(trim($scelta['a']));
        ?>
        <input type="hidden" name="risposta_corretta" value="<?php echo esc_attr($risposta_attesa); ?>">
        <label><?php echo esc_html($domanda_antispam); ?> (anti-spam)<br>
            <input type="text" name="antispam" required>
        </label><br><br>

        <label>
            <input type="checkbox" name="privacy" value="1" required>
            *Inviando questo modulo, acconsento al trattamento dei miei dati personali secondo quanto previsto dall’ 
            <a href="https://www.findplayer.it/privacy-policy-cookie-policy/" target="_blank" rel="noopener noreferrer">
            informativa privacy</a>.<br>
			<!--inizio tooltip -->
		<span class="fpfp-help">
        <span class="fpfp-help-tooltip">
        I dati visibili ai partecipanti sono: nick-name, <br>
        mentre la mail solo al creatore evento il resto è privato
        </span>
    </span>
<!--fine tooltip -->
        </label><br><br>

        <input type="submit" name="fpfp_invio_evento" value="CREA ATTIVITA'">
    </form>
    <?php
    return ob_get_clean();
});

/* -------------------------------------------------------------------------- */
/* SHORTCODE: CALENDARIO / ELENCO ALLENAMENTI                                 */
/* -------------------------------------------------------------------------- */

add_shortcode('find_player_calendar', function () {
    ob_start();

    // Filtri da GET
    $disciplina = sanitize_text_field($_GET['fp_disciplina'] ?? '');
    $citta      = sanitize_text_field($_GET['fp_citta'] ?? '');
    $posti_min  = intval($_GET['fp_posti_min'] ?? 0);
    $data_filt  = sanitize_text_field($_GET['fp_data'] ?? '');

    // Ordinamento
    $orderby  = sanitize_text_field($_GET['fp_orderby'] ?? 'data_evento');
    $orderdir = strtolower($_GET['fp_orderdir'] ?? 'asc');

    $allowed_orderby = ['data_evento', 'disciplina'];
	$allowed_orderby = ['data_evento', 'disciplina', 'citta'];
    if (!in_array($orderby, $allowed_orderby, true)) {
        $orderby = 'data_evento';
    }
    if (!in_array($orderdir, ['asc', 'desc'], true)) {
        $orderdir = 'asc';
    }

    ?>
<style>
#fp_calendar_wrapper {
    max-height: 400px;     /* mostra 5–6 eventi */
    overflow-y: auto;      /* scroll verticale */
    overflow-x: auto;      /* scroll orizzontale */
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 5px;
    background: #fff;
}

#fp_calendar_wrapper table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;      /* evita che la tabella si stringa troppo */
}
</style>
<style>
	/* 🔥 FIX MOBILE: impedisce overflow orizzontale */
* {
    max-width: 100%;
    box-sizing: border-box;
}

/* Contenitore principale del calendario */
.findplayer-calendar-wrapper {
    width: 100%;
    overflow-x: hidden !important;
}

/* Barra filtri */
.fp-filter-bar {
    width: 100%;
    overflow-x: auto;
    flex-wrap: nowrap;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
}

/* Ogni singolo campo filtro */
.fp-filter-item {
    min-width: 140px;
    white-space: normal;
}

/* Gli input non devono allargarsi oltre lo schermo */
.fp-filter-item input {
    width: 100%;
    max-width: 100%;
}

/* Pulsante “Applica filtri” */
.fp-filter-btn {
    white-space: nowrap;
}

/* Lista eventi */
.fp-calendar-event,
.fp-calendar-item {
    width: 100% !important;
    max-width: 100% !important;
    overflow: hidden;
}

/* Prevenzione overflow su eventuali testi lunghi */
.fp-calendar-event *,
.fp-filter-bar * {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.fp-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
    background: #ffffff;
    border-radius: 12px;
    padding: 12px 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.07);
    border: 1px solid #e5e5e5;
    margin-bottom: 18px;
}

.fp-filter-item {
    display: flex;
    flex-direction: column;
}

.fp-filter-item label {
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 2px;
}

.fp-filter-item input {
    padding: 6px 8px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 13px;
    min-width: 140px;
}

.fp-filter-btn {
    padding: 7px 14px;
    background: #1e88e5;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 3px 10px rgba(30,136,229,0.3);
}

.fp-filter-btn:hover {
    background: #156bbd;
}

/* Mobile */
@media (max-width: 700px) {
    .fp-filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .fp-filter-item input {
        width: 100%;
    }
    .fp-filter-btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<form method="get" class="fp-filter-bar">

    <div class="fp-filter-item">
        <label>Disciplina</label>
        <input type="text" name="fp_disciplina"
               value="<?php echo esc_attr($disciplina); ?>"
               placeholder="Es. Calcio">
    </div>

    <div class="fp-filter-item">
        <label>Città</label>
        <input type="text" name="fp_citta"
               value="<?php echo esc_attr($citta); ?>"
               placeholder="Es. Sassari">
    </div>

    <div class="fp-filter-item">
        <label>Posti liberi min.</label>
        <input type="number" name="fp_posti_min" min="0"
               value="<?php echo $posti_min ? intval($posti_min) : ''; ?>">
    </div>

    <div class="fp-filter-item">
        <label>Data</label>
        <input type="date" name="fp_data"
               value="<?php echo esc_attr($data_filt); ?>">
    </div>

    <!-- Hidden ordinamento -->
    <input type="hidden" name="fp_orderby" value="<?php echo esc_attr($orderby); ?>">
    <input type="hidden" name="fp_orderdir" value="<?php echo esc_attr($orderdir); ?>">

    <button type="submit" class="fp-filter-btn">Applica filtri</button>

</form>

    <?php

$params = [
    'select' => '*',
    'order'  => $orderby . '.' . $orderdir,
];

$params['or'] = '(evento_confermato.eq.true,evento_confermato.is.null)';

    if ($disciplina) {
        // ilike.*termine*
        $params['disciplina'] = 'ilike.*' . $disciplina . '*';
    }
    if ($citta) {
        $params['citta'] = 'ilike.*' . $citta . '*';
    }
    if ($posti_min > 0) {
        $params['posti_liberi'] = 'gte.' . $posti_min;
    }
    if ($data_filt) {
        $params['data_evento'] = 'eq.' . $data_filt;
    }

    $url = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?' . http_build_query($params);

    $response = wp_remote_get($url, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        echo '<p style="color:red;">❌ Impossibile caricare il calendario in questo momento.</p>';
        return ob_get_clean();
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        echo '<p style="color:red;">❌ Errore lettura dati calendario (codice ' . intval($code) . ').</p>';
        return ob_get_clean();
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($body) || empty($body)) {
        echo '<p>📭 Nessun evento trovato con questi filtri.</p>';
        return ob_get_clean();
    }

    // Ordinamento freccette
$dataNextDir = ($orderby === 'data_evento' && $orderdir === 'asc') ? 'desc' : 'asc';
$discNextDir = ($orderby === 'disciplina' && $orderdir === 'asc') ? 'desc' : 'asc';
$cittaNextDir = ($orderby === 'citta' && $orderdir === 'asc') ? 'desc' : 'asc';

$dataArrow = ($orderby === 'data_evento') ? ($orderdir === 'asc' ? '🔼' : '🔽') : '↕';
$discArrow = ($orderby === 'disciplina') ? ($orderdir === 'asc' ? '🔼' : '🔽') : '↕';
$cittaArrow = ($orderby === 'citta') ? ($orderdir === 'asc' ? '🔼' : '🔽') : '↕';

    $dataOrderUrl = esc_url(add_query_arg([
        'fp_orderby' => 'data_evento',
        'fp_orderdir'=> $dataNextDir,
    ]));

    $discOrderUrl = esc_url(add_query_arg([
        'fp_orderby' => 'disciplina',
        'fp_orderdir'=> $discNextDir,
    ]));

echo '<div id="fp_calendar_wrapper">';
echo '<table class="fp_calendar_table" style="font-size:13px;">';
echo '<thead><tr style="background:#f0f0f0;">'
   . '<th style="border:1px solid #ddd;padding:4px;">'
   . '<a href="' . $dataOrderUrl . '" style="text-decoration:none;">Data ' . $dataArrow . '</a></th>'
   . '<th style="border:1px solid #ddd;padding:4px;">Ora</th>'
   . '<th style="border:1px solid #ddd;padding:4px;">'
   . '<a href="' . $discOrderUrl . '" style="text-decoration:none;">Disciplina ' . $discArrow . '</a></th>'
   . '<th style="border:1px solid #ddd;padding:4px;">'
   . '<a href="' . esc_url(add_query_arg([
        'fp_orderby' => 'citta',
        'fp_orderdir'=> $cittaNextDir,
    ])) . '" style="text-decoration:none;">Città ' . $cittaArrow . '</a></th>'
   . '<th style="border:1px solid #ddd;padding:4px;">Luogo_descrizione</th>'
   . '<th style="border:1px solid #ddd;padding:4px;">Posti liberi</th>'
   . '<th style="border:1px solid #ddd;padding:4px;">Organizzatore</th>'
   . '<th style="border:1px solid #ddd;padding:4px;">Dettagli</th>'
   . '</tr></thead><tbody>';

    foreach ($body as $evento) {
        $id_ev    = isset($evento['id']) ? intval($evento['id']) : 0;
        $data_ev  = esc_html($evento['data_evento'] ?? '');
        $ora_ev   = esc_html($evento['orario'] ?? '');
        $disc     = esc_html($evento['disciplina'] ?? '');
        $citta_ev = esc_html($evento['citta'] ?? '');
        $luogo_ev = esc_html($evento['luogo_descrizione'] ?? '');
        $posti    = isset($evento['posti_liberi']) ? intval($evento['posti_liberi']) : 0;
        $nick     = esc_html($evento['nickname_creatore'] ?? ($evento['creatore_nome'] ?? 'n.d.'));

        // Trova la pagina evento collegata (CPT) tramite meta
        $link_dettagli = '';
        if ($id_ev) {
            $posts = get_posts(array(
                'post_type'      => 'findplayer_event',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_findplayer_allenamento_id',
                        'value' => $id_ev,
                    )
                ),
            ));
            if (!empty($posts)) {
                $link_dettagli = get_permalink($posts[0]);
            }
        }

    // Calcola se evento è passato
    $is_passato = false;
    if (!empty($evento['data_evento'])) {
        $data_evento_ts = strtotime($evento['data_evento']);
        $oggi = strtotime('today');
        if ($data_evento_ts < $oggi) {
            $is_passato = true;
        }
    }

    // Colore riga
    $stile_riga = $is_passato
        ? 'background:#f8d7da;color:#721c24;text-decoration:line-through;'
        : '';

    echo '<tr style="' . $stile_riga . '">';
    echo '<td style="border:1px solid #ddd;padding:4px;">' . $data_ev . '</td>';
    echo '<td style="border:1px solid #ddd;padding:4px;">' . $ora_ev . '</td>';
    echo '<td style="border:1px solid #ddd;padding:4px;">' . $disc . '</td>';
    echo '<td style="border:1px solid #ddd;padding:4px;">' . $citta_ev . '</td>';
    echo '<td style="border:1px solid #ddd;padding:4px;">' . $luogo_ev . '</td>';
    echo '<td style="border:1px solid #ddd;padding:4px;text-align:center;">' . $posti . '</td>';
    echo '<td style="border:1px solid #ddd;padding:4px;">' . $nick . '</td>';

    // Colonna "Dettagli" — se evento passato, scritta diversa
    echo '<td style="border:1px solid #ddd;padding:4px;text-align:center;">';
    if ($is_passato) {
        echo '<span style="color:#721c24;font-weight:bold;">Evento concluso</span>';
    } elseif ($link_dettagli) {
        echo '<a href="' . esc_url($link_dettagli) . '">Dettagli</a>';
    } else {
        echo '-';
    }
    echo '</td>';
    echo '</tr>';
} // ← fine foreach

    echo '</tbody></table>';
echo '</table></div>';

    return ob_get_clean();
});
/* -------------------------------------------------------------------------- */
/* SHORTCODE MAPPA EVENTI FIND PLAYER (LEAFLET)                               */
/* -------------------------------------------------------------------------- */

add_shortcode('find_player_map', function () {
    ob_start(); ?>

    <div id="findplayer-map"
         style="width:100%; height:600px; margin:20px 0; border-radius:10px;"></div>

    <style>
    #findplayer-map { min-height: 600px; }

    .leaflet-popup-content-wrapper {
        background: #fff;
        border-radius: 10px;
        width: 260px;
        max-width: 260px;
    }
    .leaflet-popup-content a {
        color: #1e88e5;
        font-weight: 600;
        text-decoration: none;
    }
    .leaflet-popup-content a:hover {
        text-decoration: underline;
    }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", async function () {

        const mapEl = document.getElementById('findplayer-map');
        if (!mapEl) return;

        const map = L.map(mapEl).setView([40.12, 9.01], 7);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        const bounds = [];
        const response = await fetch(
        "<?php echo FPFP_SUPABASE_URL; ?>/rest/v1/allenamenti?select=id,disciplina,data_evento,citta,lat,lng,wp_post_id&or=(evento_confermato.eq.true,evento_confermato.is.null)",
          {
        headers: {
            apikey: "<?php echo FPFP_SUPABASE_API_KEY; ?>",
            Authorization: "Bearer <?php echo FPFP_SUPABASE_API_KEY; ?>"
        }
    }
);
        const eventi = await response.json();

        // Raggruppamento per coordinate
        const eventiPerPosizione = {};

        eventi.forEach(ev => {
            if (!ev.lat || !ev.lng) return;

            const key = ev.lat + ',' + ev.lng;
            if (!eventiPerPosizione[key]) {
                eventiPerPosizione[key] = {
                    lat: ev.lat,
                    lng: ev.lng,
                    eventi: []
                };
            }
            eventiPerPosizione[key].eventi.push(ev);
        });

        Object.values(eventiPerPosizione).forEach(group => {

            const marker = L.marker([group.lat, group.lng]).addTo(map);

            let html = '<strong>Eventi in questa posizione</strong><br><br>';

            group.eventi.forEach(ev => {

const url = ev.wp_post_id
    ? "<?php echo home_url('/?p='); ?>" + ev.wp_post_id
    : "#";
                html += `
                    📅 <strong>${ev.data_evento}</strong><br>
                    🏅 ${ev.disciplina}<br>
                    <a href="${url}">➡️ Vai all’evento</a><br><br>
                `;
            });

            marker.bindPopup(html);
            bounds.push([group.lat, group.lng]);
        });

        if (bounds.length) {
map.fitBounds(bounds, {
    padding: [40, 40],
    maxZoom: 16
});
        } else {
            map.setView([40.12, 9.01], 7);
            L.popup()
                .setLatLng([40.12, 9.01])
                .setContent("Nessun evento con posizione disponibile")
                .openOn(map);
        }

    });
    </script>

    <?php
    return ob_get_clean();
});


/* -------------------------------------------------------------------------- */
/* SHORTCODE: GESTIONE EVENTO (CANCELLAZIONE / DETTAGLI)                      */
/* -------------------------------------------------------------------------- */

add_shortcode('find_player_gestisci_evento', function () {

    if (empty($_GET['token']) || empty($_GET['id'])) {
        return '<p style="color:red;">⚠️ Parametri mancanti.</p>';
    }

    $token = sanitize_text_field($_GET['token']);
    $id    = intval($_GET['id']);
    $response = wp_remote_request(
    FP_SUPABASE_URL . '/rest/v1/utenti?token=eq.' . urlencode($token),
    [
        'method'  => 'PATCH',
    $headers = [
            'apikey' => FP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=minimal'
        ],
        'body' => json_encode([
            'status'       => 'approved',
            'token'        => null,
            'approved_at'  => current_time('mysql')
        ])
    ]
);

    // 1) Recupera evento da Supabase
    $url = FPFP_SUPABASE_URL . "/rest/v1/allenamenti?id=eq.$id&select=*";

    $resp = wp_remote_get($url, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY
        ]
    ]);

    if (is_wp_error($resp)) {
        return "<p style='color:red;'>Errore connessione database.</p>";
    }

    $data = json_decode(wp_remote_retrieve_body($resp), true);

    if (empty($data)) {
        return "<p style='color:red;'>Evento non trovato.</p>";
    }

    $evento = $data[0];

    // 2) Verifica token
    if ($evento['token_gestore'] !== $token) {
        return "<p style='color:red;'>⚠️ Token non valido.</p>";
    }

// 3) Azione cancella
if (isset($_GET['delete']) && $_GET['delete'] === 'yes') {

    /* ------------------------------------------------------------
       1️⃣ Recupera tutti i partecipanti dell'evento
    ------------------------------------------------------------ */
    $url_partecipanti = FPFP_SUPABASE_URL . "/rest/v1/prenotazioni?allenamento_id=eq.$id&select=email,nickname,nome_partecipante";
    $resp_part = wp_remote_get($url_partecipanti, [
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY
        ]
    ]);

    $partecipanti = [];
    if (!is_wp_error($resp_part)) {
        $partecipanti = json_decode(wp_remote_retrieve_body($resp_part), true);
        if (!is_array($partecipanti)) $partecipanti = [];
    }
    
    /* ------------------------------------------------------------
       2️⃣ Elimina l'evento
    ------------------------------------------------------------ */
    $del_url = FPFP_SUPABASE_URL . "/rest/v1/allenamenti?id=eq.$id";
    $del = wp_remote_request($del_url, [
        'method'  => 'DELETE',
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
    ]);

    /* ------------------------------------------------------------
       3️⃣ Invia mail ai partecipanti
    ------------------------------------------------------------ */

    if (!empty($partecipanti)) {

        $headers_partecipanti = [
            "Content-Type: text/plain; charset=UTF-8",
            "From: ASD Oltrecity <no-reply@oltrecity.com>"
        ];

        foreach ($partecipanti as $p) {

            $email_p = sanitize_email($p['email']);
            $nick_p  = sanitize_text_field($p['nickname'] ?? $p['nome_partecipante']);

            if (!$email_p) continue;

            $msg_p = "Ciao {$nick_p},\n\n"
                   . "Ti informiamo che l'attività sportiva a cui avevi richiesto di partecipare è stata ANNULLATA dall'organizzatore.\n\n"
                   . "Dettagli evento:\n"
                   . "- Disciplina: {$evento['disciplina']}\n"
                   . "- Data: {$evento['data_evento']}\n"
                   . "- Orario: {$evento['orario']}\n"
                   . "- Città: {$evento['citta']}\n\n"
                   . "Ti invitiamo a consultare nuovamente il calendario per trovare altri eventi disponibili.\n\n"
                   . "Grazie per aver usato Find Player!\n";

            wp_mail($email_p, "Attività annullata – Find Player", $msg_p, $headers_partecipanti);
        }
    }

    /* ------------------------------------------------------------
       4️⃣ Conferma all’organizzatore
    ------------------------------------------------------------ */
    return "<p style='color:green;font-weight:bold;'>✅ Evento eliminato correttamente. Tutti i partecipanti sono stati avvisati.</p>";
}


    // 4) Mostra dettagli evento + pulsante elimina
    ob_start();
    ?>

    <h2>Gestione Evento</h2>

    <p><strong>ID Evento:</strong> <?php echo $id; ?></p>
    <p><strong>Disciplina:</strong> <?php echo esc_html($evento['disciplina']); ?></p>
    <p><strong>Data:</strong> <?php echo esc_html($evento['data_evento']); ?></p>
    <p><strong>Orario:</strong> <?php echo esc_html($evento['orario']); ?></p>
    <p><strong>Città:</strong> <?php echo esc_html($evento['citta']); ?></p>

    <p><strong>Luogo:</strong> <?php echo esc_html($evento['luogo_descrizione']); ?></p>

    <br>

    <a href="?token=<?php echo esc_attr($token); ?>&id=<?php echo esc_attr($id); ?>&delete=yes"
       onclick="return confirm('Sei sicuro di voler eliminare questo evento?');"
       style="display:inline-block;padding:10px 15px;background:#b71c1c;color:white;border-radius:4px;text-decoration:none;font-weight:bold;">
       🗑 Elimina questo evento
    </a>

    <?php
    return ob_get_clean();
});



/* -------------------------------------------------------------------------- */
/* SHORTCODE: DETTAGLIO EVENTO + MAPPA + PRENOTAZIONE                         */
/* -------------------------------------------------------------------------- */

add_shortcode('find_player_event_details', function ($atts) {
    $atts = shortcode_atts(array(
        'id' => 0, // allenamento_id su Supabase
    ), $atts, 'find_player_event_details');

$allenamento_id = intval(trim($atts['id'] ?? 0));
if ($allenamento_id <= 0) {
    echo '<p style="color:red;">⚠️ Errore nel caricamento evento (ID mancante).</p>';
    return ob_get_clean();
}

    ob_start();

    /* ----- GESTIONE INVIO PRENOTAZIONE ----- */
    if (!empty($_POST['fpfp_partecipa_evento']) && intval($_POST['fpfp_allenamento_id'] ?? 0) === $allenamento_id) {

        $nick_join  = sanitize_text_field($_POST['nick_join'] ?? '');
		$nome_join   = sanitize_text_field($_POST['nome_partecipante'] ?? '');
        $email_join = sanitize_email($_POST['email_join'] ?? '');
		$tel_join    = sanitize_text_field($_POST['tel_join'] ?? '');
// 🔒 BLOCCO USO DATI GIOCATORI REGISTRATI (PARTECIPANTE)
if (!is_user_logged_in()) {

    if (fp_dato_appartiene_a_giocatore_registrato(
        $nick_join,
        $email_join,
        $tel_join
    )) {
        echo '<p style="color:red;font-weight:bold;">
        ❌ Non puoi utilizzare nickname, email o telefono
        appartenenti a un giocatore già registrato su Find Player.
        </p>';
        return ob_get_clean();
    }
}
		
		// 🔒 BLOCCO USO DATI DI GIOCATORI REGISTRATI (SOLO GUEST)
if (!is_user_logged_in()) {

    if (fp_dato_appartiene_a_giocatore_registrato(
        $nick_join,
        $email_join,
        $tel_join
    )) {
        echo '<p style="color:red;font-weight:bold;">
        ❌ Non puoi utilizzare nickname, email o telefono
        appartenenti a un giocatore già registrato su Find Player.
        </p>';
        return ob_get_clean();
    }
}
        $livello_join = intval($_POST['livello_join'] ?? 3);
        $msg_join   = sanitize_textarea_field($_POST['messaggio_join'] ?? '');

        // piccolo anti-spam anche qui
        $risp_utente   = strtolower(trim($_POST['antispam_join'] ?? ''));
        $risp_corr     = strtolower(trim($_POST['risposta_corr_join'] ?? ''));

        if ($risp_utente !== $risp_corr) {
            echo '<p style="color:red;">⚠️ Risposta anti-spam errata nella richiesta di partecipazione.</p>';
} elseif (empty($nick_join) || empty($nome_join) || empty($email_join)) {
    echo '<p style="color:red;">⚠️ Nickname, nome vero ed email sono obbligatori per partecipare.</p>';
} else {
            // Recupera dati evento per sapere email dell'organizzatore
            $url_ev = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allenamento_id . '&select=*';
            $resp_ev = wp_remote_get($url_ev, array(
                'headers' => array(
                    'apikey'        => FPFP_SUPABASE_API_KEY,
                    'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                ),
                'timeout' => 20,
            ));

            if (is_wp_error($resp_ev) || wp_remote_retrieve_response_code($resp_ev) !== 200) {
                echo '<p style="color:red;">❌ Errore nel recupero dell\'evento. Riprova più tardi.</p>';
            } else {
                $ev_rows = json_decode(wp_remote_retrieve_body($resp_ev), true);
                if (!is_array($ev_rows) || empty($ev_rows)) {
                    echo '<p style="color:red;">❌ Evento non trovato.</p>';
                } else {
                    $evento = $ev_rows[0];
                    $disciplina   = sanitize_text_field($evento['disciplina'] ?? '');
                    $data_evento  = sanitize_text_field($evento['data_evento'] ?? '');
                    $orario       = sanitize_text_field($evento['orario'] ?? '');
                    $citta        = sanitize_text_field($evento['citta'] ?? '');
                    $posti_liberi = isset($evento['posti_liberi']) ? intval($evento['posti_liberi']) : 0;
                    $email_org    = sanitize_email($evento['creatore_email'] ?? '');
                    $nick_org     = sanitize_text_field($evento['nickname_creatore'] ?? $evento['creatore_nome'] ?? '');

                    if ($posti_liberi <= 0) {
                        echo '<p style="color:red;">❌ L\'evento è già al completo. Non ci sono più posti liberi.</p>';
                    } elseif (empty($email_org)) {
                        echo '<p style="color:red;">❌ Non è stato possibile contattare l\'organizzatore. Email mancante.</p>';
                    } else {
						
/* ------------------------------------------------------------------
   🔎 CONTROLLO DOPPIA PRENOTAZIONE (email o telefono già presenti)
------------------------------------------------------------------- */

$check_url = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni'
    . '?select=id'
    . '&allenamento_id=eq.' . $allenamento_id
    . '&or=('
    . 'email.eq.' . rawurlencode($email_join) . ','
    . 'telefono.eq.' . rawurlencode($tel_join)
    . ')';

$resp_check = wp_remote_get($check_url, [
    'headers' => [
        'apikey'        => FPFP_SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
    ],
    'timeout' => 20,
]);

if (!is_wp_error($resp_check) && wp_remote_retrieve_response_code($resp_check) === 200) {
    $existing = json_decode(wp_remote_retrieve_body($resp_check), true);

    // Se esiste anche una sola prenotazione → blocca
    if (!empty($existing)) {
        echo '<p style="color:red;font-weight:bold;">❌ Hai già presentato una richiesta di partecipazione per questa attività.<br>
        L\'organizzatore la sta valutando.<br>
        Controlla la tua email per aggiornamenti.</p>';
        return ob_get_clean();
    }
}
						
                        // Inserisci prenotazione su Supabase
                        $token_app = wp_generate_password(40, false, false);
                        $cancel_token = wp_generate_password(40, false, false);
                        $body_pren = array(
                            'allenamento_id' => $allenamento_id,
                            'nickname'       => $nick_join,
							'nome_partecipante'=> $nome_join,
                            'email'          => $email_join,
                            'livello'        => $livello_join,
                            'messaggio'      => $msg_join,
							'telefono'       => $tel_join,
                            'approvato'      => false,
                            'token_app'      => $token_app,
						    'cancel_token'   => $cancel_token,
                        );
$cancel_url = add_query_arg('cancel_token', $cancel_token, home_url('/'));	
$msg_joiner = $msg_joiner ?? '';
$msg_joiner .= ";\n\nSe vuoi annullare la tua richiesta, clicca qui:\n$cancel_url\n";
                        $resp_pren = wp_remote_post(FPFP_SUPABASE_URL . '/rest/v1/prenotazioni', array(
                            'headers' => array(
                                'apikey'        => FPFP_SUPABASE_API_KEY,
                                'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
                                'Content-Type'  => 'application/json',
                                'Prefer'        => 'return=representation',
                            ),
                            'body'    => wp_json_encode($body_pren),
                            'timeout' => 45,
                        ));

                        if (is_wp_error($resp_pren) || !in_array(wp_remote_retrieve_response_code($resp_pren), array(200,201))) {
                            echo '<p style="color:red;">❌ Errore nel salvataggio della tua richiesta. Riprova più tardi.</p>';
                        } else {

// 🗂️ SALVA NELLA TABELLA ARCHIVIO iscritti_findplayer (anche per richieste non ancora approvate)
$body_archivio = array(
    'allenamento_id' => $allenamento_id,
    'ruolo'          => 'partecipante',
    'disciplina'     => $disciplina,
    'citta'          => $citta,
    'nickname'       => $nick_join,
    'nome'           => $nome_join,
    'email'          => $email_join,
    'telefono'       => $tel_join,
    'ip_address'     => fp_get_client_ip(),
    'created_at'     => gmdate('Y-m-d\TH:i:s\Z'),
);

    $resp_arch = wp_remote_post(FPFP_SUPABASE_URL . '/rest/v1/iscritti_findplayer', array(
        'headers' => array(
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=representation',
        ),
        'body'    => wp_json_encode($body_archivio),
        'timeout' => 20,
    ));

    if (is_wp_error($resp_arch) || !in_array(wp_remote_retrieve_response_code($resp_arch), array(200,201))) {
        error_log('⚠️ Errore inserimento iscritti_findplayer (richiesta non approvata): ' . print_r($resp_arch, true));
    }

    // Email all'organizzatore per approvare (⚠️ senza telefono del partecipante)
    $approve_url = add_query_arg('approve_token', $token_app, home_url('/'));
                        $headers = array('Content-Type: text/plain; charset=UTF-8', 'From: ASD Oltrecity <no-reply@oltrecity.com>');

                        $msg_org = "Ciao $nick_org,\n\n"
                                 . "hai ricevuto una nuova richiesta di partecipazione all'evento:\n\n"
                                 . "Disciplina: $disciplina\n"
                                 . "Data: $data_evento\n"
                                 . "Orario: $orario\n"
                                 . "Città: $citta\n\n"
                                 . "Dettagli del giocatore:\n"
                                 . "- Nickname: $nick_join\n"
                                 . "- Email: $email_join\n"
                                 . "- Livello dichiarato: $livello_join / 5\n"
                                 . "- Messaggio: $msg_join\n\n"
                                 . "Per APPROVARE la partecipazione clicca qui:\n"
                                 . $approve_url . "\n\n";

                        wp_mail($email_org, 'Richiesta di partecipazione - Find Player', $msg_org, $headers);
                        $cancel_url = add_query_arg('cancel_token', $cancel_token, home_url('/'));

                        // Email di conferma ricezione al partecipante
                        $msg_joiner = "Ciao $nick_join,\n\n"
                                    . "la tua richiesta di partecipazione all'evento:\n\n"
                                    . "Disciplina: $disciplina\n"
                                    . "Data: $data_evento\n"
                                    . "Orario: $orario\n"
                                    . "Città: $citta\n\n"
                                    . "è stata inviata all'organizzatore.\n\n"
							        . "⚠️Attenzione ad alcuni aspetti:
1) NON corrispondere alcuna cifra in denaro senza ricevuta e senza aver accertato la regolarità della richiesta, meglio pagare direttamente la struttura.
2) Non fornire dati personali quali telefono o documenti, o indirizzo residenza, ecc, questo sistema è più sicuro e lo puoi risutilizzare per rincontrare i partecipanti
3) Evita attività e luoghi potenzialmente pericolosi.
4) Se non vuoi più partecipare, ricordati di avvisare l'organizzatore, è un gesto corretto, giusto e rispettoso.
5) Per qualsiasi chiarimento, necessità o segnalazione, siamo a Tua dispoizione al +39 3805140047.\n\n"
                                    . "📭 Riceverai una mail quando la tua partecipazione verrà approvata.\n";
                        wp_mail($email_join, 'Richiesta inviata - Find Player', $msg_joiner, $headers);

                        echo '<p style="color:green;">✅ La tua richiesta è stata inviata all\'organizzatore. Riceverai una mail quando verrà approvata.</p>';
                    }
                }
            }
        }
    }
}

    /* ----- RECUPERO DETTAGLI EVENTO PER MOSTRARLI ----- */

    $url_ev = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?id=eq.' . $allenamento_id . '&select=*';
    $resp_ev = wp_remote_get($url_ev, array(
        'headers' => array(
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ),
        'timeout' => 20,
    ));

    if (is_wp_error($resp_ev) || wp_remote_retrieve_response_code($resp_ev) !== 200) {
        echo '<p style="color:red;">❌ Impossibile caricare i dettagli di questo evento.</p>';
        return ob_get_clean();
    }

    $ev_rows = json_decode(wp_remote_retrieve_body($resp_ev), true);
    if (!is_array($ev_rows) || empty($ev_rows)) {
        echo '<p style="color:red;">❌ Evento non trovato.</p>';
        return ob_get_clean();
    }

    $evento = $ev_rows[0];
	$lat = $evento['lat'] ?? null;
	$lng = $evento['lng'] ?? null;
    $disciplina   = esc_html($evento['disciplina'] ?? '');
    $data_evento  = esc_html($evento['data_evento'] ?? '');
    $orario       = esc_html($evento['orario'] ?? '');
    $citta        = esc_html($evento['citta'] ?? '');
    $luogo        = esc_html($evento['luogo_descrizione'] ?? '');
    $tipo_campo   = esc_html($evento['tipo_campo'] ?? '');
    $ruolo_pers   = esc_html($evento['ruolo_personale'] ?? '');
    $ruoli_cerc   = esc_html($evento['ruoli_cercati'] ?? '');
    $livello_pers = isset($evento['livello_personale']) ? intval($evento['livello_personale']) : 0;
    $livello_min  = isset($evento['livello_richiesto_compagni']) ? intval($evento['livello_richiesto_compagni']) : 0;
    $num_gioc     = isset($evento['num_giocatori_richiesti']) ? intval($evento['num_giocatori_richiesti']) : 0;
    $posti_liberi = isset($evento['posti_liberi']) ? intval($evento['posti_liberi']) : 0;
    $note_extra   = esc_html($evento['note_extra'] ?? '');
    $nickname_org = esc_html($evento['nickname_creatore'] ?? ($evento['creatore_nome'] ?? 'Organizzatore'));
    $indirizzo_mappa = trim($luogo. ', ' . $citta);

    // Link alla Home Attività Sportive
    $home_attivita_url = home_url('/find-player/');

    ?>
    <div class="findplayer-evento-dettagli" style="max-width:900px;margin:0 auto;font-family:Arial,sans-serif;font-size:14px;line-height:1.5;">
        <h2><?php echo $disciplina; ?> – <?php echo esc_html($data_evento); ?></h2>
	<?php
$is_passato = (strtotime($data_evento) < strtotime('today'));
if ($is_passato) {
    echo '<p style="color:#721c24;font-weight:bold;">⚠️ Questo evento si è già concluso.</p>';
}
?>
	
        <p><strong>Città:</strong> <?php echo $citta; ?><br>
           <strong>Luogo / punto di ritrovo:</strong> <?php echo $luogo; ?><br>
           <?php if ($tipo_campo) : ?>
           <strong>Tipo di campo:</strong> <?php echo $tipo_campo; ?><br>
           <?php endif; ?>
           <strong>Orario:</strong> <?php echo $orario; ?><br>
           <strong>Organizzatore:</strong> <?php echo $nickname_org; ?><br>
           <strong>Posti totali:</strong> <?php echo $num_gioc; ?> – <strong>Posti liberi:</strong> <?php echo $posti_liberi; ?>
        </p>

        <p>
            <?php if ($ruolo_pers) : ?>
                <strong>Ruolo in cui giocherà l'organizzatore:</strong> <?php echo $ruolo_pers; ?><br>
            <?php endif; ?>
            <?php if ($ruoli_cerc) : ?>
                <strong>Ruoli richiesti:</strong> <?php echo $ruoli_cerc; ?><br>
            <?php endif; ?>
            <?php if ($livello_pers) : ?>
                <strong>Livello dichiarato dell'organizzatore:</strong> <?php echo $livello_pers; ?>/6<br>
            <?php endif; ?>
            <?php if ($livello_min) : ?>
                <strong>Livello minimo richiesto ai partecipanti:</strong> <?php echo $livello_min; ?>/6<br>
            <?php endif; ?>
            <?php if ($note_extra) : ?>
                <strong>Note:</strong> <?php echo $note_extra; ?><br>
            <?php endif; ?>
        </p>

<?php if (!empty($lat) && !empty($lng)): ?>

    <h3>Mappa posizione evento</h3>
    <div id="map-singolo-evento" style="width:100%;height:350px;border-radius:8px;"></div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {

        setTimeout(function () {

            const map = L.map('map-singolo-evento', {
                scrollWheelZoom: false
            }).setView(
                [<?php echo esc_js($lat); ?>, <?php echo esc_js($lng); ?>],
                14
            );

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);

            L.marker([<?php echo esc_js($lat); ?>, <?php echo esc_js($lng); ?>]).addTo(map);

            map.invalidateSize();

        }, 300);

    });
    </script>

<?php else: ?>

    <p style="color:#b71c1c;font-weight:bold;">
        📍 Luogo non trovato – l’evento è stato registrato correttamente ma la posizione non è disponibile sulla mappa.
    </p>

<?php endif; ?>

        <p style="margin-bottom:20px;">
            <a href="<?php echo esc_url($home_attivita_url); ?>" style="text-decoration:none;padding:8px 14px;border-radius:4px;border:1px solid #333;">
                🔙 Torna alla Home Attività Sportive
            </a>
        </p>

        <?php if ($posti_liberi > 0) : ?>
            <hr>
            <h3>Partecipa a questa attività</h3>
            <form method="post">
                <input type="hidden" name="fpfp_allenamento_id" value="<?php echo intval($allenamento_id); ?>">

                <label>Nickname:<br>
                    <input type="text" name="nick_join" required>
                </label><br>
				<label>Nome e Cognome:<br>
                    <input type="text" name="nome_partecipante" required>
                </label><br>
                <label>Email:<br>
                    <input type="email" name="email_join" required>
                </label><br>
				
				        <label>Telefono (solo per uso interno, NON verrà mostrato all'organizzatore):<br>
            <input type="text" name="tel_join" pattern="[0-9+\s]{6,20}" placeholder="Es. +39 380 1234567">
        </label><br>

                <label>Il tuo livello in questa disciplina (1-6):<br>
                    <select name="livello_join">
                        <option value="1">1 - Principiante</option>
                        <option value="2">2 - Base</option>
                        <option value="3" selected>3 - Intermedio</option>
                        <option value="4">4 - Avanzato</option>
                        <option value="5">5 - Agonistico</option>
			            <option value="6">6 - Istruttore</option>
                    </select>
                </label><br>

                <label>Messaggio per l'organizzatore (facoltativo):<br>
                    <textarea name="messaggio_join" rows="3" placeholder="Es. che ruolo preferisci, ecc, Niente dati personali."></textarea>
                </label><br>

                <?php
                // semplice anti-spam anche qui
                $domande_join = [
                    ['q' => 'Quanto fa 5 + 5?', 'a' => 10],
                    ['q' => 'Scrivi il numero 11 al contrario', 'a' => 11],
                    ['q' => 'Quanto fa 3 x 3?', 'a' => 9],
					['q' => 'Quanto fa 8 + 80?', 'a' => 88],
                ];
                $scj = $domande_join[array_rand($domande_join)];
                $domanda_join = $scj['q'];
                $risp_attesa_join = strtolower(trim($scj['a']));
                ?>
                <input type="hidden" name="risposta_corr_join" value="<?php echo esc_attr($risp_attesa_join); ?>">
                <label><?php echo esc_html($domanda_join); ?> (anti-spam)<br>
                    <input type="text" name="antispam_join" required>
                </label><br><br>

                <input type="submit" name="fpfp_partecipa_evento" value="Invia richiesta di partecipazione">
            </form>
        <?php else : ?>
            <p style="color:red;font-weight:bold;">❌ Evento completo – non ci sono più posti disponibili.</p>
        <?php endif; ?>
    </div>
<br>
                <label>NON corrispondere alcuna cifra in denaro a persone conosciute online, paga solo di persona dietro regolare rialscio di     ricevuta.<br>
                </label><br>
    <?php
/* -------------------------------------------------------------------------- */
/* CRON AUTOMATICO - Pulizia eventi e prenotazioni non confermati (96h)       */
/* -------------------------------------------------------------------------- */

// ✅ Registra il cron all’attivazione del plugin
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('fpfp_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'fpfp_daily_cleanup');
    }
});

// ❌ Rimuove il cron alla disattivazione
register_deactivation_hook(__FILE__, function () {
    $timestamp = wp_next_scheduled('fpfp_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'fpfp_daily_cleanup');
    }
});

// 🧹 Funzione di pulizia automatica
add_action('fpfp_daily_cleanup', function () {
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $limit = gmdate('Y-m-d\TH:i:s\Z', strtotime('-96 hours'));

    // 🔹 1) Cancella prenotazioni non approvate da più di 96 ore
    $url_pren = FPFP_SUPABASE_URL . '/rest/v1/prenotazioni?approvato=eq.false&created_at=lt.' . rawurlencode($limit);
    $resp_del_pren = wp_remote_request($url_pren, [
        'method'  => 'DELETE',
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 25,
    ]);

    if (!is_wp_error($resp_del_pren)) {
        error_log('🧹 Find Player Cleanup: eliminati iscritti non approvati prima del ' . $limit);
    } else {
        error_log('⚠️ Find Player Cleanup: errore cancellazione prenotazioni scadute.');
    }

    // 🔹 2) Cancella eventi non confermati (solo se in futuro implementerai token_conferma_evento)
    $url_eventi = FPFP_SUPABASE_URL . '/rest/v1/allenamenti?evento_confermato=eq.false&created_at=lt.' . rawurlencode($limit);
    $resp_del_ev = wp_remote_request($url_eventi, [
        'method'  => 'DELETE',
        'headers' => [
            'apikey'        => FPFP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FPFP_SUPABASE_API_KEY,
        ],
        'timeout' => 25,
    ]);

    if (!is_wp_error($resp_del_ev)) {
        error_log('🧹 Find Player Cleanup: eliminati eventi non confermati prima del ' . $limit);
    } else {
        error_log('⚠️ Find Player Cleanup: errore cancellazione eventi scaduti.');
    }
});
/**
 * Allinea dati WP User con scheda FP Giocatore
 * - display_name
 * - nickname
 * - user_nicename (slug author)
 */
add_action('profile_update', 'fp_sync_wp_user_with_player', 10, 2);
add_action('user_register', 'fp_sync_wp_user_with_player', 10, 1);

function fp_sync_wp_user_with_player($user_id) {

    // recupera player collegato
    $player = get_posts([
        'post_type'   => 'fp_giocatore',
        'post_status' => 'publish',
        'meta_key'    => 'fp_wp_user_id',
        'meta_value'  => $user_id,
        'numberposts' => 1,
        'fields'      => 'ids'
    ]);

    if (empty($player)) return;

    $player_id = $player[0];

    // nome da usare
    $nickname = get_post_meta($player_id, 'fp_nickname', true);
    if (!$nickname) {
        $nickname = get_the_title($player_id);
    }

    if (!$nickname) return;

    // aggiorna utente WP
    wp_update_user([
        'ID'           => $user_id,
        'display_name' => $nickname,
        'nickname'     => $nickname,
        'user_nicename'=> sanitize_title($nickname)
    ]);
}
/**
 * Override totale link autore → scheda giocatore
 */
add_filter('author_link', 'fp_author_link_to_player', 20, 3);

function fp_author_link_to_player($link, $author_id, $author_nicename) {

    // cerca player collegato all'utente WP
    $player = get_posts([
        'post_type'   => 'fp_giocatore',
        'post_status' => 'publish',
        'meta_key'    => 'fp_wp_user_id',
        'meta_value'  => $author_id,
        'numberposts' => 1,
        'fields'      => 'ids'
    ]);

    if (empty($player)) return $link;

    return get_permalink($player[0]);
}


    return ob_get_clean();
});