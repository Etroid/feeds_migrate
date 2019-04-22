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
 *     "keepOrphans",
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
   * The frequency at which this importer should be executed.
   *
   * @var int
   */
  protected $importFrequency;

  /**
   * Indicates how existing content should be processed.
   *
   * @var string
   */
  protected $existing;

  /**
   * Indicates if orphaned content should be kept.
   *
   * @var bool
   */
  protected $keepOrphans;

  /**
   * The migration ID.
   *
   * @var string
   */
  protected $migrationId;

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
   * {@inheritdoc}
   */
  public function getImportFrequency() {
    return $this->importFrequency;
  }

  /**
   * {@inheritdoc}
   */
  public function setImportFrequency(int $importFrequency) {
    $this->importFrequency = $importFrequency;
  }

  /**
   * {@inheritdoc}
   */
  public function getExisting() {
    return $this->existing;
  }

  /**
   * {@inheritdoc}
   */
  public function setExisting(string $existing) {
    $this->existing = $existing;
  }

  /**
   * {@inheritdoc}
   */
  public function keepOrphans() {
    return $this->keepOrphans;
  }

  /**
   * {@inheritdoc}
   */
  public function setKeepOrphans(bool $keep_orphans) {
    $this->keepOrphans = $keep_orphans;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastRun() {
    return Drupal::state()->get('feeds_migrate_importer.' . $this->id() . '.last_run', 0);
  }

  /**
   * {@inheritdoc}
   */
  public function setLastRun(int $last_run) {
    Drupal::state()->set('feeds_migrate_importer.' . $this->id() . '.last_run', $last_run);
  }

  /**
   * {@inheritdoc}
   */
  public function getMigrationId() {
    return $this->migrationId;
  }

  /**
   * {@inheritdoc}
   */
  public function setMigrationId(string $id) {
    $this->migrationId = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalMigration() {
    if (!isset($this->originalMigration)) {
      $this->originalMigration = Migration::load($this->migrationId);
    }

    return $this->originalMigration;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function needsImport() {
    $request_time = Drupal::time()->getRequestTime();
    if ($this->importFrequency != -1 && ($this->getLastRun() + $this->importFrequency) <= $request_time) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
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
