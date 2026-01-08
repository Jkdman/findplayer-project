<?php
/**
 * Template email default – privacy safe
 *
 * @var string $message
 * @var WP_User $user
 */
?>

Ciao <?php echo esc_html($user->display_name); ?>,

<?php echo nl2br(esc_html($message)); ?>


—
Team Find Player

Questa email è stata inviata esclusivamente per finalità di servizio,
in relazione alla tua attività sulla piattaforma Find Player.