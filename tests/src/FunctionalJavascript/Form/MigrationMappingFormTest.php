<?php

namespace Drupal\Tests\feeds_migrate\FunctionalJavascript\Form;

use Drupal\migrate_plus\Entity\Migration;
use Drupal\Tests\feeds_migrate\FunctionalJavascript\FeedsMigrateJavascriptTestBase;

/**
 * Tests adding and editing mappings using the UI.
 *
 * @group feeds_migrate
 */
class MigrationMappingFormTest extends FeedsMigrateJavascriptTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a migration entity.
    $migration = Migration::create([
      'id' => 'example_migration',
      'label' => 'Example Migration',
      'migration_group' => 'default',
      'source' => [
        'plugin' => 'url',
        'data_fetcher_plugin' => 'http',
        'data_parser_plugin' => 'simple_xml',
      ],
      'destination' => [
        'plugin' => 'entity:node',
        'default_bundle' => 'article',
      ],
      'process' => [],
      'migration_tags' => [],
      'migration_dependencies' => [],
    ]);
    $migration->save();
  }

  /**
   * Tests adding a new migration mapping.
   */
  public function testAddMigrationMapping() {
    $this->drupalGet('/admin/structure/migrate/sources/example_migration/mapping/add');

    // Select 'entity:taxonomy_term' for destination, so a selector for bundle appears.
    $this->assertSession()->fieldExists('destination_field');
    $this->getSession()->getPage()->selectFieldOption('destination_field', 'title');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set source for title field.
    $source_field = $this->assertSession()->fieldExists('mapping[title][source]');
    $source_field->setValue('source_a');

    // And submit the form.
    $this->submitForm([], 'Save');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('example_migration');
    $this->assertEquals('source_a', $migration->get('process')['title'][0]['source']);
  }

  /**
   * Tests editing an existing mapping.
   */
  public function testEditMigrationMapping() {
    $this->drupalGet('/admin/structure/migrate/sources/example_migration/mapping/title');

    // Set source for title field.
    $source_field = $this->assertSession()->fieldExists('mapping[title][source]');
    $source_field->setValue('source_b');

    // And submit the form.
    $this->submitForm([], 'Save');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('example_migration');
    $this->assertEquals('source_b', $migration->get('process')['title'][0]['source']);
  }

  /**
   * Tests deleing an existing mapping.
   */
  public function testDeleteMigrationMapping() {
    $this->drupalGet('/admin/structure/migrate/sources/example_migration/mapping/title/delete');

    // And submit the form.
    $this->submitForm([], 'Delete');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('example_migration');
    $this->assertArrayNotHasKey('title', $migration->get('process'));
  }

}
