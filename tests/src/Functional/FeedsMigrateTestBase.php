<?php

namespace Drupal\Tests\feeds_migrate\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\feeds_migrate\Traits\FeedsCommonTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Base class for feeds migrate functional tests.
 */
abstract class FeedsMigrateTestBase extends BrowserTestBase {

  use CronRunTrait;
  use FeedsCommonTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'file',
    'node',
    'user',
    'migrate',
    'migrate_plus',
    'migrate_tools',
    'feeds_migrate',
    'feeds_migrate_ui',
    'feeds_migrate_test',
    'system',
  ];

  /**
   * A test user with administrative privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Whether config schemas should be validated.
   *
   * Sets schema validation to FALSE to get around an issue where dynamic config
   * files are not validated correctly.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a content type.
    $this->setUpNodeType();

    // Create a user with admin privileges.
    $this->adminUser = $this->drupalCreateUser([
      'administer feeds migrate importers',
      'administer migrations',
      'edit any article content',
      'delete any article content',
      'access administration pages',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Wait until the batch has finished processing.
   *
   * Wait until the id="updateprogress" element is gone, or timeout after 3
   * minutes (180,000 ms).
   */
  public function waitForBatchToFinish() {
    $this->getSession()->wait(180000, 'jQuery("#updateprogress").length === 0');
  }

}
