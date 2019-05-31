<?php
/**
 * @file
 * Represents form and callback for administration pages.
 */

/**
 * Amount of images.
 *
 * @todo better to make dynamic form for adding N images. Not enough time.
 */
define("DKC_SIDEBAR_IMAGES", 2);

/**
 * Form handler for admin page.
 */
function dkc_sidebar_settings_form($form, &$form_state) {
  $settings = variable_get('dkc_sidebar_settings', array());

  $form['wrapper'] = array(
    '#type' => 'fieldset',
    '#title' => t('DKC sidebar images'),
    '#description' => t('Form for defining sidebar images.'),
    '#collapsible' => FALSE,
    '#tree' => TRUE,
  );

  for ($i = 0; $i < DKC_SIDEBAR_IMAGES; $i++) {
    $form['wrapper'][$i]['image'] = array(
      '#title' => t('Image #@num', array('@num' => $i + 1)),
      '#type' => 'managed_file',
      '#description' => t('The uploaded image will be displayed on sidebar conform order.'),
      '#default_value' => !empty($settings[0]['image']) ? $settings[0]['image'] : NULL,
      '#upload_location' => 'public://dkc_sidebar/',
    );

    $form['wrapper'][$i]['url'] = array(
      '#title' => t('Destination url for image #@num', array('@num' => $i + 1)),
      '#type' => 'textfield',
      '#description' => t('On click to image, user will be redirected to defined url.'),
      '#default_value' => !empty($settings[0]['url']) ? $settings[0]['url'] : NULL,
      '#element_validate' => array('element_validate_url'),
    );
  }

  $form['save'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );

  return $form;
}

/**
 * Custom validate form element.
 */
function element_validate_url($element, &$form_state) {
  $value = $element['#value'];
  if ($value != '' && !valid_url($value, TRUE)) {
    form_error($element, t('%name must be a valid url.', array('%name' => $element['#title'])));
  }
}

/**
 * Submit handler for admin pages.
 */
function dkc_sidebar_settings_formdr_submit($form, &$form_state) {
  $values = $form_state['values'];
  foreach ($values['wrapper'] as $value) {
    $fid = $value['image'];
    $file = file_load($fid);
    $file->status = FILE_STATUS_PERMANENT;
    file_save($file);
  }

  variable_set('dkc_sidebar_settings', $values['wrapper']);
  $form_state['rebuild'] = TRUE;
}
