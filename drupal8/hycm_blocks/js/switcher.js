(function ($, Drupal, settings) {

  "use strict";

  Drupal.behaviors.hycmSwitcher = {
    attach: function (context, settings) {
    //  console.log('hycmSwitcher');

      var $switcher = $('.black-switch a.nav-link', context),
          $panel = $('.black-switcher-wrapper', context),
          $close = $('.close-switcher', context);


      $(document).mouseup(function (e) {
        if (!$panel.is(e.target) && $panel.has(e.target).length === 0) {
          $panel.removeClass('open');
          $switcher.removeClass('open');
        }
      });

      $switcher.once('hycmSwitcher').each(function () {
        $(this).on('click', function () {
          if( $(this).hasClass('open') ) {
            $panel.removeClass('open');
            $switcher.removeClass('open');
            console.log('switcher closed');
          }else {
            $panel.addClass('open');
            $switcher.addClass('open');
            console.log('switcher opened');
          }
        });

      });

      $close.on('click', function () {
        $panel.removeClass('open');
        $switcher.removeClass('open');
      });

      //$switcher.addClass('bg-icon');

    }
  };

  Drupal.hycmSwitcher = {

  };

})(jQuery, Drupal, drupalSettings);