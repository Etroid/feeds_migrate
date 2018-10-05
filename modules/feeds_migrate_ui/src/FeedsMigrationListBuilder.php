<?php

namespace Drupal\feeds_migrate_ui;

use Drupal\migrate_tools\Controller\MigrationListBuilder;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class FeedsMigrateImporterListBuilder.
 *
 * @package Drupal\feeds_migrate
 */
class FeedsMigrationListBuilder extends MigrationListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {

    // Override migrate_tools migration list to include edit and delete links.
    $row = parent::buildRow($entity);

    $edit_delete_ops = ConfigEntityListBuilder::buildRow($entity);

    if (is_array($row['operations'])) {
      // migrate_tools is giving us execute button, so add edit and delete.
      $row = $row + $edit_delete_ops;
    }
    else {
      // migrate_tools is giving us N/A, so wipe that and add edit and delete.
      $row['operations'] = $edit_delete_ops['operations'];
    }

    return $row;
  }

}
