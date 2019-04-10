<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\feeds_migrate\MappingFieldFormManager;
use Drupal\feeds_migrate\MigrationEntityHelperManager;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for listing/saving mapping settings.
 *
 * @package Drupal\feeds_migrate\Form
 *
 * @todo consider moving this UX into migrate_tools module to allow editors
 * to create simple migrations directly from the admin interface
 */
class MigrationMappingForm extends EntityForm {

  /**
   * @var \Drupal\feeds_migrate\MigrationEntityHelperManager
   */
  protected $migrationEntityHelperManager;

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Plugin manager for migration mapping plugins.
   *
   * @var \Drupal\feeds_migrate\MappingFieldFormManager
   */
  protected $mappingFieldManager;

  /**
   * Fill This.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * {@inheritdoc}
   * TODO clean up dependencies.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('plugin.manager.feeds_migrate.mapping_field_form'),
      $container->get('entity_field.manager'),
      $container->get('feeds_migrate.migration_entity_helper')
    );
  }

  /**
   * @todo: clean up dependencies.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   * @param \Drupal\feeds_migrate\MappingFieldFormManager $mapping_field_manager
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   * @param \Drupal\feeds_migrate\MigrationEntityHelperManager $migration_entity_helper_manager
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, MappingFieldFormManager $mapping_field_manager, EntityFieldManager $field_manager, MigrationEntityHelperManager $migration_entity_helper_manager) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->mappingFieldManager = $mapping_field_manager;
    $this->fieldManager = $field_manager;
    $this->migrationEntityHelperManager = $migration_entity_helper_manager;
  }

  /**
   * Returns the helper for a migration entity.
   */
  protected function migrationEntityHelper() {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    return $this->migrationEntityHelperManager->get($migration);
  }

  /**
   * {@inheritdoc}
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    // Overriding \Drupal\Core\Entity\EntityForm::afterBuild because
    // it calls ::buildEntity(), which calls ::copyFormValuesToEntity, which
    // attempts to populate the entity even though nothing has been validated.
    // @see \Drupal\Core\Entity\EntityForm::afterBuild
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $header = $this->getTableHeader();

    // Build table rows for mappings.
    $rows = [];
    $mappings = $this->migrationEntityHelper()->getSortableMappings();
    foreach ($mappings as $target => $mapping) {
      $rows[$target] = $this->buildTableRow($mapping);

      if (isset($mapping['#properties'])) {
        foreach ($mapping['#properties'] as $property => $info) {
          $id = $target . '.' . $property;
          $rows[$id] = $this->buildTableRow($mapping, $property);

          // Exclude these rows from the form_state.
          $rows[$id]['#parents'] = ['mapping', $target];
        }
      }
    }

    $form['mappings'] = [
      '#type' => 'table',
      '#header' => $header,
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
    ] + $rows;

    // Add custom CSS.
    $form['#attached']['library'][] = 'feeds_migrate_ui/feeds_migrate_ui';

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

    // We hide the parent column since we have no actual use for it in the UI.
    $header['operations'] = [
      'data' => $this->t('Operations'),
      'colspan' => 2,
    ];

    return $header;
  }

  /**
   * Build the table row.
   *
   * @param $mapping
   *   The raw mapping array.
   * @param $property
   *   The field property to generate the nested table row for.
   *
   * @return array
   *   The built field row.
   */
  protected function buildTableRow(array $mapping, $property = NULL) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    /** @var \Drupal\feeds_migrate\MappingFieldFormInterface  $plugin */
    $plugin = $this->mappingFieldManager->getMappingFieldInstance($mapping, $migration);
    $parent_id = $row_id = $destination = $mapping['#destination']['key'];
    $source = $mapping['source'] ?? [];
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
      'parent' => [
        'id' => [
          '#parents' => ['mappings', $row_id, 'id'],
          '#type' => 'hidden',
          '#value' => $row_id,
          '#attributes' => [
            'class' => ['row-id'],
          ],
        ],
        'pid' => [
          '#parents' => ['mappings', $row_id, 'pid'],
          '#type' => 'hidden',
          '#title' => $this->t('Parent ID'),
          '#value' => $parent_id,
          '#attributes' => [
            'class' => ['row-pid'],
          ],
        ],
      ],
      'operations' => [],
    ];

    // Whenever applicable, use the field label as our destination value.
    if (isset($mapping['#destination']['#field'])) {
      $destination = $mapping['#destination']['#field']->getLabel() . '(' . $destination . ')';
    }

    // If we are handling a field property, adjust the table values.
    if ($property) {
      $row['#attributes']['class'][] = 'tabledrag-leaf';
      // Add custom class to prevent dragging from properties.
      $row['#attributes']['class'][] = 'tabledrag-locked';
      $destination = $property;
      $source = $mapping['#properties'][$property]['source'];
    }
    else {
      $row['#attributes']['class'][] = 'tabledrag-root';

      // Only add the weight column for the root mapping fields.
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
    }

    // Destination.
    $row['destination'] = [
      [
        '#theme' => 'indentation',
        // Adjust indentation if we are handling a nested field property.
        '#size' => ($property) ? 1 : 0,
      ],
      [
        '#markup' => $destination,
      ],
    ];

    // Source.
    $row['source'] = [
      '#markup' => is_array($source) ? implode('<br>', $source) : $source,
    ];

    // Summary of process plugins.
    $summary = $plugin->getSummary($mapping, $property);
    if ($summary) {
      $row['summary'] = [
        '#type' => 'textarea',
        '#rows' => 5,
        '#disabled' => TRUE,
        '#default_value' => $summary,
      ];
    }

    // Unique.
    if (!$property) {
      $unique = array_key_exists($source, $migration->source['ids']);
      $row['unique'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Unique'),
        '#title_display' => 'invisible',
        '#default_value' => $unique,
        '#disabled' => TRUE,
      ];
    }

    // Operations.
    if (!$property) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'url' => new Url(
          'entity.migration.mapping.edit_form',
          [
            'migration' => $migration->id(),
            'key' => rawurlencode($mapping['#destination']['key']),
          ]
        ),
      ];
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'url' => new Url(
          'entity.migration.mapping.delete_form',
          [
            'migration' => $migration->id(),
            'key' => rawurlencode($mapping['#destination']['key']),
          ]
        ),
      ];
    }
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
    $mappings_original = $this->migrationEntityHelper()->getMappings();

    // Get the sorted mappings and sort them by weight.
    $mappings = $form_state->cleanValues()->getValue('mappings') ?: [];
    uasort($mappings, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

    // Make sure the reordered mapping keys match the existing mapping keys.
    if (array_diff_key($mappings, $mappings_original)) {
      $form_state->setError($form['mappings'],
        $this->t('The mapping properties have been altered. Please try again'));
    }

    $mappings_sorted = [];
    foreach ($mappings as $key => $process_lines) {
      // Validate missing mappings.
      if (!isset($mappings_original[$key])) {
        $form_state->setError($form['mappings'][$key],
          $this->t('A mapping for field %destination_field does not exist.', [
            '%destination_field' => $key,
          ]));
        continue;
      }

      $mappings_sorted[$key] = $mappings_original[$key];
    }

    if ($form_state->hasAnyErrors()) {
      return;
    }

    // Set the new mappings.
    $this->migrationEntityHelper()->setMappings($mappings_sorted);

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Write process back to migration entity.
    $mappings = $this->migrationEntityHelper()->getMappings();
    $process = $this->migrationEntityHelper()->processMappings($mappings);

    $entity->set('process', $process);
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

}
