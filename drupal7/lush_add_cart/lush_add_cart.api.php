<?php
/**
 * @file
 * Lush add cart hooks.
 */

/**
 * Hook lush line item update alter.
 *
 * @param object $line_item
 *   Update line item value.
 */
function hook_lush_line_item_update_alter($line_item) {
  // No example.
}

/**
 * Hook product items options alter.
 *
 * @param array $option
 *   Alter product option value.
 */
function hook_product_items_options_alter($option) {
  // No example.
}

/**
 * Hook lush cart attributes refresh alter.
 *
 * @param array $output
 *   Output json to js.
 * @param array $ret
 *   Post values.
 */
function hook_lush_cart_attributes_refresh_alter(&$output, $ret) {
  $output['submit_value'] = t('Add to basket', array(), array('context' => 'lush add to cart'));
}

/**
 * Add additional validate function.
 *
 * @param array $errors
 *   Array of errors.
 * @param array $ret
 *   Post values.
 */
function hook_lush_add_cart_validate_alter($errors, $ret) {
  // No example.
}

/**
 * Set additional data value.
 *
 * @param int $quantity
 *   Prodcut quantity.
 * @param array $data
 *   Line item data.
 * @param string $type
 *   Line item type.
 * @param array $ret
 *   Post value.
 */
function hook_lush_add_cart_data_update_alter($quantity, &$data, $type, $ret) {
  $data = array(
    'context' => array(
      'display_path' => 'node/[nid]',
      'product_ids' => 'entity',
      'add_to_cart_combine' => TRUE,
      'show_single_product_attributes' => TRUE,
      'product_select_options_use_tokens' => 1,
      'product_select_options_tokens_formatter' => '[commerce-product:commerce_price] [commerce-product:select-options-per]',
      'entity' => array(
        'entity_type' => 'node',
        'entity_id' => '[nid]',
        'product_reference_field_name' => 'field_product',
      ),
    ),
  );
}

/**
 * Update add product finish message.
 *
 * @param array $output
 *   Array of output.
 */
function hook_lush_add_cart_finish_alter($output) {
  $output['message'] = t('@quantity items were added to your basket', array('@quantity' => 1));
  $output['quantity'] = 1;
}
