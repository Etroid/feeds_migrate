<?php

namespace Drupal\feeds_migrate;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class for Migrate Entity.
 */
class MigrationEntityHelper {

  /**
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   */
  protected $migration;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Array of normalized migration mappings, keyed by destination field.
   * Field properties are nested under ['properties']['property_1'] => [] etc...
   *
   * @var array
   */
  protected $mappings;

  /**
   * Constructs a migration helper.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   */
  public function __construct(EntityFieldManager $field_manager, MigrationInterface $migration = NULL) {
    $this->fieldManager = $field_manager;
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, MigrationInterface $migration = NULL) {
    return new static(
      $container->get('entity_field.manager'),
      $migration
    );
  }

  /**
   * Find the entity type the migration is importing into.
   *
   * @return string
   *   Machine name of the entity type eg 'node'.
   */
  public function getEntityTypeIdFromDestination() {
    if (isset($this->migration->destination['plugin'])) {
      $destination = $this->migration->destination['plugin'];
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
  public function getEntityBundleFromDestination() {
    if (!empty($this->migration->destination['default_bundle'])) {
      return $this->migration->destination['default_bundle'];
    }
    elseif (!empty($this->migration->source['constants']['bundle'])) {
      return $this->migration->source['constants']['bundle'];
    }
  }

  /**
   * Resolves shorthands into a list of plugin configurations and ensures
   * 'get' plugins at the start of the process.
   *
   * Based of:
   * @see \Drupal\migrate\Plugin\Migration
   *
   * @param array $process
   *   A process configuration array.
   *
   * @return array
   *   The normalized process configuration.
   */
  public function getProcessNormalized(array $process) {
    $normalized_configurations = [];
    foreach ($process as $destination => $configuration) {
      if (is_string($configuration)) {
        $configuration = [
          'plugin' => 'get',
          'source' => $configuration,
        ];
      }
      if (isset($configuration['plugin'])) {
        $configuration = [$configuration];
      }

      // Always ensure we have a 'get' plugin to start with.
      $first_plugin = $configuration[0]['plugin'] ?? FALSE;
      if (!$first_plugin || $first_plugin !== 'get') {
        $source = '';
        foreach ($configuration as $index => &$process_line) {
          if (isset($process_line['source'])) {
            $source = $process_line['source'];

            // Remove the source value from this plugin, as we have accounted
            // for it in the initial 'get'.
            unset($process_line['source']);
            break;
          }
        }

        array_unshift($configuration, [
          'plugin' => 'get',
          'source' => $source
        ]);
      }

      $normalized_configurations[$destination] = $configuration;
    }
    return $normalized_configurations;
  }

  /**
   * Set migration mappings.
   *
   * @param array $mappings
   *  Array of migration mapping configurations.
   */
  public function setMappings(array $mappings) {
    $this->mappings = $mappings;
  }

  /**
   * Get normalized migration mapping configurations.
   */
  public function getMappings() {
    $this->initializeMappings();
    return $this->mappings;
  }

  /**
   * Get a migration mapping for a single destination key.
   *
   * @param $key
   *
   * @return array
   */
  public function getMapping($key) {
    $mappings = $this->getMappings();
    return (isset($mappings[$key])) ? $mappings[$key] : $this->getDefaultMapping($key);
  }

  /**
   * Get a mapping stub for a destination key.
   *
   * @param $key
   *
   * @return array
   */
  public function getDefaultMapping($key) {
    return [
      '#destination' => [
        'key' => $key,
      ],
      'plugin' => 'get',
      'source' => [],
      '#process' => [],
    ];
  }

  /**
   * Parse raw migration process configuration into mappings decorated with
   * additional properties.
   */
  public function initializeMappings() {
    if (isset($this->mappings)) {
      return;
    }

    $mappings = [];
    $process = $this->getProcessNormalized($this->migration->get('process'));
    foreach ($process as $destination => $configuration) {
      // Determine the destination field. Migrations support `field/property`
      // destination as well.
      // Example: 'body/value' and 'body/text_format' have the same destination
      // field (i.e. body).
      $destination_parts = explode('/', $destination);
      $destination_field_name = $destination_parts[0];
      $destination_property = $destination_parts[1] ?? FALSE;

      // Set the field mapping destination.
      $mappings[$destination_field_name]['#destination'] = [
        'key' => $destination_field_name,
      ];

      // Use a shorthand variable.
      $mapping = &$mappings[$destination_field_name];

      // Try and load the destination field.
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $destination_field */
      $destination_field = $this->getMappingField($destination_field_name);
      if ($destination_field) {
        $mapping['#destination']['#type'] = $destination_field->getType();
        $mapping['#destination']['#field'] = $destination_field;
      }

      if ($destination_property) {
        $mapping['#properties'][$destination_property] = $this->initializeMappingRecursively($configuration);
      }
      else {
        $mapping += $this->initializeMappingRecursively($configuration);
      }

      // TODO add support for sub_process plugins
    }

    $this->mappings = $mappings;
  }

  /**
   * Extracts mapping source for a given mapping configuration and stores it
   * at the root.
   *
   * @param $configuration
   *   The process configuration for a given mapping.
   *
   * @return mixed
   *   An simplified mapping array with the source information at the root.
   */
  public function initializeMappingRecursively(array $configuration) {
    // We use the 'get' plugin information as the source of our mapping.
    $mapping = array_shift($configuration);
    $mapping['#process'] = $configuration ?? [];

    return $mapping;
  }

  /**
   * Parse a mapping back into raw migration process configurations.
   *
   * @param $mapping
   * @return array
   */
  public function processMapping($mapping) {
    $process = [];
    $key = $mapping['#destination']['key'];

    if (isset($mapping['#properties'])) {
      foreach ($mapping['#properties'] as $property => $process_lines) {
        $key = implode('/', [$key, $property]);

        array_unshift($process_lines['#process'], [
          'plugin' => $process_lines['plugin'],
          'source' => $process_lines['source'],
        ]);
        $process[$key] = $process_lines['#process'];
      }
    }
    else {
      array_unshift($mapping['#process'], [
        'plugin' => $mapping['plugin'],
        'source' => $mapping['source'],
      ]);
      $process[$key] = $mapping['#process'];
    }

    return $process;
  }

  /**
   * Parse mappings back into raw migration process configurations.
   *
   * @param array $mappings
   * @return array
   */
  public function processMappings($mappings) {
    $process = [];

    foreach ($mappings as $destination => $mapping) {
      $process += $this->processMapping($mapping);
    }

    return $process;
  }

  /**
   * Get migration mappings as an associative array of sortable elements.
   *
   * @return array
   *   An associative array of sortable elements.
   */
  public function getSortableMappings() {
    $mappings = $this->getMappings();

    $weight = 0;
    foreach ($mappings as $key => &$mapping) {
      $mapping['#weight'] = $weight;
      $weight++;
    }

    return $mappings;
  }

  /**
   * Find the field this migration mapping is pointing to.
   *
   * @param $name
   *   The machine name of the field.
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The field definition if found, NULL otherwise. Migrations support
   *   pseudo fields which are used to store values for the duration of the
   *   migration.
   */
  public function getMappingField($name) {
    $entity_type_id = $this->getEntityTypeIdFromDestination();
    $entity_bundle = $this->getEntityBundleFromDestination();

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_manager */
    $field_definitions = $this->fieldManager->getFieldDefinitions($entity_type_id, $entity_bundle);

    if (isset($field_definitions[$name])) {
      return $field_definitions[$name];
    }

    return NULL;
  }

  /**
   * Get the mapping field's label or key.
   *
   * @param string key
   *  The specific key to return the label for,
   * @return string
   *   The migration mapping field's label or key,
   */
  public function getMappingFieldLabel($key) {
    $mapping_field = $this->mapping['#destination']['#field'] ?? FALSE;

    return ($mapping_field) ? $mapping_field->getLabel() : $key;
  }

  /**
   * Checks the process pipeline configuration for any mappings with the
   *
   *
   * @param string $key
   *   The key of the mapping destination field of which to remove the process
   *   pipeline configuration.
   *
   * @return bool
   *   TRUE if the mapping exists, FALSE otherwise.
   */
  public function mappingExists($key) {
    return (isset($this->mappings[$key]));
  }

  /**
   * Delete the process pipeline configuration for an individual destination
   * field.
   *
   * This method allows you to remove the process pipeline configuration for a
   * single property within the full migration process pipeline configuration.
   *
   * @param string $key
   *   The key of the mapping destination field of which to remove the process
   *   pipeline configuration.
   */
  public function deleteMapping($key) {
    unset($this->mappings[$key]);
  }

}
