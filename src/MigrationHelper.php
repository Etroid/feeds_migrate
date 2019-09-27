<?php

namespace Drupal\feeds_migrate;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class for Migration Entity.
 */
class MigrationHelper {

  /**
   * Array of normalized migration mappings.
   *
   * @var array
   */
  protected $mappings;

  /**
   * Field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Constructs a migration helper.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   The field manager service.
   */
  public function __construct(EntityFieldManager $field_manager) {
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * Find the entity type this migration will import into.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   *
   * @return string
   *   Machine name of the entity type eg 'node'.
   */
  public function getEntityTypeIdFromDestination(MigrationInterface $migration) {
    if (isset($migration->destination['plugin'])) {
      $destination = $migration->destination['plugin'];
      if (strpos($destination, ':') !== FALSE) {
        list(, $entity_type) = explode(':', $destination);
        return $entity_type;
      }
    }
  }

  /**
   * The bundle the migration is importing into.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   *
   * @return string
   *   Entity type bundle eg 'article'.
   */
  public function getEntityBundleFromDestination(MigrationInterface $migration) {
    if (!empty($migration->destination['default_bundle'])) {
      return $migration->destination['default_bundle'];
    }
    elseif (!empty($migration->source['constants']['bundle'])) {
      return $migration->source['constants']['bundle'];
    }
  }

  /**
   * Get a list of fields for the destination the this migration is pointing at.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   The array of field definitions for the bundle, keyed by field name.
   */
  public function getDestinationFields(MigrationInterface $migration) {
    $entity_type_id = $this->getEntityTypeIdFromDestination($migration);
    $entity_bundle = $this->getEntityBundleFromDestination($migration);

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_manager */
    $field_definitions = $this->fieldManager->getFieldDefinitions($entity_type_id, $entity_bundle);

    return $field_definitions;
  }

  /**
   * Find the field this migration mapping is pointing to.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param string $field_name
   *   The name of the field to look for.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition - if any.
   */
  public function getDestinationField(MigrationInterface $migration, $field_name) {
    $field_definitions = $this->getDestinationFields($migration);

    return $field_definitions[$field_name] ?? NULL;
  }

  /**
   * Deletes a migration mapping for a single destination key.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param array $mapping
   *   The mapping to delete from the migration entity.
   */
  public function deleteMapping(MigrationInterface $migration, array $mapping) {
    $raw_process = $migration->get('process');
    $destination_key = $mapping['destination']['key'];

    // If the destination key has a match in our mapping array, delete it
    // immediately.
    if (isset($raw_process[$destination_key])) {
      unset($raw_process[$destination_key]);
    }
    else {
      // An immediate match was not found. Try using the the destination key
      // as a field name to delete all field_name/property mappings.
      $destination_field = $this->getDestinationField($migration, $destination_key);
      if (isset($destination_field)) {
        $field_name = $destination_field->getName();
        $properties = $destination_field->getFieldStorageDefinition()->getPropertyNames();

        foreach ($properties as $property_name) {
          $destination_key = implode('/', [$field_name, $property_name]);
          if (isset($raw_process[$destination_key])) {
            unset($raw_process[$destination_key]);
          }
        }
      }
    }

    $migration->set('process', $raw_process);
  }

  /**
   * Deletes a migration mapping for a single destination key.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param array $mapping
   *   The mapping to delete from the migration entity.
   */
  public function saveMapping(MigrationInterface $migration, array $mapping) {
    $raw_process = $migration->get('process');
    $destination_key = $mapping['destination']['key'];

    // If the destination key has a match in our mapping array, delete it
    // immediately.
    if (isset($raw_process[$destination_key])) {
      unset($raw_process[$destination_key]);
    }
    else {
      // An immediate match was not found. Try using the the destination key
      // as a field name to delete all field_name/property mappings.
      $destination_field = $this->getDestinationField($migration, $destination_key);
      if (isset($destination_field)) {
        $field_name = $destination_field->getName();
        $properties = $destination_field->getFieldStorageDefinition()->getPropertyNames();

        foreach ($properties as $property_name) {
          $destination_key = implode('/', [$field_name, $property_name]);
          if (isset($raw_process[$destination_key])) {
            unset($raw_process[$destination_key]);
          }
        }
      }
    }

    $migration->set('process', $raw_process);
  }

  /**
   * Get all migration mappings.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   *
   * @return array
   *   List of migration mappings.
   */
  public function getMappings(MigrationInterface $migration) {
    if (isset($this->mappings)) {
      return $this->mappings;
    }

    return $this->mappings = $this->initializeMappings($migration);
  }

  /**
   * Get a migration mapping for a single destination key.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param string $destination_key
   *   The destination key to retrieve a mapping for.
   *
   * @return array
   *   Migration mapping.
   */
  public function getMapping(MigrationInterface $migration, $destination_key) {
    $mappings = $this->getMappings($migration);

    // If the destination key has a match in our mapping array, return it
    // immediately.
    if (isset($mappings[$destination_key])) {
      return $mappings[$destination_key];
    }
    else {
      // An immediate match was not found. Try using the the destination key
      // as a field name to return all field_name/property mappings.
      $destination_field = $this->getDestinationField($migration, $destination_key);
      if (isset($destination_field)) {
        foreach ($mappings as $mapping) {
          if ($mapping['destination']['key'] === $destination_field->getName()) {
            return $mapping;
          }
        }
      }
    }

    return [];
  }

  /**
   * Initialize mapping field instances based on the migration configuration.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   *
   * @return array
   *   List of migration mappings.
   */
  public function initializeMappings(MigrationInterface $migration) {
    $process_config = $this->normalizeProcessConfig($migration);
    $mappings = [];
    $mapping_dictionary = [];

    // Store a unique list of mapping field instances.
    foreach ($process_config as $destination => $process) {
      $destination_field_name = $process['destination']['field_name'];

      if (!isset($mapping_dictionary[$destination_field_name])) {
        // We aggregate mapping for fields with multiple field properties.
        $destination_field = $this->getDestinationField($migration, $destination_field_name);
        if (isset($destination_field)) {
          $mapping = [
            'destination' => [
              'key' => $destination_field_name,
              'field' => $destination_field,
            ],
          ];
          $properties = $destination_field->getFieldStorageDefinition()->getPropertyNames();

          foreach ($properties as $property_name) {
            $destination_key = implode('/', [$destination_field_name, $property_name]);

            if (isset($process_config[$destination_key])) {
              $mapping[$property_name] = $process_config[$destination_key];
            }
          }

          $mapping_dictionary[$destination_field_name] = $mapping;
        }
        else {
          $mapping_dictionary[$destination_field_name] = $process;
        }
      }

      $mappings[$destination] = $mapping_dictionary[$destination_field_name];
    }

    return $mappings;
  }

  /**
   * Normalizes migrate process configuration.
   *
   * Resolves shorthands into a list of plugin configurations and ensures
   * 'get' plugins at the start of the process.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   *
   * @return array
   *   The normalized mapping.
   */
  protected function normalizeProcessConfig(MigrationInterface $migration) {
    $raw_config = $migration->get('process');
    $normalized_config = [];
    foreach ($raw_config as $destination => $process) {
      if (is_string($process)) {
        $process = [
          'plugin' => 'get',
          'source' => $process,
        ];
      }
      if (isset($process['plugin'])) {
        if ($process['plugin'] === 'sub_process') {
          foreach ($process['process'] as $property => $sub_process_line) {
            if (is_string($sub_process_line)) {
              $sub_process_line = [
                'plugin' => 'get',
                'source' => $sub_process_line,
              ];
            }

            $destination = implode('/', [$destination, $property]);
            $sub_process_line['source'] = implode('/', [$process['source'], $sub_process_line['source']]);
            $normalized_config[$destination] = $sub_process_line;
          }
        }
        else {
          $process = [$process];
        }
      }

      // Determine the destination field. Migrations support `field/property`
      // destination as well.
      // Example: 'body/value' and 'body/text_format' have the same destination
      // field (i.e. body).
      $destination_parts = explode('/', $destination);
      $destination_field = $destination_parts[0];
      $destination_property = $destination_parts[1] ?? '';

      $configuration = [
        'destination' => [
          'key' => $destination,
          'field_name' => $destination_field,
          'property' => $destination_property,
        ],
        'source' => '',
        'process' => [],
      ];

      foreach ($process as $index => $process_line) {
        if (isset($process_line['source'])) {
          $source = $process_line['source'];
          $configuration['source'] = $source;
          if (is_string($source)) {
            $configuration['is_unique'] = array_key_exists($source, $migration->source["ids"]) ?? FALSE;
          }
        }
        else {
          $configuration['process'][$index] = $process_line;
        }
      }

      $normalized_config[$destination] = $configuration;
    }

    return $normalized_config;
  }

}
