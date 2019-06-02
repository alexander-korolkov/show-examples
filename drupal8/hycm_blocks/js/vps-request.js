(function ($, Drupal, settings) {

  "use strict";

  Drupal.hycmRequestVPS = {

    setCookie: function (c_name, value, min = null) {
      var exdate = new Date();
      var minutes = min * 60 * 1000;
      exdate.setTime(exdate.getTime() + minutes);
      var c_value = escape(value) + ((min == null) ? "" : "; expires=" + exdate.toGMTString());
      console.log("cook " + c_value)
      document.cookie = c_name + "=" + c_value + " ;path=/";

      console.log(document.cookie);
    },

    // todo: fix  drupal.js?v=8.6.1:13 Uncaught TypeError: Cannot read property 'replace' of undefined
    //            at Object.getCookie (vps-request.js?pf6yps:20)
    getCookie: function (name) {
      var matches = document.cookie.match(new RegExp(
          "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
      ));
      return matches ? decodeURIComponent(matches[1]) : undefined;
    },

    deleteCookie: function (name) {
      self = this;
      self.setCookie(name, "", {
        expires: -1
      })
    }


  };


  Drupal.behaviors.hycmRequestVPS = {
    attach: function (context, settings) {

      console.log(settings.hycm_blocks.vpsrequest.cookies);

      var isLogIn = false,
          cName = 'from_vps',
          cValue = true,
          login = '/en/login',
          back_text = Drupal.t("Go Back", {}, {context: "VPS Request"}),
          blockConfig = settings.hycm_blocks.vpsrequest;


      // confirm modal
      var requestConfirm = Drupal.dialog(blockConfig.confirm_content, {
        title: blockConfig.request_title,
        dialogClass: 'hycm-vps-send',
        width: 500,
        height: 300,
        autoResize: true,
        close: function (event) {
          $(event.target).remove();
        },

      });

      //request modal
      var requestModal = Drupal.dialog(blockConfig.request_content, {
        title: blockConfig.request_title,
        dialogClass: 'hycm-vps',
        width: 500,
        height: 300,
        autoResize: true,
        close: function (event) {
          $(event.target).remove();
        },
        buttons: [
          {
            text: Drupal.t('Confirm'),
            class: 'vps-confirm',
            //  icons: {
            //    primary: 'ui-icon-hycm'
            //  },
            click: function () {
              $(this).dialog('close');
              requestConfirm.showModal();
            }
          },
          {
            text: back_text,
            // icons: {
            //   primary: 'ui-icon-close'
            // },
            click: function () {
              $(this).dialog('close');
            }
          },
        ]
      });
      

      // button on page
      $('.vps-request-trigger', context).once('hycmRequestVPSBehavior').each(function () {
        $(this).on('click', function () {

          var isLogIn = (localStorage.getItem('loggedIn') == 'true');
          if (!isLogIn) {
            Drupal.hycmRequestVPS.setCookie(cName, cValue);
            window.location.href = "/en/login";
          }else {
            if (blockConfig.cookies.from_vps == "true") {
              Drupal.hycmRequestVPS.deleteCookie(cName);
            }

            requestModal.showModal();
          }

        });

      });

    }
  };



})(jQuery, Drupal, drupalSettings);