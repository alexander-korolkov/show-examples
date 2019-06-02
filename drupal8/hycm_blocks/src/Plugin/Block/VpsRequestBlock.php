<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;

/**
 * Provides a 'VpsRequest' block.
 *
 * @Block(
 *   id = "hycm_blocks_vpsrequest",
 *   admin_label = @Translation("VPS Request"),
 *   category = @Translation("Buttons")
 * )
 */
class VpsRequestBlock extends BlockBase {



  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'request' =>  t('You are about to request 24/7 access to HYCM VPS. USD 27 or equivalent account currency will be deducted from your account for the first monthly fee.'),
      'confirm' =>  t('Your request has been submitted. You will receive an email containing your access credentials within 24 working hours.'),
      'request_title' => t('HYCM VPS'),
      'confirm_title' => t('Request sent'),
      'after_text' => '<p>'.t('In order to be eligible for our VPS, you should have an HYCM live account.').'</p>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['request_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request title'),
      '#default_value' => $this->configuration['request_title'],
    ];

    $form['request'] = [
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#title' => $this->t('Request text'),
      '#default_value' => $this->configuration['request'],
    ];

    $form['confirm_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirm title'),
      '#default_value' => $this->configuration['confirm_title'],
    ];
    $form['confirm'] = [
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#title' => $this->t('Сonfirm text'),
      '#default_value' => $this->configuration['confirm'],
    ];

    $form['after_text'] = [
      '#type' => 'text_format',
      '#format' => 'full_html',
      '#title' => $this->t('After button text'),
      '#default_value' => $this->configuration['after_text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['request_title'] = $form_state->getValue('request_title');
    $this->configuration['request'] = $form_state->getValue('request')['value'];
    $this->configuration['confirm_title'] = $form_state->getValue('confirm_title');
    $this->configuration['confirm'] = $form_state->getValue('confirm')['value'];
    $this->configuration['after_text'] = $form_state->getValue('after_text')['value'];
  }



  /**
   * {@inheritdoc}
   */
  public function build() {

    $cookies = \Drupal::request()->cookies;

    $settings = [
      'request_title' => $this->configuration['request_title'],
      'request_content' => $this->configuration['request'],
      'confirm_title' => $this->configuration['confirm_title'],
      'confirm_content' => $this->configuration['confirm'],
      'cookies' => [
        'from_vps' => $cookies->get('from_vps'),
      ],
    ];

    $build['content']['button'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Request HYCM VPS'),
      '#attributes' => [
        'class' => [
          'btn',
          'btn-success',
          'vps-request-trigger'
        ]
      ],
    ];
    $build['content']['after_text'] = [
      '#markup' => $this->configuration['after_text'],
    ];

    $build['content']['#attached'] = [
      'library' => ['hycm_blocks/vps-request'],
      'drupalSettings' => [
        'hycm_blocks' => [
          'vpsrequest' => $settings,
        ],
      ],
    ];


    return $build;
  }

}
