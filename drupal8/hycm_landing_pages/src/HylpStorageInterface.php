<?php

namespace Drupal\hycm_landing_pages;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface HylpStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Landing page revision IDs for a specific Landing page.
   *
   * @param \Drupal\hycm_landing_pages\Entity\HylpInterface $entity
   *   The Landing page entity.
   *
   * @return int[]
   *   Landing page revision IDs (in ascending order).
   */
  public function revisionIds(HylpInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Landing page author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Landing page revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\hycm_landing_pages\Entity\HylpInterface $entity
   *   The Landing page entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(HylpInterface $entity);

  /**
   * Unsets the language for all Landing page with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
