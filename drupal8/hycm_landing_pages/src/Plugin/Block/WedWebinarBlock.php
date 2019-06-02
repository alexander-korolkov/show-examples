<?php

namespace Drupal\hycm_landing_pages\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'WedWebinarBlock' block.
 *
 * @Block(
 *   id = "hycm_landing_pages_wed_webinar",
 *   admin_label = @Translation("Wed Webinar"),
 *   category = @Translation("HYCM LP Blocks")
 * )
 */
class WedWebinarBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $build['content'] = [
      '#theme' => 'lpblock_wed_webinar',
      '#fields' => [
        'header' => $this->configuration['header'],
        'top_left' => $this->configuration['top_left'],
        'top_right' => $this->configuration['top_right'],
        'bottom_left' => $this->configuration['bottom_left'],
        'bottom_right' => $this->configuration['bottom_left'],
      ],
      '#attached' => [
        //TODO: need to change this after disabling old theme
        'library' => ['hycm/landing_wed-webinar',],
      ],
    ];
    return $build;
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['header'] = [
      '#type' => 'details',
      '#title' => $this->t('Header'),
      '#tree' => TRUE,
    ];
    $form['header']['subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subtitle'),
      '#default_value' => isset($this->configuration['header']['subtitle']) ? $this->configuration['header']['subtitle'] : '',
    ];
    $form['header']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('title'),
      '#default_value' => isset($this->configuration['header']['title']) ? $this->configuration['header']['title'] : '',
    ];
    $form['header']['webinar_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('webinar_time'),
      '#default_value' => isset($this->configuration['header']['webinar_time']) ? $this->configuration['header']['webinar_time'] : '',
    ];
    $form['header']['duration'] = [
      '#type' => 'textfield',
      '#title' => $this->t('duration'),
      '#default_value' => isset($this->configuration['header']['duration']) ? $this->configuration['header']['duration'] : '',
    ];
    $form['header']['btn_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Btn label'),
      '#default_value' => isset($this->configuration['header']['subtitle']) ? $this->configuration['header']['btn_label'] : '',
    ];

    $form['top_left'] = [
      '#type' => 'details',
      '#title' => $this->t('Top right'),
      '#tree' => TRUE,
    ];
    $form['top_left']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => isset($this->configuration['top_left']['title']) ? $this->configuration['top_left']['title'] : '',
    ];
    $form['top_left']['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => isset($this->configuration['top_left']['content']) ? $this->configuration['top_left']['content'] : '',
    ];

    $form['top_right'] = [
      '#type' => 'details',
      '#title' => $this->t('Top right'),
      '#tree' => TRUE,
    ];
    $form['top_right']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => isset($this->configuration['top_right']['title']) ? $this->configuration['top_right']['title'] : '',
    ];
    $form['top_right']['content'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Content'),
      '#default_value' => isset($this->configuration['top_right']['content']) ? $this->configuration['top_right']['content'] : '',
    ];

    $form['bottom_left'] = [
      '#type' => 'details',
      '#title' => $this->t('Bottom left'),
      '#tree' => TRUE,
    ];

    $form['bottom_right'] = [
      '#type' => 'details',
      '#title' => $this->t('Bottom right'),
      '#tree' => TRUE,
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['header'] = $form_state->getValue('header');
    $this->configuration['top_left'] = $form_state->getValue('top_left');
    $this->configuration['top_right'] = $form_state->getValue('top_right');
    $this->configuration['bottom_left'] = $form_state->getValue('bottom_left');
    $this->configuration['bottom_right'] = $form_state->getValue('bottom_right');
  }


}
