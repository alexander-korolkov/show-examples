<?php

namespace Drupal\hycm_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class FrequentlyAskedQuestionsForm.
 */
class FrequentlyAskedQuestionsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'hycm_blocks.frequently_asked_questions',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'frequently_asked_questions_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('hycm_blocks.frequently_asked_questions');

    $items = $config->get('faqs');
    $numQuestion = $form_state->get('num_question');

    if ($numQuestion === NULL) {
      $numQuestion = !empty($items) ? count($items) : 1;
      $form_state->set('num_question', $numQuestion);
    }

    $form['#tree'] = TRUE;
    $form['faqs'] = [
      '#type' => 'container',
      '#prefix' => '<div id="questions-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $numQuestion; $i++) {
      $form['faqs'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('FAQ #@id', ['@id' => $i+1]),
        '#tree' => TRUE,
      ];
      $form['faqs'][$i]['question'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Question'),
        '#size' => 64,
        '#default_value' => (isset($items[$i]) && isset($items[$i]['question'])) ? $items[$i]['question'] :'',
        '#allowed_formats' => ['basic_html'],
      ];
      $form['faqs'][$i]['answer'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Answer'),
        '#size' => 64,
        '#default_value' => (isset($items[$i]) && isset($items[$i]['answer'])) ? $items[$i]['answer'] :'',
        '#allowed_formats' => ['basic_html'],
      ];
    }

    $form['faq_actions']['actions'] = [
      '#type' => 'actions',
    ];
    $form['faq_actions']['actions']['add_name'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add one more'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addmoreCallback',
        'wrapper' => 'questions-fieldset-wrapper',
      ],
    ];
    // If there is more than one name, add the remove button.
    if ($numQuestion > 1) {
      $form['faq_actions']['actions']['remove_name'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove one'),
        '#submit' => ['::removeCallback'],
        '#ajax' => [
          'callback' => '::addmoreCallback',
          'wrapper' => 'questions-fieldset-wrapper',
        ],
      ];
    }


    return parent::buildForm($form, $form_state);
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function addmoreCallback(array &$form, FormStateInterface $form_state) {
    return $form['faqs'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public function addOne(array &$form, FormStateInterface $form_state) {
    $numQuestion = $form_state->get('num_question');
    $add_button = $numQuestion + 1;
    $form_state->set('num_question', $add_button);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public function removeCallback(array &$form, FormStateInterface $form_state) {
    $numQuestion = $form_state->get('num_question');
    if ($numQuestion > 1) {
      $remove_button = $numQuestion - 1;
      $form_state->set('num_question', $remove_button);
    }
    $form_state->setRebuild();
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

    $items = $form_state->getValue('faqs');
    $this->config('hycm_blocks.frequently_asked_questions')
      ->set('faqs',  $items)
      ->save();

  }

}
