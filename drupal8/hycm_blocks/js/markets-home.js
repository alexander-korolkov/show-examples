(function ($, Drupal, settings) {

  "use strict";

  Drupal.behaviors.hycmBlocksHomeMarkets = {
    attach: function (context, settings) {


      var $slickContainer = $('.slick-container .graphs-slick-list', context),
          $marketContainer = $('.market-about-container', context),
          $markets = $('.markets-item', context);

      var $firstMarketAbout = $markets.filter(":first").find('.market-about-item').clone();

      $firstMarketAbout.appendTo('.market-about-container');

      $marketContainer.once('homeMarketsSlickOnLad', function () {

      });

      $slickContainer.once('homeMarketsSlick').each(function () {

        $(this).slick({
          slidesToShow: 3,
          slidesToScroll: 1,
          autoplay: true,
          autoplaySpeed: 2000,
        });
      })

    }
  };

  Drupal.hycmBlocksHomeMarkets = {

  };

})(jQuery, Drupal, drupalSettings);