<?php

namespace Drupal\Tests\feeds_migrate\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\Tests\feeds_migrate\Traits\FeedsCommonTrait;
use Drupal\Tests\feeds_migrate\Traits\FeedsCreationTrait;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Base class for Feeds javascript tests.
 */
abstract class FeedsMigrateJavascriptTestBase extends WebDriverTestBase {

  use CronRunTrait;
  use FeedsCommonTrait;
  use FeedsCreationTrait;

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
