<?php
if (!defined('ABSPATH')) exit;
get_header();

$post_id = get_the_ID();

$nickname = get_post_meta($post_id, 'fp_nickname', true);
$citta    = get_post_meta($post_id, 'fp_luogo_residenza', true);
$foto     = get_post_meta($post_id, 'fp_foto_url', true);
$disc     = get_post_meta($post_id, 'fp_discipline', true);
?>

<div class="fp-single-wrapper" style="max-width:1100px;margin:40px auto;padding:20px">

    <?php if ($foto): ?>
        <img src="<?php echo esc_url($foto); ?>" style="width:100%;max-height:360px;object-fit:cover;border-radius:14px">
    <?php endif; ?>

    <h1><?php echo esc_html($nickname); ?></h1>

    <?php if ($citta): ?>
        <p><strong>Città:</strong> <?php echo esc_html($citta); ?></p>
    <?php endif; ?>

    <div class="fp-description">
        <?php the_content(); ?>
    </div>
<?php
// ======================================================
// EVENTI CREATI DAL GIOCATORE (CPT findplayer_event)
// Legge gli eventi collegati via meta: fp_giocatore_id
// ======================================================
// ======================================================
// EVENTI CREATI DAL GIOCATORE
// Meta chiave: fp_creatore_giocatore
// ======================================================
$player_id = get_the_ID();

$eventi = get_posts([
    'post_type'      => 'findplayer_event',
    'post_status'    => 'publish',
    'posts_per_page' => 20,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'   => 'fp_creatore_giocatore',
            'value' => $player_id,
            'compare' => '='
        ]
    ],
]);


if ($eventi): ?>
    <h3 style="margin-top:30px;">Attività / Eventi creati</h3>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;">
        <?php foreach ($eventi as $ev):
            $data_evento = get_post_meta($ev->ID, 'data_evento', true);
            $citta_ev    = get_post_meta($ev->ID, 'citta', true);
            $disc_ev     = get_post_meta($ev->ID, 'disciplina', true);
            ?>
            <a href="<?php echo get_permalink($ev->ID); ?>" style="display:block;border:1px solid #e5e5e5;border-radius:12px;padding:14px;text-decoration:none;background:#fff;">
                <div style="font-weight:700;color:#111;margin-bottom:6px;">
                    <?php echo esc_html($ev->post_title); ?>
                </div>

                <?php if ($data_evento): ?>
                    <div style="font-size:13px;color:#444;">📅 <?php echo esc_html($data_evento); ?></div>
                <?php endif; ?>

                <?php if ($disc_ev): ?>
                    <div style="font-size:13px;color:#444;">🏷️ <?php echo esc_html($disc_ev); ?></div>
                <?php endif; ?>

                <?php if ($citta_ev): ?>
                    <div style="font-size:13px;color:#444;">📍 <?php echo esc_html($citta_ev); ?></div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <h3 style="margin-top:30px;">Attività / Eventi creati</h3>
    <p>Nessun evento collegato a questo giocatore.</p>
<?php endif; ?>

    <?php if (is_array($disc) && !empty($disc)): ?>
        <h3>Sport praticati</h3>
        <ul>
            <?php foreach ($disc as $d): ?>
                <li>
                    <?php echo esc_html($d['sport']); ?>
                    – livello <?php echo intval($d['livello']); ?>/10
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</div>

<?php get_footer(); ?>