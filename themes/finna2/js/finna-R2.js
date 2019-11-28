/*global finna */
finna.R2 = (function finnaR2() {
  function initModal() {
    // Transform form h1-element to a h2 so that the modal gets a proper title bar
    var modal = $('#modal');
    modal.on('show.bs.modal', function onShowModal (/*e*/) {
      var title = $(this).find('.feedback-content h1:first-child');
      if (title.length > 0) {
        var body = $(this).find('.modal-body');
        var h2 = $('<h2/>').text(title.text());
        h2.prependTo(body);
        title.remove();
      }
    });
  }

  function initAutoOpenRegistration() {
    $('.R2-status .register .btn-primary').trigger('click');
  }

  var my = {
    initAutoOpenRegistration: initAutoOpenRegistration,
    initModal: initModal
  };

  return my;
})();
