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
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Set label and wait for machine name element to appear.
    $label = $assert_session->fieldExists('migration[label]');
    $label->focus();
    $label->setValue('Migration A');
    $assert_session->waitForField('edit-migration-id');

    // Select 'url' for source.
    $source_tab = $page->find('css', '[href="#plugin_settings--source"]');
    $source_tab->click();
    $source_plugin = $assert_session->fieldExists('migration[source][plugin]');
    $source_plugin->selectOption('url');

    // Select 'http' for data fetcher.
    $data_fetcher_tab = $assert_session->waitForElementVisible('css', '[href="#plugin_settings--data_fetcher"]');
    $data_fetcher_tab->click();
    $data_fetcher_plugin = $assert_session->fieldExists('migration[source][data_fetcher_plugin]');
    $data_fetcher_plugin->selectOption('http');
    $data_fetcher_urls = $assert_session->waitForField('source_wrapper[configuration][data_fetcher_wrapper][configuration][urls]');
    $data_fetcher_urls->setValue('https://test.com/api/items');

    // Select 'json' for data parser.
    $data_parser_tab = $page->find('css', '[href="#plugin_settings--data_parser"]');
    $data_parser_tab->click();
    $data_parser_plugin = $assert_session->fieldExists('migration[source][data_parser_plugin]');
    $data_parser_plugin->selectOption('json');
    $item_selector = $assert_session->waitForField('source_wrapper[configuration][data_parser_wrapper][configuration][item_selector]');
    $item_selector->setValue('/');

    // Select 'entity:node' for destination, so a selector for bundle appears.
    $destination_tab = $page->find('css', '[href="#plugin_settings--destination"]');
    $destination_tab->click();
    $destination_plugin = $assert_session->fieldExists('migration[destination][plugin]');
    $destination_plugin->selectOption('entity:node');

    // Set bundle.
    $destination_bundle = $assert_session->waitForField('destination_wrapper[options][default_bundle]');
    $destination_bundle->selectOption($content_type->id());

    // And submit the form.
    $this->submitForm([], 'Save');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('migration_a');
    $this->assertEquals('migration_a', $migration->id());
    $this->assertEquals('Migration A', $migration->label());
    $this->assertEquals('http', $migration->get('source')['data_fetcher_plugin']);
    $this->assertEquals('https://test.com/api/items', $migration->get('source')['urls']);
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
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Set label and wait for machine name element to appear.
    $label = $assert_session->fieldExists('migration[label]');
    $label->focus();
    $label->setValue('Migration B');
    $assert_session->waitForField('edit-migration-id');

    // Select 'url' for source.
    $source_tab = $page->find('css', '[href="#plugin_settings--source"]');
    $source_tab->click();
    $source_plugin = $assert_session->fieldExists('migration[source][plugin]');
    $source_plugin->selectOption('url');

    // Select 'file' for data fetcher.
    $data_fetcher_tab = $assert_session->waitForElementVisible('css', '[href="#plugin_settings--data_fetcher"]');
    $data_fetcher_tab->click();
    $data_fetcher_plugin = $assert_session->fieldExists('migration[source][data_fetcher_plugin]');
    $data_fetcher_plugin->selectOption('file');
    $data_fetcher_dir = $assert_session->waitForField('source_wrapper[configuration][data_fetcher_wrapper][configuration][directory]');
    $data_fetcher_dir->setValue('public://migrate');

    // Select 'simple_xml' for data parser.
    $data_parser_tab = $page->find('css', '[href="#plugin_settings--data_parser"]');
    $data_parser_tab->click();
    $data_parser_plugin = $assert_session->fieldExists('migration[source][data_parser_plugin]');
    $data_parser_plugin->selectOption('simple_xml');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $item_selector = $page->findField('source_wrapper[configuration][data_parser_wrapper][configuration][item_selector]');
    $item_selector->setValue('/');

    // Select 'entity:taxonomy_term' for destination, so a selector for bundle
    // appears.
    $destination_tab = $page->find('css', '[href="#plugin_settings--destination"]');
    $destination_tab->click();
    $destination_plugin = $assert_session->fieldExists('migration[destination][plugin]');
    $destination_plugin->selectOption('entity:taxonomy_term');

    // Set bundle.
    $this->assertSession()->assertWaitOnAjaxRequest();
    $destination_bundle = $page->findField('destination_wrapper[options][default_bundle]');
    $destination_bundle->selectOption($vocabulary->label());

    // And submit the form.
    $this->submitForm([], 'Save');

    // Check if migration is saved with the expected values.
    $migration = Migration::load('migration_b');
    $this->assertEquals('migration_b', $migration->id());
    $this->assertEquals('Migration B', $migration->label());
    $this->assertEquals('file', $migration->get('source')['data_fetcher_plugin']);
    $this->assertEquals('public://migrate', $migration->get('source')['data_fetcher_directory']);
    $this->assertEquals('simple_xml', $migration->get('source')['data_parser_plugin']);
    $this->assertEquals('/', $migration->get('source')['item_selector']);
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

    $this->drupalGet('/admin/structure/migrate/manage/default/migrations/migration_c/edit');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Check if fields have the expected values.
    $assert_session->fieldValueEquals('migration[label]', 'Migration C');
    $assert_session->fieldValueEquals('migration[source][data_fetcher_plugin]', 'http');
    $assert_session->fieldValueEquals('migration[source][data_parser_plugin]', 'simple_xml');
    $assert_session->fieldValueEquals('migration[destination][plugin]', 'entity:taxonomy_term');
    $assert_session->fieldValueEquals('destination_wrapper[options][default_bundle]', $vocabulary2->id());

    // Change destination to 'user'.
    $destination_tab = $page->find('css', '[href="#plugin_settings--destination"]');
    $destination_tab->click();
    $destination_plugin = $assert_session->fieldExists('migration[destination][plugin]');
    $destination_plugin->selectOption('entity:user');
    $assert_session->assertWaitOnAjaxRequest();

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
