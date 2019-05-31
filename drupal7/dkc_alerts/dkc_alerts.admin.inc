<?php

/**
 * @file
 * Contains configuration forms and all logic related to it.
 */

/**
 * Form handler for alerts settings form.
 */
function dkc_alerts_settings_form($form, $form_state) {
  // @todo add default values for configs.

  $form['wrapper'] = array(
    '#type' => 'fieldset',
    '#title' => t('DKC alerts global settings'),
    '#collapsible' => FALSE,
  );

  $form['wrapper']['dkc_alerts_timeout'] = array(
    '#type' => 'textfield',
    '#default_value' => variable_get('dkc_alerts_timeout', 3),
    '#title' => t('Timeout (in seconds)'),
    '#description' => t('Represents timeout for updating alerts. Defines how often alerts will be updated.'),
  );

  $form['wrapper']['dkc_alerts_region'] = array(
    '#type' => 'textfield',
    '#default_value' => variable_get('dkc_alerts_region', 'header_top'),
    '#title' => t('Choose region for alert'),
    '#description' => t('Defines part of page, in which alerts will be displayed.'),
  );

  return system_settings_form($form);
}
