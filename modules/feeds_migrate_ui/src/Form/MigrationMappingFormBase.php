<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Url;
use Drupal\feeds_migrate\MappingFieldFormManager;
use Drupal\feeds_migrate\MigrationHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form for migration mapping configuration.
 *
 * @package Drupal\feeds_migrate\Form
 */
class MigrationMappingFormBase extends EntityForm {

  const CUSTOM_DESTINATION_KEY = '_custom';

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
   * Helper service for migration entity.
   *
   * @var \Drupal\feeds_migrate\MigrationHelper
   */
  protected $migrationHelper;

  /**
   * The destination field definition.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $destinationField;

  /**
   * The destination key.
   *
   * This is filled out when we are not migrating into a standard Drupal field
   * instance (e.g. table column name, virtual field etc...)
   *
   * @var string
   */
  protected $destinationKey;

  /**
   * Field mapping for this migration.
   *
   * @var array
   */
  protected $mapping = [];

  /**
   * Creates a new MigrationMappingFormBase.
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
   * Gets the label for the destination field - if any.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The label of the field, or key if custom property.
   */
  public function getDestinationFieldLabel() {
    // Get field label.
    if (isset($this->destinationField)) {
      $label = $this->destinationField->getLabel();
    }
    else {
      $label = $this->destinationKey;
    }

    return $label;
  }

  /**
   * Sets the mapping for this field.
   *
   * @param array $mapping
   *   The field mapping for this migration.
   */
  public function setMapping(array $mapping) {
    $this->destinationKey = $mapping['destination']['key'];
    $this->destinationField = $mapping['destination']['field'];
    $this->mapping = $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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
    $options = $this->getMappingDestinationOptions();
    asort($options);
    // Allow custom destination keys.
    $options[self::CUSTOM_DESTINATION_KEY] = $this->t('Other...');

    // Determine default value.
    $default_value = NULL;
    if (isset($this->destinationKey)) {
      $default_value = array_key_exists($this->destinationKey, $options) ?
        $this->destinationKey : self::CUSTOM_DESTINATION_KEY;
    }

    $form['general']['destination_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination Field'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a destination -'),
      '#default_value' => $default_value,
      '#disabled' => ($this->operation === 'mapping-edit'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxCallback'],
        'event' => 'change',
        'wrapper' => 'feeds-migration-mapping-ajax-wrapper',
        'effect' => 'fade',
        'progress' => 'throbber',
      ],
    ];

    $form['general']['destination_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Destination key'),
      '#default_value' => $this->destinationKey,
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
    if ($this->destinationKey) {
      // Field specific mapping settings.
      $form['mapping'] = [
        '#parents' => ['mapping'],
        '#type' => 'container',
        '#tree' => TRUE,
        $this->destinationKey => [
          '#parents' => ['mapping', $this->destinationKey],
        ],
      ];

      /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
      $migration = $this->entity;
      $destination_field = $this->mapping['destination']['field'];
      $plugin_id = $this->mappingFieldManager->getPluginIdFromField($destination_field);
      $plugin = $this->mappingFieldManager->createInstance($plugin_id, $this->mapping, $migration);
      $plugin_form_state = SubformState::createForSubform($form['mapping'][$this->destinationKey], $form, $form_state);

      if ($plugin) {
        $form['mapping'][$this->destinationKey] = $plugin->buildConfigurationForm([], $plugin_form_state);
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
    if ($this->operation === 'mapping-edit' && isset($this->destinationKey)) {
      $actions['delete']['#url'] = new Url(
        'entity.migration.mapping.delete_form',
        [
          'migration' => $this->entity->id(),
          'destination' => rawurlencode($this->destinationKey),
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
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->getEntity();

    // Save the migration.
    $status = $migration->save();

    if ($status == SAVED_UPDATED) {
      // If we edited an existing mapping.
      $this->messenger()->addMessage($this->t('Migration mapping for field 
        @destination_field has been updated.', [
          '@destination_field' => $this->getDestinationFieldLabel(),
        ]
      ));
    }
    else {
      // If we created a new mapping.
      $this->messenger()->addMessage($this->t('Migration mapping for field
        @destination_field has been added.', [
          '@destination_field' => $this->getDestinationFieldLabel(),
        ]
      ));
    }

    // Redirect the user to the mapping edit form.
    $form_state->setRedirect('entity.migration.mapping.list', [
      'migration' => $migration->id(),
      'destination' => $this->destinationKey,
    ]);
  }

  /****************************************************************************/
  // Callbacks.
  /****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_plugin = $this->loadMappingFieldFormPlugin();
    if ($this->destinationKey && $form_plugin) {
      $subform = &$form['mapping'][$this->destinationKey];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $form_plugin->validateConfigurationForm($subform, $subform_state);

      // Get plugin validation errors.
      $plugin_errors = $subform_state->getErrors();
      foreach ($plugin_errors as $plugin_error) {
        $form_state->setErrorByName(NULL, $plugin_error);
      }

      // Stop validation if the element's properties has any errors.
      if ($subform_state->hasAnyErrors()) {
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

    // Get migration configuration(s).
    $source_config = $migration->get('source') ?: [];
    $process_config = $migration->get('process') ?: [];

    $form_plugin = $this->loadMappingFieldFormPlugin();
    if ($this->destinationKey && $form_plugin) {
      $subform = &$form['mapping'][$this->destinationKey];
      $subform_state = SubformState::createForSubform($subform, $form, $form_state);
      $form_plugin->submitConfigurationForm($subform, $subform_state);

      // Retrieve the mapping configuration and save on the migration entity.
      $plugin_configuration = $form_plugin->getConfiguration();

      foreach ($plugin_configuration as $destination => $mapping) {
        if (!isset($mapping['source'])) {
          continue;
        }

        // We always start with the get plugin to obtain the source value.
        $source = $mapping['source'];
        $process_config[$destination][] = [
          'plugin' => 'get',
          'source' => $source,
        ];
        // Now merge in all process plugins.
        $process_config[$destination] = array_merge($process_config[$destination], $mapping['process']);

        // Save off field properties in source.
        if (array_search($source, array_column($source_config['fields'], 'name')) === FALSE) {
          $source_config['fields'][] = [
            'name' => $source,
            'label' => $source,
            'selector' => $source,
          ];
        }

        // Handle unique field values.
        if ($mapping['is_unique']) {
          $source_config['ids'][$source] = ['type' => 'string'];
        }
        else {
          unset($source_config['ids'][$source]);
        }
      }
    }

    $migration->set('source', $source_config);
    $migration->set('process', $process_config);
  }

  /**
   * The form ajax callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form element to return.
   */
  public static function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /****************************************************************************/
  // Helper functions.
  /****************************************************************************/

  /**
   * Returns a list of all mapping destination options, keyed by field name.
   */
  protected function getMappingDestinationOptions() {
    $options = [];
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = $this->migrationHelper->getDestinationFields($migration);
    foreach ($fields as $field_name => $field) {
      $options[$field->getName()] = $field->getLabel();
    }

    return $options;
  }

  /**
   * Load mapping field form plugin.
   *
   * @return \Drupal\feeds_migrate\MappingFieldFormInterface
   *   Mapping field form plugin instance.
   */
  protected function loadMappingFieldFormPlugin() {
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;
    $mapping = $this->mapping;
    $destination_field = $mapping['destination']['field'] ?? NULL;
    $plugin_id = $this->mappingFieldManager->getPluginIdFromField($destination_field);

    /** @var \Drupal\feeds_migrate\MappingFieldFormInterface $plugin */
    $form_plugin = $this->mappingFieldManager->createInstance($plugin_id, $mapping, $migration);
    return $form_plugin;
  }

}
