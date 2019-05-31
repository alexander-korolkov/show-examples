<?php

/**
 * @file
 *
 * Theme related logic.
 */

/**
 * Implements hook_preprocess_module_tpl_theme.
 */
function lush_add_cart_preprocess_lush_add_cart_tpl_theme(&$vars) {
  // This logic moved from legacy system.
  // @todo check whether it still needed.
  $node = $vars['node']['node'];

  if (isset($node->field_wishlist_entry[LANGUAGE_NONE][0]['value'])
    && empty($node->field_wishlist_entry[LANGUAGE_NONE][0]['value'])) {

    $vars['node']['wishlist'] = "";
  }
}
