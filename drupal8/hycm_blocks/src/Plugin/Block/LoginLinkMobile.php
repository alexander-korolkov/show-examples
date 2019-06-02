<?php
/**
 * Created by PhpStorm.
 * User: Lex
 * Date: 23.08.2018
 * Time: 12:27
 */

namespace Drupal\hycm_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a 'Login link' block.
 *
 * @Block(
 *   id = "hycm_blocks_login_link",
 *   admin_label = @Translation("Login link"),
 *   category = @Translation("HYCM")
 * )
 */
class LoginLinkMobile extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $link = Link::fromTextAndUrl($this->t('Login'), Url::fromUri('internal://login'));

    $build['content'] = $link->toRenderable();
    return $build;
  }

}