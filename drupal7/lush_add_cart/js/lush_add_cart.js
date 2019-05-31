/**
 * @file
 * lush_add_cart.js
 */

"use strict";

(function ($) {

  Drupal.behaviors.lushAddCart = {
    attach: function (context, settings) {

    },

    // Add cart button.
    addCartButton: function () {
      $(".node-add-to-basket").each(function () {
        var $addToBasket = $(this).find(".product-module-price-rating");
        if ($addToBasket.find(".add-cart-button").length < 1) {
          $addToBasket.append($("<div class='add-cart-button' />"));
        }
      });
    },

    // Add cart button to kitchen Click.
    KitchenaddAartButton: function() {
      $(".header-products-contents li.last, .header-sub-menu-products li.kitchen").click(function () {
        if ($("body").addClass("absolute_header")) {
          if ($(".object-shelf-right").find(".add-cart-button").length < 1) {
            setTimeout(function () {
              Drupal.behaviors.lushAddCart.addCartButton();
            }, 1000);
          }
        }
      });
    },

    // Add cart button to naviplus.
    naviplusAddCartButton: function () {
      if (document.readyState == "complete") {
        setTimeout(function () {
          Drupal.behaviors.lushAddCart.addCartButton();
        }, 2000);
      }
    },

    lushAddCartPopup: function () {
      $("body").on("click", ".node-add-to-basket .add-cart-button", function () {
        $(".add-cart-button .throbber").remove();
        $(this).parents(".node-add-to-basket").find(".review-super-wrapper,.highlighted-review-wrapper").removeAttr("style");
        $(this).append(" <div class='throbber' /> ");
        var $nodeParents = $(this).parents(".node-add-to-basket");
        var $parents = $(this).parents(".node-add-to-basket");
        var $addbox1 = $parents.find(".product-super-wrapper");
        var $addbox2 = $parents.find(".object-shelf-right-product");
        if ($addbox1.length > 0) {
          $parents = $addbox1;
        }
        else if ($addbox2.length > 0) {
          $parents = $addbox2;
        }
        var nid = $nodeParents.attr("class").match(/nid-\d+/g)[0].split('-')[1];
        var featured = $(this).parents(".featured-normal").length != 0;
        $.ajax({
          type: "post",
          url: Drupal.settings.basePath + 'lush/product/ajax',
          data: {'nid': nid, 'featured': featured},
          dataType: 'json',
          cache: false,
          success: function (data) {
            $(".add-cart-button .throbber").remove();
            $(".add-cart-box").remove();
            $(".add-cart-button").removeAttr("style");
            $parents.find(".add-cart-button").attr("style", "z-index:10");
            if ($parents.find(".add-cart-box").length < 1) {
              $parents.append(data);
              $(".add-cart-box .product-image img").each(function () {
                if ($(this).width() > $(this).height()) {
                  $(this).css("margin-top", "4px");
                }
              });
            }

          },
          error: function () {
            $(".add-cart-button .throbber").remove();
          }
        });
      });
    },

    lushAddCartWishlist: function () {
      $("body").on("click", ".add-cart-box .wishlist", function (e) {
        e.preventDefault();
        var href = $(this).attr("href");
        var $parents = $(this).parents(".add-cart-box");
        var $wishlistBox = $parents.find(".lush-cart-wishlist");
        var url = window.location.pathname;
        $.ajax({
          type: "post",
          url: Drupal.settings.basePath + 'lush/flag/ajax',
          data: {'href': href, 'url': url},
          dataType: 'json',
          cache: false,
          success: function (data) {
            if (data.message == undefined) {
              $wishlistBox.html(data.wishlist);
            }
            else {
              var errors = $("<div class='add-success-box'>" + "<div class='success-message errors'>" + Drupal.t(data.message) + "</div>" + "<div class='close' style='right:0'>" + "x" + "</div>" + "</div>");
              $parents.html(Drupal.t(errors));
              $parents.parents(".node-add-to-basket").find(".review-super-wrapper,.highlighted-review-wrapper").css("z-index", "0");
            }
          }
        });
        return false;
      });
    },

    lushAddCartSelectChange: function () {
      $("body").on("change", ".add-cart-box select", function () {
        var $parents = $(this).parents(".node-add-to-basket");
        var $form = $parents.find(".add-cart-box form");
        var obj = $form.serializeArray();
        var featured = $(this).parents(".featured-normal").length != 0;
        $.ajax({
          type: "post",
          url: Drupal.settings.basePath + 'lush/product/change/ajax',
          data: {'obj': obj, 'featured': featured},
          dataType: 'json',
          cache: false,
          success: function (data) {
            var img = data.image;
            var submit = Drupal.t(data.submit_value);
            if (!img == false) {
              $parents.find(".object-commerce-image-inner-inner a").html(img);
              if ($parents.find(".form-submit-box").length > 0) {
                $parents.find(".form-submit-box").html(submit);
              }
              else if ($parents.find(".coming-soon-box").length > 0) {
                $parents.find(".coming-soon-box").remove();
                $parents.find(".form-type-textfield").after("<div class='form-submit-box'>" + submit + "</div>");
              }
            }
          }
        });
        return false;
      });
    },

    lushAddCartFormSubmit: function () {
      $("body").on("click", ".add-cart-box .form-submit", function (e) {
        e.preventDefault();
        var $parents = $(this).parents(".add-cart-box");
        var $form = $parents.find("form");
        var obj = $form.serializeArray();
        $.ajax({
          type: "post",
          url: Drupal.settings.basePath + 'lush/product/submit/ajax',
          data: {'obj': obj},
          dataType: 'json',
          cache: false,
          success: function (data) {
            if (navigator.appName == "Microsoft Internet Explorer" && navigator.appVersion.match(/9./i) == "9.") {
              var message = $("<div class='add-success-box'>" + "<div class='success-message'>" + "<span class='successIcon2'>" + "<embed src='/sites/all/modules/custom/lush_add_cart/img/confirmation-tick.swf' quality='high' wmode='transparent' width='100%' height='100%' type='application/x-shockwave-flash' />" + "</span>" + "<div class='message'>" + Drupal.t(data.message) + "</div>" + "</div>" + "</div>");
            }
            else {
              var message = $("<div class='add-success-box'>" + "<div class='success-message'>" + "<span class='successIcon'/>" + "<div class='message'>" + Drupal.t(data.message) + "</div>" + "</div>" + "</div>");
            }
            for (var key in data.errors) {
              var dataKey = Drupal.t(data.errors[key]);
            }
            var errors = $("<div class='add-success-box'>" + "<div class='errors'>" + dataKey + "</div>" + "<div class='close' style='right:0'>" + "x" + "</div>" + "</div>");
            $parents.parents(".node-add-to-basket").find(".review-super-wrapper,.highlighted-review-wrapper").css("z-index", "0");
            if (data.errors == undefined) {
              $parents.html(message);
              $("body").addClass("add-success");
              setInterval(function () {
                $parents.fadeOut("slow");
              }, 2000);
            }
            else {
              $parents.find(".submit-errors").remove();
              $parents.html(errors);
            }

            // We added integration to GTM right here just to keep time, I thinks this logic
            // should be rewritten with ajax commands & use Drupal api instead of explicit
            // ajax calls. If would be confirmed for refactoring we will check it later.
            var dataLayer = window.dataLayer || [];

            // The dataLayer array is provided by GTM js & if present the on push action it
            // syncs data with external GTM service.
            if (data.dataLayer) {
              var i;
              for (i in data.dataLayer) {
                if (data.dataLayer.hasOwnProperty(i)) {
                  dataLayer.push(data.dataLayer[i]);
                }
              }
            }
          }
        });
        return false;
      });
    },

    UpdateRightSideCart: function () {
      if ($("#loadA").length < 1) {
        var loadA = $("<a href='/lush-basket' id='loadA' class='use-ajax'/>");
        $(".blackboard").append(loadA);
      }
      $("body").on("click", ".header-cart a", function () {
        if ($("body").hasClass("add-success")) {
          if ($("body").hasClass("slideout-open")) {
            $(".object-cart-slideout-wrapper .view-lush-shopping-cart-block").remove();
          }
          // To avoid additional display of cart.
          if ($("#load-cart-contents").length == 0) {
            $('#loadA').trigger("click");
          }
          $("body").removeClass("add-success");
        }
      });
    },

    closeAddCartBox: function () {
      $("body").on("click", ".add-cart-box .close", function () {
        // Remove all instances of add-cart-box.
        $(".add-cart-box").remove();
        $(".add-cart-button .throbber").remove();
        $(".add-cart-button").removeAttr("style");
        $(".node-add-to-basket").find(".review-super-wrapper,.highlighted-review-wrapper").removeAttr("style");
      });
    },

    lushAddCartProduct: function () {
      var $body = $('body');

      $body.on('click', '.commerce-add-to-cart .basket.form-submit', function (event) {
        event.preventDefault();

        var $commerceDetail = $body.find('.object-commerce-detail');
        var $parents = $commerceDetail.find('.add-cart-box');

        // Retrieve node ID.
        var nid = $body[0].className.match(/page-node-\d+/g)[0].split('-')[2];

        if ($parents.length < 1) {
          $parents = $('<div class="add-cart-box" />');
          $commerceDetail.append($parents);
        }

        // Prepare form values for submit. Values names should match to _lush_add_cart_ajax_submit().
        var dummy = {};
        var $form = $(this).closest('form');
        $.each($form.serializeArray(), function(_, item) {
          dummy[item.name] = item.value;
        });
        // Data update when product has variations.
        if (dummy.hasOwnProperty('quantity')) {
          dummy.lush_quantity = dummy.quantity;
          delete dummy.quantity;
        }
        if (dummy.hasOwnProperty('product_id')) {
          dummy.lush_product_id = dummy.product_id;
          delete dummy.product_id;
        }
        // Data update regardless of variations.
        dummy.hide_nid = nid;
        if (!dummy.hasOwnProperty('hide_product_id')) {
          dummy.hide_product_id = $('select[name=product_id] option:first-child').val();
        }
        // Finalization.
        var obj = [];
        for (var key in dummy) {
          obj.push({'name': key, 'value': dummy[key]});
        }

        $.ajax({
          type: 'post',
          url: Drupal.settings.basePath + 'lush/product/submit/ajax',
          data: {obj: obj},
          dataType: 'json',
          cache: false,
          success: function (data) {
            $parents
                .parents('.node-add-to-basket')
                .find('.review-super-wrapper, .highlighted-review-wrapper')
                .css('z-index', 0);

            if (undefined === data.errors) {
              var message = '';

              if ('Microsoft Internet Explorer' === navigator.appName && navigator.appVersion.test(/9./i)) {
                message += '<div class="add-success-box">';
                message +=   '<div class="success-message">';
                message +=     '<span class="successIcon2">';
                message +=       '<embed src="/sites/all/modules/custom/lush_add_cart/img/confirmation-tick.swf" quality="high" wmode="transparent" width="100%" height="100%" type="application/x-shockwave-flash" />';
                message +=     '</span>';
                message +=     '<div class="message">' + Drupal.t(data.message) + '</div>';
                message +=   '</div>';
                message += '</div>';
              }
              else {
                message += '<div class="add-success-box">';
                message +=   '<div class="success-message">';
                message +=     '<span class="successIcon" />';
                message +=       '<div class="message">' + Drupal.t(data.message) + '</div>';
                message +=   '</div>';
                message += '</div>';
              }

              $parents.html(message);
              $body.addClass('add-success');

              setTimeout(function () {
                $parents.fadeOut('slow').remove();
              }, 2000);
            }
            else {
              var errors = '';
              errors += '<div class="add-success-box">';
              errors +=   '<div class="errors">';

              for (var key in data.errors) {
                errors +=   '<div>';
                errors += Drupal.t(data.errors[key]);
                errors +=   '</div>';
              }

              errors +=    '<div class="close" style="right: 0;">x</div>';
              errors +=   '</div>';
              errors += '</div>';

              $parents.find('.submit-errors').remove();
              $parents.html(errors);
            }

            updateCartCounter(data.quantity);

            // The dataLayer array is provided by GTM js & if present the on push action it
            // syncs data with external GTM service.
            if (data.dataLayer && window.dataLayer) {
              for (var i in data.dataLayer) {
                if (data.dataLayer.hasOwnProperty(i)) {
                  window.dataLayer.push(data.dataLayer[i]);
                }
              }
            }
          }
        });
      });
    },

    lushAddCartBasket: function () {
      $('.object-cart-slideout-wrapper div').on('DOMSubtreeModified', '.view-lush-shopping-cart-block', function (e) {
        var itemsRaw = parseInt($('.line-item-quantity-raw').first().text().trim()) || 0;
        var itemsCart = parseInt($('#items-in-cart').text().trim()) || 0;
        if (itemsRaw != itemsCart) {
          $('#items-in-cart').html(itemsRaw.toString());
        }
      });
    },
  };
  
  function updateCartCounter(itemsToAdd) {
    // We've added a product to basked, so we need to increase the Items Counter.
    // If it's the first product we add, we need to create our container.
    if (0 == $('#items-in-cart').length) {
      $('#items-in-cart-wrapper').append('<div id="items-in-cart">0</div>');
    }
    var itemsCart = parseInt($('#items-in-cart').text().trim()) || 0;
    itemsCart += parseInt(itemsToAdd) || 0;
    $('#items-in-cart').html(itemsCart.toString());
  }

  $(document).ready(function () {
    Drupal.behaviors.lushAddCart.lushAddCartBasket();
    Drupal.behaviors.lushAddCart.lushAddCartProduct();
    Drupal.behaviors.lushAddCart.lushAddCartPopup();
    Drupal.behaviors.lushAddCart.lushAddCartWishlist();
    Drupal.behaviors.lushAddCart.lushAddCartSelectChange();
    Drupal.behaviors.lushAddCart.UpdateRightSideCart();
    Drupal.behaviors.lushAddCart.closeAddCartBox();
    Drupal.behaviors.lushAddCart.addCartButton();
    document.onreadystatechange = Drupal.behaviors.lushAddCart.naviplusAddCartButton;
    // Search page add cart button.
    if ($("body").hasClass("page-search")) {
      setInterval("Drupal.behaviors.lushAddCart.addCartButton();", 1000);
    }
    Drupal.behaviors.lushAddCart.lushAddCartFormSubmit();
    Drupal.behaviors.lushAddCart.KitchenaddAartButton();
  });
})(jQuery);
