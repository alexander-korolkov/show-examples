(function ($, Drupal, settings) {

  "use strict";

  Drupal.behaviors.hycmmobSwitcher = {
    attach: function (context, settings) {
     // console.log('hycmmobSwitcher');

      var $mobswitcher = $('.btn-lang-switch-mobile', context),
          $mobpanel = $('.black-switcher-wrapper', context),
          $mobclose = $('.close-switcher', context);


      $(document).mouseup(function (e) {
        if (!$mobpanel.is(e.target) && $mobpanel.has(e.target).length === 0) {
          $mobpanel.removeClass('open');
          $mobswitcher.removeClass('open');
        }
      });

      $mobswitcher.once('hycmSwitcher').each(function () {
        $(this).on('click', function () {
          if( $(this).hasClass('open') ) {
            $mobpanel.removeClass('open');
            $mobswitcher.removeClass('open');
          //  console.log('switcher closed');
          }else {
            $mobpanel.addClass('open');
            $mobswitcher.addClass('open');
          //  console.log('switcher opened');
          }
        });

      });

      $mobclose.on('click', function () {
        $mobpanel.removeClass('open');
        $mobswitcher.removeClass('open');
      });

      //$switcher.addClass('bg-icon');

    }
  };

  Drupal.hycmmobSwitcher = {

  };

})(jQuery, Drupal, drupalSettings);