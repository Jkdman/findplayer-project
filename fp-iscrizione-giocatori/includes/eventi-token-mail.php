<?php
if (!defined('ABSPATH')) exit;

/**
 * Genera token e invia mail per un evento
 */
function fp_chiudi_evento_e_invia_token($evento_id) {

  // 1) recupera evento
  $evento = fp_sb_get("fp_eventi?select=id,titolo,data_evento,stato& id=eq.$evento_id&limit=1");
  if (is_wp_error($evento) || empty($evento)) return false;

  $evento = $evento[0];
  if ($evento['stato'] !== 'programmato') return false;

  // 2) partecipanti presenti
  $partecipanti = fp_sb_get(
    "fp_eventi_partecipanti?select=giocatore_id&evento_id=eq.$evento_id&presente=is.true"
  );

  if (is_wp_error($partecipanti) || empty($partecipanti)) return false;

  // 3) chiude evento
  fp_sb_patch("fp_eventi?id=eq.$evento_id", [
    'stato' => 'svolto'
  ]);

  // 4) genera token + mail
  foreach ($partecipanti as $p) {

    $giocatore_id = (int)$p['giocatore_id'];

    // recupera dati giocatore (WP user o CPT)
    $user = get_user_by('id', $giocatore_id);
    if (!$user) continue;

    // crea token
    $token = fp_sb_post('fp_voti_token', [[
      'evento_id'  => $evento_id,
      'votante_id' => $giocatore_id,
      'scade_il'   => gmdate('c', strtotime('+72 hours'))
    ]]);

    if (is_wp_error($token)) continue;

    $token_uuid = $token[0]['token'];

    // link voto
    $link = site_url('/vota-evento/?token=' . $token_uuid);

    // mail
    $subject = 'Votazione evento ' . $evento['titolo'];
    $message = "
Ciao {$user->first_name},

l’attività \"{$evento['titolo']}\" del {$evento['data_evento']} si è conclusa.

Ora puoi valutare gli altri partecipanti:
$link

⏰ Il link è valido per 72 ore.

Grazie per la collaborazione.
";

    wp_mail($user->user_email, $subject, $message);
  }

  return true;
}