<?php

namespace Drupal\Tests\feeds_migrate\FunctionalJavascript\Form;

use Drupal\feeds_migrate\Entity\FeedsMigrateImporter;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\Tests\feeds_migrate\FunctionalJavascript\FeedsMigrateJavascriptTestBase;

/**
 * Tests adding and editing feeds migrate importers.
 *
 * @group feeds_migrate
 */
class ImporterFormTest extends FeedsMigrateJavascriptTestBase {

  /**
   * Tests adding a new importer.
   */
  public function testAddImporter() {
    $migration = $this->createMigration();
    $migration->set('source', [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'http',
      'data_parser_plugin' => 'simple_xml',
      'item_selector' => '/items/item',
    ]);
    $migration->set('destination', [
      'plugin' => 'entity:node',
      'default_bundle' => 'article',
    ]);
    $migration->save();

    // Navigate to create a new importer.
    $this->drupalGet('/admin/content/feeds-migrate/importer/add');
    $page = $this->getSession()->getPage();

    // Set label and wait for machine name element to appear.
    $label = $page->findField('edit-label');
    $label->setValue('Importer A');
    $this->assertSession()->waitForElementVisible('css', '#edit-label-machine-name-suffix .admin-link');

    // Set import frequency to 'As often as possible'.
    $import_frequency = $page->findField('edit-importfrequency');
    $import_frequency->setValue(0);

    // Set processor setting to 'Update existing content'.
    $page->selectFieldOption('existing', 'update');

    // Set processor setting to 'Keep orphaned items'.
    $keep_orphans = $page->findField('edit-keeporphans');
    $keep_orphans->check();

    // Set migration id to 'Migration Simple XML'.
    $migration_id = $page->findField('edit-migrationid');
    $migration_id->selectOption($migration->id());
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert source configuration matches up with our migration.
    $source_tab = $page->find('css', '[href="#plugin_settings--source"]');
    $source_tab->click();
    $source_plugin = $page->findField('migration[source][plugin]');
    $this->assertTrue($migration->get('source')['plugin'], $source_plugin->getValue(), 'Source plugin matches with migration.');

    // Assert data fetcher configuration matches up with our migration.
    $data_fetcher_tab = $page->find('css', '[href="#plugin_settings--data_fetcher"]');
    $data_fetcher_tab->click();
    $data_fetcher_plugin = $page->findField('migration[source][data_fetcher_plugin]');
    $this->assertTrue($migration->get('source')['data_fetcher_plugin'], $data_fetcher_plugin->getValue(), 'Data fetcher plugin matches with migration.');

    // Assert data fetcher 'File Location' is visible.
    $data_fetcher_urls = $this->assertSession()->fieldExists('source_wrapper[configuration][data_fetcher_wrapper][configuration][urls]');
    // Field location should be empty.
    $this->assertTrue(empty($data_fetcher_urls->getValue()), 'File location is empty.');
    // Set the file location to an API endpoint.
    $data_fetcher_urls->setValue('http://news.com/api/articles');

    // Assert data parser configuration matches up with our migration.
    $data_parser_tab = $page->find('css', '[href="#plugin_settings--data_parser"]');
    $data_parser_tab->click();
    $data_parser_plugin = $page->findField('migration[source][data_parser_plugin]');
    $this->assertTrue($migration->get('source')['data_parser_plugin'], $data_parser_plugin->getValue(), 'Data parser plugin matches with migration.');

    // Assert destination configuration matches up with our migration.
    $destination_tab = $page->find('css', '[href="#plugin_settings--destination"]');
    $destination_tab->click();
    $destination_plugin = $page->findField('migration[destination][plugin]');
    $this->assertTrue($migration->get('destination')['plugin'], $destination_plugin->getValue(), 'Destination plugin matches with migration.');
    $destination_bundle = $page->findField('destination_wrapper[options][default_bundle]');
    $this->assertTrue($migration->get('destination')['default_bundle'], $destination_bundle->getValue(), 'Destination bundle matches with migration.');

    // Submit the form.
    $this->submitForm([], 'Save');

    // Check if importer is saved with the expected values.
    $importer = FeedsMigrateImporter::load('importer_a');
    $this->assertEquals('importer_a', $importer->id());
    $this->assertEquals('Importer A', $importer->label());
    $this->assertEquals(0, $importer->get('importFrequency'));
    $this->assertEquals('update', $importer->get('existing'));
    $this->assertEquals(TRUE, $importer->get('keepOrphans'));
    $this->assertEquals($migration->id(), $importer->get('migrationId'));

    // Assert importer overrides take precedent over migration configuration.
    // In this example we've only updated the file location. All other values
    // should be the same as the original migration configuration.
    $assert_migration_config = [
      'source' => [
        'urls' => 'http://news.com/api/articles',
      ],
      'destination' => [],
    ];
    $this->assertEquals($assert_migration_config, $importer->get('migrationConfig'));
  }

  /**
   * Tests editing an existing importer.
   */
  public function testEditImporter() {
    // Create a migration entity.
    $migration = $this->createMigration();
    $migration->set('source', [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => 'json',
      'item_selector' => '/items/item',
    ]);
    $migration->set('destination', [
      'plugin' => 'entity:node',
      'default_bundle' => 'article',
    ]);
    $migration->save();

    // Create an importer entity.
    $importer = $this->createImporter();
    $importer->set('migrationConfig', [
      'source' => [
        'data_fetcher_plugin' => 'http',
        'urls' => [
          'https://www.news.co.jp/api/articles',
        ],
      ],
      'destination' => [],
    ]);
    $importer->save();

    // Navigate to edit our importer.
    $this->drupalGet("/admin/content/feeds-migrate/importer/{$importer->id()}/edit");
    $session = $this->assertSession();

    $session->fieldValueEquals('edit-label', $importer->label());
    $session->fieldValueEquals('edit-importfrequency', $importer->getImportFrequency());
    $session->fieldValueEquals('existing', $importer->getExisting());
    $session->fieldValueEquals('edit-keeporphans', $importer->keepOrphans());
    $session->fieldValueEquals('edit-migrationid', $importer->getMigrationId());
    $session->fieldValueEquals('migration[source][plugin]', $importer->getMigration()->get('source')['plugin']);
    $session->fieldValueEquals('migration[source][data_fetcher_plugin]', $importer->getMigration()->get('source')['data_fetcher_plugin']);
    $session->fieldValueEquals('source_wrapper[configuration][data_fetcher_wrapper][configuration][urls]', $importer->getMigration()->get('source')['urls'][0]);
    $session->fieldValueEquals('migration[source][data_parser_plugin]', $importer->getMigration()->get('source')['data_parser_plugin']);
    $session->fieldValueEquals('migration[destination][plugin]', $importer->getMigration()->get('destination')['plugin']);
    $session->fieldValueEquals('destination_wrapper[options][default_bundle]', $importer->getMigration()->get('destination')['default_bundle']);
  }

  /**
   * Tests deleting an importer.
   */
  public function testDeleteImporter() {
    // Create a migration entity.
    $migration = $this->createMigration();
    $migration->set('source', [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => 'json',
      'item_selector' => '/items/item',
    ]);
    $migration->set('destination', [
      'plugin' => 'entity:node',
      'default_bundle' => 'article',
    ]);
    $migration->save();

    // Create an importer entity.
    $importer = $this->createImporter();
    $importer->set('migrationConfig', [
      'source' => [
        'data_fetcher_plugin' => 'http',
        'urls' => [
          'https://www.news.co.jp/api/articles',
        ],
      ],
      'destination' => [],
    ]);
    $importer->save();

    // Navigate to the delete form.
    $this->drupalGet("/admin/content/feeds-migrate/importer/{$importer->id()}/delete");

    // And submit the form.
    $this->submitForm([], 'Delete');

    // Check if importer is deleted.
    $deleted_importer = FeedsMigrateImporter::load($importer->id());
    $this->assertFALSE(isset($deleted_importer), 'Importer was deleted successfully');
  }

}
