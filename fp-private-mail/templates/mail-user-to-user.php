<?php
if (!defined('ABSPATH')) exit;
?>

<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#111;line-height:1.6">

    <p style="margin-bottom:20px">
        <strong>
            <a href="<?php echo esc_url(site_url()); ?>"
               style="color:#000;text-decoration:none;font-size:18px">
                FIND PLAYER
            </a>
        </strong>
    </p>

    <p>
        Ciao <strong><?php echo esc_html($to_user->display_name); ?></strong>,
    </p>

    <p>
        hai ricevuto un messaggio dal player
        <strong><?php echo esc_html($from_nickname); ?></strong>
        tramite la piattaforma
        <strong>
            <a href="<?php echo esc_url(site_url()); ?>"
               style="color:#000;text-decoration:none">
                FIND PLAYER
            </a>
        </strong>.
    </p>

    <p><strong>Ecco il messaggio:</strong></p>

    <blockquote style="margin:15px 0;padding-left:15px;border-left:3px solid #ccc;font-style:italic">
        “<?php echo esc_html($message); ?>”
    </blockquote>

    <p>
        <strong>
            Non puoi rispondere direttamente da qui,
            ma puoi farlo accedendo al suo profilo dove vedrai
            anche le sue attività.
        </strong>
    </p>

    <p style="margin:20px 0">
        👉
        <a href="<?php echo esc_url($profile_url); ?>"
           style="font-weight:bold;text-transform:uppercase">
            Accedi al profilo di <?php echo esc_html($from_nickname); ?>
        </a>
    </p>

    <p style="font-style:italic;color:#444">
        Ti ricordo che puoi evitare di fornire i tuoi contatti diretti:
        con Find Player puoi comunicare in modo riservato direttamente
        sulla piattaforma.
    </p>

    <p style="margin-top:30px">
        — Team Find Player
    </p>

</div>