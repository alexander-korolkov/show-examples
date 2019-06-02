<?php

namespace Drupal\hycm_landing_pages\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for HYCM Landing pages routes.
 */
class HycmLandingPagesController extends ControllerBase {

  /**
   * The example service.
   *
   */
  protected $renderer;

  /**
   * Constructs the controller object.
   *
   *   The example service.
   */
  public function __construct(Renderer $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer')
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
