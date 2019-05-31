/**
 * Contains javascript to refresh alert div contents.
 *
 * @file dkc_alerts.js
 */

(function ($) {
  var basePath;
  Drupal.behaviors.dkcAlerts = {
    attach: function (context, settings) {
      basePath = settings.basePath;
      $('.dkc-alert', context).each(function (alert) {
        loadAlert($(this));
      })
    }
  };

  // Function to update alert text.
  var loadAlert = function (alert) {
    var aid = alert.data('aid');
    $.get('/ajax/dkc-alerts/' + aid + '/alert' , null, function(response) {
      $(alert).replaceWith(response);
    });

    // Update content at configured interval.
    if (Drupal.settings.dkcAlerts.timeout > 0) {
      setTimeout(function () {
        loadAlert(alert)
      }, Drupal.settings.dkcAlerts.timeout * 1000);
    }
  }

})(jQuery);
