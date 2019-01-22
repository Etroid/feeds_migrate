<?php

namespace Drupal\Tests\feeds_migrate\FunctionalJavascript\Form;

use Drupal\migrate_plus\Entity\Migration;
use Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait;
use Drupal\Tests\feeds_migrate\FunctionalJavascript\FeedsMigrateJavascriptTestBase;

/**
 * Tests adding and editing migrations using the UI.
 *
 * @group feeds_migrate
 */
class MigrationFormTest extends FeedsMigrateJavascriptTestBase {

  use TaxonomyTestTrait;

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
    // Create another content type.
    $content_type = $this->drupalCreateContentType();

    $this->drupalGet('/admin/structure/migrate/sources/add');

    $edit = [
      'fetcher' => 'http',
      'parser' => 'json',
      'destination_wrapper[advanced][default_bundle]' => $content_type->id(),
    ];

    // Select 'entity:node' for destination, so a selector for bundle appears.
    $this->assertSession()->fieldExists('destination');
    $this->getSession()->getPage()->selectFieldOption('destination', 'entity:node');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set label and wait for machine name element to appear.
    $field = $this->assertSession()->fieldExists('label');
    $field->setValue('Migration A');
    $this->assertSession()->waitForElementVisible('css', '#edit-label-machine-name-suffix');

    // And submit the form.
    $this->submitForm($edit, 'Save');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('migration_a');
    $this->assertEquals('migration_a', $migration->id());
    $this->assertEquals('Migration A', $migration->label());
    $this->assertEquals('http', $migration->get('source')['data_fetcher_plugin']);
    $this->assertEquals('json', $migration->get('source')['data_parser_plugin']);
    $this->assertEquals('entity:node', $migration->get('destination')['plugin']);
    $this->assertEquals($content_type->id(), $migration->get('destination')['default_bundle']);

    // Process.
    $this->assertEquals([], $migration->get('process'));
  }

  /**
   * Tests adding a new migration for importing terms.
   */
  public function testAddMigrationForTaxonomyTerm() {
    // Create a vocabulary.
    $vocabulary = $this->createVocabulary();

    $this->drupalGet('/admin/structure/migrate/sources/add');
    $edit = [
      'fetcher' => 'file',
      'parser' => 'simple_xml',
      'destination_wrapper[advanced][default_bundle]' => $vocabulary->id(),
    ];

    // Select 'entity:taxonomy_term' for destination, so a selector for bundle appears.
    $this->assertSession()->fieldExists('destination');
    $this->getSession()->getPage()->selectFieldOption('destination', 'entity:taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set label and wait for machine name element to appear.
    $field = $this->assertSession()->fieldExists('label');
    $field->setValue('Migration B');
    $this->assertSession()->waitForElementVisible('css', '#edit-label-machine-name-suffix');

    // And submit the form.
    $this->submitForm($edit, 'Save');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('migration_b');
    $this->assertEquals('migration_b', $migration->id());
    $this->assertEquals('Migration B', $migration->label());
    $this->assertEquals('file', $migration->get('source')['data_fetcher_plugin']);
    $this->assertEquals('simple_xml', $migration->get('source')['data_parser_plugin']);
    $this->assertEquals('entity:taxonomy_term', $migration->get('destination')['plugin']);
    $this->assertEquals($vocabulary->id(), $migration->get('destination')['default_bundle']);

    // Process.
    $this->assertEquals([], $migration->get('process'));
  }

  /**
   * Tests editing an existing migration.
   */
  public function testEditMigration() {
    // Create two vocabularies.
    $vocabulary1 = $this->createVocabulary();
    $vocabulary2 = $this->createVocabulary();

    // Create a migration entity.
    $migration = Migration::create([
      'id' => 'migration_c',
      'label' => 'Migration C',
      'migration_group' => 'default',
      'source' => [
        'plugin' => 'null',
        'data_fetcher_plugin' => 'http',
        'data_parser_plugin' => 'simple_xml',
      ],
      'destination' => [
        'plugin' => 'entity:taxonomy_term',
        'default_bundle' => $vocabulary2->id(),
      ],
      'migration_tags' => [],
      'migration_dependencies' => [],
    ]);
    $migration->save();

    // Check if fields have the expected values.
    $this->drupalGet('/admin/structure/migrate/manage/default/migrations/migration_c/edit');
    $session = $this->assertSession();
    $session->fieldValueEquals('label', 'Migration C');
    $session->fieldValueEquals('fetcher', 'http');
    $session->fieldValueEquals('parser', 'simple_xml');
    $session->fieldValueEquals('destination', 'entity:taxonomy_term');
    $session->fieldValueEquals('destination_wrapper[advanced][default_bundle]', $vocabulary2->id());

    // Change destination to 'user'.
    $this->getSession()->getPage()->selectFieldOption('destination', 'entity:user');
    $session->assertWaitOnAjaxRequest();

    $this->submitForm([], 'Save');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('migration_c');
    $this->assertEquals('migration_c', $migration->id());
    $this->assertEquals('Migration C', $migration->label());
    $this->assertEquals('http', $migration->get('source')['data_fetcher_plugin']);
    $this->assertEquals('simple_xml', $migration->get('source')['data_parser_plugin']);
    $this->assertEquals('entity:user', $migration->get('destination')['plugin']);

    // Check if bundle information was destroyed.
    $this->assertArrayNotHasKey('default_bundle', $migration->get('destination'));
  }

}
