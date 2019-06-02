(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.hycmBlocksAwardsSlick = {
    attach: function (context, settings) {
      var $slickContainer = $('.awards-slick', context);

      $slickContainer.once('AwardsSlickCreate').each(function () {

        console.log($(this).find('.paragraph-slick-slider').data('slick'));
        $(this).slick({
          slidesToShow: 4,
          //rtl: rtlFlag,
          //prevArrow: prevArrow,
          //nextArrow: nextArrow,
          slidesToScroll: 4,
          infinite: false,
          responsive: [
            {
              breakpoint: 992,
              settings: {
                slidesToShow: 3,
                slidesToScroll: 3
              }
            },
            {
              breakpoint: 768,
              settings: {
                slidesToShow: 2,
                slidesToScroll: 2
              }
            },
            {
              breakpoint: 540,
              settings: {
                slidesToShow: 1,
                slidesToScroll: 1,
                adaptiveHeight: true
              }
            }
          ]
        });
      });

    }
  };


})(jQuery, Drupal);
