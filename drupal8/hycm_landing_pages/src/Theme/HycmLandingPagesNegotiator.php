<?php

namespace Drupal\hycm_landing_pages\Theme;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a theme negotiator that deals with the active theme on example page.
 */
class HycmLandingPagesNegotiator implements ThemeNegotiatorInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new HycmLandingPagesNegotiator.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack used to retrieve the current request.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    return $route_match->getRouteName() == 'entity.hylp.canonical';
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    if ($route_match->getParameters()->has('hylp')) {
      /** @var \Drupal\hycm_landing_pages\Entity\Hylp $entity */
      $entity = $route_match->getParameter('hylp');

      if ($theme = $entity->replaceTheme()) {
        \Drupal::logger('wor')->debug($theme);
        return $theme;
      }else{
        return 'hycm_landing';
      }
    }

    return NULL;
  }

}
