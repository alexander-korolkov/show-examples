(function ($, Drupal) {
  $(document).ready(function(){
    $('.form-item-receive-news').addClass('unchecked');
    $('.custom_question').attr("value", "");
  });

  "use strict";

  Drupal.behaviors.hycmBlocksJoinWorkshopForm = {
    attach: function (context, settings) {


      var navU = navigator.userAgent;
      // Android Mobile
      var isAndroidMobile = navU.indexOf('Android') > -1 && navU.indexOf('Mozilla/5.0') > -1 && navU.indexOf('AppleWebKit') > -1;
      // Apple webkit
      var regExAppleWebKit = new RegExp(/AppleWebKit\/([\d.]+)/);
      var resultAppleWebKitRegEx = regExAppleWebKit.exec(navU);
      var appleWebKitVersion = (resultAppleWebKitRegEx === null ? null : parseFloat(regExAppleWebKit.exec(navU)[1]));
      // Chrome
      var regExChrome = new RegExp(/Chrome\/([\d.]+)/);
      var resultChromeRegEx = regExChrome.exec(navU);
      var chromeVersion = (resultChromeRegEx === null ? null : parseFloat(regExChrome.exec(navU)[1]));
      // Native Android Browser
      var isAndroidBrowser = isAndroidMobile && (appleWebKitVersion !== null && appleWebKitVersion < 537) || (chromeVersion !== null && chromeVersion < 37);


      var $root = $('html');
      if (isAndroidBrowser) {
        $root.addClass('stock-android')
      }

      if ((/Edge\/\d./i.test(navigator.userAgent)) || (/MSIE 9/i.test(navigator.userAgent) || /rv:11.0/i.test(navigator.userAgent)) || (/MSIE 10/i.test(navigator.userAgent))){
        $root.addClass('ie')
      }



      var items = settings.hycm_services.phone_codes;
      var countryCodeFromGoogle = 'cy';
        //geolocation
        var requestJson = {
                "radioType": "gsm"
            };
        $.ajax({
            dataType: "json",
            url: "https://www.googleapis.com/geolocation/v1/geolocate?key=AIzaSyDO5hn6OmbMkuwdx5q-_VZwGLUHkZ62xGw",
            method: 'POST',
            contentType: "application/json; charset=utf-8",
            data : JSON.stringify(requestJson)
        }).done(function(data) {
            $.ajax({
                dataType: "json",
                url: "https://maps.googleapis.com/maps/api/geocode/json?latlng="+data['location'].lat+","+data['location'].lng+"&key=AIzaSyAzgyfH67CbFgapjc3EGAl6hVuWyITs4fc",
                method: 'POST'
            }).done(function(data) {
                function getCountry(addrComponents) {
                    for (var i = 0; i < addrComponents.length; i++) {
                        if (addrComponents[i].types[0] == "country") {
                            return addrComponents[i].short_name;
                        }
                        if (addrComponents[i].types.length == 2) {
                            if (addrComponents[i].types[0] == "political") {
                                return addrComponents[i].short_name;
                            }
                        }
                    }
                    return false;
                }

                // getCountry(data['results'][0].address_components)

                countryCodeFromGoogle = getCountry(data['results'][0].address_components);
                if(countryCodeFromGoogle.length!=0) {
                    items.forEach(function (key) {
                        if (key['code'] === countryCodeFromGoogle){
                            $('#join-workshop-form').append('<input type="hidden" name="country" value="' + key['name'] + '">');
                            $('#phone-prefix').val(key['phoneCode']);
                            $('#select2-phone-prefix-container').attr('title',key['name']).find('div').html(key['phoneCode']);
                        }
                    })
                }
            });
        });

        function matchCustom(params, data) {
            // If there are no search terms, return all of the data
            if (data['phoneCode'].indexOf($.trim(params.term)) > -1 || data['text'].toLowerCase().indexOf($.trim(params.term)) > -1) {
                return data;
            }

            // Do not display the item if there is no 'text' property
            if (typeof data.text === 'undefined') {
                return null;
            }


            // Return `null` if the term should not be displayed
            return null;
        }

      $('#phone-prefix').empty();
      if($root.hasClass('desktop')) {
        $("#phone-prefix").select2({
          data: items,
          matcher: matchCustom,
          dropdownCssClass: 'prefix-drop',
          templateResult: formatState,
          templateSelection: formatSelectionState
        });
      } else {
        // @todo test mobile
        $.each(items, function (i, item) {
            var selected = '';
            if (item.code === countryCodeFromGoogle.toUpperCase()){
                console.log(item.code);
                console.log(countryCodeFromGoogle.toUpperCase());
                $("#phone-prefix").val(item.phoneCode);
                selected = 'selected="selected"';
            }
          var option = '<option '+selected+' data-val="' + item.code + '" value="' + item.phoneCode + '">' + item .phoneCode + '</option>';
          $("#phone-prefix").append(option);
        })
      }

      function formatState (state) {
        if (!state.id) {
          return state.text;
        }
        var $state = $(
            '<div><span class="flag-icon flag-icon-' + state.element.value.toLowerCase() + '"></span>' + state.name + ' ' + state.phoneCode + '</div>'
        );
        return $state;
      };

      function formatSelectionState (state) {
        if (!state.id) {
          return state.text;
        }
        var $state = $(
            '<div>' + state.phoneCode + '</div>'
        );
        return $state;
      };

      $('select').on("select2:open", function() {
        $('.desktop .select-scroll').mCustomScrollbar('destroy');
        if(!$('.select-scroll').length) {
            $('.select2-results__options').wrap('<div class="select-scroll"></div>')
        }
        setTimeout(function () {
            $('.desktop .select-scroll').mCustomScrollbar({
                mouseWheel: true,
                advanced: {
                    updateOnContentResize: true
                }
            });
        }, 20);
    });


      $('.form-item-receive-news').on('click', function (e) {
        e.preventDefault();
        if ($(this).hasClass('active')) {

          $(this).find('input').removeAttr("checked");

          $(this).removeClass('checked');
          $(this).addClass('unchecked');
          $(this).removeClass('active');

          $(this).parent().find('.custom_question').attr("value", "");
        } else if($(this).hasClass('unchecked')) {
          $(this).addClass('active');
          $(this).removeClass('unchecked');
          $(this).addClass('checked');

          $(this).find('input').attr("checked", "checked");

          $(this).parent().find('.custom_question').attr("value", "I would like to receive Company news, products updates and promotions.");
        }
      });

    }
  };


})(jQuery, Drupal);