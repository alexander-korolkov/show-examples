(function ($, Drupal, settings) {

  "use strict";

  Drupal.behaviors.hycmMainNavMobile = {
    attach: function (context, settings) {
    //  console.log('hycmMainNavMobile');

      $('.navbar-toggler').once().click(function(){
			$('.bg-primary').toggleClass('open');
		});
      $('.navbar-toggler-icon').once().click(function(){
			$('.navbar-toggler-icon').toggleClass('close');
		});

    /*$(".btn-lang-switch-mobile.lang-en.form-control").after(
      	'test'
      	);*/

    }
  };

})(jQuery, Drupal, drupalSettings);

