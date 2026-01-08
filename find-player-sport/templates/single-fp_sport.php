<?php
if (!defined('ABSPATH')) exit;

get_header();

global $post;

$sport_slug = $post->post_name; // es: calcio
$sport_name = get_the_title();
$seo_title  = get_post_meta($post->ID, '_fp_seo_title', true);
$seo_desc   = get_post_meta($post->ID, '_fp_seo_desc', true);
$seo_intro  = get_post_meta($post->ID, '_fp_seo_intro', true);

// SEO override
if ($seo_title) {
    add_filter('pre_get_document_title', fn() => $seo_title);
}

if ($seo_desc) {
    add_action('wp_head', function () use ($seo_desc) {
        echo '<meta name="description" content="' . esc_attr($seo_desc) . '">' . PHP_EOL;
    });
}
?>

<main class="fp-sport-page" style="max-width:1200px;margin:auto;padding:40px 20px;">

    <header class="fp-sport-header">
        <h1>Giocare a <?= esc_html($sport_name); ?> in Sardegna | Find Player</h1>
    </header>

    <?php if ($seo_intro): ?>
        <section class="fp-sport-intro">
            <?= wpautop($seo_intro); ?>
        </section>
    <?php endif; ?>

<div class="fp-tabs">
    <button class="fp-tab active" data-tab="giocatori">Giocatori</button>
    <button class="fp-tab" data-tab="aziende">Aziende</button>
</div>

<div class="fp-tab-content active" id="tab-giocatori">

<?php
$args = [
    'post_type'      => 'fp_giocatore',
    'posts_per_page' => 12,
    'meta_query'     => [
        [
            'key'     => 'fp_discipline',
            'value'   => 's:5:"sport";s:' . strlen($sport_slug) . ':"' . $sport_slug . '"',
            'compare' => 'LIKE'
        ]
    ]
];

$giocatori = new WP_Query($args);

if ($giocatori->have_posts()):
    echo '<div class="fp-grid">';
    while ($giocatori->have_posts()): $giocatori->the_post();
        ?>
        <div class="fp-card">
            <h3><?php the_title(); ?></h3>
            <a href="<?php the_permalink(); ?>">Vedi profilo</a>
        </div>
        <?php
    endwhile;
    echo '</div>';
    wp_reset_postdata();
else:
    echo '<p>Nessun giocatore trovato.</p>';
endif;
?>
</div>

<div class="fp-tab-content" id="tab-aziende">

<?php
$args = [
    'post_type'      => 'lf_azienda',
    'posts_per_page' => 12,
    'meta_query'     => [
        [
            'key'     => 'lf_settore_sportivo',
            'value'   => $sport_slug,
            'compare' => 'LIKE'
        ]
    ]
];

$aziende = new WP_Query($args);

if ($aziende->have_posts()):
    echo '<div class="fp-grid">';
    while ($aziende->have_posts()): $aziende->the_post(); ?>
        <div class="fp-card">
            <h3><?php the_title(); ?></h3>
            <a href="<?php the_permalink(); ?>">Vedi azienda</a>
        </div>
    <?php endwhile;
    echo '</div>';
    wp_reset_postdata();
else:
    echo '<p>Nessuna azienda trovata per questo sport.</p>';
endif;
?>

</div>



<style>
.fp-tabs {
    margin:20px 0;
    display:flex;
    gap:12px;
}

.fp-tab {
    padding:10px 20px;
    border:1px solid #999;
    background:#d6d6d6;
    color:#222;
    cursor:pointer;
    font-weight:600;
    border-radius:6px;
    transition:all .2s ease;
}

.fp-tab:hover {
    background:#bdbdbd;
}

.fp-tab.active {
    background:#111;
    color:#fff;
    border-color:#111;
}

.fp-tab-content {
    display:none;
}

.fp-tab-content.active {
    display:block;
}

.fp-grid {
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
    gap:16px;
}

.fp-card {
    border:1px solid #ddd;
    padding:16px;
    border-radius:8px;
    background:#fff;
}
</style>


</main>

<script>
document.querySelectorAll('.fp-tab').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.fp-tab, .fp-tab-content').forEach(el => el.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
    });
});
</script>

<?php get_footer(); ?>