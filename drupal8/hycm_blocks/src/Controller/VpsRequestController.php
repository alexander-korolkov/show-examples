<?php

namespace Drupal\hycm_blocks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\hycm_blocks\VpsRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Hycm Blocks routes.
 */
class VpsRequestController extends ControllerBase {

  /**
   *
   */
  protected $vpsRequest;

  /**
   * Constructs the controller object.
   *
   * @param VpsRequest $vpsRequest
   */
  public function __construct(VpsRequest $vpsRequest) {
    $this->vpsRequest = $vpsRequest;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('hycm_blocks.vps_request')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
