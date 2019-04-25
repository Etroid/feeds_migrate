<?php

namespace Drupal\feeds_migrate;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining feeds migrate importer entities.
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
   * Get the import frequency.
   *
   * @return int
   *   Interval in seconds.
   */
  public function getImportFrequency();

  /**
   * Set the import frequency.
   *
   * @param int $import_frequency
   *   Interval in seconds.
   */
  public function setImportFrequency(int $import_frequency);

  /**
   * Get how existing items should be processed.
   *
   * @return string
   *   One of the following:
   *   - 'leave' => FeedsMigrateImporterInterface::EXISTING_LEAVE.
   *   - 'replace' => FeedsMigrateImporterInterface::EXISTING_REPLACE.
   *   - 'update' => FeedsMigrateImporterInterface::EXISTING_UPDATE.
   */
  public function getExisting();

  /**
   * Sets how existing items should be processed.
   *
   * @param string $existing
   *   One of the following:
   *   - 'leave' => FeedsMigrateImporterInterface::EXISTING_LEAVE.
   *   - 'replace' => FeedsMigrateImporterInterface::EXISTING_REPLACE.
   *   - 'update' => FeedsMigrateImporterInterface::EXISTING_UPDATE.
   */
  public function setExisting(string $existing);

  /**
   * Returns if orphaned items should be kept.
   *
   * @return bool
   *   TRUE if orphans should be kept, FALSE if they can be deleted.
   */
  public function keepOrphans();

  /**
   * Sets if orphaned items should be kept.
   *
   * @param bool $keep_orphans
   *   TRUE if orphans should be kept, FALSE if they can be deleted.
   */
  public function setKeepOrphans(bool $keep_orphans);

  /**
   * Get the timestamp of the last import.
   *
   * @return int
   *   Unix timestamp.
   */
  public function getLastRun();

  /**
   * Update the timestamp during which the import last ran.
   *
   * @param int $last_run
   *   Unix timestamp of the last import.
   */
  public function setLastRun(int $last_run);

  /**
   * Get the id of the migration.
   *
   * @return string
   *   ID of the migration plugin.
   */
  public function getMigrationId();

  /**
   * Update the timestamp during which the import last ran.
   *
   * @param string $id
   *   ID of the migration plugin.
   */
  public function setMigrationId(string $id);

  /**
   * Get the original migration plugin.
   *
   * @return \Drupal\migrate_plus\Entity\MigrationInterface
   *   The original migration entity before any configuration changes.
   */
  public function getOriginalMigration();

  /**
   * Get the altered migration plugin.
   *
   * @return \Drupal\migrate_plus\Entity\MigrationInterface
   *   The migration plugin after configuration changes are applied.
   */
  public function getMigration();

  /**
   * If the import should be executed.
   *
   * @return bool
   *   TRUE if it should be run on cron, FALSE otherwise.
   */
  public function needsImport();

  /**
   * Get the altered migrate executable object that can run the import.
   *
   * @return \Drupal\feeds_migrate\FeedsMigrateExecutable
   *   The executable to run the import.
   *
   * @throws \Drupal\migrate\MigrateException
   *   If the executable failed.
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getExecutable();

}
