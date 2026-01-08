<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [fp_vota]
 * Pagina: /vota-evento/?token=UUID
 */

add_shortcode('fp_vota', 'fp_render_voting_page');
add_action('wp_enqueue_scripts', 'fp_voting_assets');

add_action('wp_ajax_fp_submit_votes', 'fp_submit_votes');
add_action('wp_ajax_nopriv_fp_submit_votes', 'fp_submit_votes');

function fp_voting_assets() {
  if (!is_singular()) return;
  global $post;
  if (!$post || !has_shortcode($post->post_content, 'fp_vota')) return;

  wp_enqueue_script('fp-vote-js', plugin_dir_url(__FILE__) . 'fp-vote.js', ['jquery'], '1.0', true);
  wp_localize_script('fp-vote-js', 'FPVOTE', [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('fp_vote_nonce'),
  ]);
}

function fp_supabase_headers() {
  return [
    'Content-Type'  => 'application/json',
    'apikey'        => defined('FP_SUPABASE_API_KEY') ? FP_SUPABASE_API_KEY : '',
    'Authorization' => 'Bearer ' . (defined('FP_SUPABASE_API_KEY') ? FP_SUPABASE_API_KEY : ''),
  ];
}

function fp_sb_get($path) {
  $base = rtrim(FP_SUPABASE_URL, '/') . '/rest/v1/';
  $url  = $base . ltrim($path, '/');
  $res  = wp_remote_get($url, ['headers' => fp_supabase_headers(), 'timeout' => 15]);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  $body = wp_remote_retrieve_body($res);
  if ($code < 200 || $code >= 300) return new WP_Error('sb_get_failed', $body, ['code' => $code]);
  return json_decode($body, true);
}

function fp_sb_post($path, $payload) {
  $base = rtrim(FP_SUPABASE_URL, '/') . '/rest/v1/';
  $url  = $base . ltrim($path, '/');
  $res  = wp_remote_post($url, [
    'headers' => array_merge(fp_supabase_headers(), ['Prefer' => 'return=representation']),
    'body'    => wp_json_encode($payload),
    'timeout' => 15
  ]);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  $body = wp_remote_retrieve_body($res);
  if ($code < 200 || $code >= 300) return new WP_Error('sb_post_failed', $body, ['code' => $code]);
  return json_decode($body, true);
}

function fp_sb_patch($path, $payload) {
  $base = rtrim(FP_SUPABASE_URL, '/') . '/rest/v1/';
  $url  = $base . ltrim($path, '/');
  $res  = wp_remote_request($url, [
    'method'  => 'PATCH',
    'headers' => array_merge(fp_supabase_headers(), ['Prefer' => 'return=representation']),
    'body'    => wp_json_encode($payload),
    'timeout' => 15
  ]);
  if (is_wp_error($res)) return $res;
  $code = wp_remote_retrieve_response_code($res);
  $body = wp_remote_retrieve_body($res);
  if ($code < 200 || $code >= 300) return new WP_Error('sb_patch_failed', $body, ['code' => $code]);
  return json_decode($body, true);
}

function fp_render_voting_page() {
  $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
  if (!$token) return '<div class="fp-box fp-error">Token mancante.</div>';

  // 1) recupera token
  $now_iso = gmdate('c');

  $t = fp_sb_get("fp_voti_token?select=id,token,evento_id,votante_id,stato,scade_il&token=eq.$token&limit=1");
  if (is_wp_error($t) || empty($t)) return '<div class="fp-box fp-error">Token non valido.</div>';

  $t = $t[0];

  if ($t['stato'] !== 'valido') return '<div class="fp-box fp-error">Token già utilizzato o non valido.</div>';
  if (strtotime($t['scade_il']) < time()) return '<div class="fp-box fp-error">Token scaduto.</div>';

  // 2) carica partecipanti presenti e rimuove votante
  $evento_id  = (int)$t['evento_id'];
  $votante_id = (int)$t['votante_id'];

  $pars = fp_sb_get("fp_eventi_partecipanti?select=giocatore_id,presente&evento_id=eq.$evento_id&presente=is.true");
  if (is_wp_error($pars)) return '<div class="fp-box fp-error">Errore caricamento partecipanti.</div>';

  $ids = [];
  foreach ($pars as $p) {
    $gid = (int)$p['giocatore_id'];
    if ($gid && $gid !== $votante_id) $ids[] = $gid;
  }

  if (empty($ids)) return '<div class="fp-box fp-error">Nessun partecipante da votare.</div>';

  // 3) UI
  ob_start(); ?>
  <div class="fp-box fp-vote">
    <h2>Votazione evento #<?php echo esc_html($evento_id); ?></h2>
    <p>Assegna un punteggio da 1 a 10 agli altri partecipanti.</p>

    <form id="fp-vote-form">
      <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>" />

      <?php foreach ($ids as $votato_id): ?>
        <div class="fp-vote-row">
          <div class="fp-vote-name">Giocatore #<?php echo esc_html($votato_id); ?></div>
          <input type="number" min="1" max="10" step="1" name="vote[<?php echo esc_attr($votato_id); ?>]" required />
        </div>
      <?php endforeach; ?>

      <button type="submit" class="fp-btn">Invia voti</button>
      <div id="fp-vote-msg" style="margin-top:10px;"></div>
    </form>
  </div>
  <?php
  return ob_get_clean();
}

function fp_submit_votes() {
  check_ajax_referer('fp_vote_nonce', 'nonce');

  $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
  $votes = isset($_POST['vote']) && is_array($_POST['vote']) ? $_POST['vote'] : [];

  if (!$token || empty($votes)) wp_send_json_error(['message' => 'Dati incompleti.']);

  // carica token
  $t = fp_sb_get("fp_voti_token?select=id,token,evento_id,votante_id,stato,scade_il&token=eq.$token&limit=1");
  if (is_wp_error($t) || empty($t)) wp_send_json_error(['message' => 'Token non valido.']);
  $t = $t[0];

  if ($t['stato'] !== 'valido') wp_send_json_error(['message' => 'Token già usato.']);
  if (strtotime($t['scade_il']) < time()) wp_send_json_error(['message' => 'Token scaduto.']);

  $evento_id  = (int)$t['evento_id'];
  $votante_id = (int)$t['votante_id'];

  // recupera lista votabili (presenti, escluso votante)
  $pars = fp_sb_get("fp_eventi_partecipanti?select=giocatore_id&evento_id=eq.$evento_id&presente=is.true");
  if (is_wp_error($pars)) wp_send_json_error(['message' => 'Errore partecipanti.']);

  $allowed = [];
  foreach ($pars as $p) {
    $gid = (int)$p['giocatore_id'];
    if ($gid && $gid !== $votante_id) $allowed[$gid] = true;
  }

  // prepara insert voti
  $insert = [];
  foreach ($votes as $votato_id => $punteggio) {
    $votato_id = (int)$votato_id;
    $punteggio = (int)$punteggio;

    if (!isset($allowed[$votato_id])) continue;
    if ($punteggio < 1 || $punteggio > 10) continue;

    $insert[] = [
      'evento_id'  => $evento_id,
      'votante_id' => $votante_id,
      'votato_id'  => $votato_id,
      'punteggio'  => $punteggio
    ];
  }

  if (empty($insert)) wp_send_json_error(['message' => 'Nessun voto valido.']);

  // salva voti
  $saved = fp_sb_post('fp_voti', $insert);
  if (is_wp_error($saved)) wp_send_json_error(['message' => 'Errore salvataggio voti.']);

  // marca token come usato
  $patched = fp_sb_patch("fp_voti_token?token=eq.$token", [
    'stato'    => 'usato',
    'usato_il' => gmdate('c')
  ]);
  if (is_wp_error($patched)) {
    // voti salvati ma token non aggiornato: caso raro, ma gestibile
    wp_send_json_error(['message' => 'Voti salvati, ma errore chiusura token. Contatta supporto.']);
  }

  wp_send_json_success(['message' => 'Votazione completata. Grazie!']);
}