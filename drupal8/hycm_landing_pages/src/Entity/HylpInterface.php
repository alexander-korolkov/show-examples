<?php

namespace Drupal\hycm_landing_pages\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Landing page entities.
 *
 * @ingroup hycm_landing_pages
 */
interface HylpInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Landing page name.
   *
   * @return string
   *   Name of the Landing page.
   */
  public function getName();

  /**
   * Sets the Landing page name.
   *
   * @param string $name
   *   The Landing page name.
   *
   * @return \Drupal\hycm_landing_pages\Entity\HylpInterface
   *   The called Landing page entity.
   */
  public function setName($name);

  /**
   * Gets the Landing page creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Landing page.
   */
  public function getCreatedTime();

  /**
   * Sets the Landing page creation timestamp.
   *
   * @param int $timestamp
   *   The Landing page creation timestamp.
   *
   * @return \Drupal\hycm_landing_pages\Entity\HylpInterface
   *   The called Landing page entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Landing page published status indicator.
   *
   * Unpublished Landing page are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Landing page is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Landing page.
   *
   * @param bool $published
   *   TRUE to set this Landing page to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\hycm_landing_pages\Entity\HylpInterface
   *   The called Landing page entity.
   */
  public function setPublished($published);

  /**
   * Gets the Landing page revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Landing page revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\hycm_landing_pages\Entity\HylpInterface
   *   The called Landing page entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Landing page revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Landing page revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\hycm_landing_pages\Entity\HylpInterface
   *   The called Landing page entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Return theme name for replace
   *
   * @return string|null
   */
  public function replaceTheme();

}
