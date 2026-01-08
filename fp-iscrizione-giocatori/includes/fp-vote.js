jQuery(function($){
  $('#fp-vote-form').on('submit', function(e){
    e.preventDefault();

    const $msg = $('#fp-vote-msg');
    $msg.text('Invio in corso...');

    const data = $(this).serializeArray();
    data.push({name:'action', value:'fp_submit_votes'});
    data.push({name:'nonce', value:FPVOTE.nonce});

    $.post(FPVOTE.ajaxurl, data)
      .done(function(res){
        if(res && res.success){
          $msg.text(res.data.message);
          $('#fp-vote-form button[type="submit"]').prop('disabled', true);
        } else {
          $msg.text(res && res.data && res.data.message ? res.data.message : 'Errore.');
        }
      })
      .fail(function(){
        $msg.text('Errore di rete.');
      });
  });
});