<?php

namespace Drupal\hycm_blocks;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * VpsRequest service.
 */
class VpsRequest {

  /**
   * Node storage.
   *
   * @var \Drupal\Node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a VpsRequest object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
  }

  /**
   * Retrieves the last created node.
   */
  public function getLastNode() {
    $nids = $this->nodeStorage->getQuery()
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    $nid = reset($nids);
    return $nid ? $this->nodeStorage->load($nid) : FALSE;
  }

}
