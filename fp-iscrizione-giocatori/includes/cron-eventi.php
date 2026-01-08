<?php
if (!defined('ABSPATH')) exit;

/**
 * Cron: chiude eventi passati e invia votazioni
 */

// 1) schedulazione
add_action('init', function () {

  if (!wp_next_scheduled('fp_cron_chiudi_eventi')) {
    wp_schedule_event(time(), 'hourly', 'fp_cron_chiudi_eventi');
  }

  if (!wp_next_scheduled('fp_cron_scadenza_token')) {
    wp_schedule_event(time(), 'hourly', 'fp_cron_scadenza_token');
  }

  if (!wp_next_scheduled('fp_cron_reminder_voti')) {
    wp_schedule_event(time(), 'twicedaily', 'fp_cron_reminder_voti');
  }

});


// 2) hook cron
add_action('fp_cron_chiudi_eventi', 'fp_cron_chiudi_eventi_callback');

function fp_cron_chiudi_eventi_callback() {

  $oggi = date('Y-m-d');

  // eventi con data passata, ancora non chiusi
  $eventi = fp_sb_get(
    "fp_eventi?select=id,data_evento,stato&data_evento=lte.$oggi&stato=eq.programmato"
  );

  if (is_wp_error($eventi) || empty($eventi)) return;
  
  foreach ($eventi as $evento) {
    fp_chiudi_evento_e_invia_token((int)$evento['id']);
  }
}
add_action('fp_cron_scadenza_token', 'fp_cron_scadenza_token_callback');

function fp_cron_scadenza_token_callback() {

  $now = gmdate('c');

  $tokens = fp_sb_get(
    "fp_voti_token?select=id,token,stato&stato=eq.valido&scade_il=lt.$now"
  );

  if (is_wp_error($tokens) || empty($tokens)) return;

  foreach ($tokens as $t) {
    fp_sb_patch("fp_voti_token?id=eq.{$t['id']}", [
      'stato' => 'scaduto'
    ]);
  }
}

add_action('fp_cron_reminder_voti', 'fp_cron_reminder_voti_callback');

function fp_cron_reminder_voti_callback() {

  $limite = gmdate('c', strtotime('-24 hours'));

$tokens = fp_sb_get(
  "fp_voti_token?select=id,token,votante_id,evento_id,created_at,stato
   &stato=eq.valido
   &reminder_inviato=is.false
   &created_at=lt.$limite"
);

  if (is_wp_error($tokens) || empty($tokens)) return;

foreach ($tokens as $t) {

  $user = get_user_by('id', (int)$t['votante_id']);
  if (!$user) continue;

  $link = site_url('/vota-evento/?token=' . $t['token']);

  wp_mail(
    $user->user_email,
    'Promemoria votazione evento',
    "Ciao {$user->first_name},\n\nnon hai ancora completato la votazione.\n\n$link\n\nIl link scade a breve."
  );

  // ✅ segna reminder inviato (UNA SOLA VOLTA)
  fp_sb_patch("fp_voti_token?id=eq.{$t['id']}", [
    'reminder_inviato' => true
  ]);
}
}