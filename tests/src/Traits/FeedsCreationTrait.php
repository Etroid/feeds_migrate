<?php

namespace Drupal\Tests\feeds_migrate\Traits;

use Drupal\feeds_migrate\Entity\FeedsMigrateImporter;
use Drupal\migrate_plus\Entity\Migration;

/**
 * Provides methods to create migrations and importers with default settings.
 *
 * This trait is meant to be used only by test classes.
 */
trait FeedsCreationTrait {

  /**
   * Stubs a migration entity.
   *
   * @return \Drupal\migrate_plus\Entity\MigrationInterface
   *   Stubbed out Migration Entity.
   */
  protected function createMigration() {
    $migration = Migration::create([
      'id' => 'migration_a',
      'label' => 'Migration A',
      'migration_group' => 'default',
      'source' => [],
      'destination' => [],
      'process' => [],
      'migration_tags' => [],
      'migration_dependencies' => [],
    ]);
    return $migration;
  }

  /**
   * Stubs an importer entity.
   *
   * @return \Drupal\feeds_migrate\FeedsMigrateImporterInterface
   *   Stubbed out Importer Entity.
   */
  protected function createImporter() {
    $importer = FeedsMigrateImporter::create([
      'id' => 'importer_a',
      'label' => 'Importer A',
      'importFrequency' => -1,
      'existing' => 'leave',
      'keepOrphans' => FALSE,
      'migrationId' => 'migration_a',
      'migrationConfig' => [
        'source' => [],
        'destination' => [],
      ],
    ]);
    return $importer;
  }

}
