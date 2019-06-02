(function ($, Drupal, settings) {

  "use strict";

  Drupal.behaviors.hycm_blocksJoinWebinarForm = {
    attach: function (context, settings) {

      $('.checkbox').on('change','input[type="checkbox"]',function () {
        $(this).parent().toggleClass('active');
      });

      window.Parsley.on('field:error', function() {
        this.$element.parent().addClass('error').removeClass('success');
      });
      window.Parsley.on('field:success', function () {
        this.$element.parent().addClass('success').removeClass('error');
      });

      $(document).on('focusin focusout', '.field-input', function (event) {
        if (event.type == 'focusin') {
          $(event.target).parent().addClass('is-focused has-label');
        } else {
          //$parent = $(event.target).parent();
          if (!$(event.target).val().length) {
            $(event.target).parent().removeClass('has-label');
          }
          $(event.target).parent().removeClass('is-focused');
        }
      });

      $('.register-btn, .video-play').on('click', function (e) {
        e.preventDefault();
        $('body, html').animate({
          scrollTop: $(".join-webinar-form-wrap").offset().top - 90
        });
      })


      //todo fix
      $('.join-webinar-form-wrap form').on('submit', function (e) {
        if(!$('.join-webinar-form-wrap form').hasClass('validated')){
          e.preventDefault();
          if ($('form').parsley().validate()) {
            $.ajax({
              method: "POST",
              url: "https://api.hycm.com/clients/exist",
              data: 'username='+encodeURIComponent($('#email').val())
            }).done(function( response ) {
              ga('send', 'event', 'registration', 'send', 'short_form');
              if(response['response'].messageCode == '81'){
                // new user
                $('#user_exist').val('false');
                $('form').addClass('validated').submit();
              }else{
                // exist user
                $('#user_exist').val('true');
                $('form').addClass('validated').submit();
              }
            });
          }
        }
      });


    }
  };

})(jQuery, Drupal, drupalSettings);