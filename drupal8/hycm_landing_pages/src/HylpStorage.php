<?php

namespace Drupal\hycm_landing_pages;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\hycm_landing_pages\Entity\HylpInterface;

/**
 * Defines the storage handler class for Landing page entities.
 *
 * This extends the base storage class, adding required special handling for
 * Landing page entities.
 *
 * @ingroup hycm_landing_pages
 */
class HylpStorage extends SqlContentEntityStorage implements HylpStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(HylpInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {hylp_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {hylp_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(HylpInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {hylp_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('hylp_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
