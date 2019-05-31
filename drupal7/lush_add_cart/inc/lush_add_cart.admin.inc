<?php

/**
 * @file
 * Lush add cart config code.
 */

/**
 * Lush configure options.
 *
 * @return array
 *   Return form array.
 */
function lush_add_cart_configure_form($form, &$form_state) {
  // Tab wrapper.
  $form['lush_add_cart'] = array(
    '#type' => 'vertical_tabs',
  );

  // Visibility configuration.
  $form['visibility'] = array(
    '#type' => 'fieldset',
    '#title' => t('Visibility'),
    '#description' => t("Configure pages where add to cart widget will be available."),
    '#collapsible' => TRUE,
    '#group' => 'lush_add_cart',
  );

  // Bulk buying mode.
  $form['visibility']['lush_add_cart_visibility_global'] = array(
    '#type' => 'checkbox',
    '#title' => t('Global'),
    '#prefix' => '<br />',
    '#description' => t('Add to cart widget will be added to all products globally that rendered in teaser view mode.'),
    '#default_value' => variable_get('lush_add_cart_visibility_global'),
  );

  $form['visibility']['lush_add_cart_visibility_home_pages'] = array(
    '#type' => 'checkbox',
    '#title' => t('Home pages'),
    '#description' => t('Front page & all node of home page content type will have  add to cart widget.'),
    '#default_value' => variable_get('lush_add_cart_visibility_home_pages'),
    '#states' => array(
      'invisible' => array(
        ':input[name="lush_add_cart_visibility_global"]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['visibility']['lush_add_cart_visibility_categories'] = array(
    '#type' => 'checkbox',
    '#title' => t('Categories'),
    '#description' => t('Category pages will have add to cart widget.'),
    '#default_value' => variable_get('lush_add_cart_visibility_categories', TRUE),
    '#states' => array(
      'invisible' => array(
        ':input[name="lush_add_cart_visibility_global"]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['visibility']['lush_add_cart_product_content_type'] = array(
    '#type' => 'fieldset',
    '#title' => t('Select product content type'),
    '#prefix' => '<br />',
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['visibility']['lush_add_cart_product_content_type']['lush_add_cart_content_type_list'] = array(
    '#type' => 'checkboxes',
    '#options' => drupal_map_assoc(array_keys(node_type_get_types())),
    '#title' => t('Select product content type'),
    '#default_value' => variable_get('lush_add_cart_content_type_list', []),
  );

  // Widget configuration.
  $form['widget'] = array(
    '#type' => 'fieldset',
    '#title' => t('Widget fields'),
    '#description' => t("Configure widget settings."),
    '#collapsible' => TRUE,
    '#group' => 'lush_add_cart',
  );

  $form['widget']['lush_add_cart_include_product_tax_suffix'] = array(
    '#type' => 'checkbox',
    '#default_value' => variable_get('lush_add_cart_include_product_tax_suffix', TRUE),
    '#description' => t(
      'Please user translation interface to alter wording for "(tax included)"'
    ),
    '#title' => t('Include product tax suffix'),
  );

  $form['widget']['lush_add_cart_product_items_option'] = array(
    '#type' => 'textfield',
    '#default_value' => variable_get('lush_add_cart_product_items_option', 'commerce_price/field_display_size'),
    '#title' => t('Set lush cart product select option'),
    '#description' => t('Set product select option value. example: commerce_price/field_display_size'),
  );

  $form['widget']['lush_add_cart_flag_anonymous_msg'] = array(
    '#type' => 'textarea',
    '#title' => t('Set message of anonymous'),
    '#default_value' => variable_get('lush_add_cart_flag_anonymous_msg', ''),
    '#description' => t('Can set :login,:register for replace'),
  );

  // Social configuration.
  $form['social'] = array(
    '#type' => 'fieldset',
    '#title' => t('Social'),
    '#description' => t("Configure social settings."),
    '#collapsible' => TRUE,
    '#group' => 'lush_add_cart',
  );

  $form['social']['lush_add_cart_fivestar_disable'] = array(
    '#type' => 'checkbox',
    '#default_value' => variable_get('lush_add_cart_fivestar_disable', TRUE),
    '#title' => t('Disable five start for products'),
  );

  $form['social']['lush_add_cart_enable_wish_list_link'] = array(
    '#type' => 'checkbox',
    '#default_value' => variable_get('lush_add_cart_enable_wish_list_link', FALSE),
    '#title' => t('Enable wish list product link'),
  );

  return system_settings_form($form);
}
