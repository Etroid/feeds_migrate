<?php

namespace Drupal\feeds_migrate;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;

/**
 * Interface MappingFieldFormManagerInterface.
 *
 * @package Drupal\feeds_migrate
 */
interface MappingFieldFormManagerInterface {

  /**
   * Get the plugin ID from the field type.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The destination field definition.
   *
   * @return \Drupal\feeds_migrate\MappingFieldFormInterface
   *   The plugin id.
   */
  public function getPluginIdFromField(FieldDefinitionInterface $field = NULL);

  /**
   * Creates a pre-configured instance of a migration plugin.
   *
   * A specific createInstance method is necessary to pass the migration on.
   *
   * @param string $plugin_id
   *   The ID of the plugin being instantiated.
   * @param array $configuration
   *   An array of configuration relevant to the plugin instance.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration context in which the plugin will run.
   *
   * @return \Drupal\feeds_migrate\MappingFieldFormInterface
   *   A fully configured plugin instance.
   */
  public function createInstance($plugin_id, array $configuration = [], MigrationInterface $migration = NULL);

}
