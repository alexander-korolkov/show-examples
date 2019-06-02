<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'NotFound' block.
 *
 * @Block(
 *   id = "hycm_blocks_notfound",
 *   admin_label = @Translation("NotFound"),
 *   category = @Translation("HYCM")
 * )
 */
class NotFoundBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'text' => 'The requested page does not exist.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('404 Text'),
      '#default_value' => $this->configuration['text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['text'] = $form_state->getValue('text');
  }


  /**
   * {@inheritdoc}
   */
  public function build() {

    $build['content'] = [
      '#markup' =>
        new FormattableMarkup("<span class='http-code'>@code</span><p>@text</p>", [
          '@code' => '404',
          '@text' => $this->t($this->configuration['text']),
        ]),
    ];
    return $build;
  }

}
