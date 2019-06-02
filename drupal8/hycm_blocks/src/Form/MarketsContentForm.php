<?php

namespace Drupal\hycm_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class MarketsContentForm.
 */
class MarketsContentForm extends ConfigFormBase {

  /**
   * @var array
   */
  static protected $markets = [
    'forex',
    'stocks',
    'indices',
    'cryptocurrencies',
    'commodities'
  ];
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'hycm_blocks.marketscontent',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'markets_content_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hycm_blocks.marketscontent');

    foreach (self::$markets as $market) {
      $form[$market] = [
        '#type' => 'details',
        '#title' => $this->t('Market @name', ['@name' => $market]),
        '#tree' => TRUE,
      ];
      $form[$market]['name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#maxlength' => 150,
        '#size' => 64,
        '#default_value' => $config->get($market. '.name'),
      ];
      $form[$market]['title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Title (Home block)'),
        '#maxlength' => 150,
        '#size' => 64,
        '#default_value' => $config->get($market. '.title'),
      ];
      $form[$market]['description'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Description'),
        '#default_value' => $config->get($market. '.description'),
      ];
      $form[$market]['max_leverage'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Max leverage (.com)'),
        '#maxlength' => 64,
        '#size' => 64,
        '#default_value' => $config->get($market. '.max_leverage'),
      ];
      $form[$market]['max_leverage_eu'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Max leverage (.eu & .co.uk)'),
        '#maxlength' => 64,
        '#size' => 64,
        '#default_value' => $config->get($market. '.max_leverage_eu'),
      ];
      $form[$market]['spreads'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Spreads from (.com)'),
        '#maxlength' => 64,
        '#size' => 64,
        '#default_value' => $config->get($market. '.spreads'),
        '#field_suffix' => ' pips',
      ];
      $form[$market]['spreads_eu'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Spreads from (.eu & .co.uk)'),
        '#maxlength' => 64,
        '#size' => 64,
        '#default_value' => $config->get($market. '.spreads_eu'),
        '#field_suffix' => ' pips',
      ];
      $form[$market]['margins'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Margins from just (.com)'),
        '#maxlength' => 64,
        '#size' => 64,
        '#default_value' => $config->get($market. '.margins'),
        '#field_suffix' => ' %',
      ];
      $form[$market]['margins_eu'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Margins from just (.eu & .co.uk)'),
        '#maxlength' => 64,
        '#size' => 64,
        '#default_value' => $config->get($market. '.margins_eu'),
        '#field_suffix' => ' %',
      ];
      $form[$market]['market_id'] = [
        '#type' => 'hidden',
        '#value' => $market,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    foreach (self::$markets as $market) {
      $this->config('hycm_blocks.marketscontent')
        ->set($market,  $form_state->getValue($market))
        ->save();
    }

  }

}
