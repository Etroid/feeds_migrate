<?php

namespace Drupal\Tests\feeds_migrate\Functional;

use Drupal\Core\File\FileSystemInterface;

/**
 * Tests adding and editing feeds migrate importers.
 *
 * @group feeds_migrate
 */
class ImporterTest extends FeedsMigrateTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Copy sample xml file to the expected file directory (i.e. public://).
    $files = scandir($this->resourcesPath());
    $files = array_diff($files, ['.', '..']);
    foreach ($files as $file) {
      \Drupal::service('file_system')->copy($this->resourcesPath() . '/' . $file, 'public://', FileSystemInterface::EXISTS_REPLACE);
    }
  }

  /**
   * Tests execution of import and rollback of an import.
   */
  public function testExecution() {
    // Run Import using sample xml file. (see data/simple_xml.xml)
    $importer = 'simple_xml_importer';
    $url = "/admin/content/feeds-migrate/importer/{$importer}/import";
    $this->drupalGet($url);
    $this->waitForBatchToFinish();
    $this->drupalGet('/admin/content');
    $expected_count = 4;
    $this->assertNodeCount($expected_count);

    // Roll back the operation.
    $importer = 'simple_xml_importer';
    $url = "/admin/content/feeds-migrate/importer/{$importer}/rollback";
    $this->drupalGet($url);
    $this->submitForm([], 'Confirm');
    $this->waitForBatchToFinish();
    $expected_count = 0;
    $this->assertNodeCount($expected_count);
  }

}
