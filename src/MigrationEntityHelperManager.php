<?php

namespace Drupal\feeds_migrate;

use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Manages migration entity helpers.
 *
 * @package Drupal\feeds_migrate
 */
class MigrationEntityHelperManager implements ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * @var \Drupal\feeds_migrate\MigrationEntityHelper[]
   */
  protected $migrations = [];

  /**
   * Gets the MigrationEntityHelper instance for a given Migration Entity.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *
   * @return \Drupal\feeds_migrate\MigrationEntityHelper
   */
  public function get(MigrationInterface $migration) {
    $id = $migration->id();

    if (!isset($this->migrations[$id])) {
      $this->migrations[$id] = MigrationEntityHelper::create($this->container, $migration);
    }

    return $this->migrations[$id];
  }

}
