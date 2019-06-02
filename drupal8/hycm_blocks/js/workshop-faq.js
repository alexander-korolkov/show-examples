(function ($, Drupal) {

  "use strict";

  Drupal.behaviors.hycmBlocksWorkshopFaq = {
    attach: function (context, settings) {
// console.log('hycmBlocksWorkshopFaq');
      $('.question-header').click(function () {
        if(!$(this).parent().hasClass('opened')) {
          $(this).parents('.faq-container').find('.opened').removeClass('opened').find('.answer-body').slideUp();
          $(this).parent().addClass('opened');
          $(this).next().slideDown();
        } else {
          $(this).parent().removeClass('opened');
          $(this).next().slideUp();
        }
      });

      $('.show-more button').click(function (e) {
        e.preventDefault();
        $('.item-hidden').removeClass('item-hidden').addClass('item-view');
        $(this).hide()
      })
    }
  };


})(jQuery, Drupal);