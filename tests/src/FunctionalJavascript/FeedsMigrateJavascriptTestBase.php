<?php

namespace Drupal\Tests\feeds_migrate\FunctionalJavascript;

use Drupal\feeds_migrate\Entity\FeedsMigrateImporter;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Tests\feeds_migrate\Traits\FeedsCommonTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\migrate_plus\Entity\MigrationGroup;

/**
 * Base class for Feeds javascript tests.
 */
abstract class FeedsMigrateJavascriptTestBase extends WebDriverTestBase {

  use CronRunTrait;
  use FeedsCommonTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'feeds_migrate',
    'feeds_migrate_ui',
    'file',
    'node',
    'user',
  ];

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a content type.
    $this->setUpNodeType();

    // Create an user with admin privileges.
    $this->adminUser = $this->drupalCreateUser([
      'administer feeds migrate importers',
      'administer migrations',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create a migration group.
    MigrationGroup::create([
      'id' => 'default',
      'label' => 'Default',
    ])->save();
  }

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
