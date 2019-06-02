(function ($, Drupal, settings) {

  "use strict";

  Drupal.behaviors.hycmMainNav = {
    attach: function (context, settings) {
     // console.log('hycmMainNav');

      var $mainRoot = $('.hycm-main-menu-wrap', context),
          $mainItems = $mainRoot.find('.hy-nav-item'),
          $mainNav = $('#main-nav-container', context),
          $page = $('#page', context),
          $dropOpened = $('li.hy-nav-item a.nav-link', context),
          $subContainer = $('#main-sub-nav-container' ,context);

      // remove title
      $('.hy-dropdown-menu a', context).once('hycmMainNavTitleRoot').removeAttr('title');
      $('.hycm-dropdown-menu a', context).once('hycmMainNavTitle').removeAttr('title');


      $(document).once('hycmMainNavOther').mouseup(function (e) {

        var $dropOpened = $('li.hy-nav-item a.nav-link', context),
            $rootItems = $('.hy-dropdown-menu a.nav-link', context);
       // console.log('mouseup event');
        if (!$subContainer.is(e.target) && $subContainer.has(e.target).length === 0) {

          if (!$rootItems.is(e.target) && $rootItems.has(e.target).length === 0) {
            console.log('root event success');
            $mainNav.removeClass('open');
            $subContainer.removeClass('open');
            $page.removeClass('main-nav-open');
            $dropOpened.removeClass('dropdown-open');
            $('.hy-dropdown-menu li.active').removeClass('active');
          }


     //     console.log('mouseup event success');

        }
      });

      $mainItems.once('hycmMainNavRoot').find('a.nav-link').each(function () {
        $(this).on('click', function (e) {

          var itemId = $(this).data('hycm-nav');
          var $subMenu = $('ul[data-main-subnav="' + itemId + '"]'),
              $opened = $('ul.hycm-dropdown-menu.open'),
              $dropOpened = $('li.hy-nav-item a.nav-link', context);

          $('.hy-dropdown-menu li').removeClass('active');


          if( $(this).hasClass('dropdown-open') && $mainNav.hasClass('open')) {
            // close (hidden) full wrapper
            $mainNav.removeClass('open');
            $subContainer.removeClass('open');
            $page.removeClass('main-nav-open');
            $dropOpened.removeClass('dropdown-open');
        //    console.log('close item')
          }else{
            // close opened section
        //    console.log('open item');
         //   console.log($(this).parent());
            $opened.removeClass('open');
            $dropOpened.removeClass('dropdown-open');
            // open clicked section
            $(this).addClass('dropdown-open');
            $(this).parent().addClass('active');
            $page.addClass('main-nav-open');
            $subMenu.addClass('open');
            // show sub menu
            $mainNav.addClass('open');
            $subContainer.addClass('open');

         //   console.log($subMenu.data('mainSubnav'));

          }
          e.preventDefault();
        })
      })

    }
  };

  Drupal.hycmMainNav = {

    openItem: function (itemId) {
      var self = this;


    },

  };

})(jQuery, Drupal, drupalSettings);
