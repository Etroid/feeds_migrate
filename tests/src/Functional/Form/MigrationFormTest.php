<?php

namespace Drupal\Tests\feeds_migrate\Functional\Form;

use Drupal\migrate_plus\Entity\Migration;
use Drupal\Tests\feeds_migrate\Functional\FeedsMigrateBrowserTestBase;

/**
 * Tests adding and editing migrations using the UI.
 *
 * @group feeds_migrate
 */
class MigrationFormTest extends FeedsMigrateBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'feeds_migrate',
    'feeds_migrate_ui',
    'file',
    'node',
    'taxonomy',
    'user',
  ];

  /**
   * Tests adding a new migration.
   */
  public function testAddMigration() {
    $edit = [
      'id' => 'migration_a',
      'label' => 'Migration A',
      'fetcher' => 'http',
      'parser' => 'json',
      'destination' => 'entity:node',
    ];
    $this->drupalPostForm('/admin/structure/feeds-migrate/sources/add', $edit, 'Save');

    // With JS disabled, the form should come back to set bundle.
    // @todo does not work yet.
    /*
    $edit = [
      'type' => 'article',
    ];
    $this->submitForm($edit, 'Save');
    */

    // Check if migration is saved with the expected values.
    $migration = Migration::load('migration_a');
    $this->assertEquals('migration_a', $migration->id());
    $this->assertEquals('Migration A', $migration->label());
    $this->assertEquals('http', $migration->get('source')['data_fetcher_plugin']);
    $this->assertEquals('json', $migration->get('source')['data_parser_plugin']);
    $this->assertEquals('entity:node', $migration->get('destination')['plugin']);

    // Bundle.
    $expected_processes = [
      'type' => 'constants/bundle',
    ];
    $this->assertEquals($expected_processes, $migration->get('process'));
  }

  /**
   * Tests adding a new migration for importing terms.
   */
  public function testAddMigrationForTaxonomyTerm() {
    $this->markTestIncomplete('Test not implemented yet');
  }

  /**
   * Tests editing an existing migration.
   */
  public function testEditMigration() {
    $this->markTestIncomplete('Test not implemented yet');
  }

}
