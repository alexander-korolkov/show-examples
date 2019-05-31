<?php

/**
 * @file
 *
 * Lush cart form call back.
 */

/**
 * Ajax call back.
 */
function _lush_add_cart_ajax_call_product() {
  $nid = isset($_POST['nid']) ? $_POST['nid'] : arg(3);
  $featured = isset($_POST['featured']) ? $_POST['featured'] : arg(4);
  $featured = $featured == "true" ? TRUE : FALSE;

  if (!$node = node_load($nid)) {
    drupal_json_output(t('error'));
  }

  $node_view['field_image'] = _lush_add_cart_get_image($node, $featured);

  // Disable the wish list link by default.
  if (variable_get('lush_add_cart_enable_wish_list_link', FALSE)) {
    $flag_link = _lush_add_cart_flag_create_link('wishlist', $nid);
    $node_view['wishlist'] = $flag_link;
  }

  $node_view['node'] = $node;

  $form = drupal_get_form('lush_add_cart_get_cart_form', $node);

  $form_html = drupal_render($form);

  $content = theme('lush_add_cart_tpl_theme', array(
    'node' => $node_view,
    'form_content' => $form_html,
  ));

  if (isset($_POST['nid'])) {
    drupal_json_output($content);
  }
  else {
    drupal_json_output($content);
  }
}

/**
 * Get cart form.
 *
 * @param array $form
 *   Form.
 * @param array $form_state
 *   Form state.
 * @param object $node
 *   Node.
 *
 * @return array
 *   Return form array.
 */
function lush_add_cart_get_cart_form($form, $form_state, $node) {
  $wrapper = entity_metadata_wrapper('node', $node);
  $product_ids = $wrapper->field_product->raw();

  $products = commerce_product_load_multiple(
    $product_ids,
    array('status' => 1)
  );
  $order = array_flip($product_ids);
  $available = [];
  foreach ($products as $product_id => $product) {
    $available[$product_id] = lush_commerce_sml_stock_product_can_be_reserved_cached($product, 1);
  }
  uksort($products, function ($a, $b) use ($order, $available) {
    if ($available[$a] == $available[$b]) {
      return $order[$a] < $order[$b] ? -1 : 1;
    }
    else {
      return $available[$a] ? -1 : 1;
    }
  });

  $form['hide_nid'] = array(
    '#type' => 'hidden',
    '#value' => $node->nid,
  );

  $form['#theme'] = 'lush_add_cart_form_theme';

  if (empty($node->status) || empty($products)) {

    $form['lush_submit'] = array(
      '#markup' => sprintf(
        '<div class="unavailable disabled">%s</div>',
        t('Product not available', array(), array('context' => 'lush add to cart'))
      ),
      '#attributes' => array('disabled' => 'disabled'),
    );

    $form['product_status'] = array(
      '#type' => 'hidden',
      '#value' => $node->status,
    );

    $form['lush_submit'] = array(
      '#markup' => sprintf(
        '<div class="unavailable disabled">%s</div>',
        t("Product not available", array('context' => 'lush add to cart'))
      ),
    );

    return $form;
  }

  $options = _lush_add_cart_get_product_items_option($products);

  $product_ids = array_keys($products);
  $default_product_id = !empty($product_ids) ? reset($product_ids) : '';

  $form['lush_quantity'] = array(
    '#type' => 'textfield',
    '#default_value' => 1,
  );

  $form['lush_product_id'] = array(
    '#type' => 'select',
    '#options' => $options,
    '#disabled' => count($options) == 1,
  );

  $form['hide_nid'] = array(
    '#type' => 'hidden',
    '#value' => $node->nid,
  );

  $form['hide_product_id'] = array(
    '#type' => 'hidden',
    '#value' => $default_product_id,
  );

  $form['lush_submit'] = array(
    '#type' => 'submit',
    '#value' => t(
      'Add to basket',
      array(),
      array('context' => 'lush add to cart')
    ),
  );

  $can_be_reserved = FALSE;
  $product = NULL;

  if (!empty($products)) {
    $product_ids = array_keys($products);
    $product = $products[$product_ids[0]];
    $can_be_reserved = $available[$product_ids[0]];
  }

  // Change form action status
  lush_add_cart_set_form_submit_value($node->nid, $can_be_reserved, $form, $product);

  return $form;
}

/**
 * Updates form submit value depends on context.
 *
 * @param $nid
 * @param $can_be_reserved
 * @param $form
 * @param $product
 */
function lush_add_cart_set_form_submit_value($nid, $can_be_reserved, &$form, $product) {
  $stock_out = FALSE;

  if ($product && !$can_be_reserved) {
    $form['lush_submit']['#markup'] = sprintf(
      '<div class="coming-soon-box size-9 bold white">%s</div>',
      t("Out of stock", array(), array('context' => 'lush add to cart'))
    );

    $form['lush_submit']['#type']   = 'markup';
    $stock_out = TRUE;
  }
  elseif (!$product) {
    // Disable product by default.
    $form['lush_submit']['#attributes'] = array('disabled' => 'disabled', 'class' => array('unavailable'));
  }

  $add_to_basket = t('Add to Basket', array('context' => 'lush add to cart'));

  switch (TRUE) {
    case module_exists('lush_kitchen'):
      $kitchen_status = lush_kitchen_product_status($nid);

      switch ($kitchen_status) {
        case 'coming_soon' :
          $form['lush_submit']['#markup'] = sprintf(
            '<div class="coming-soon-box size-9 bold white">%s</div>',
            t('Coming Soon')
          );

          $form['lush_submit']['#type'] = 'markup';
          unset($form['lush_submit']['#attributes']);

          break;

        case 'available' :
          if (isset($form['lush_submit']['#markup']) && !$stock_out) {
            unset($form['lush_submit']['#markup']);
          }

          if (!$stock_out) {
            $form['lush_submit']['#type'] = 'submit';
            $form['lush_submit']['#value'] = $add_to_basket;
          }

          break;

        case 'limited_stock' :
          if (isset($form['lush_submit']['#markup']) && !$stock_out) {
            unset($form['lush_submit']['#markup']);
          }
          if (!$stock_out) {
            $form['lush_submit']['#type'] = 'submit';
            $form['lush_submit']['#value'] = $add_to_basket;
          }
          break;

        case 'unavailable' :
          $form['lush_submit']['#markup'] = sprintf(
            '<div class="coming-soon-box size-9 bold white">%s</div>',
            t('Unavailable')
          );

          $form['lush_submit']['#type'] = 'markup';
          unset($form['lush_submit']['#attributes']);

          break;

        case 'sold_out' :
          $form['lush_submit']['#markup'] = sprintf(
            '<div class="coming-soon-box size-9 bold white">%s</div>',
            t('Sold out')
          );

          $form['lush_submit']['#type'] = 'markup';
          unset($form['lush_submit']['#attributes']);

          break;

        default :
          if (isset($form['lush_submit']['#attributes']['disabled'])
            && $form['lush_submit']['#attributes']['disabled'] == 'disabled') {

            $form['lush_submit']['#markup'] = sprintf(
              '<div class="unavailable coming-soon-box size-9 bold white">%s</div>',
              t('Product not available')
            );

            $form['lush_submit']['#type']   = 'markup';
            unset($form['lush_submit']['#attributes']);
          }
          else {
            $form['lush_submit']['#value'] = $add_to_basket;
          }
          break;
      }
      break;

    default:
      if (isset($form['lush_submit']['#attributes']['disabled'])
        && $form['lush_submit']['#attributes']['disabled'] == 'disabled') {

        $form['lush_submit']['#markup'] = sprintf(
          '<div class="unavailable coming-soon-box size-9 bold white">%s</div>',
          t('Product not available')
        );

        $form['lush_submit']['#type'] = 'markup';
        unset($form['lush_submit']['#attributes']);
      }
      else {
        $form['lush_submit']['#value'] = $add_to_basket;
      }

      // Add  new class to define layout,Product is inactive.
      if (!$product && !empty($kitchen_value)) {

        if (isset($form['lush_submit']['#markup'])) {
          $form['lush_submit']['#markup'] =  str_replace(
            'coming-soon-box',
            'unavailable',
            $form['lush_submit']['#markup']
          );
        }

        if ($form['lush_submit']['#type'] == 'submit') {
          $form['lush_submit_disable'] = array(
            '#type' => 'hidden',
            '#value' => "submit-disable",
          );
        }
      }
      break;
  }
}

/**
 * Get product items.
 *
 * @param array $products
 *   Product load value.
 *
 * @return array
 *   Product option array.
 */
function _lush_add_cart_get_product_items_option($products) {
  $option = array();
  $text_items_txt = variable_get(
    'lush_add_cart_product_items_option',
    'commerce_price/field_display_size'
  );

  $items = explode('/', $text_items_txt);

  if (!isset($items[0]) || !isset($items[1])) {
    return array();
  }

  if (is_array($products)) {
    foreach ($products as $product_id => $product) {
      $price = $product->{$items[0]}[LANGUAGE_NONE][0]['amount'];
      $currency_code = $product->{$items[0]}[LANGUAGE_NONE][0]['currency_code'];
      $price = commerce_currency_format($price, $currency_code);

      if (!empty($product->{$items[1]}[LANGUAGE_NONE][0])) {
        $size_item = &$product->{$items[1]}[LANGUAGE_NONE][0];
        if (!empty($size_item['value'])) {
          $size = $size_item['value'];
        }
        elseif (!empty($size_item['tid'])) {
          if ($term = taxonomy_term_load($size_item['tid'])) {
            if (function_exists('i18n_taxonomy_localize_terms')) {
              // Translate term.
              $term = i18n_taxonomy_localize_terms($term);
            }
            $size = $term->name;
          }
        }
      }

      if (!empty($price) && !empty($size)) {
        $txt_option = $price . ' / ' . $size;
      }
      elseif (!empty($price)) {
        $txt_option = $price;
      }
      else {
        $txt_option = '';
      }

      $option[$product_id] = $txt_option;
    }
  }

  drupal_alter('product_items_options', $option);

  // Let's avoid product options tax suffix if needed.
  if (!variable_get('lush_add_cart_include_product_tax_suffix', TRUE)) {
    return $option;
  }

  if (is_array($option)) {
    foreach ($option as $key => $item) {
      if (!empty($item)) {
        $im = explode('/', $item);

        if (!empty($im[0])) {
          $new_item = trim($im[0]) . t(
            " (tax included)",
            array(),
            array('context' => 'lush add to cart')
          );

          if (!empty($im[1])) {
            $new_item .= ' / ' . trim($im[1]);
          }

          $option[$key] = $new_item;
        }
      }
    }
  }

  return $option;
}

/**
 * Change product use ajax.
 */
function _lush_add_cart_change_product_image_ajax() {
  if (empty($_POST['obj'])) {
    return;
  }

  $featured = isset($_POST['featured']) ? $_POST['featured'] : arg(4);
  $featured = $featured == "true" ? TRUE : FALSE;

  foreach ($_POST['obj'] as $item) {
    $ret[$item['name']] = $item['value'];
  }

  $product_id = $ret['lush_product_id'];
  $nid = $ret['hide_nid'];

  if (!$product = commerce_product_load($product_id)) {
    return;
  }

  // Get product image and button value.
  $output = [];

  $image = _lush_add_cart_get_image($product, $featured);

  $output['image'] = empty($image)
    ? _lush_add_cart_get_image(node_load($nid), $featured)
    : $image;

  // Manage submit values depends on stock.
  $form['lush_submit'] = [
    '#type' => 'submit',
    '#value' => t(
      "Add to basket",
      [],
      ['context' => 'lush add to cart button']
    ),
  ];

  $can_be_reserved = lush_commerce_sml_stock_product_can_be_reserved_cached(
    $product,
    1
  );

  // Adjust form submit value.
  lush_add_cart_set_form_submit_value(
    $nid,
    $can_be_reserved,
    $form,
    $product
  );

  $output['submit_status'] = 0;
  $output['submit_value'] = drupal_render($form['lush_submit']);

  drupal_alter('lush_cart_attributes_refresh', $output, $ret);

  drupal_json_output($output);
  drupal_exit();
}

/**
 * Get product image.
 *
 * @param $object Object node or commerce_product
 *
 * @return string|FALSE
 *   Return image html or FALSE.
 */
function _lush_add_cart_get_image($object, $featured = FALSE) {
  $filename = $object->field_image[LANGUAGE_NONE][0]['uri'];
  $style = $featured ? 'lush_cart_image_featured' : 'lush_cart_image';
  if (isset($filename) && !empty($filename)) {
    return theme('image_style', [
      'style_name' => $style,
      'path' => $filename,
    ]);
  }
  return FALSE;
}

/**
 * Add product to cart use ajax.
 */
function _lush_add_cart_ajax_submit() {
  global $user;

  $post = $_POST['obj'];

  foreach ($post as $item) {
    $ret[$item['name']] = $item['value'];
  }

  // Common validate.
  $quantity = $ret['lush_quantity'];
  $errors = _lush_add_cart_ajax_validate($quantity);

  drupal_alter('lush_add_cart_validate', $errors, $ret);

  if (!empty($ret) && empty($errors)) {
    // Imitate sml validation interface;
    $form_state = [
      'values' => [
        'product_id' => empty($ret['lush_product_id'])
          ? $ret['hide_product_id']
          : $ret['lush_product_id'],
        'quantity' => $ret['lush_quantity'],
      ],
      'submitted' => TRUE,
    ];

    // After legacy system current environment uses sml stock validation.
    // Use it to avoid stock problems.
    lush_commerce_sml_stock_add_to_cart_validate(
      [],
      $form_state
    );

    // Get form errors & raise the error considering current interface.
    $errors = form_get_errors();
  }

  if (isset($errors) && is_array($errors)) {
    $output['errors'] = $errors;
  }
  else {
    $quantity = (int) $ret['lush_quantity'];
    if (isset($ret['lush_product_id'])) {
      $product_id = $ret['lush_product_id'];
    }
    if (empty($product_id)) {
      $product_id = $ret['hide_product_id'];
    }
    $data = array();
    $type = 'product';

    drupal_alter('lush_add_cart_data_update', $quantity, $data, $type, $ret);

    $nid  = $ret['hide_nid'];

    $data = array(
      'context' => array(
        'display_path' => 'node/' . $nid,
        'product_ids' => 'entity',
        'add_to_cart_combine' => TRUE,
        'show_single_product_attributes' => TRUE,
        'product_select_options_use_tokens' => 1,
        'product_select_options_tokens_formatter' => '[commerce-product:commerce_price] [commerce-product:select-options-per]',
        'entity' => array(
          'entity_type' => 'node',
          'entity_id' => $nid,
          'product_reference_field_name' => 'field_product'
        )
      )
    );

    if (_lush_add_cart_product_in_cart($product_id) > 0) {
      lush_add_cart_product_alter_quantity($product_id, $quantity, 'add');
    }
    else {
      _lush_add_cart_product_cart_add($product_id, $quantity, $user->uid, $data, $type);
    }

    $output['message'] = t('@quantity item was added to your basket', array('@quantity' => $quantity));

    if ($quantity > 1) {
      $output['message'] = t('@quantity items were added to your basket', array('@quantity' => $quantity));
    }

    $output['quantity'] = $quantity;
  }

  drupal_alter('lush_add_cart_finish', $output);
  drupal_json_output($output);
  drupal_exit();
}

/**
 * Lush cart validate.
 *
 * @param int $quantity
 *   Product quantity.
 *
 * @return array|NULL
 *   Return errors or NULL.
 */
function _lush_add_cart_ajax_validate($quantity) {
  $errors = NULL;
  // Validate quantity data type.
  if (!is_numeric($quantity) || $quantity <= 0 || (int) $quantity != $quantity) {
    $errors['quantity'] = t('You must specify a valid quantity to add to the cart.', array(), array('context' => 'lush add to cart'));
  }
  return $errors;
}
