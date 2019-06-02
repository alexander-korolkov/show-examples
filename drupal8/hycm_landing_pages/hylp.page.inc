<?php

/**
 * @file
 * Contains hylp.page.inc.
 *
 * Page callback for Landing page entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Landing page templates.
 *
 * Default template: hylp.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_hylp(array &$variables) {
  // Fetch Hylp Entity Object.
  $hylp = $variables['elements']['#hylp'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
