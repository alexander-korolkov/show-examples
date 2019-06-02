<?php

namespace Drupal\hycm_landing_pages\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'theme_selector_widget' widget.
 *
 * @FieldWidget(
 *   id = "theme_selector_widget",
 *   label = @Translation("Theme selector"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class ThemeSelectorWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'size' => 60,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['value'] = $element + [
      '#type' => 'select',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#options' => $this->themesList(),
    ];

    return $element;
  }

  protected function themesList() {
    /** @var \Drupal\Core\Extension\Extension[]  $themes */
    $themes = \Drupal::service('theme_handler')->listInfo();
    unset($themes['hycm']);
    unset($themes['bartik']);
    unset($themes['classy']);
    unset($themes['seven']);
    unset($themes['stable']);
    $options = [];
    foreach ($themes as $theme) {
      $options[$theme->getName()] = $theme->getName();
    }
    return $options;
  }

}
