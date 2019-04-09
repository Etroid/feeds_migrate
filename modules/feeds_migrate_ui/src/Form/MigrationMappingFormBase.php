<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Url;
use Drupal\feeds_migrate\Annotation\MigrateForm;
use Drupal\feeds_migrate\MigrationEntityHelperManager;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\feeds_migrate\Plugin\PluginFormFactory;
use Drupal\feeds_migrate\MappingFieldFormManager;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form for migration mapping configuration.
 *
 * @package Drupal\feeds_migrate\Form
 *
 * @todo consider moving this UX into migrate_tools module to allow editors
 * to create simple migrations directly from the admin interface
 */
class MigrationMappingFormBase extends EntityForm {

  const CUSTOM_DESTINATION_KEY = '_custom';

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
   * The form factory.
   *
   * @var \Drupal\feeds_migrate\Plugin\PluginFormFactory
   */
  protected $formFactory;

  /**
   * Plugin manager for migration mapping plugins.
   *
   * @var \Drupal\feeds_migrate\MappingFieldFormManager
   */
  protected $mappingFieldManager;

  /**
   * Manager for entity types.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Manager for entity fields.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Manager for entity bundles.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleManager;

  /**
   * The key of the destination field.
   *
   * @var string
   */
  protected $key;

  /**
   * Get the normalized process pipeline configuration describing the process
   * plugins, keyed by the destination field.
   *
   * @var array
   */
  protected $mapping;

  /**
   * Get whether the field is a unique field used for migration IDs.
   *
   * @var bool
   */
  protected $unique;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('feeds_migrate.migrate_form_plugin_factory'),
      $container->get('plugin.manager.feeds_migrate.mapping_field_form'),
      $container->get('entity_field.manager'),
      $container->get('feeds_migrate.migration_entity_helper')
    );
  }

  /**
   * MigrationMappingFormBase constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory $form_factory
   * @param \Drupal\feeds_migrate\MappingFieldFormManager $mapping_field_manager
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   * @param \Drupal\feeds_migrate\MigrationEntityHelperManager $migration_entity_helper_manager
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, MigrateFormPluginFactory $form_factory, MappingFieldFormManager $mapping_field_manager, EntityFieldManager $field_manager, MigrationEntityHelperManager $migration_entity_helper_manager) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->formFactory = $form_factory;
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
  public function buildForm(array $form, FormStateInterface $form_state, MigrationInterface $migration = NULL, string $key = NULL) {
    // Support AJAX callback.
    $form['#tree'] = FALSE;
    $form['#parents'] = [];
    $form['#prefix'] = '<div id="feeds-migration-mapping-ajax-wrapper">';
    $form['#suffix'] = '</div>';

    // General mapping settings.
    $form['general'] = [
      '#title' => $this->t('General'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => FALSE,
    ];

    // Retrieve a list of mapping field destinations.
    $options = $this->getMappingOptions();
    asort($options);
    // Allow custom destination keys.
    $options[self::CUSTOM_DESTINATION_KEY] = $this->t('Other...');

    $form['general']['destination_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination Field'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a destination -'),
      '#default_value' => ($this->key && !key_exists($this->key, $options)) ? self::CUSTOM_DESTINATION_KEY : $this->key,
      '#disabled' => ($this->operation === 'mapping-edit'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'event' => 'change',
        'wrapper' => 'feeds-migration-mapping-ajax-wrapper',
        'effect' => 'fade',
        'progress' => 'throbber',
      ],
    ];

    $form['general']['destination_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination key'),
      '#default_value' => $this->key,
      '#disabled' => ($this->operation === 'mapping-edit'),
      '#states' => [
        'required' => [
          ':input[name="destination_field"]' => ['value' => self::CUSTOM_DESTINATION_KEY],
        ],
        'visible' => [
          ':input[name="destination_field"]' => ['value' => self::CUSTOM_DESTINATION_KEY],
        ],
      ],
    ];

    // Mapping Field Plugin settings.
    if ($this->key) {
      // Field specific mapping settings.
      $form['mapping'] = [
        '#parents' => ['mapping'],
        '#type' => 'container',
        '#tree' => TRUE,
        $this->key => [
          '#parents' => ['mapping', $this->key],
        ],
      ];

      /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
      $migration = $this->entity;
      $plugin = $this->mappingFieldManager->getMappingFieldInstance($this->mapping, $migration);
      $plugin_form_state = SubformState::createForSubform($form['mapping'][$this->key], $form, $form_state);

      if ($plugin) {
        $form['mapping'][$this->key] = $plugin->buildConfigurationForm([], $plugin_form_state);
      }
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Get the basic actions from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Change delete url.
    if ($this->operation === 'mapping-edit') {
      $actions['delete']['#url'] = new Url(
        'entity.migration.mapping.delete_form',
        [
          'migration' => $this->entity->id(),
          'key' => rawurlencode($this->key),
        ]
      );
    }
    else {
      unset($actions['delete']);
    }

    // Return the result.
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    // Mapping Field Plugin validation.
    if ($this->key) {
      $plugin = $this->mappingFieldManager->getMappingFieldInstance($this->mapping, $migration);
      $plugin_form_state = SubformState::createForSubform($form['mapping'][$this->key], $form, $form_state);

      if ($plugin) {
        $plugin->validateConfigurationForm($form, $plugin_form_state);
      }

      // Get plugin validation errors.
      $plugin_errors = $plugin_form_state->getErrors();
      foreach ($plugin_errors as $plugin_error) {
        $form_state->setErrorByName(NULL, $plugin_error);
      }

      // Stop validation if the element's properties has any errors.
      if ($plugin_form_state->hasAnyErrors()) {
        return;
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    // Mapping Field Plugin validation.
    if ($this->key) {
      $plugin = $this->mappingFieldManager->getMappingFieldInstance($this->mapping, $migration);
      $plugin_form_state = SubformState::createForSubform($form['mapping'][$this->key], $form, $form_state);

      if ($plugin) {
        $plugin->submitConfigurationForm($form, $plugin_form_state);
        // Copy mapping values from plugin.
        $mapping = $plugin->getConfigurationFormMapping($form, $plugin_form_state);
        $mapping['#destination']['key'] = $this->key;

        $this->mapping = $mapping;

        // Copy unique value from plugin.
        $unique = $plugin->isUnique($form, $plugin_form_state);
        $this->unique = $unique;
      }
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Add the mapping to the process section.
    $mapping = $this->mapping;
    $process = $entity->get('process') ?: [];
    $process = array_merge($process, $this->migrationEntityHelper()
      ->processMapping($mapping));

    $entity->set('process', $process);

    // Add the unique values to the source section.
    $source = $entity->get('source');
    $ids = $source['ids'] ?: [];
    if ($this->unique) {
      // Is unique, make sure it's there.
      if (!array_key_exists($mapping["source"], $ids)) {
        // Doesn't exist, so add it.
        $ids[$mapping["source"]] = ['type' => 'string'];
      }
    }
    else {
      // Is not unique, make sure it's not there.
      if (array_key_exists($mapping["source"], $ids)) {
        // Doesn't exist, so add it.
        unset($ids[$mapping["source"]]);
      }
    }
    $source['ids'] = $ids;

    // Add the fields to the source section.
    //    fields:
    //      -
    //      name: title
    //      label: Title
    //      selector: title

    $fields = $source['fields'] ?: [];
    $foundField = FALSE;
    foreach ($fields as $field) {
      if ($field['name'] == $mapping['source']) {
        $foundField = TRUE;
        break;
      }
    }
    if (!$foundField) {
      $newField = [
        'name' => $mapping['source'],
        'label' => $mapping['source'],
        'selector' => $mapping['source'],
      ];
      $fields[] = $newField;
      $source['fields'] = $fields;
      $entity->set('source', $source);

    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->getEntity();

    // Save the migration.
    $status = $migration->save();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing mapping.
      $this->messenger()->addMessage($this->t('Migration mapping for field 
        @destination_field has been updated.', [
        '@destination_field' => $this->migrationEntityHelper()
          ->getMappingFieldLabel($this->key),
      ]));
    }
    else {
      // If we created a new mapping.
      $this->messenger()->addMessage($this->t('Migration mapping for field
        @destination_field has been added.', [
        '@destination_field' => $this->migrationEntityHelper()
          ->getMappingFieldLabel($this->key),
      ]));
    }

    // Redirect the user to the mapping edit form.
    $form_state->setRedirect('entity.migration.mapping.list', [
      'migration' => $migration->id(),
      'key' => $this->key,
    ]);
  }

  /**
   * Callback for ajax requests.
   *
   * @return array
   *   The form element to return.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * ----------- .*/

  /**
   * Returns a list of all mapping destination options, keyed by field name.
   */
  protected function getMappingOptions() {
    $options = [];

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = $this->fieldManager->getFieldDefinitions($this->migrationEntityHelper()
      ->getEntityTypeIdFromDestination(), $this->migrationEntityHelper()
      ->getEntityBundleFromDestination());
    foreach ($fields as $field_name => $field) {
      $options[$field->getName()] = $field->getLabel();
    }

    return $options;
  }

}
