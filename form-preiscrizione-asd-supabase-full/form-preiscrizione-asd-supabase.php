<?php
/**
 * Plugin Name: Form Preiscrizione ASD Supabase (Conferma Email)
 * Description: Invia i dati al database SOLO dopo conferma via email del token. Include calcolo offline del CF, CAP automatico, invio email (admin+utente), antispam + cron di pulizia (se conferma mail entro 48 ore) e stampa modulo pdf.
 * Version: 2.3.1
 * Author: Facile pmi
 */

if (!defined('ABSPATH')) { exit; }

if (!defined('FP_SUPABASE_URL')) {
    define('FP_SUPABASE_URL', '...');
}

if (!defined('FP_SUPABASE_API_KEY')) {
    define('FP_SUPABASE_API_KEY', '...');
}

/* -------------------------------------------------------------------------- */
/* INSTALL: tabella temporanea + cron                                         */
/* -------------------------------------------------------------------------- */
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $table = $wpdb->prefix . 'preiscrizioni_temp';
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

    if (!wp_next_scheduled('fp_cleanup_preiscrizioni')) {
        wp_schedule_event(time() + 3600, 'daily', 'fp_cleanup_preiscrizioni');
    }
});

register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('fp_cleanup_preiscrizioni');
    if ($timestamp) wp_unschedule_event($timestamp, 'fp_cleanup_preiscrizioni');
});

add_action('fp_cleanup_preiscrizioni', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'preiscrizioni_temp';
    // Elimina non confermate dopo 48 ore
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table WHERE confirmed = 0 AND created_at < %s",
        gmdate('Y-m-d H:i:s', time() - 1*3600)
    ));
});

/* -------------------------------------------------------------------------- */
/* GESTIONE LINK DI CONFERMA                                                  */
/* -------------------------------------------------------------------------- */
add_action('init', function() {
    if (!empty($_GET['confirm_token'])) {
        fp_handle_confirmation(sanitize_text_field($_GET['confirm_token']));
        exit;
    }
});

function fp_handle_confirmation($token) {
    global $wpdb;
    $table = $wpdb->prefix . 'preiscrizioni_temp';

    $token_hashed = hash('sha256', $token . NONCE_SALT);
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE token = %s", $token_hashed));
    if (!$row) {
        wp_die('<h2>Token non valido</h2><p>Il link di conferma non è valido o è stato già utilizzato.</p>', 'Conferma iscrizione', [ 'response' => 400 ]);
    }

    // Scaduto?
    $created_ts = strtotime($row->created_at . ' UTC');
    if ($created_ts < time() - 48*3600) {
        $wpdb->delete($table, ['id' => $row->id], ['%d']);
        wp_die('<h2>Link scaduto</h2><p>Il link di conferma è scaduto. Per favore invia di nuovo la preiscrizione.</p>', 'Conferma iscrizione', [ 'response' => 410 ]);
    }

    if (intval($row->confirmed) === 1) {
        wp_die('<h2>Già confermato</h2><p>Questa preiscrizione è già stata confermata.</p>', 'Conferma iscrizione', [ 'response' => 200 ]);
    }

    // Decodifica dati e invia a Supabase
    $data = json_decode($row->data, true);
    if (!is_array($data)) {
        wp_die('<h2>Errore dati</h2><p>I dati associati non sono più disponibili, ricompila il modulo.</p>', 'Conferma iscrizione', [ 'response' => 500 ]);
    }

    $response = wp_remote_post(FP_SUPABASE_URL . '/rest/v1/iscrizione_associazione', [
        'headers' => [
            'apikey'        => FP_SUPABASE_API_KEY,
            'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'return=minimal',
        ],
        'body'    => wp_json_encode($data),
        'method'  => 'POST',
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) {
        wp_die('<h2>Errore invio</h2><p>Non è stato possibile completare la conferma (connessione). Riprova tra poco.</p>', 'Conferma iscrizione', [ 'response' => 502 ]);
    }

    $code = wp_remote_retrieve_response_code($response);
    if (!in_array($code, [200,201], true)) {
        $body = esc_html(wp_remote_retrieve_body($response));
        wp_die('<h2>Errore Supabase</h2><p>Codice: '.intval($code).'</p><pre style="white-space:pre-wrap">'.$body.'</pre>', 'Conferma iscrizione', [ 'response' => 500 ]);
    }

    // Marca come confermato
    $wpdb->update($table, ['confirmed' => 1], ['id' => $row->id], ['%d'], ['%d']);

    // Invia email finali (admin + utente)
    $to_admin = 'oltrecity.asd@gmail.com';
    $subject  = 'Nuova preiscrizione confermata';

    $user_ip = $data['ip'] ?? 'IP non rilevato';

    $messaggio_legibile = "Nuova preiscrizione confermata:\n\n"
        . "Nome: {$data['nome']}\n"
        . "Cognome: {$data['cognome']}\n"
        . "Data di nascita: {$data['data_nascita']}\n"
        . "Codice Fiscale: {$data['codice_fiscale']}\n"
        . "Città di nascita: {$data['citta_nascita']}\n"
        . "Città di residenza: {$data['citta_residenza']}\n"
        . "CAP Residenza: {$data['cap_residenza']}\n"
        . "Via/Domicilio: {$data['via_domicilio']}\n"
        . "Telefono: {$data['telefono']}\n"
        . "Email: {$data['email']}\n"
        . "Sesso: {$data['sesso']}\n"
        . "Sport scelto: {$data['sport']}\n"
        . "Data attività: {$data['data_attivita']}\n"
        . "Messaggio: {$data['messaggio']}\n"
        . "Data iscrizione: {$data['data_iscrizione']}\n"
        . "Indirizzo IP: {$user_ip}\n";

    $csv_row = [
        $data['cognome'], $data['nome'], $data['data_nascita'], $data['codice_fiscale'],
        $data['citta_nascita'], $data['citta_residenza'], $data['cap_residenza'],
        $data['via_domicilio'], $data['telefono'], $data['email'],
        $data['sesso'], $data['sport'], $data['data_attivita'], $data['messaggio'],
        $data['data_iscrizione'], $user_ip
    ];
    $csv_text = '"' . implode('","', array_map('sanitize_text_field', $csv_row)) . '"';

    $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: ASD Oltrecity <no-reply@oltrecity.com>'];
    wp_mail($to_admin, $subject, $messaggio_legibile . "\n---\nCSV compatto:\n" . $csv_text, $headers);
    if (!empty($data['email'])) {
        wp_mail(
            $data['email'],
            'Conferma completata - ' . $subject,
            "Ciao {$data['nome']} {$data['cognome']},\n\nla tua preiscrizione è stata confermata e registrata.\n\n" . $messaggio_legibile,
            $headers
        );
    }

    wp_die('<h2>Grazie! ✅</h2><p>Conferma completata. La tua preiscrizione è stata registrata correttamente. Siamo sempre disponibili al +393805140047 o su https://www.oltrecity.com/contatti/</p>', 'Conferma iscrizione', [ 'response' => 200 ]);
}

/* -------------------------------------------------------------------------- */
/* SHORTCODE (form)                                                           */
/* -------------------------------------------------------------------------- */
add_shortcode('form_preiscrizione_asd_supabase', function() {
    ob_start();

    if (!empty($_POST['invio_iscrizione'])) {
        // Anti-spam dinamico
        $risposta_utente   = strtolower(trim($_POST['antispam'] ?? ''));
        $risposta_corretta = strtolower(trim($_POST['risposta_corretta'] ?? ''));

        if ($risposta_utente !== $risposta_corretta) {
            echo '<p style="color:red;">⚠️ Risposta anti-spam errata. Riprova.</p>';
            return ob_get_clean();
        }

        $privacy = !empty($_POST['privacy']);
        if (!$privacy) {
            echo '<div class="notice notice-error" style="color:red;">⚠️ Devi accettare la Privacy Policy per inviare la preiscrizione.</div>';
            return ob_get_clean();
        }

		
        // Prepara dati da salvare in tabella TEMP (non inviamo ancora a Supabase)
        $citta_nascita = '';
        if (!empty($_POST['citta_nascita_italia'])) {
            $citta_nascita = sanitize_text_field($_POST['citta_nascita_italia']);
        } elseif (!empty($_POST['citta_nascita_estero'])) {
            $citta_nascita = sanitize_text_field($_POST['citta_nascita_estero']);
        } elseif (!empty($_POST['citta_nascita'])) {
            // fallback compatibilità vecchio campo
            $citta_nascita = sanitize_text_field($_POST['citta_nascita']);
        }

        $data = array(
            'cognome'         => sanitize_text_field($_POST['cognome']),
            'nome'            => sanitize_text_field($_POST['nome']),
            'data_nascita'    => sanitize_text_field($_POST['data_nascita']),
            'codice_fiscale'  => sanitize_text_field($_POST['codice_fiscale']),
            'citta_nascita'   => $citta_nascita,  // 👈 FIX
            'citta_residenza' => sanitize_text_field($_POST['citta_residenza']),
            'cap_residenza'   => sanitize_text_field($_POST['cap_residenza']),
            'via_domicilio'   => sanitize_text_field($_POST['via_domicilio']),
            'telefono'        => sanitize_text_field($_POST['telefono']),
            'email'           => sanitize_email($_POST['email']),
            'sesso'           => sanitize_text_field($_POST['sesso']),
            'sport'           => sanitize_text_field($_POST['sport']),
            'data_attivita'   => sanitize_text_field($_POST['data_attivita']),
            'messaggio'       => sanitize_textarea_field($_POST['messaggio']),
            'data_iscrizione' => date('Y-m-d'),
            'ip'              => $_SERVER['REMOTE_ADDR'] ?? ''
        );
        // Salva in tabella temporanea con token
        $token = wp_generate_password(40, false, false);
        global $wpdb;
        $table = $wpdb->prefix . 'preiscrizioni_temp';
        $wpdb->insert($table, [
            'email'      => $data['email'],
            'token'      => hash('sha256', $token . NONCE_SALT),
            'data'       => wp_json_encode($data),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'confirmed'  => 0,
        ], ['%s','%s','%s','%s','%d']);

        // Link di conferma (il token in URL è la versione "raw"; verifichiamo l'hash a server)
        $confirm_url = add_query_arg([ 'confirm_token' => $token ], home_url('/'));

        // Email con link di conferma
        $headers = ['Content-Type: text/plain; charset=UTF-8', 'From: ASD Oltrecity <no-reply@oltrecity.com>'];
        $msg = "Ciao {$data['nome']} {$data['cognome']},\n\n"
             . "per completare la tua preiscrizione, conferma la tua email cliccando sul link qui sotto:\n\n"
             . $confirm_url . "\n\n"
             . "Il link scade tra 48 ore.\n\n"
             . "Se non hai richiesto questa preiscrizione, ignora questa email.";
        wp_mail($data['email'], 'Conferma la tua preiscrizione', $msg, $headers);

        // 🔄 Mostra messaggio e reindirizza alla pagina riepilogo
        ?>
<form id="redirect-riepilogo" action="<?php echo esc_url( home_url('/riepilogo-iscrizione/') ); ?>" method="post">
    <input type="hidden" name="nome" value="<?php echo esc_attr($data['nome']); ?>">
    <input type="hidden" name="cognome" value="<?php echo esc_attr($data['cognome']); ?>">
    <input type="hidden" name="data_nascita" value="<?php echo esc_attr($data['data_nascita']); ?>">
    <input type="hidden" name="sesso" value="<?php echo esc_attr($data['sesso']); ?>">

    <input type="hidden" name="citta_nascita" value="<?php echo esc_attr($data['citta_nascita'] ?: ($_POST['comune_nascita'] ?? '')); ?>">
    <input type="hidden" name="comune_nascita" value="<?php echo esc_attr($_POST['comune_nascita'] ?? ''); ?>">
    <input type="hidden" name="via_domicilio" value="<?php echo esc_attr($data['via_domicilio']); ?>">
    <input type="hidden" name="cap_residenza" value="<?php echo esc_attr($data['cap_residenza']); ?>">
    <input type="hidden" name="citta_residenza" value="<?php echo esc_attr($data['citta_residenza']); ?>">

    <input type="hidden" name="codice_fiscale" value="<?php echo esc_attr($data['codice_fiscale']); ?>">
    <input type="hidden" name="telefono" value="<?php echo esc_attr($data['telefono']); ?>">
    <input type="hidden" name="email" value="<?php echo esc_attr($data['email']); ?>">

    <input type="hidden" name="sport" value="<?php echo esc_attr($data['sport']); ?>">
    <input type="hidden" name="data_attivita" value="<?php echo esc_attr($data['data_attivita']); ?>">
    <input type="hidden" name="messaggio" value="<?php echo esc_attr($data['messaggio']); ?>">
</form>

<script>
alert("✅ Ti abbiamo inviato una email con il link di conferma.\n\nScarica e stampa le 3 copie del pdf e verifica i dati sulla tua mail, se vedi errori diccelo subito.");
document.getElementById('redirect-riepilogo').submit();
</script>
        <?php
        return ob_get_clean();
		
        echo '<div class="notice notice-success" style="color:green;">✅ Grazie! Ti abbiamo inviato una email con un link di conferma. Controlla la casella di posta (anche SPAM). Riceveremo i tuoi dati solo dopo la conferma, altrimenti verranno cestinati in automatico.</div>';
        return ob_get_clean();
    }
    ?>
<style>
	
/* inizio divisione in righe -----*/	
/* -------------------------------------------------
   GRID Find Player - righe con 2 o 3 colonne
--------------------------------------------------*/

/* Contenitore riga */
.fp-row {
    display: grid;
    gap: 20px;
    margin-bottom: 20px;
}

/* 3 colonne */
.fp-row-3 {
    grid-template-columns: repeat(3, 1fr);
}

/* 2 colonne (se ti serve) */
.fp-row-2 {
    grid-template-columns: repeat(2, 1fr);
}

/* Colonna singola */
.fp-full {
    grid-template-columns: 1fr;
}

/* Stile dei campi */
.fp-field label {
    font-weight: bold;
    display: block;
    margin-bottom: 6px;
}

.fp-field input,
.fp-field select,
.fp-field textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}
	@media (max-width: 768px) {
    .fp-row-3,
    .fp-row-2 {
        grid-template-columns: 1fr;
    }
}
/* fine divisione in righe -----*/
	
/* inizio creazione stile tooltip
 * -------------------------------------------------
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
.fp-row > .fp-field fieldset {
    width: 100%;
    box-sizing: border-box;
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
<!-- fine creazione stile tooltip -->

<!-- 1a) dati personali -->
<form method="post" id="fp-preiscrizione-form">
<div class="fp-row fp-row-3">
    <div class="fp-field">
        <label>*Cognome:</label>
			<input type="text" name="cognome" required>
    </div>

    <div class="fp-field">
        <label>*Nome:</label>
			<input type="text" name="nome" required>
    </div>
    <div class="fp-field">
        <label>*Sesso:
            <select name="sesso" required>
                <option value="">Seleziona...</option>
                <option value="Maschio">Maschio</option>
                <option value="Femmina">Femmina</option>
            </select>
       </label>
    </div>
Luogo di Nascita<br><br>
  </div>	
<div class="fp-row fp-row-3">

    <!-- COLONNA 1 → Italia / Estero -->
    <div class="fp-field">
        <label style="display:inline-flex; align-items:center; margin-right:15px;">
            <input type="radio" name="nascita_tipo" value="italia" checked
                   style="transform:scale(0.75); width:14px; height:14px; margin-right:5px;">
            Italia
        </label>

        <label style="display:inline-flex; align-items:center;">
            <input type="radio" name="nascita_tipo" value="estero"
                   style="transform:scale(0.75); width:14px; height:14px; margin-right:5px;">
            Estero
        </label>
    </div>

    <!-- COLONNA 2 → Comune / Nazione di nascita -->
    <div class="fp-field">
        <div id="nascita-italia">
            <label>*Comune di nascita (Italia):<br>
                <input type="text" name="citta_nascita_italia" id="comune_nascita"
                       list="lista_comuni" placeholder="Es. Roma" required>
                <datalist id="lista_comuni"></datalist>
            </label>
        </div>

        <div id="nascita-estero" style="display:none;">
            <label>Nazione di nascita (Estero):<br>
                <input type="text" name="citta_nascita_estero" id="nazione_nascita"
                       list="lista_esteri" placeholder="Es. Francia" disabled>
                <datalist id="lista_esteri"></datalist>
            </label>
        </div>
    </div>

    <!-- COLONNA 3 → Codice catastale -->
    <div class="fp-field">
        <label>Codice catastale:<br>
            <input type="text" id="codice_catastale_override"
                   placeholder="Es. H501 per Roma, Z110 per Francia">
        </label>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const radioItalia = document.querySelector('input[name="nascita_tipo"][value="italia"]');
  const radioEstero = document.querySelector('input[name="nascita_tipo"][value="estero"]');
  const boxItalia   = document.getElementById('nascita-italia');
  const boxEstero   = document.getElementById('nascita-estero');
  const inItalia    = document.querySelector('input[name="citta_nascita_italia"]');
  const inEstero    = document.querySelector('input[name="citta_nascita_estero"]');

  function toggleNascita() {
    if (radioItalia.checked) {
      boxItalia.style.display = '';
      boxEstero.style.display = 'none';
      inItalia.disabled = false;
      inItalia.required = true;
      inEstero.disabled = true;
      inEstero.required = false;
    } else {
      boxItalia.style.display = 'none';
      boxEstero.style.display = '';
      inItalia.disabled = true;
      inItalia.required = false;
      inEstero.disabled = false;
      inEstero.required = true;
    }
  }
  radioItalia.addEventListener('change', toggleNascita);
  radioEstero.addEventListener('change', toggleNascita);
  toggleNascita();
});
</script>

<!-- 1a) dati personali -->
<form method="post" id="fp-preiscrizione-form">
<div class="fp-row fp-row-3">
    <div class="fp-field">
        <label>*Data di nascita:</label>
			<input type="date" name="data_nascita" required>
    </div>

    <div class="fp-field">
        <label>Codice Fiscale:</label>
            <input type="text" id="codice_fiscale" name="codice_fiscale" required>
		   </div>	
	
<div class="fp-field" style="display:flex; align-items:flex-end;">
    <label>&nbsp;</label>
    <button type="button" id="calcola_cf" class="button"
            style="
                padding:10px 10px;
                font-size:15px;
                width:auto;
                min-width:150px;
                white-space:nowrap;
                margin-top:5px;
            ">
        Calcola C.F.
    </button>
</div>
  </div>	

<div class="fp-row fp-row-3">
    <div class="fp-field">
        <label>*Città di residenza:</label>
        <input type="text" name="citta_residenza" id="citta_residenza" required>
    </div>

    <div class="fp-field">
        <label>*CAP:</label>
        <input type="text" id="cap_residenza" name="cap_residenza" maxlength="5">
    </div>

    <div class="fp-field">
        <label>Via/Domicilio:</label>
        <input type="text" name="via_domicilio" required>
    </div>
</div>
	
<div class="fp-row fp-row-2">
    <div class="fp-field">
        <label>*Telefono:</label>
        <input type="text" name="telefono" required>
    </div>

    <div class="fp-field">
        <label>Email:</label>
        <input type="email" name="email" required>
    </div>
</div>

<div class="fp-row fp-row-2">
    <div class="fp-field">
        <label>Sport:</label>
        <select name="sport" required>
                <option value="">Seleziona sport...</option>
                <option value="Arma air soft">Arma air soft</option>
                <option value="Realta Virtuale">Relatà Virtuale</option>
                <option value="Jeet Kune Do">Jeet Kune Do</option>
                <option value="Nordic Walking">Nordic Walking</option>
                <option value="Orienteering">Orienteering</option>
            </select>
    </div>

    <div class="fp-field">
        <label>Data attività richiesta:</label>
        <input type="date" name="data_attivita" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
    </div>
</div>
<div class="fp-row">
    <div class="fp-field">        
	<label>Messaggio:</label>
		<textarea name="messaggio" rows="4" cols="50"></textarea><br>
</div>

	
	
</div>
        <?php
        // --- Genera domanda anti-spam casuale ---
        $domande_antispam = [
            ['q' => 'Quanto fa 8 + 80?', 'a' => 88],
            ['q' => 'Quanto fa 10 + 90?', 'a' => 100],
            ['q' => 'Scrivi il numero 55 al contrario', 'a' => 55],
            ['q' => 'Quanto fa 50 diviso 10?', 'a' => 5],
            ['q' => 'Qual è il risultato di 40 + 4?', 'a' => 44],
            ['q' => 'Se oggi è Venerdì, che giorno sarà domani?', 'a' => 'sabato'],
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
            Inviando questo modulo, acconsento al trattamento dei miei dati personali secondo quanto previsto dall’
            <a href="https://www.oltrecity.com/privacy-policy-cookie-policy/" target="_blank" rel="noopener noreferrer">
            informativa privacy</a> di questo sito, ai sensi del Regolamento (UE) 2016/679 (GDPR) e del D.lgs. 196/2003, come modificato dal D.lgs. 101/2018.<br>
            *campo obbligatorio
        </label><br><br>

        <input type="submit" name="invio_iscrizione" value="Invia Preiscrizione">

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const campoCitta = document.getElementById('citta_residenza');
            const campoCap = document.getElementById('cap_residenza');

            function normalizeComune(s) {
                return s.normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toUpperCase()
                    .replace(/\(.*?\)/g, '')
                    .replace(/[^A-Z0-9 ]/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            campoCitta.addEventListener('blur', function() {
                const comuneInput = normalizeComune(campoCitta.value);
                let capTrovato = '';

                if (window.CAP_COMUNI) {
                    if (CAP_COMUNI[comuneInput]) {
                        const valore = CAP_COMUNI[comuneInput];
                        capTrovato = Array.isArray(valore) ? valore[0] : valore;
                    } else {
                        for (const key in CAP_COMUNI) {
                            if (key.includes(comuneInput)) {
                                const valore = CAP_COMUNI[key];
                                capTrovato = Array.isArray(valore) ? valore[0] : valore;
                                break;
                            }
                        }
                    }
                }
                campoCap.value = capTrovato || '';
            });
        });
        </script>
    </form>

    <!-- Script CF offline + dataset -->
    <script>
      window.FP_CF_ASSETS = "<?php echo esc_js( plugin_dir_url(__FILE__) . 'assets/' ); ?>";
    </script>
    <script src="<?php echo plugin_dir_url(__FILE__); ?>assets/codici_comuni.js"></script>
    <script src="<?php echo plugin_dir_url(__FILE__); ?>assets/codici_esteri.js"></script>
    <script src="<?php echo plugin_dir_url(__FILE__); ?>assets/codicefiscale.js"></script>
    <script src="<?php echo plugin_dir_url(__FILE__); ?>assets/cap_comuni.js"></script>
    <?php
    return ob_get_clean();
});
/* -------------------------------------------------------------------------- */
/* SHORTCODE RIEPILOGO                                                        */
/* -------------------------------------------------------------------------- */
add_shortcode('riepilogo_iscrizione_asd', function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '<p style="color:red;">❌ Nessun dato ricevuto.</p>';
    }
    // === INPUT COMPLETI ===
    $nome            = sanitize_text_field($_POST['nome'] ?? ''); 
    $cognome         = sanitize_text_field($_POST['cognome'] ?? '');
    $data_nascita    = sanitize_text_field($_POST['data_nascita'] ?? '');
    $sesso           = sanitize_text_field($_POST['sesso'] ?? '');
    $citta_nascita   = sanitize_text_field($_POST['citta_nascita'] ?: ($_POST['comune_nascita'] ?? ''));
    $codice_fiscale  = sanitize_text_field($_POST['codice_fiscale'] ?? '');
    $via_domicilio   = sanitize_text_field($_POST['via_domicilio'] ?? '');
    $cap_residenza   = sanitize_text_field($_POST['cap_residenza'] ?? '');
    $citta_residenza = sanitize_text_field($_POST['citta_residenza'] ?? '');
    $telefono        = sanitize_text_field($_POST['telefono'] ?? '');
    $email           = sanitize_email($_POST['email'] ?? '');
    $sport           = sanitize_text_field($_POST['sport'] ?? '');
    $data_attivita   = sanitize_text_field($_POST['data_attivita'] ?? '');
    $messaggio       = sanitize_textarea_field($_POST['messaggio'] ?? '');
	
	    // === ETÀ E MAGGIORENNITÀ ===

    $eta = 0;
    if ($data_nascita) {
        $eta = (int) floor((time() - strtotime($data_nascita)) / (365*24*60*60));
    }
    $is_maggiorenne = ($eta >= 18);

// === Recupera dati anagrafica associazione da Supabase ===
$anagrafica_associazione = [
    'denominazione' => '',
    'codice_fiscale' => '',
    'partita_iva' => '',
    'indirizzo' => '',
    'citta' => '',
    'cap' => '',
    'provincia' => '',
    'telefono' => '',
    'email' => ''
];

$response_associazione = wp_remote_get(FP_SUPABASE_URL . '/rest/v1/anagrafica_associazione?select=denominazione,codice_fiscale,partita_iva,indirizzo,citta,cap,provincia,telefono,email', [
    'headers' => [
        'apikey'        => FP_SUPABASE_API_KEY,
        'Authorization' => 'Bearer ' . FP_SUPABASE_API_KEY,
    ],
    'timeout' => 15,
]);

if (!is_wp_error($response_associazione)) {
    $body = json_decode(wp_remote_retrieve_body($response_associazione), true);
    if (is_array($body) && !empty($body[0])) {
        $row = $body[0];
        foreach ($row as $key => $val) {
            if (isset($anagrafica_associazione[$key])) {
                $anagrafica_associazione[$key] = $val;
            }
        }
    }
}

ob_start(); ?>
<div id="riepilogo-scroll-container" 
	 style="width:100%;overflow-x:auto;overflow-y:auto;max-height:95vh;padding:5px;background:#f9f9f9;border:1px solid #ccc;">
  <div id="riepilogo-iscrizione-wrapper" 
	   style="font-family:Arial,Helvetica,sans-serif;
			  font-size:11px;line-height:1.1;
			  width:190mm;
			  min-height:270mm;
			  margin:0 auto;
			  padding:5mm;
			  background-color:#fff;
			  color:#000;
			  box-sizing:border-box;border:1px solid #000;">

<h2 style="text-align:center;margin:0 0 2px 0;font-size:16px;text-transform:uppercase;">
  <?= esc_html($anagrafica_associazione['denominazione']) ?>
</h2>

<p style="text-align:center;margin:0;font-size:12px;">
  <?php if (!empty($anagrafica_associazione['codice_fiscale'])): ?>
    CF <?= esc_html($anagrafica_associazione['codice_fiscale']) ?>
  <?php endif; ?>
  <?php if (!empty($anagrafica_associazione['partita_iva'])): ?>
    - P.IVA <?= esc_html($anagrafica_associazione['partita_iva']) ?>
  <?php endif; ?>
</p>

<p style="text-align:center;margin:0 0 2px 0;font-size:11px;text-transform:lowercase;">
  <?= esc_html($anagrafica_associazione['indirizzo']) ?>
  <?= !empty($anagrafica_associazione['cap']) ? ', ' . esc_html($anagrafica_associazione['cap']) : '' ?>
  <?= !empty($anagrafica_associazione['citta']) ? ' ' . esc_html($anagrafica_associazione['citta']) : '' ?>
  <?= !empty($anagrafica_associazione['provincia']) ? ' (' . esc_html($anagrafica_associazione['provincia']) . ')' : '' ?>
</p>

<p style="text-align:center;margin:0 0 8px 0;font-size:11px;">
  <?php if (!empty($anagrafica_associazione['email'])): ?>
    <a href="mailto:<?= esc_attr($anagrafica_associazione['email']) ?>" style="color:#000;text-decoration:none;">
        <?= esc_html($anagrafica_associazione['email']) ?>
    </a>
  <?php endif; ?>
  <?php if (!empty($anagrafica_associazione['telefono'])): ?>
    <?= !empty($anagrafica_associazione['email']) ? ' - ' : '' ?>
    Tel. <?= esc_html($anagrafica_associazione['telefono']) ?>
  <?php endif; ?>
</p>

<hr style="border:0;border-top:1px solid #000;margin:2px 0 10px 0;">

<h3 style="text-align:center;text-transform:uppercase;margin-bottom:10px;">Richiesta di ammissione</h3>

<?php if ($is_maggiorenne): ?>
<p><strong>Dati del socio</strong></p>
<div style="display:flex;flex-wrap:wrap;gap:4px 4px;">
    <div style="flex:1 1 30%;">
        <strong>Nome:</strong> <?= esc_html($nome) ?><br>
        <strong>Data di nascita:</strong> <?= esc_html($data_nascita) ?><br>
        <strong>Città di nascita:</strong> <?= esc_html($citta_nascita) ?><br>
        <strong>Via/Domicilio:</strong> <?= esc_html($via_domicilio) ?><br>
        <strong>CAP:</strong> <?= esc_html($cap_residenza) ?><br>
        <strong>Telefono:</strong> <?= esc_html($telefono) ?><br>
    </div>
    <div style="flex:1 1 30%;">
        <strong>Cognome:</strong> <?= esc_html($cognome) ?><br>
        <strong>Sesso:</strong> <?= esc_html($sesso) ?><br>
        <strong>Codice Fiscale:</strong> <?= esc_html($codice_fiscale) ?><br>
        <strong>Città di residenza:</strong> <?= esc_html($citta_residenza) ?><br>
        <strong>Email:</strong> <?= esc_html($email) ?><br>
        <strong>Sport:</strong> <?= esc_html($sport) ?> (data: <?= esc_html($data_attivita) ?>)<br>
    </div>
</div>

<hr style="border:0;border-top:1px solid #000;margin:10px 0 10px 0;">
<?php else: ?>

        <!-- ===== MINORENNE: dati del minore compilati, genitore in bianco ===== -->
<p><strong>Dati del minore</strong></p>
<div style="display:flex;flex-wrap:wrap;gap:4px 4px;">
    <div style="flex:1 1 30%;">
        <strong>Nome:</strong> <?= esc_html($nome) ?><br>
        <strong>Data di nascita:</strong> <?= esc_html($data_nascita) ?><br>
        <strong>Città di nascita:</strong> <?= esc_html($citta_nascita) ?><br>
        <strong>Via/Domicilio:</strong> <?= esc_html($via_domicilio) ?><br>
        <strong>CAP:</strong> <?= esc_html($cap_residenza) ?><br>
        <strong>Telefono:</strong> <?= esc_html($telefono) ?><br>
    </div>
    <div style="flex:1 1 30%;">
        <strong>Cognome:</strong> <?= esc_html($cognome) ?><br>
        <strong>Sesso:</strong> <?= esc_html($sesso) ?><br>
        <strong>Codice Fiscale:</strong> <?= esc_html($codice_fiscale) ?><br>
        <strong>Città di residenza:</strong> <?= esc_html($citta_residenza) ?><br>
        <strong>Email:</strong> <?= esc_html($email) ?><br>
        <strong>Sport:</strong> <?= esc_html($sport) ?> (data: <?= esc_html($data_attivita) ?>)<br>
    </div>
</div>

        <hr style="border:0;border-top:1px solid #000;margin:10px 0px 10px 0;">

        <p><strong>Dati del genitore/tutore</strong>
            *Nome: __________________________________  Cognome: ___________________________________<br>
			<br>
            *Data e luogo nascita: ______________ – _______________________*C.Fiscale: ______________________________<br>
			<br>
            *Residenza: Via ________________________________ N° _____ CAP ________ Città _________________________<br>
			<br>
            Telefono: _______________________________  Email: ___________________________________________<br>
        </p>
        <?php endif; ?>

    <p>Il sottoscritto <strong><?= esc_html("$nome $cognome") ?></strong> (o il genitore/tutore, qualora soggetto minore) i cui dati sono visibili in calce CHIEDE di poter essere ammesso/ammettere il minore in qualità di atleta tesserato e DICHIARA per se o per il minore:<br>
    1. Di godere di piena SALUTE e di trovarsi in condizione fisica ottimale tale da poter svolgere un attività cardio vascolare elevata, di non aver assunto sostanze stupefacenti o alcol nei precedenti giorni e qualora fosse avvenuto di sollevare il direttivo da ogni responsabilità in merito. Dichiara altresì di essere in possesso di idoneità medica attestante la sana e robusta costituzione e di impegnarsi a consegnare entro breve tempo l'attestazione medica che comprovi tale stato fisico entro 7 giorni dalla presente, sollevo quindi totalmente l'asd oltrecity ed il suo direttivo da ogni eventuale complicanza fisica, sgravandoli fin da ora di ogni eventuale riguardo</p>
    <p><strong>Firma _________________________</strong></p>

    <p>2. Di aver PRESO VISIONE dello Statuto e dei Regolamenti dell'Associazione allegati alla presente domanda di ammissione o visibili sul sito web e di accettarli e rispettarli in ogni punto.<br>
    3. Di impegnarsi al pagamento della QUOTA associativa annuale e dei contributi associativi variabili a seconda dell’attività scelta come da regolamento in corso.<br>
    4. Di acconsentire al TRATTAMENTO DEI DATI PERSONALI da parte dell'associazione i cui dati in calce, ai sensi ex art. 13 del Regolamento (UE) 2016/679, la cui informativa allegata alla presente è stata visionata in ogni sua parte, conscio che la stessa è gestita dal Presidente in carica e tenuta presso la sede associativa.<br>
	5. Di poter esercitare in qualsiasi momento il diritto alla richiesta di CANCELLAZIONE e/o modifica totale o parziale degli stessitramite invio raccomandata AR agli indirizzi in calce<br>
    6. Di essere stato informato, conscio e responsabile dei RISCHI che le attività comportano ed esonerare l'organizzazione per danni subiti o causati a e da terze parti, compresi infortuni personali, nonché di CORRISPONDERE IN SOLIDO eventuali danni causati a cose o attrezzature non proprie.<br>
	7. Di esonerare da ogni e qualsiasi RESPONSABILITA' derivante da lesioni fisiche che potrebbero verificarsi in seguito ad imperizia, imprudenza e negligenza da parte dello stesso Atleta i cui dati sono in calce ed a terzi. Dichiara altresì di aver compreso che le PROTEZIONI non devono essere rimosse fino all'uscita dal campo di gioco e di assumersi ogni responsabilità sull'uso improprio dell'attrezzatura e sui danni che con essa può causare a terze parti.</p>
    <p><strong>Firma _________________________</strong></p>

    <p>8. Di voler ricevere INFORMAZIONI riguardanti l'attività associativa dal sito web e tramite supporti digitali (newsletter, mail, WhatsApp, Telegram, SMS).<br>
    9. Di fornire autorizzazione al ricevimento delle offerte ai soci da parte di partner commerciali e agli eventi futuri.<br>

    <p><strong>Firma _________________________</strong></p>
    <p>10. Si autorizza l'asd oltrecity a eseguire FOTOGRAFIE e riprese video  del sottoscritto/del minore durante lo svolgimento delle attività e degli eventi out/in door . Contestualmente, si autorizza la pubblicazione delle stesse su vari supporti    □ facebook,    □ instagram,    □ whatsapp,    □ oltrecity.com, e .it,    □ You tube,     □ Telegram ai soli fini istituzionali e per pubblicizzare le attività.<br>
     N.B. Qualora il soggetto si inserisce volontariamente all'interno delle foto e delle riprese, rende palese la sua volontà alla pubblicazione delle stesse come da presente postilla pur non avendo accettato la presente con l'apposizione della propria firma</p>
    <p><strong>Firma _________________________</strong></p>
    <p style="margin-top:10px;">Luogo e data: _____________ <?= date('d/m/Y') ?><br>
  </div> <!-- fine riepilogo-iscrizione-wrapper -->
</div> <!-- fine riepilogo-scroll-container -->

<div style="text-align:center;margin:20px 0;">
    <button onclick="window.print()" 
            style="padding:10px 16px;font-size:14px;background:#000;color:#fff;border:none;border-radius:5px;cursor:pointer;">
            🖨 Stampa / Salva PDF
    </button>
</div>

<style>
  /* Schermo: normale */
  @media screen {
    #riepilogo-iscrizione-wrapper{
      background:#fff; color:#000;
      width: 200px; margin: 10px auto; padding: 0;
    }
  }

  /* Stampa: una pagina A4, margini stretti, tutto dall'alto */
  @media print {
    @page {
      size: A4 portrait;
      margin: 5mm;
    }

    html, body {
      margin: 0 !important;
      padding: 0 !important;
      background: #fff !important;
    }

    /* Nasconde tutto il resto */
    body * { visibility: hidden !important; }

    /* Mostra solo il wrapper e i suoi figli */
    #riepilogo-iscrizione-wrapper,
    #riepilogo-iscrizione-wrapper * {
      visibility: visible !important;
    }

    /* Posiziona in alto pagina e centra, con larghezza ~180mm (0.5 cm laterali) */
    #riepilogo-iscrizione-wrapper{
    position: fixed  !important;      /* resta ancorato in alto */
    top: 0; left: 0; right: 0; 
	margin: 0 auto !important;
    width: 190mm;
    padding: 0;
    color: #000 !important;
    background: #fff !important;
    font-size: 10pt;
    line-height: 1.2;
	/* PERMETTI i salti pagina dove servono */
    page-break-inside: auto !important;
    overflow: visible !important;
    transform: none !important;
    }
  /* NON bloccare i salti pagina su tutti gli elementi */
  #riepilogo-iscrizione-wrapper * {
    page-break-inside: auto !important;
  }

  /* Evita solo spezzature brutte su titoli o righe firma */
  #riepilogo-iscrizione-wrapper h2,
  #riepilogo-iscrizione-wrapper h3,
  #riepilogo-iscrizione-wrapper p strong {
    page-break-after: avoid !important;
  }
	  
    /* Compatta i margini dei titoli e paragrafi */
    #riepilogo-iscrizione-wrapper h2{ margin: 0 0 3pt 0; font-size: 12pt; }
    #riepilogo-iscrizione-wrapper h3{ margin: 3pt 0; font-size: 10pt; }
    #riepilogo-iscrizione-wrapper p { margin: 3pt 0; }

    /* Rimuove il bottone di stampa in output */
    button, .no-print{ display: none !important; }

    /* Evita spezzature interne (per sicurezza) */
    #riepilogo-iscrizione-wrapper { page-break-inside: avoid; }
    #riepilogo-iscrizione-wrapper * { page-break-inside: avoid; }
  }
</style>

<?php
return ob_get_clean();
});