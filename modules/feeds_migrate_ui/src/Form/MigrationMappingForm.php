<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
      $rows[$target] = $this->buildFormRow($mapping, $form, $form_state);
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

    $header['source'] = [
      'data' => $this->t('Source'),
    ];

    $header['destination'] = [
      'data' => $this->t('Destination'),
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
  protected function buildFormRow(array $mapping, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\Migration $migration */
    $migration = $this->entity;
    $target_field = $this->getMappingTarget($mapping['#target']);
    $target_field_name = $target_field->getName();
    $target_field_label = $target_field->getLabel();

    $row['#attributes']['class'][] = 'draggable';

    $row['source'] = [
      '#markup' => $mapping['#source'],
    ];

    $row['target'] = [
      '#markup' => $target_field_label,
    ];

    $row['summary'] = [
      '#markup' => $target_field_label,
    ];

    // TODO add conditional logic around this checkbox
    $row['unique'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unique'),
      '#title_display' => 'invisible',
      '#default_value' => FALSE,
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
          'key' => $target_field_name,
        ]
      ),
    ];
    $operations['delete'] = [
      'title' => $this->t('Delete'),
      'url' => new Url(
        'entity.migration.mapping.delete_form',
        [
          'migration' => $migration->id(),
          'key' => $target_field_name,
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
   * Find the entity type the migration is importing into.
   *
   * @return string
   *   Machine name of the entity type eg 'node'.
   */
  protected function getEntityTypeFromMigration() {
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
    if (!empty($this->entity->source['constants']['bundle'])) {
      return $this->entity->source['constants']['bundle'];
    }
  }

  /****************************************************************************/
  // Mapping handling. @todo move to migration entity
  /****************************************************************************/

  /**
   * Gets the initialized mapping plubin
   *
   * @return \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getMappingPlugin() {
    $field = $this->getMappingTarget($this->mappingKey);

    /** @var \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldInterface $plugin */
    $plugin = $this->fieldProcessorManager->getFieldPlugin($field, $this->migration);

    return $plugin;
  }

  /**
   * Get all mapping destinations.
   *
   * @return FieldDefinitionInterface[]
   *  A list of mapping destination objects.
   */
  protected function getMappingTargets() {
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $entity_storage */
    $entity_storage = $this->entityTypeManager->getStorage($this->getEntityTypeFromMigration());
    /** @var \Drupal\Core\Entity\ContentEntityType $entity_type */
    $entity_type = $entity_storage->getEntityType();

    return $this->fieldManager->getFieldDefinitions($entity_type->id(), $this->getEntityBundleFromMigration());
  }

  /**
   * Get a single mapping target identified by its name.
   *
   * @param $name
   *  The machine name of the mapping target to return.
   * @return string|FieldDefinitionInterface
   */
  protected function getMappingTarget($name) {
    $mapping_targets = $this->getMappingTargets();

    return $mapping_targets[$name];
  }

  /**
   * Returns a list of all mapping target options, keyed by field name.
   */
  protected function getMappingTargetOptions() {
    $mapping_target_options = [];

    /** @var FieldDefinitionInterface[] $fields */
    $mapping_targets = $this->getMappingTargets();
    foreach ($mapping_targets as $field_name => $field) {
      $mapping_target_options[$field->getName()] = $field->getLabel();
    }

    return $mapping_target_options;
  }

  /**
   * Get migration mappings as an associative array of sortable elements.
   *
   * @todo parse yaml into usable mapping array
   * This should be on the migration entity itself
   *
   * @return array
   *   An associative array of sortable elements.
   */
  protected function getSortableMappings() {
    $mappings = $this->entity->process;

    // TODO iterate over mappings and decorate each mapping with
    // #source, #target, #weight, #unique...

    return [
      'title' => [
        '#source' => 'title',
        '#target' => 'title',
        '#weight' => 0,
      ]
    ];
  }

}
