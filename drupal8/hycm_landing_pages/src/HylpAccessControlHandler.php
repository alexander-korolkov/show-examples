<?php

namespace Drupal\hycm_landing_pages;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Landing page entity.
 *
 * @see \Drupal\hycm_landing_pages\Entity\Hylp.
 */
class HylpAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\hycm_landing_pages\Entity\HylpInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished landing page entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published landing page entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit landing page entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete landing page entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add landing page entities');
  }

}
