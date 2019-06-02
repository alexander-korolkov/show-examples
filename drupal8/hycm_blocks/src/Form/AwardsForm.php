<?php

namespace Drupal\hycm_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Hycm Blocks settings for this site.
 */
class AwardsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hycm_blocks_awards';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['hycm_blocks.awards'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $items = $this->config('hycm_blocks.awards')->get('items');
    $numItems = $form_state->get('num_items');

    if ($numItems === NULL) {
      $numItems = !empty($items) ? count($items) : 1;
      $form_state->set('num_items', $numItems);
    }

    $form['#tree'] = TRUE;
    $form['items'] = [
      '#type' => 'container',
      '#prefix' => '<div id="items-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $numItems; $i++) {
      $form['items'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Award #@id', ['@id' => $i+1]),
        '#tree' => TRUE,
        '#open' => TRUE,
      ];

      $form['items'][$i]['label'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Label'),
        '#default_value' => (isset($items[$i]) && isset($items[$i]['label'])) ? $items[$i]['label'] :'',
      ];
      $form['items'][$i]['content'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Content'),
        '#default_value' => (isset($items[$i]) && isset($items[$i]['content'])) ? $items[$i]['content'] :'',
      ];
      $form['items'][$i]['year'] = [
        '#type' => 'textfield',
        '#required' => TRUE,
        '#title' => $this->t('Year'),
        '#default_value' => (isset($items[$i]) && isset($items[$i]['year'])) ? $items[$i]['year'] :'',
      ];
      $form['items'][$i]['city'] = [
        '#type' => 'textfield',
        '#title' => $this->t('City'),
        '#default_value' => (isset($items[$i]) && isset($items[$i]['city'])) ? $items[$i]['city'] :'',
      ];
    }

    $form['more']['actions'] = [
      '#type' => 'actions',
    ];
    $form['more']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => ['::addOne'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'items-fieldset-wrapper',
      ],
    ];
    // If there is more than one item, add the remove button.
    if ($numItems > 1) {
      $form['more']['actions']['remove_name'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one'),
        '#submit' => ['::removeCallback'],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'items-fieldset-wrapper',
        ],
      ];
    }



    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /*
    if ($form_state->getValue('example') != 'example') {
      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
    }
    */
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
   /*
    dpm(array_filter($form_state->getValue('items'), function($item){
      return !empty($item['label']);
    }));
   */
    $this->config('hycm_blocks.awards')
      ->set('items', $form_state->getValue('items'))
      ->save();
    parent::submitForm($form, $form_state);
  }


  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['items'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $numItems = $form_state->get('num_items');
    $add_button = $numItems + 1;
    $form_state->set('num_items', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $numItems = $form_state->get('num_items');
    if ($numItems > 1) {
      $remove_button = $numItems - 1;
      $form_state->set('num_items', $remove_button);
    }
    $form_state->setRebuild();
  }


}
