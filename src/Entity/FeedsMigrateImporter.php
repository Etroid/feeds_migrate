<?php

namespace Drupal\feeds_migrate\Entity;

use Drupal;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\feeds_migrate\FeedsMigrateExecutable;
use Drupal\feeds_migrate\FeedsMigrateImporterInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate_plus\Entity\Migration;

/**
 * Feeds Migrate Source configuration entity.
 *
 * @ConfigEntityType(
 *   id = "feeds_migrate_importer",
 *   label = @Translation("Feeds Migrate Importer"),
 *   handlers = {
 *     "list_builder" = "Drupal\feeds_migrate\FeedsMigrateImporterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\feeds_migrate\Form\FeedsMigrateImporterForm",
 *       "edit" = "Drupal\feeds_migrate\Form\FeedsMigrateImporterForm",
 *       "delete" = "Drupal\feeds_migrate\Form\FeedsMigrateImporterDeleteForm",
 *       "enable" = "Drupal\feeds_migrate\Form\FeedsMigrateImporterEnableForm",
 *       "disable" = "Drupal\feeds_migrate\Form\FeedsMigrateImporterDisableForm",
 *       "rollback" = "Drupal\feeds_migrate\Form\FeedsMigrateImporterRollbackForm"
 *     },
 *   },
 *   config_prefix = "importer",
 *   admin_permission = "administer feeds migrate importers",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "importFrequency",
 *     "existing",
 *     "orphans",
 *     "lastRan",
 *     "migrationId",
 *     "migrationConfig"
 *   },
 *   links = {
 *     "canonical" = "/admin/content/feeds-migrate/{feeds_migrate_importer}",
 *     "edit-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}",
 *     "delete-form" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/delete",
 *     "enable" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/enable",
 *     "disable" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/disable",
 *     "import" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/import",
 *     "rollback" = "/admin/content/feeds-migrate/{feeds_migrate_importer}/rollback"
 *   }
 * )
 */
class FeedsMigrateImporter extends ConfigEntityBase implements FeedsMigrateImporterInterface {

  /**
   * The identifier of the importer.
   *
   * @var string
   */
  public $id;

  /**
   * The label for the importer.
   *
   * @var string
   */
  public $label;

  /**
   * The frequency at which this importer should be executed.
   *
   * @var int
   */
  public $importFrequency;

  /**
   * Indicates how existing content should be processed.
   *
   * @var string
   */
  public $existing;

  /**
   * Indicates how orphaned content should be handled.
   *
   * @var string
   */
  public $orphans;

  /**
   * Indicates the last time the import was run.
   *
   * @var int
   */
  public $lastRan = 0;

  /**
   * The migration ID.
   *
   * @var string
   */
  public $migrationId;

  /**
   * Migration Config.
   *
   * @var array
   */
  protected $migrationConfig;

  /**
   * The original migration entity.
   *
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   *   The migration entity object before configuration alterations.
   */
  protected $originalMigration;

  /**
   * The migration entity.
   *
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   *   The migration entity object after configuration alterations.
   */
  protected $migration;

  /**
   * Get the original migration plugin.
   *
   * @return \Drupal\migrate_plus\Entity\MigrationInterface
   *   The original migration entity before any configuration changes.
   */
  public function getOriginalMigration() {
    if (!isset($this->originalMigration)) {
      $this->originalMigration = Migration::load($this->migrationId);
    }

    return $this->originalMigration;
  }

  /**
   * Get the altered migration plugin.
   *
   * @return \Drupal\migrate_plus\Entity\MigrationInterface
   *   The migration plugin after configuration changes are applied.
   */
  public function getMigration() {
    if (!isset($this->migration)) {
      /* @var \Drupal\migrate_plus\Entity\MigrationInterface $altered_migration */
      $altered_migration = $this->migration = clone $this->getOriginalMigration();

      $source = array_merge($this->originalMigration->get('source'), $this->migrationConfig['source']);
      $altered_migration->set('source', $source);
      $destination = array_merge($this->originalMigration->get('destination'), $this->migrationConfig['destination']);
      $altered_migration->set('destination', $destination);
    }

    return $this->migration;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    // TODO add dependency on migration entity.
    return $dependencies;
  }

  /**
   * If the periodic import should be executed.
   *
   * @return bool
   *   TRUE if it should be run on cron, FALSE otherwise.
   */
  public function needsImport() {
    $request_time = Drupal::time()->getRequestTime();
    if ($this->importFrequency != -1 && ($this->lastRan + $this->importFrequency) <= $request_time) {
      return TRUE;
    }

    return FALSE;
  }

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
  public function getExecutable() {
    /* @var \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager */
    $migration_manager = Drupal::service('plugin.manager.migration');
    $test = $this->getMigration();
    $migration_plugin = $migration_manager->createInstance($this->migrationId, $test->toArray());
    $messenger = new MigrateMessage();

    if ($this->existing == 2) {
      $this->$migration_plugin->getIdMap()->prepareUpdate();
    }

    return new FeedsMigrateExecutable($migration_plugin, $messenger);
  }

}
