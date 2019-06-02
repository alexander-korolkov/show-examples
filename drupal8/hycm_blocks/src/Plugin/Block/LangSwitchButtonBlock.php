<?php

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Lang Switch button' block.
 *
 * @Block(
 *   id = "hycm_blocks_lang_switch_button",
 *   admin_label = @Translation("Lang Switch button"),
 *   category = @Translation("HYCM")
 * )
 */
class LangSwitchButtonBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();

    $build['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t($currentLanguage->getName()),
      '#attributes' => [
        'class' => [
          'btn-lang-switch-mobile',
          'dir' . $currentLanguage->getDirection(),
          'lang-' . $currentLanguage->getId(),
        ],
      ],
    ];
    return $build;
  }

}
