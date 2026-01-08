jQuery(document).ready(function($) {

    function closeModal() {
        $(".ce-modal, .ce-modal-overlay").remove();
    }

    $(document).on("click", ".ce-day.has-events", function () {

        const $popup = $(this).find(".ce-day-popup").clone();

        closeModal();

        // Overlay
        const $overlay = $('<div class="ce-modal-overlay"></div>');
        $("body").append($overlay);

        // Modale
        const $modal = $('<div class="ce-modal"></div>');
        $modal.append($popup);
        $("body").append($modal);

        $popup.show(); // forza visualizzazione
    });

    // Chiudi cliccando sull'overlay
    $(document).on("click", ".ce-modal-overlay", function () {
        closeModal();
    });

    // Chiudi cliccando sulla X
    $(document).on("click", ".ce-popup-close", function (e) {
        e.stopPropagation();
        closeModal();
    });

});
