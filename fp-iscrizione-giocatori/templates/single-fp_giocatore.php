<?php
if (!defined('ABSPATH')) exit;

get_header();

if (have_posts()) : while (have_posts()) : the_post();

  $post_id  = get_the_ID();
$player_user_id = intval(get_post_meta($post_id, 'fp_wp_user_id', true));
$player_user    = $player_user_id ? get_user_by('ID', $player_user_id) : null;

$raw = get_post_meta(get_the_ID(), 'fp_mostra_dati_pubblici', true);

/**
 * Normalizzazione privacy:
 * MOSTRA solo se true / 1 / '1'
 */
$mostra_dati = in_array($raw, [true, 1, '1'], true);

  // HERO: foto (featured image) + fallback su meta fp_foto_url
  $thumb_url = get_the_post_thumbnail_url($post_id, 'large');
  $foto_url  = get_post_meta($post_id, 'fp_foto_url', true);
  $hero_img  = $thumb_url ?: $foto_url;

  // Dati base
  $nickname  = get_post_meta($post_id, 'fp_nickname', true) ?: get_the_title();
  $citta     = get_post_meta($post_id, 'fp_luogo_residenza', true); // “città di pratica sportiva”
  $descrizione = get_post_field('post_content', $post_id);

  // Info contatto
  $telefono  = get_post_meta($post_id, 'fp_telefono', true);
  $email     = get_post_meta($post_id, 'fp_email', true);

  // Social
  $instagram = get_post_meta($post_id, 'fp_instagram', true);
  $facebook  = get_post_meta($post_id, 'fp_facebook', true);
  $tiktok    = get_post_meta($post_id, 'fp_tiktok', true);
  $linkedin  = get_post_meta($post_id, 'fp_linkedin', true);

  // Sport + livello
  $discipline = get_post_meta($post_id, 'fp_discipline', true);
  if (!is_array($discipline)) $discipline = [];

  // (Opzionale) Galleria: se in futuro salvi un array di attachment IDs qui, la mostriamo
  $gallery_ids = get_post_meta($post_id, 'fp_gallery_ids', true);
  if (!is_array($gallery_ids)) $gallery_ids = [];

  // Helpers
  $tel_digits = preg_replace('/[^0-9]/', '', (string)$telefono);
  $wa_link = $tel_digits ? 'https://wa.me/' . $tel_digits : '';
  $mail_link = $email ? 'mailto:' . sanitize_email($email) : '';

  ?>
  <div class="fp-player">

    <!-- HERO -->
    <section class="fp-player-hero">
      <?php if (!empty($hero_img)) : ?>
        <div class="fp-player-hero-media">
          <img src="<?php echo esc_url($hero_img); ?>" alt="<?php echo esc_attr($nickname); ?>">
        </div>
      <?php endif; ?>

 <div class="fp-player-hero-meta">
  <h1 class="fp-player-nickname"><?php echo esc_html($nickname); ?></h1>

<?php if ($mostra_dati === '1' && !empty($citta)) : ?>
  <div class="fp-player-city">📍 <?php echo esc_html($citta); ?></div>
<?php endif; ?>


  <?php /* rating hero rimosso: rating solo per disciplina */ ?>
</div>
</section>

    <!-- LAYOUT 2 COLONNE -->
    <section class="fp-player-layout">
      <!-- COLONNA SINISTRA (70%) -->
      <main class="fp-player-main">

        <!-- Sport praticati -->
        <article class="fp-block fp-sport">
          <h2>Sport praticati</h2>

<?php if (!$mostra_dati) : ?>
  <p class="fp-muted">
    Questo giocatore ha scelto di non mostrare i dati personali.
  </p>
<?php endif; ?>

          <?php if (!empty($discipline)) : ?>
            <ul class="fp-sport-list">
  <?php foreach ($discipline as $d) :

    $sport = sanitize_text_field($d['sport'] ?? '');
    $liv_user = intval($d['livello_user'] ?? 0);
    $liv_real = intval($d['livello_real'] ?? 0);

    // fallback sicurezza per dati vecchi
    $liv = $liv_real ?: $liv_user;

    if (!$sport) continue;
    if ($liv < 1 || $liv > 10) $liv = 0;

    // normalizzazione disciplina
    $sport_key = strtolower(trim($sport));

$rating_disciplina = 0;
if (function_exists('fp_calcola_rating_giocatore_disciplina') && $sport_key !== '') {
  $rating_disciplina = fp_calcola_rating_giocatore_disciplina($post_id, $sport_key);
}

  ?>
    <li class="fp-sport-item">
      <span class="fp-sport-badge"><?php echo esc_html($sport); ?></span>

      <?php if ($rating_disciplina > 0): ?>
        <span class="fp-sport-rating">
          ★ <?php echo esc_html(number_format($rating_disciplina, 1)); ?>/10
        </span>
      <?php else: ?>
        <span class="fp-sport-rating fp-muted">
          Nessuna valutazione
        </span>
        
      <?php endif; ?>

      <?php if ($liv): ?>
        <span class="fp-sport-level">Livello: <?php echo esc_html($liv); ?>/10</span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>Nessuno sport indicato.</p>
          <?php endif; ?>
        </article>

        <!-- Eventi in corso -->
        <article class="fp-block fp-eventi-attivi">
          <h2>Eventi in corso</h2>

          <?php
          if (function_exists('fp_get_eventi_attivi_utente') && $player_user_id) :
$eventi_attivi = fp_get_eventi_attivi_giocatore(get_the_ID());
          else :
            $eventi_attivi = [];
          endif;
          ?>

          <?php if (!empty($eventi_attivi)) : ?>
            <ul class="fp-eventi-list">
              <?php foreach ($eventi_attivi as $ev) : ?>
                <li class="fp-evento-item">
                  <strong><?php echo esc_html($ev['titolo']); ?></strong><br>
                  📍 <?php echo esc_html($ev['citta']); ?> –
                  📅 <?php echo esc_html(date('d/m/Y', strtotime($ev['data_evento']))); ?><br>
                  <a href="<?php echo esc_url($ev['url']); ?>" class="fp-link">
                    Vai all’evento →
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <p class="fp-muted">Nessun evento attivo al momento.</p>
          <?php endif; ?>
        </article>

        <!-- Descrizione -->
        <article class="fp-block fp-description">
          <h2>Descrizione</h2>
          <?php if (!empty($descrizione)) : ?>
            <div class="fp-text">
              <?php echo wpautop(wp_kses_post($descrizione)); ?>
            </div>
          <?php else: ?>
            <p>Descrizione non inserita.</p>
          <?php endif; ?>
        </article>

<!-- Galleria foto (in fondo) -->
        <article class="fp-block fp-gallery">
          <h2>Galleria foto</h2>

          <?php if (!empty($gallery_ids)) : ?>
            <ul class="fp-gallery-list">
              <?php foreach ($gallery_ids as $img_id) :
                $img_id = intval($img_id);
                if (!$img_id) continue;
                $url = wp_get_attachment_url($img_id);
                if (!$url) continue;
              ?>
                <li class="fp-gallery-item">
<a href="<?php echo esc_url($url); ?>" data-fp-lightbox>

                    <img src="<?php echo esc_url($url); ?>" alt="">
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p>Nessuna foto in galleria (per ora).</p>
          <?php endif; ?>
        </article>

      </main>
      

      <!-- COLONNA DESTRA (30%) -->
      <aside class="fp-player-aside">

        <!-- Info rapide -->
 <?php if ($mostra_dati)
: ?>
       
        <div class="fp-block fp-quickinfo">
          <h3>Info rapide</h3>

          <ul class="fp-quick-list">
            <?php if (!empty($citta)) : ?>
              <li><strong>Città:</strong> <?php echo esc_html($citta); ?></li>
            <?php endif; ?>

            <?php if (!empty($telefono)) : ?>
              <li><strong>Telefono:</strong> <?php echo esc_html($telefono); ?></li>
            <?php endif; ?>

            <?php if (!empty($email)) : ?>
              <li><strong>Email:</strong> <?php echo esc_html($email); ?></li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Social -->
        <?php if ($mostra_dati)
: ?>

        <div class="fp-block fp-social">
          <h3>Social</h3>
          <ul class="fp-social-list">
            <?php if (!empty($instagram)) : ?><li><a target="_blank" rel="noopener" href="<?php echo esc_url($instagram); ?>">Instagram</a></li><?php endif; ?>
            <?php if (!empty($facebook)) : ?><li><a target="_blank" rel="noopener" href="<?php echo esc_url($facebook); ?>">Facebook</a></li><?php endif; ?>
            <?php if (!empty($tiktok)) : ?><li><a target="_blank" rel="noopener" href="<?php echo esc_url($tiktok); ?>">TikTok</a></li><?php endif; ?>
            <?php if (!empty($linkedin)) : ?><li><a target="_blank" rel="noopener" href="<?php echo esc_url($linkedin); ?>">LinkedIn</a></li><?php endif; ?>

            <?php if (empty($instagram) && empty($facebook) && empty($tiktok) && empty($linkedin)) : ?>
              <li>Nessun social indicato.</li>
            <?php endif; ?>
          </ul>
        </div>
<?php endif; ?>

        <!-- CTA -->
<?php
$unread = is_user_logged_in()
    ? fp_get_unread_messages(get_current_user_id())
    : 0;
if ($unread > 0 && is_user_logged_in() && $player_user_id === get_current_user_id()) :
?>
<span class="fp-badge">
    🔔 <?php echo intval($unread); ?> nuovo<?php echo $unread > 1 ? 'i' : ''; ?> messaggio
</span>
<?php endif; ?>

<!-- Invia messaggio → SEMPRE VISIBILE -->
        <div class="fp-block fp-cta">
          <h3>Contatta</h3>
          
          <?php
$current_user_id = get_current_user_id();
$player_user_id  = intval(get_post_meta($post_id, 'fp_wp_user_id', true));

if (
    is_user_logged_in() &&
    $player_user_id &&
    $player_user_id !== $current_user_id
) {
echo '<a 
  class="fp-chat-link"
  href="' . esc_url( site_url('/chat-utenti/?chat_with=' . $player_user_id) ) . '">
  💬 Chatta
</a>';
}
?>


          <div class="fp-cta-actions">
            <?php if (!empty($wa_link)) : ?>
              <p><a href="<?php echo esc_url($wa_link); ?>" target="_blank" rel="noopener">💬 Scrivi su WhatsApp</a></p>
            <?php endif; ?>

            <?php if (!empty($mail_link)) : ?>
              <p><a href="<?php echo esc_url($mail_link); ?>">✉️ Invia email</a></p>
            <?php endif; ?>
<?php
$player_user_id = intval(get_post_meta($post_id, 'fp_wp_user_id', true));
$player_user    = $player_user_id ? get_user_by('ID', $player_user_id) : null;

if (
    is_user_logged_in() &&
    $player_user &&
    get_current_user_id() !== $player_user_id
) :
?>
    <a class="fp-message-link"
       href="<?php echo esc_url( site_url('/invia-messaggio?to=' . $player_user->user_login) ); ?>">
        ✉️ Invia messaggio
    </a>
<?php endif; ?>
            <?php if (empty($wa_link) && empty($mail_link)) : ?>
              <p>Nessun contatto disponibile.</p>
            <?php endif; ?>
          </div>
        </div>
<?php endif; ?>

<?php
global $wpdb;
$all = $wpdb->get_col("
  SELECT meta_value 
  FROM {$wpdb->postmeta} 
  WHERE meta_key = 'fp_discipline'
");

$sports = [];

foreach ($all as $row) {
  $arr = maybe_unserialize($row);
  if (!is_array($arr)) continue;
  foreach ($arr as $d) {
    if (!empty($d['sport'])) {
      $sports[strtolower($d['sport'])] = $d['sport'];
    }
  }
}

if ($sports): ?>
<div class="fp-sidebar-box">
  <h4>Sport disponibili</h4>
  <ul class="fp-sport-list">
    <?php foreach ($sports as $s): ?>
      <li>
        <a href="<?= add_query_arg('sport',$s,site_url('/cerca-giocatori')) ?>">
          <?= esc_html($s) ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

      </aside>
      
<?php
// ===============================
// ALTRI GIOCATORI STESSO SPORT (UNICO BLOCCO)
// ===============================
$disc = get_post_meta($post_id, 'fp_discipline', true);
if (!is_array($disc)) {
  $disc = maybe_unserialize($disc);
}

$sport_ref = '';
if (is_array($disc)) {
  foreach ($disc as $dd) {
    if (!empty($dd['sport'])) {
      $sport_ref = sanitize_text_field($dd['sport']);
      break;
    }
  }
}

if ($sport_ref) {

  $q = new WP_Query([
    'post_type'      => 'fp_giocatore',
    'posts_per_page' => 12,
    'post__not_in'   => [$post_id],
    'post_status'    => 'publish',
  ]);

  $found = [];

  while ($q->have_posts()) {
    $q->the_post();

    $d = get_post_meta(get_the_ID(), 'fp_discipline', true);
    if (!is_array($d)) {
      $d = maybe_unserialize($d);
    }
    if (!is_array($d)) continue;

    foreach ($d as $x) {
      if (!empty($x['sport']) && strcasecmp($x['sport'], $sport_ref) === 0) {
        $found[] = [
          'id' => get_the_ID(),
          'livello' => intval($x['livello_real'] ?? $x['livello_user'] ?? 0),
        ];
        break;
      }
    }
  }

  wp_reset_postdata();

  if (!empty($found)) { ?>
    <section class="fp-related-row">
      <h2 class="fp-related-title">
        Altri giocatori di <?php echo esc_html($sport_ref); ?>
      </h2>

      <div class="fp-related-scroll">
        <?php foreach ($found as $item):
          $pid  = $item['id'];
          $liv  = $item['livello'];
          $nick = get_post_meta($pid,'fp_nickname',true) ?: get_the_title($pid);
          $city = get_post_meta($pid,'fp_luogo_residenza',true);

$raw_altro = get_post_meta($pid,'fp_mostra_dati_pubblici',true);
$mostra_altro = in_array($raw_altro, [true, 1, '1'], true);

        ?>
          <a href="<?php echo get_permalink($pid); ?>" class="fp-related-card">
            <div class="fp-related-avatar">
              <?php echo get_the_post_thumbnail($pid,'medium'); ?>
            </div>

            <div class="fp-related-meta">
              <strong class="fp-related-name"><?php echo esc_html($nick); ?></strong>

                <?php if ($mostra_altro && $city): ?>
                <div class="fp-related-city"><?php echo esc_html($city); ?></div>
              <?php endif; ?>

              <?php if ($liv): ?>
                <div class="fp-related-level">Livello <?php echo intval($liv); ?>/10</div>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php }
}
?>


<?php
endwhile; endif;
?>

<!-- LIGHTBOX -->
<div id="fp-lightbox" style="display:none">
  <span class="fp-lightbox-close">×</span>
  <img src="" alt="">
</div>

<style>
#fp-lightbox{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.9);
  display:flex;
  align-items:center;
  justify-content:center;
  z-index:9999;
}
#fp-lightbox img{
  max-width:90%;
  max-height:90%;
}
.fp-lightbox-close{
  position:absolute;
  top:20px;
  right:30px;
  font-size:40px;
  color:#fff;
  cursor:pointer;
}
</style>

<script>
(function(){
  let images = [];
  let index = 0;

  const lightbox = document.getElementById('fp-lightbox');
  const img = lightbox.querySelector('img');

  function openLightbox(i){
    index = i;
    img.src = images[index];
    lightbox.style.display = 'flex';
  }

  function closeLightbox(){
    lightbox.style.display = 'none';
  }

  function next(){
    index = (index + 1) % images.length;
    img.src = images[index];
  }

  function prev(){
    index = (index - 1 + images.length) % images.length;
    img.src = images[index];
  }

  document.addEventListener('click', function(e){
    const link = e.target.closest('[data-fp-lightbox]');
    if (!link) return;

    e.preventDefault();

    images = Array.from(document.querySelectorAll('[data-fp-lightbox]'))
                  .map(a => a.href);

    openLightbox(images.indexOf(link.href));
  });

  lightbox.addEventListener('click', function(e){
    const w = window.innerWidth;
    if (e.target.classList.contains('fp-lightbox-close')) return closeLightbox();
    if (e.clientX > w / 2) next();
    else prev();
  });

  document.addEventListener('keydown', function(e){
    if (lightbox.style.display !== 'flex') return;

    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowRight') next();
    if (e.key === 'ArrowLeft') prev();
  });
})();

</script>


<?php
get_footer();