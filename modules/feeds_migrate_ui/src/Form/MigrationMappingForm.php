<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\feeds_migrate_ui\FeedsMigrateUIEntityTrait;
use Drupal\feeds_migrate_ui\FeedsMigrateUiFieldManager;
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

  // Temporarily use a trait for easier development.
  use FeedsMigrateUIEntityTrait;
  
  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Fill This.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Fill This.
   *
   * @var \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldManager
   */
  protected $fieldProcessorManager;

  /**
   * {@inheritdoc}
   * TODO clean up dependencies
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('plugin.manager.feeds_migrate_ui.field'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * @todo: clean up dependencies.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   * @param \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldManager $field_processor
   * @param \Drupal\feeds_migrate_ui\Form\EntityFieldManager $field_manager
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, FeedsMigrateUiFieldManager $field_processor, EntityFieldManager $field_manager) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->fieldProcessorManager = $field_processor;
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $header = $this->getTableHeader();

    // Build table rows for mappings.
    $rows = [];
    $mappings = $this->getSortableMappings();
    foreach ($mappings as $target => $mapping) {
      $rows[$target] = $this->buildTableRow($mapping, $form, $form_state);
    }

    $form['mappings'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('Please add mappings to this migration.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ]
      ],
    ] + $rows;

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
    ];

    return $header;
  }

  /**
   * Build the table row.
   *
   * @param $mapping
   *   The raw mapping array.
   * @param array $form
   *   Current form.
   * @param FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   The built field row.
   */
  protected function buildTableRow(array $mapping, array $form, FormStateInterface $form_state) {
    $migration = $this->entity;
    $row['#attributes']['class'][] = 'draggable';

    $destination_label = isset($mapping['#destination']) ?
      $mapping['#destination']->getLabel() : Html::escape($mapping['#destination_key']);
    $row['destination'] = [
      '#markup' => $destination_label,
    ];

    $row['source'] = [
      '#markup' => Xss::filterAdmin($mapping['#source']),
    ];

    $row['summary'] = [
      '#markup' => '@todo',
    ];

    $row['unique'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unique'),
      '#title_display' => 'invisible',
      '#default_value' => FALSE,
      '#disabled' => TRUE,
    ];

    $row['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight'),
      '#title_display' => 'invisible',
      '#default_value' => $mapping['#weight'],
      '#attributes' => [
        'class' => ['table-sort-weight'],
      ],
    ];

    $operations['edit'] = [
      'title' => $this->t('Edit'),
      'url' => new Url(
        'entity.migration.mapping.edit_form',
        [
          'migration' => $migration->id(),
          'key' => $mapping['#destination_key'],
        ]
      ),
    ];
    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'url' => new Url(
        'entity.migration.mapping.delete_form',
        [
          'migration' => $migration->id(),
          'key' => $mapping['#destination_key'],
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
    $mappings_original = $this->getMappings();

    // Get the sorted mappings and sort them by weight.
    $mappings = $form_state->getValue('mappings') ?: [];
    uasort($mappings, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

    // Make sure the reordered mapping keys match the existing mapping keys.
    if (array_diff_key($mappings, $mappings_original)) {
      $form_state->setError($form['mappings'], $this->t('The mappings have been altered. Please try again'));
    }

    if ($form_state->hasAnyErrors()) {
      return;
    }

    // Rebuild migration process to reflect new order.
    $process_sorted = [];
    foreach ($mappings as $destination_key => $table_mapping) {
      // Validate missing mapping.
      if (!isset($mappings_original[$destination_key])) {
        $form_state->setError($form['mapping'][$destination_key],
          $this->t('Mapping %destination_key does not exist.', ['%destination_key' => $destination_key]));
        continue;
      }

      $process_sorted[$destination_key] = $mappings_original[$destination_key]['#process_lines'];
    }
    // Set the process value so it can be used to update the migration entity
    //@see copyFormValuesToEntity.
    $form_state->setValue('mapping_process', $process_sorted);

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $values =& $form_state->getValues();

    // Write process back to migration entity.
    $entity->set('process', $values['process']);
  }

}
