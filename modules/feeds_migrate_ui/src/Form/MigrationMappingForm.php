<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\feeds_migrate\MappingFieldFormInterface;
use Drupal\feeds_migrate\MappingFieldFormManager;
use Drupal\feeds_migrate\MigrationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for listing/saving mapping settings.
 *
 * @package Drupal\feeds_migrate\Form
 */
class MigrationMappingForm extends EntityForm {

  /**
   * Plugin manager for migration mapping plugins.
   *
   * @var \Drupal\feeds_migrate\MappingFieldFormManager
   */
  protected $mappingFieldManager;

  /**
   * Field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Helper service for migration entity.
   *
   * @var \Drupal\feeds_migrate\MigrationHelper
   */
  protected $migrationHelper;

  /**
   * Creates a new MigrationMappingForm.
   *
   * @param \Drupal\feeds_migrate\MappingFieldFormManager $mapping_field_manager
   *   Mapping field manager service.
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   Field manager service.
   * @param \Drupal\feeds_migrate\MigrationHelper $migration_helper
   *   Helper service for migration entity.
   */
  public function __construct(MappingFieldFormManager $mapping_field_manager, EntityFieldManager $field_manager, MigrationHelper $migration_helper) {
    $this->mappingFieldManager = $mapping_field_manager;
    $this->fieldManager = $field_manager;
    $this->migrationHelper = $migration_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.feeds_migrate.mapping_field_form'),
      $container->get('entity_field.manager'),
      $container->get('feeds_migrate.migration_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    // Build mapping table.
    $form['mappings'] = [
      '#type' => 'table',
      '#header' => $this->getTableHeader(),
      '#empty' => $this->t('Please add mappings to this migration.'),
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'row-pid',
          'source' => 'row-id',
          'hidden' => TRUE, /* hides the WEIGHT & PARENT tree columns below */
          'limit' => 0,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'row-weight',
        ],
      ],
    ];

    // Load migration process configuration.
    $mappings = $this->getSortableMappings();
    foreach ($mappings as $target => $mapping) {
      $plugin = $mapping['#plugin'];
      $property = $mapping['#property'];
      $form['mappings'][$target] = $this->buildTableRow($plugin, $property);
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * Gets the mapping table header.
   *
   * @return array
   *   The headers.
   */
  protected function getTableHeader() {
    $header = [];

    $header['destination'] = [
      'data' => $this->t('Destination'),
    ];

    $header['source'] = [
      'data' => $this->t('Source'),
    ];

    $header['summary'] = [
      'data' => $this->t('Summary'),
    ];

    $header['unique'] = [
      'data' => $this->t('Unique'),
    ];

    $header['weight'] = [
      'data' => $this->t('Weight'),
    ];

    $header['operations'] = [
      'data' => $this->t('Operations'),
      'colspan' => 2,
    ];

    return $header;
  }

  /**
   * Build the table row.
   *
   * @param \Drupal\feeds_migrate\MappingFieldFormInterface $plugin
   *   The mapping field form plugin.
   * @param string $property
   *   The mapping field property - if any.
   *
   * @return array
   *   The built field row.
   */
  protected function buildTableRow(MappingFieldFormInterface $plugin, $property = NULL) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    $mapping = $plugin->getConfiguration($property);
    $operations = [];

    // Initialize our row.
    $row = [
      '#attributes' => [
        'class' => ['draggable'],
      ],
      'destination' => [],
      'source' => [],
      'summary' => [],
      'unique' => [],
      'weight' => [],
      'operations' => [],
    ];

    // Whenever applicable, use the field label as our destination value.
    $row['destination'] = [
      '#type' => 'label',
      '#title' => $destination = $plugin->getLabel($property),
    ];

    // Add the weight column.
    $row['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#title_display' => 'invisible',
      '#default_value' => $mapping['#weight'],
      '#delta' => 30,
      '#attributes' => [
        'class' => ['row-weight'],
      ],
    ];

    // Source.
    $row['source'] = [
      '#markup' => is_array($mapping['source']) ? implode('<br>', $mapping['source']) : $mapping['source'],
    ];

    // Summary of process plugins.
    $row['summary'] = $plugin->getSummary($property);

    // Unique.
    $row['unique'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unique'),
      '#title_display' => 'invisible',
      '#default_value' => $mapping['is_unique'],
      '#disabled' => TRUE,
    ];

    // Operations.
    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'url' => new Url(
        'entity.migration.mapping.edit_form',
        [
          'migration' => $migration->id(),
          'destination' => rawurlencode($plugin->getDestinationKey()),
        ]
      ),
    ];
    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'url' => new Url(
        'entity.migration.mapping.delete_form',
        [
          'migration' => $migration->id(),
          'destination' => rawurlencode($plugin->getDestinationKey()),
        ]
      ),
    ];
    $row['operations'] = [
      '#type' => 'operations',
      '#links' => $operations,
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    $mappings = $this->migrationHelper->getMappings($migration);

    // Get the sorted mappings and sort them by weight.
    $sorted_mappings = $form_state->cleanValues()->getValue('mappings') ?: [];
    uasort($sorted_mappings, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

    // Make sure the reordered mapping keys match the existing mapping keys.
    if (array_diff_key($sorted_mappings, $mappings)) {
      $form_state->setError($form['mappings'],
        $this->t('The mapping properties have been altered. Please try again'));
    }

    // $mappings_sorted = [];
    // foreach ($mappings as $key => $process_lines) {
    //   // Validate missing mappings.
    //   if (!isset($mappings_original[$key])) {
    //     $form_state->setError($form['mappings'][$key],
    //       $this->t('A mapping for field %destination_field does not exist.', [
    //         '%destination_field' => $key,
    //       ]));
    //     continue;
    //   }
    //
    //   $mappings_sorted[$key] = $mappings_original[$key];
    // }

    if ($form_state->hasAnyErrors()) {
      return;
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // // Write process back to migration entity.
    // $mappings = $this->migrationEntityHelper()->getMappings();
    // $process = $this->migrationEntityHelper()->processMappings($mappings);
    //
    // $entity->set('process', $process);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->getEntity();
    $status = parent::save($form, $form_state);

    // If we edited an existing mapping.
    $this->messenger()->AddMessage($this->t('Migration mapping for migration 
        @migration has been updated.', [
          '@migration' => $migration->label(),
        ]));
  }

  /****************************************************************************/
  // Helper functions.
  /****************************************************************************/

  /**
   * Get migration mappings as an associative array of sortable elements.
   *
   * @return array
   *   An associative array of sortable elements.
   */
  protected function getSortableMappings() {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    $sortable_mappings = [];
    $mappings = $this->migrationHelper->getMappings($migration);

    $weight = 0;
    foreach ($mappings as $key => $configuration) {
      $destination_field = $configuration['destination']['field'] ?? NULL;
      $plugin_id = $this->mappingFieldManager->getPluginIdFromField($destination_field);

      /** @var \Drupal\feeds_migrate\MappingFieldFormInterface $plugin */
      $form_plugin = $this->mappingFieldManager->createInstance($plugin_id, $configuration, $migration);
      $property = explode('/', $key)[1] ?? NULL;

      $sortable_mappings[$key] = [
        '#plugin' => $form_plugin,
        '#property' => $property,
        '#weight' => $weight,
      ];
      $weight++;
    }

    return $sortable_mappings;
  }

}
