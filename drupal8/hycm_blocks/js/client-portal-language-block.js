(function ($, Drupal, settings) {

  $("ul.lang_dropdown li a").not(".zh-hans").each( function () {
    this.href = this.href + location.pathname.replace('/'+settings.language+'/', '');
  });

  "use strict";

  Drupal.behaviors.hycmBlocksClientPortalLanguageBlock = {
    attach: function (context, settings) {

    }
  };


})(jQuery, Drupal, drupalSettings);