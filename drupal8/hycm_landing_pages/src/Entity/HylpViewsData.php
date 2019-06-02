<?php

namespace Drupal\hycm_landing_pages\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Landing page entities.
 */
class HylpViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}
