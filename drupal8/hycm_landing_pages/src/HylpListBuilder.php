<?php

namespace Drupal\hycm_landing_pages;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Landing page entities.
 *
 * @ingroup hycm_landing_pages
 */
class HylpListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Landing page ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\hycm_landing_pages\Entity\Hylp */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.hylp.canonical',
      ['hylp' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
