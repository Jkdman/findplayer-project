<?php
if (!defined('ABSPATH')) exit;

/**
 * METABOX — Chiusura evento + invio votazioni
 */

/* 🔹 REGISTRA METABOX */
add_action('add_meta_boxes', function () {
  add_meta_box(
    'fp_evento_chiusura',
    'Chiusura evento',
    'fp_render_evento_chiusura_metabox',
    'page', // ⚠️ CAMBIA se il tuo CPT evento ha slug diverso
    'side',
    'high'
  );
});

/* 🔹 UI METABOX */
function fp_render_evento_chiusura_metabox($post) {

  wp_nonce_field('fp_chiudi_evento_nonce', 'fp_chiudi_evento_nonce_field');

  $stato = get_post_meta($post->ID, 'fp_stato_evento', true) ?: 'programmato';
  $chiuso = get_post_meta($post->ID, 'fp_evento_chiuso', true);

  echo '<p><strong>Stato evento:</strong><br>' . esc_html($stato) . '</p>';

  if ($chiuso) {
    echo '<p style="color:green;"><strong>Evento chiuso</strong></p>';
    return;
  }
  ?>
  <button
    type="submit"
    name="fp_chiudi_evento"
    class="button button-primary"
    onclick="return confirm('Confermi la chiusura dell’evento e l’invio delle mail di votazione?');"
  >
    Chiudi evento e invia votazioni
  </button>
  <?php
}

/* 🔹 HANDLER SALVATAGGIO */
add_action('save_post_page', 'fp_handle_chiusura_evento');

function fp_handle_chiusura_evento($post_id) {

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

  if (!isset($_POST['fp_chiudi_evento_nonce_field']) ||
      !wp_verify_nonce($_POST['fp_chiudi_evento_nonce_field'], 'fp_chiudi_evento_nonce')
  ) return;

  if (!isset($_POST['fp_chiudi_evento'])) return;

  if (get_post_meta($post_id, 'fp_evento_chiuso', true)) return;

  if (!function_exists('fp_chiudi_evento_e_invia_token')) return;

$post = get_post($post_id);
if (!$post) return;

if (!preg_match('/id="(\d+)"/', $post->post_content, $m)) return;

$evento_id = (int)$m[1];

  // 🚀 AZIONE CORE
  fp_chiudi_evento_e_invia_token($post_id);

  update_post_meta($post_id, 'fp_evento_chiuso', 1);
  update_post_meta($post_id, 'fp_stato_evento', 'svolto');
}