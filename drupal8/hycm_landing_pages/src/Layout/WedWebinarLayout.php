<?php
/**
 * Created by PhpStorm.
 * User: Lex
 * Date: 31.07.2018
 * Time: 20:17
 */

namespace Drupal\hycm_landing_pages\Layout;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Class WedWebinarLayout
 * @deprecated 8.5.0 Do not use
 * @package Drupal\hycm_landing_pages\Layout
 */
class WedWebinarLayout extends LayoutDefault implements PluginFormInterface {


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
        'extra_classes' => 'Default',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();


    foreach ($this->getPluginDefinition()->getRegionNames() as $region) {

      $form[$region] = [
        '#type' => 'details',
        '#tree' => TRUE,
        '#title' => $this->t('Settings @region region', ['@region' => $region]),
      ];

      $form[$region]['extra_classes'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Extra classes'),
        '#default_value' => $configuration['extra_classes'],
      ];

    }

    return $form;
  }

  /**
   * @inheritdoc
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // any additional form validation that is required
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

    foreach ($this->getPluginDefinition()->getRegionNames() as $region) {
      $this->configuration[$region]['extra_classes'] = $form_state->getValue('extra_classes');

    }


  }



}