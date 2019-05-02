<?php

namespace Drupal\Tests\feeds_migrate\Functional;

/**
 * Tests adding and editing feeds migrate importers.
 *
 * @group feeds_migrate
 */
class ImporterTest extends FeedsMigrateTestBase {

  /**
   * Directory for assets/files used in tests.
   *
   * @var string
   */
  protected $sourceDir;

  /**
   * A node query.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $nodeQuery;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Copy file in expected directory.
    $this->sourceDir = __DIR__ . '/../../data/';
    $files = scandir($this->sourceDir);
    $files = array_diff($files, ['.', '..']);
    foreach ($files as $file) {
      file_unmanaged_copy($this->sourceDir . $file, 'public://', FILE_EXISTS_REPLACE);
    }
    $this->nodeQuery = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery();
  }

  /**
   * Tests execution of import and rollback of an import.
   */
  public function testExecution() {
    $pre_import_count = $this->nodeQuery->count()->execute();
    $expected_count = 0;
    $this->assertEquals($expected_count, $pre_import_count);

    // Run Import using sample xml file. (see data/simple_xml.xml)
    $importer = 'simple_xml_importer';
    $url = "/admin/content/feeds-migrate/importer/{$importer}/import";
    $this->drupalGet($url);
    $this->waitForBatchToFinish();
    $this->drupalGet('/admin/content');
    $import_count = $this->nodeQuery->count()->execute();
    $expected_count = 4;
    $this->assertEquals($expected_count, $import_count);

    // Roll back the operation.
    $importer = 'simple_xml_importer';
    $url = "/admin/content/feeds-migrate/importer/{$importer}/rollback";
    $this->drupalGet($url);
    $this->submitForm([], 'Confirm');
    $this->waitForBatchToFinish();
    $rollback_count = $this->nodeQuery->count()->execute();
    $expected_count = 0;
    $this->assertEquals($expected_count, $rollback_count);
  }

}
