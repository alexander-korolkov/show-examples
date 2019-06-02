(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.hycmBlocksParagraphSlick = {
    attach: function (context, settings) {
     // console.log('hycmBlocksParagraphSlick');

      var $slickContainer = $('.slick-settings-container', context);

      $slickContainer.once('ParagraphSlickCreate').each(function () {
        //console.log(this);

        var slickSettings = $(this).data('slick-settings');
      //  console.log(slickSettings);

     //   var dataSlick = JSON.parse(slickSettings);
    //    $(this).data('slick', slickSettings);
       // console.log($(this).find('.paragraph-slick-slider').data('slick'));
        $(this).find('.paragraph-slick-slider').slick({
          infinite: false,
          //rtl: true,
        });
      });
      /*
          $marketContainer = $('.market-about-container', context),
          $markets = $('.markets-item', context);
/*
      var $firstMarketAbout = $markets.filter(":first").find('.market-about-item').clone();

      $firstMarketAbout.appendTo('.market-about-container');

      $marketContainer.once('homeMarketsSlickOnLad', function () {
      });

*/
    }
  };


})(jQuery, Drupal);