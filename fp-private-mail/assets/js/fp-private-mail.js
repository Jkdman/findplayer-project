jQuery(document).ready(function ($) {

    $('#fp-pm-send').on('click', function () {

        const message = $('#fp-pm-message').val().trim();
        const userId  = $(this).data('user-id');

        if (!message) {
            alert('Scrivi un messaggio prima di inviare.');
            return;
        }

        $('#fp-pm-send').prop('disabled', true);

        $.post(FP_PM.ajax_url, {
            action: 'fp_pm_send_mail',
            _wpnonce: FP_PM.nonce,
            user_id: userId,
            message: message
        })
        .done(function (res) {
            alert(res.success ? 'Azione eseguita correttamente (test).' : 'Errore: ' + res.data);
        })
        .fail(function () {
            alert('Errore di comunicazione.');
        })
        .always(function () {
            $('#fp-pm-send').prop('disabled', false);
        });

    });

});