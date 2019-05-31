/**
 * Add spectrum color picker.
 *
 * @file dkc_alerts_form.js
 */

(function ($) {
  Drupal.behaviors.dkcAlertsForm = {
    attach: function (context, settings) {
      $('.spectrum-color-picker', context).each(function ($i) {
        var elem = $(this);
        var color = $(this).data('color');
        var options = {
          showInput: true,
          allowEmpty: true,
          showAlpha: true,
          showInitial: true,
          showInput: true,
          preferredFormat: "hex",
          clickoutFiresChange: true,
          showButtons: false
        };

        if (color.length > 0) {
          options[color] = color;
        }

        elem.spectrum(options);
      });
    }
  };

})(jQuery);
