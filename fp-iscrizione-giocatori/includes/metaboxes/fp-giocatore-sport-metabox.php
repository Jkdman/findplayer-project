<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
    add_meta_box(
        'fp_sport_metabox',
        'Sport praticati',
        'fp_render_sport_metabox',
        'fp_giocatore',
        'normal',
        'high'
    );
});

function fp_render_sport_metabox($post) {

    $discipline = get_post_meta($post->ID, 'fp_discipline', true);
    if (!is_array($discipline)) $discipline = [];

echo '<p><strong>Sport già inseriti</strong> <span style="color:#666">(utente non può modificarli, tu sì)</span></p>';

foreach ($discipline as $i => $d) {

    $sport_corrente = sanitize_text_field($d['sport'] ?? '');

    // compatibilità: se vecchio schema aveva solo "livello"
    $liv_user = intval($d['livello_user'] ?? ($d['livello'] ?? 0));
    $liv_real = intval($d['livello_real'] ?? $liv_user);

    echo '<div style="display:flex;gap:10px;margin-bottom:8px;align-items:center">';

    // sport read-only (admin può solo rimuovere, non rinominare qui)
    echo '<input type="text" value="'.esc_attr($sport_corrente).'" disabled style="width:55%">';

    // livello reale EDITABILE SOLO DA ADMIN
    echo '<label style="display:flex;flex-direction:column;font-size:12px;color:#333">';
    echo 'Livello reale';
    echo '<input type="number" name="fp_admin_level['.$i.']" value="'.esc_attr($liv_real).'" min="1" max="10" style="width:90px">';
    echo '</label>';

    // livello utente solo informativo
    echo '<div style="font-size:12px;color:#666;min-width:120px">';
    echo 'Utente: <strong>'.intval($liv_user).'</strong>';
    echo '</div>';

    // rimozione sport
    echo '<label style="font-size:12px;color:#b00">';
    echo '<input type="checkbox" name="fp_remove_sport[]" value="'.$i.'"> Rimuovi';
    echo '</label>';

    echo '</div>';
}

    echo '<hr>';
    echo '<p><strong>Aggiungi nuovo sport</strong></p>';

    echo '<select name="fp_new_sport[]">';
    echo '<option value="">Seleziona sport</option>';
    foreach (fp_get_all_sports() as $sport) {
        echo '<option value="'.esc_attr($sport->post_title).'">';
        echo esc_html($sport->post_title);
        echo '</option>';
    }
    echo '</select>';

    echo ' ';

    echo '<select name="fp_new_level[]">';
    for ($i=1;$i<=10;$i++) {
        echo "<option value='$i'>$i</option>";
    }
    echo '</select>';
}

add_action('save_post_fp_giocatore', function ($post_id) {

    // sicurezza base
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

// prendo sport esistenti
$discipline = get_post_meta($post_id, 'fp_discipline', true);
if (!is_array($discipline)) $discipline = [];

// 1) admin modifica livello reale
if (!empty($_POST['fp_admin_level']) && is_array($_POST['fp_admin_level'])) {
    foreach ($_POST['fp_admin_level'] as $idx => $val) {
        $idx = intval($idx);
        if (!isset($discipline[$idx])) continue;

        $discipline[$idx]['livello_real'] = max(1, min(10, intval($val)));

        // migrazione soft: se esiste ancora "livello" vecchio lo trasformo
        if (!isset($discipline[$idx]['livello_user']) && isset($discipline[$idx]['livello'])) {
            $discipline[$idx]['livello_user'] = intval($discipline[$idx]['livello']);
        }
        if (!isset($discipline[$idx]['livello_real']) && isset($discipline[$idx]['livello'])) {
            $discipline[$idx]['livello_real'] = intval($discipline[$idx]['livello']);
        }
        unset($discipline[$idx]['livello']); // pulizia schema vecchio
    }
}

// 2) admin rimuove sport
if (!empty($_POST['fp_remove_sport']) && is_array($_POST['fp_remove_sport'])) {
    foreach ($_POST['fp_remove_sport'] as $idx) {
        $idx = intval($idx);
        unset($discipline[$idx]);
    }
    $discipline = array_values($discipline);
}

// salvo subito (prima dei nuovi)
update_post_meta($post_id, 'fp_discipline', $discipline);

    // se non arrivano nuovi sport → esci
    if (empty($_POST['fp_new_sport']) || !is_array($_POST['fp_new_sport'])) return;

    // mappa sport già presenti (lowercase → evita duplicati)
    $existing = [];
    foreach ($discipline as $d) {
        if (!empty($d['sport'])) {
            $existing[strtolower($d['sport'])] = true;
        }
    }

    // nuovi sport
    foreach ($_POST['fp_new_sport'] as $i => $sport) {

        $sport = sanitize_text_field($sport);
        $liv   = intval($_POST['fp_new_level'][$i] ?? 0);

        if (!$sport || $liv < 1 || $liv > 10) continue;

        // evita duplicati
        if (isset($existing[strtolower($sport)])) continue;

$discipline[] = [
    'sport'        => $sport,
    'livello_user' => $liv,
    'livello_real' => $liv
];

        $existing[strtolower($sport)] = true;
    }

    update_post_meta($post_id, 'fp_discipline', $discipline);
});