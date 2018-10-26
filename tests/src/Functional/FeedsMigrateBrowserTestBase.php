<?php

namespace Drupal\Tests\feeds_migrate\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\feeds_migrate\Traits\FeedsCommonTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;
use Drupal\migrate_plus\Entity\MigrationGroup;

/**
 * Provides a base class for Feeds functional tests.
 */
abstract class FeedsMigrateBrowserTestBase extends BrowserTestBase {

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

}
