<?php

namespace Drupal\feeds_migrate;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining  entities.
 */
interface FeedsMigrateImporterInterface extends ConfigEntityInterface {

  /**
   * Indicates that a feed should never be scheduled.
   */
  const SCHEDULE_NEVER = -1;

  /**
   * Indicates that a feed should be imported as often as possible.
   */
  const SCHEDULE_CONTINUOUSLY = 0;

  /**
   * Indicates that existing items should be left alone.
   */
  const EXISTING_LEAVE = 'leave';

  /**
   * Indicates that existing items should be replaced.
   */
  const EXISTING_REPLACE = 'replace';

  /**
   * Indicates that existing items should be updated.
   */
  const EXISTING_UPDATE = 'update';

  /**
   * Indicates that orphaned items should be kept.
   */
  const ORPHANS_KEEP = 'keep';

  /**
   * Indicates that orphaned items should be deleted.
   */
  const ORPHANS_DELETE = 'delete';

}
