<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
    add_meta_box(
        'fp_sport_seo',
        'SEO – Pagina Sport',
        'fp_render_sport_seo_metabox',
        'fp_sport',
        'normal',
        'high'
    );
});

function fp_render_sport_seo_metabox($post) {

    $seo_title = get_post_meta($post->ID, '_fp_seo_title', true);
    $seo_desc  = get_post_meta($post->ID, '_fp_seo_desc', true);
    $seo_intro = get_post_meta($post->ID, '_fp_seo_intro', true);
    ?>

    <p>
        <label><strong>SEO Title</strong></label><br>
        <input type="text" name="fp_seo_title" value="<?= esc_attr($seo_title); ?>" style="width:100%;">
    </p>

    <p>
        <label><strong>SEO Description</strong></label><br>
        <textarea name="fp_seo_desc" rows="2" style="width:100%;"><?= esc_textarea($seo_desc); ?></textarea>
    </p>

    <p>
        <label><strong>Intro SEO Pagina Sport</strong></label><br>
        <textarea name="fp_seo_intro" rows="6" style="width:100%;"><?= esc_textarea($seo_intro); ?></textarea>
        <em>Questo testo viene mostrato sopra ai giocatori ed è fondamentale per la SEO.</em>
    </p>

    <?php
}

add_action('save_post_fp_sport', function ($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['fp_seo_title'])) {
        update_post_meta($post_id, '_fp_seo_title', sanitize_text_field($_POST['fp_seo_title']));
    }

    if (isset($_POST['fp_seo_desc'])) {
        update_post_meta($post_id, '_fp_seo_desc', sanitize_textarea_field($_POST['fp_seo_desc']));
    }

    if (isset($_POST['fp_seo_intro'])) {
        update_post_meta($post_id, '_fp_seo_intro', wp_kses_post($_POST['fp_seo_intro']));
    }

});