<?php

namespace Drupal\feeds_migrate_ui;

/**
 * Trait class for Migrate Entity UI support.
 * @TODO This is a temporary trait to consolidate shared logic.
 */
trait FeedsMigrateUIEntityTrait {

  /**
   * Find the entity type the migration is importing into.
   *
   * @return string
   *   Machine name of the entity type eg 'node'.
   */
  protected function getEntityTypeIdFromMigration() {
    if (isset($this->entity->destination['plugin'])) {
      $destination = $this->entity->destination['plugin'];
      if (strpos($destination, ':') !== FALSE) {
        list(, $entity_type) = explode(':', $destination);
        return $entity_type;
      }
    }
  }

  /**
   * The bundle the migration is importing into.
   *
   * @return string
   *   Entity type bundle eg 'article'.
   */
  protected function getEntityBundleFromMigration() {
    if (!empty($this->entity->destination['default_bundle'])) {
      return $this->entity->destination['default_bundle'];
    }
    elseif (!empty($this->entity->source['constants']['bundle'])) {
      return $this->entity->source['constants']['bundle'];
    }
  }

  /**
   * Find the field this migration mapping is pointing to.
   *
   * @param $name
   *  The machine name of the field.
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *  The field definition if found, NULL otherwise. Migrations support
   *  pseudo fields which are used to store values for the duration of the
   *  migration.
   */
  protected function getDestinationField($name) {
    $entity_type_id = $this->getEntityTypeIdFromMigration();
    $entity_bundle = $this->getEntityBundleFromMigration();

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[]  $field_manager */
    $field_definitions = $this->fieldManager->getFieldDefinitions($entity_type_id, $entity_bundle);

    if (isset($field_definitions[$name])) {
      return $field_definitions[$name];
    }

    return NULL;
  }

  /**
   * Get migration mappings as an associative array of sortable elements.
   *
   * @return array
   *   An associative array of sortable elements.
   */
  protected function getSortableMappings() {
    $mappings = $this->getMappings();

    $weight = 0;
    foreach ($mappings as $key => &$mapping) {
      $mapping['#weight'] = 0;

      $weight++;
    }

    return $mappings;
  }

  /**
   * Get migration mappings decorated with additional properties and return them
   * as an associative array.
   */
  protected function getMappings() {
    $migration = $this->entity;
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
    $migration_plugin = $this->migrationPluginManager->createInstance($migration->id(), $migration->toArray());

    $mappings = [];
    foreach ($migration_plugin->getProcess() as $destination_key => $process_lines) {
      $mapping['#destination_key'] = $destination_key;
      $mapping['#process_lines'] = $process_lines;
      $mapping['#source'] = $process_lines[0]['source'] ?? '';

      // Try and load the field from the destination key.
      $destination = $this->getDestinationField($destination_key);
      $mapping['#destination_is_pseudo'] = FALSE;
      if ($destination) {
        $mapping['#destination'] = $destination;
      }
      else {
        // The destination field could not be retrieved, we assume it is a
        // pseudo destination.
        $mapping['#destination'] = NULL;
        $mapping['#destination_is_pseudo'] = TRUE;
      }

      $mappings[$destination_key] = $mapping;
    }

    return $mappings;
  }

}
