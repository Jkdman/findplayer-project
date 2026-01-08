<?php
if (!defined('ABSPATH')) exit;

function fp_calcola_rating_giocatore_disciplina($giocatore_id, $disciplina) {

  if (!function_exists('fp_sb_get') || empty($disciplina)) {
    return 0;
  }

  $disciplina = urlencode($disciplina);

  $voti = fp_sb_get(
    "fp_voti?select=punteggio,created_at
     &votato_id=eq.$giocatore_id
     &disciplina=eq.$disciplina"
  );

  if (is_wp_error($voti) || empty($voti)) {
    return 0;
  }

  $somma = 0;
  $count = 0;
  $recenti = 0;
  $limite = strtotime('-90 days');

  foreach ($voti as $v) {
    $somma += (int)$v['punteggio'];
    $count++;

    if (!empty($v['created_at']) && strtotime($v['created_at']) >= $limite) {
      $recenti++;
    }
  }

  if ($count === 0) return 0;

  $media = $somma / $count;

  $fattore_affidabilita = min(1, $count / 5);
  $fattore_attivita     = min(1, 0.7 + ($recenti / 10));

  return round($media * $fattore_affidabilita * $fattore_attivita, 2);
}