<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
  add_meta_box(
    'fp_privacy_metabox',
    'Privacy scheda giocatore',
    'fp_render_privacy_metabox',
    'fp_giocatore',
    'side',
    'high'
  );
});

function fp_render_privacy_metabox($post) {

  $val = get_post_meta($post->ID, 'fp_mostra_dati_pubblici', true);
  if ($val === '') $val = '1'; // default: mostra

  wp_nonce_field('fp_privacy_nonce', 'fp_privacy_nonce_field');
  ?>
  <label style="display:block;margin-bottom:6px;">
    <input type="radio" name="fp_mostra_dati_pubblici" value="1" <?php checked($val, '1'); ?>>
    Mostra i miei dati
  </label>

  <label style="display:block;">
    <input type="radio" name="fp_mostra_dati_pubblici" value="0" <?php checked($val, '0'); ?>>
    Mostra solo nickname
  </label>
  <?php
}

add_action('save_post_fp_giocatore', function ($post_id) {

  if (!isset($_POST['fp_privacy_nonce_field'])) return;
  if (!wp_verify_nonce($_POST['fp_privacy_nonce_field'], 'fp_privacy_nonce')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

  if (isset($_POST['fp_mostra_dati_pubblici'])) {
    update_post_meta(
      $post_id,
      'fp_mostra_dati_pubblici',
      sanitize_text_field($_POST['fp_mostra_dati_pubblici'])
    );
  }
});