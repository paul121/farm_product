<?php

namespace Drupal\farm_product\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining package entities.
 */
interface PackageInterface extends ContentEntityInterface, EntityChangedInterface, RevisionLogInterface, EntityOwnerInterface {

  /**
   * Gets the quantity creation timestamp.
   *
   * @return int
   *   Creation timestamp of the quantity.
   */
  public function getCreatedTime();

  /**
   * Sets the quantity creation timestamp.
   *
   * @param int $timestamp
   *   Creation timestamp of the quantity.
   *
   * @return \Drupal\quantity\Entity\QuantityInterface
   *   The quantity entity.
   */
  public function setCreatedTime($timestamp);

}
