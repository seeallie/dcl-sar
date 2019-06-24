(function ($, Drupal) {
  Drupal.behaviors.dclVboSar = {
    attach: function (context, settings) {
      $('#vbo-action-form-wrapper .form-item.form-item-action').hide(); 
    }
  };
})(jQuery, Drupal);
