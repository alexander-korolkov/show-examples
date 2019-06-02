<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'JoinWebinarBlock' block.
 *
 * @Block(
 *  id = "join_webinar_block_html",
 *  admin_label = @Translation("Join webinar block (HTML)"),
 *  category = @Translation("Forms")
 * )
 */
class JoinWebinarBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'webinarid' => '',
          ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['webinarid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('WebinarID'),
      '#default_value' => $this->configuration['webinarid'],
      '#maxlength' => 150,
      '#size' => 64,
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['webinarid'] = $form_state->getValue('webinarid');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['join_webinar_form'] = [
      '#theme' => 'form_join_webinar',
      '#webinarid' => $this->configuration['webinarid'],
      '#attributes' => [
        'class' => ['join-webinar-form-wrap'],
      ],
      '#attached' => [
        'library' => [
          'hycm_blocks/form-join-webinar-html'
        ]
      ]
    ];
    //['#markup'] = '<p>' .  . '</p>';

    return $build;
  }

}
