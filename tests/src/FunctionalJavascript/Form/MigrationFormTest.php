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

    // Set label and wait for machine name element to appear.
    $label = $this->assertSession()->fieldExists('migration[label]');
    $label->focus();
    $label->setValue('Migration A');
    $this->assertSession()->waitForElementVisible('css', '#edit-migration-id');

    // Select 'url' for source.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--source"]')->click();
    $this->assertSession()->fieldExists('migration[source][plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[source][plugin]', 'url');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select 'http' for data fetcher.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--data_fetcher"]')->click();
    $this->assertSession()->fieldExists('migration[source][data_fetcher_plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[source][data_fetcher_plugin]', 'http');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select 'json' for data parser.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--data_parser"]')->click();
    $this->assertSession()->fieldExists('migration[source][data_parser_plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[source][data_parser_plugin]', 'json');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $item_selector = $this->assertSession()->fieldExists('source_wrapper[configuration][data_parser_wrapper][configuration][item_selector]');
    $item_selector->setValue('/');

    // Select 'entity:node' for destination, so a selector for bundle appears.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--destination"]')->click();
    $this->assertSession()->fieldExists('migration[destination][plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[destination][plugin]', 'entity:node');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set bundle.
    $this->assertSession()->fieldExists('destination_wrapper[options][default_bundle]');
    $this->getSession()->getPage()->selectFieldOption('destination_wrapper[options][default_bundle]', $content_type->id());

    // And submit the form.
    $this->submitForm([], 'Save');

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

    // Set label and wait for machine name element to appear.
    $label = $this->assertSession()->fieldExists('migration[label]');
    $label->focus();
    $label->setValue('Migration B');
    $this->assertSession()->waitForElementVisible('css', '#edit-migration-id');

    // Select 'url' for source.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--source"]')->click();
    $this->assertSession()->fieldExists('migration[source][plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[source][plugin]', 'url');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select 'http' for data fetcher.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--data_fetcher"]')->click();
    $this->assertSession()->fieldExists('migration[source][data_fetcher_plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[source][data_fetcher_plugin]', 'file');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Select 'json' for data parser.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--data_parser"]')->click();
    $this->assertSession()->fieldExists('migration[source][data_parser_plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[source][data_parser_plugin]', 'simple_xml');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $item_selector = $this->assertSession()->fieldExists('source_wrapper[configuration][data_parser_wrapper][configuration][item_selector]');
    $item_selector->setValue('/');

    // Select 'entity:taxonomy_term' for destination, so a selector for bundle appears.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--destination"]')->click();
    $this->assertSession()->fieldExists('migration[destination][plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[destination][plugin]', 'entity:taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set bundle.
    $this->assertSession()->fieldExists('destination_wrapper[options][default_bundle]');
    $this->getSession()->getPage()->selectFieldOption('destination_wrapper[options][default_bundle]', $vocabulary->id());

    // And submit the form.
    $this->submitForm([], 'Save');

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
    // Create vocabulary.
    $vocabulary2 = $this->createVocabulary();

    // Create a migration entity.
    $migration = Migration::create([
      'id' => 'migration_c',
      'label' => 'Migration C',
      'migration_group' => 'default',
      'source' => [
        'plugin' => 'url',
        'data_fetcher_plugin' => 'http',
        'data_parser_plugin' => 'simple_xml',
        'item_selector' => '/items/item',
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
    $session->fieldValueEquals('migration[label]', 'Migration C');
    $session->fieldValueEquals('migration[source][data_fetcher_plugin]', 'http');
    $session->fieldValueEquals('migration[source][data_parser_plugin]', 'simple_xml');
    $session->fieldValueEquals('migration[destination][plugin]', 'entity:taxonomy_term');
    $session->fieldValueEquals('destination_wrapper[options][default_bundle]', $vocabulary2->id());

    // Change destination to 'user'.
    // Select 'entity:taxonomy_term' for destination, so a selector for bundle appears.
    $this->getSession()->getPage()->find('css', '[href="#plugin_settings--destination"]')->click();
    $this->assertSession()->fieldExists('migration[destination][plugin]');
    $this->getSession()->getPage()->selectFieldOption('migration[destination][plugin]', 'entity:user');
    $this->assertSession()->assertWaitOnAjaxRequest();

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
