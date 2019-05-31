<?php
/**
 * @file
 * Represents pages and form related to alerts entity.
 */

/**
 * Form handler for dkc list page.
 */
function dkc_alerts_list_page($form, $form_state) {
  $form['#tree'] = TRUE;

  $header = array(
    'name' => t('Name'),
    'status' => t('Status'),
    'created' => t('Created'),
    'changed' => t('Updated'),
    'edit' => t('Operations'),
  );

  $options = array();
  $alerts = dkc_alerts_load();
  foreach ($alerts as $alert) {
    foreach (array_keys($header) as $prop) {
      switch ($prop) {
        case 'created':
        case 'changed':
          $alert->{$prop} = format_date($alert->{$prop}, 'custom', 'M/d/Y H:i:s');
          break;

        case 'status':
          $alert->{$prop} = $alert->{$prop} ? t('Enabled') : t('Disabled');
          break;

        case 'edit':
          $alert->{$prop} = l(t('Edit'), "admin/structure/dkc-alerts/{$alert->aid}/edit");
          break;
      }

      $options[$alert->aid][$prop] = $alert->{$prop};
    }
  }

  $form['dkc_alerts'] = array(
    '#type' => 'tableselect',
    '#header' => $header,
    '#options' => $options,
    '#multiple' => TRUE,
    '#empty' => t("No Alerts available"),
  );

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['disable'] = array(
    '#type' => 'submit',
    '#value' => t('Disable'),
    '#name' => 'dkc_disable',
    '#submit' => array('dkc_alerts_disable_submit'),
    '#states' => array(
      'visible' => array(
        ':input[type="checkbox"]' => array('checked' => TRUE),
      ),
    ),
  );

  $form['actions']['delete'] = array(
    '#type' => 'submit',
    '#value' => t('Delete'),
    '#name' => 'dkc_delete',
    '#submit' => array('dkc_alerts_delete_submit'),
    '#states' => array(
      'visible' => array(
        ':input[type="checkbox"]' => array('checked' => TRUE),
      ),
    ),
  );
  return $form;
}

/**
 * Submit handler for disabling alerts.
 */
function dkc_alerts_disable_submit($form, &$form_state) {
  $values = $form_state['values'];
  $alerts = array_filter($values['dkc_alerts']);
  dkc_alerts_disable_all($alerts);
  $form_state['rebuild'] = TRUE;
}

/**
 * Submit handler for removing alerts.
 */
function dkc_alerts_delete_submit($form, &$form_state) {
  $values = $form_state['values'];
  $alerts = array_filter($values['dkc_alerts']);
  dkc_alerts_delete($alerts);
  $form_state['rebuild'] = TRUE;
}

/**
 * Form handler for creating/editing alerts.
 *
 * @param string $aid
 *   Id of dkc alert.
 */
function dkc_alert_form($form, &$form_state, $aid = NULL) {
  $form['#tree'] = TRUE;

  $alert = new stdClass();
  $button_title = t('Update');
  if (empty($aid)) {
    drupal_set_title(t('Creating DKC alert'));
    $button_title = t('Create');
  }
  else {
    $alert = dkc_alert_load($aid);
    $form_state['alert'] = $alert;
    drupal_set_title(t('Editing @name alert', array('@name' => $alert->name)));
  }
  $form['alert'] = array(
    '#type' => 'fieldset',
    '#title' => t('Alerts properties'),
    '#collapsible' => FALSE,
  );

  $form['alert']['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Name'),
    '#description' => t('Name of dkc alert.'),
    '#default_value' => !empty($alert->name) ? $alert->name : NULL,
    '#attributes' => array('placeholder' => t('Enter name of alert')),
    '#maxlength' => 256,
    '#required' => TRUE,
  );

  $form['alert']['description'] = array(
    '#type' => 'textarea',
    '#title' => t('Description'),
    '#description' => t('Description of dkc alert.'),
    '#default_value' => !empty($alert->description) ? $alert->description : NULL,
    '#attributes' => array('placeholder' => t('Enter description of alert')),
    '#maxlength' => 512,
  );

  $form['alert']['body'] = array(
    '#type' => 'text_format',
    '#title' => t('Body of alert'),
    '#description' => t('Represents text/html for displaying as alert.'),
    '#default_value' => !empty($alert->body) ? $alert->body : NULL,
    '#attributes' => array('placeholder' => t('Enter an text/html')),
  );

  $form['alert']['colors'] = array(
    '#type' => 'fieldset',
    '#collapsible' => FALSE,
    '#title' => t('Colors'),
  );

  $form['alert']['colors']['color'] = array(
    '#type' => 'textfield',
    '#prefix' => '<div class="dkc-wrapper"><p><strong>' . t('Background color') . '</strong></p>',
    '#suffix' => '</div>',
    '#default_value' => !empty($alert->color) ? $alert->color : '',
    '#attributes' => array(
      'class' => array('spectrum-color-picker'),
      'data-color' => !empty($alert->color) ? $alert->color : '',
    ),
  );

  $form['alert']['colors']['textcolor'] = array(
    '#type' => 'textfield',
    '#prefix' => '<div class="dkc-wrapper"><p><strong>' . t('Text color') . '</strong></p>',
    '#suffix' => '</div>',
    '#default_value' => !empty($alert->textcolor) ? $alert->textcolor : '',
    '#attributes' => array(
      'class' => array('spectrum-color-picker'),
      'data-color' => !empty($alert->textcolor) ? $alert->textcolor : '',
    ),
  );

  $form['alert']['dates'] = array(
    '#type' => 'fieldset',
    '#collapsible' => FALSE,
    '#title' => t('Dates'),
  );

  $form['alert']['dates']['start'] = array(
    '#title' => t('Starting of alert'),
    '#description' => t('Starting from this day, alert will be displayed on entered pages.'),
    '#type' => 'date_popup',
    '#date_format' => 'M/d/Y',
    '#default_value' => !empty($alert->start) ? format_date($alert->start, 'custom', 'Y-m-d') : NULL,
  );

  $form['alert']['dates']['end'] = array(
    '#title' => t('End date of alert'),
    '#description' => t('Alert will be shown up to this date.'),
    '#type' => 'date_popup',
    '#date_format' => 'M/d/Y',
    '#default_value' => !empty($alert->end) ? format_date($alert->end, 'custom', 'Y-m-d') : NULL,
  );

  $form['alert']['options'] = array(
    '#type' => 'fieldset',
    '#collapsible' => FALSE,
    '#title' => t('Options'),
  );

  $form['alert']['options']['region'] = array(
    '#type' => 'textfield',
    '#title' => t('Region'),
    '#description' => t('Region where alert should be displayed.'),
    '#default_value' => !empty($alert->region) ? $alert->region : variable_get('dkc_alert_region', 'header_top'),
    '#maxlength' => 63,
    '#required' => TRUE,
  );

  $form['alert']['options']['icon'] = array(
    '#type' => 'checkbox',
    '#title' => t('Display error icon'),
    '#description' => t('Indicates visibility of error icon.'),
    '#default_value' => !empty($alert->icon) ? $alert->icon : NULL,
  );

  $form['alert']['options']['status'] = array(
    '#type' => 'checkbox',
    '#title' => t('Active'),
    '#description' => t('Indicates status of dkc alert.'),
    '#default_value' => !empty($alert->status) ? $alert->status : NULL,
  );

  if (!empty($aid)) {
    $form['alert']['aid'] = array(
      '#type' => 'value',
      '#value' => $alert->aid,
    );

    $form['alert']['created'] = array(
      '#type' => 'value',
      '#value' => $alert->created,
    );
  }

  $form['configs_wrapper'] = array(
    '#type' => 'fieldset',
    '#title' => t('Configs'),
    '#collapsible' => TRUE,
    '#prefix' => '<div id="dkc-alerts-config-wrapper">',
    '#suffix' => '</div>',
    '#empty' => t("At this moment not one config doesn't exists"),
    '#theme' => 'dkc_alert_config_table',
    '#header' => array(
      'path' => t('Path'),
      'color' => t('regexpression'),
      'delete' => t('Delete'),
    ),
  );

  if (empty($form_state['config_count'])) {
    $ids = array_keys($alert->config);
    if (!empty($ids)) {
      $form_state['config_count'] = array_combine($ids, $ids);
    }
    else {
      $form_state['config_count'] = array(0 => 0);
    }
  }
  foreach ($form_state['config_count'] as $i) {
    $form['configs_wrapper']['configs'][$i]['path'] = array(
      '#type' => 'textfield',
      '#default_value' => !empty($alert->config[$i]->path) ? $alert->config[$i]->path : NULL,
      '#size' => 120,
      '#attributes' => array('placeholder' => t('Enter relative path or regexpression')),
    );

    $form['configs_wrapper']['configs'][$i]['regexpression'] = array(
      '#type' => 'checkbox',
      '#default_value' => !empty($alert->config[$i]->regexpression) ? $alert->config[$i]->regexpression : NULL,
    );

    $form['configs_wrapper']['configs'][$i]['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Remove'),
      '#name' => 'dkc_alert_delete_' . $i,
      '#config_id' => $i,
      '#cid' => !empty($alert->config[$i]->cid) ? $alert->config[$i]->cid : NULL,
      '#limit_validation_errors' => array(),
      '#submit' => array('dkc_alert_config_remove_submit'),
      '#ajax' => array(
        'wrapper' => 'dkc-alerts-config-wrapper',
        'callback' => 'dkc_alert_config_ajax_handler',
      ),
    );
  }

  $form['configs_wrapper']['add'] = array(
    '#type' => 'submit',
    '#value' => t('Add'),
    '#name' => 'dkc_alert_add',
    '#submit' => array('dkc_alert_config_add_submit'),
    '#limit_validation_errors' => array(),
    '#ajax' => array(
      'wrapper' => 'dkc-alerts-config-wrapper',
      'callback' => 'dkc_alert_config_ajax_handler',
    ),
  );

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['save'] = array(
    '#type' => 'submit',
    '#value' => $button_title,
    '#name' => 'dkc_alert_save',
    '#submit' => array('dkc_alert_save_submit'),
  );

  if (!empty($aid)) {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete'),
      '#name' => 'dkc_alert_delete',
      '#submit' => array('dkc_alert_delete_submit'),
    );
  }

  $form['#attached'] = array(
    'js' => array(
      libraries_get_path('spectrum') . '/spectrum.js',
      drupal_get_path('module', 'dkc_alerts') . '/js/dkc_alerts_form.js',
    ),
    'css' => array(
      libraries_get_path('spectrum') . '/spectrum.css',
      drupal_get_path('module', 'dkc_alerts') . '/css/dkc_alerts_form.css',
    ),
  );
  return $form;
}

/**
 * Submit handler for saving alert.
 */
function dkc_alert_save_submit($form, &$form_state) {
  $values = $form_state['values'];
  // Process dates.
  $values['alert']['start'] = strtotime($values['alert']['dates']['start']);
  $values['alert']['end'] = strtotime($values['alert']['dates']['end']);
  unset($values['alert']['dates']);

  // Process body.
  $values['alert']['body'] = $values['alert']['body']['value'];

  // Process colors.
  $values['alert']['color'] = $values['alert']['colors']['color'];
  $values['alert']['textcolor'] = $values['alert']['colors']['textcolor'];
  unset($values['alert']['colors']);

  // Process options.
  $values['alert']['region'] = $values['alert']['options']['region'];
  $values['alert']['icon'] = $values['alert']['options']['icon'];
  $values['alert']['status'] = $values['alert']['options']['status'];
  unset($values['alert']['options']);

  // Process configs.
  $config = (object) array_map(function ($i) {
    unset($i['delete']);
    return $i;
  }, $values['configs_wrapper']['configs']);

  // Create alert.
  $alert = (object) $values['alert'];
  $alert->config = $config;
  $alert = dkc_alert_save($alert);

  if ($alert == FALSE) {
    drupal_set_message(t('Something happened during saving. Check reports.'), 'error');
  }
  else {
    drupal_set_message(t('Successfully saved.'));
  }

  drupal_goto('admin/structure/dkc-alerts');
}

/**
 * Submit handler for adding configs.
 */
function dkc_alert_config_add_submit($form, &$form_state) {
  $max = max(array_values($form_state['config_count']));
  $form_state['config_count'][$max + 1] = $max + 1;
  $form_state['rebuild'] = TRUE;
}

/**
 * Submit handler for adding configs.
 */
function dkc_alert_config_remove_submit($form, &$form_state) {
  $elem = $form_state['triggering_element'];
  unset($form_state['config_count'][$elem['#config_id']]);
  $form_state['rebuild'] = TRUE;
}

/**
 * Ajax handler for getting part of form.
 */
function dkc_alert_config_ajax_handler($form, &$form_state) {
  $elem = $form_state['triggering_element'];
  $parents = $elem['#parents'];
  switch (substr($elem['#name'], 0, 16)) {
    case 'dkc_alert_add':
      array_pop($parents);
      return drupal_array_get_nested_value($form, $parents);

    case 'dkc_alert_delete':
      $parents = array_slice($parents, 0, count($parents) - 3);
      return drupal_array_get_nested_value($form, $parents);
  }
}
