<?php

namespace Drupal\feeds_migrate;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FeedsMigrateUiFieldProcessorBase.
 *
 * @package Drupal\feeds_migrate
 */
abstract class MappingFieldFormBase extends PluginBase implements MappingFieldFormInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The migration.
   *
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   */
  protected $migration;

  /**
   * The Migrate process plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $processPluginManager;

  /**
   * Field Type Manager Service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected $fieldTypeManager;

  /**
   * The form factory.
   *
   * @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory
   */
  protected $formFactory;

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
   * Constructs a mapping field form base.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $process_plugin_manager
   *   The plugin manager for migrate process plugins.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The manager for field types.
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory $form_factory
   *   The factory for feeds migrate form plugins.
   * @param \Drupal\feeds_migrate\MigrationHelper $migration_helper
   *   Helper service for migration entity.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, PluginManagerInterface $process_plugin_manager, FieldTypePluginManagerInterface $field_type_manager, MigrateFormPluginFactory $form_factory, MigrationHelper $migration_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
    $this->fieldTypeManager = $field_type_manager;
    $this->processPluginManager = $process_plugin_manager;
    $this->formFactory = $form_factory;
    $this->migrationHelper = $migration_helper;

    // Set some properties.
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
    $this->destinationField = $this->configuration['destination']['field'] ?? NULL;
    $this->destinationKey = $this->configuration['destination']['key'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('plugin.manager.migrate.process'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('feeds_migrate.migrate_form_plugin_factory'),
      $container->get('feeds_migrate.migration_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [];
    $default_config = [
      'destination' => [
        'key' => '',
        'field' => NULL,
      ],
      'source' => '',
      'is_unique' => FALSE,
      'process' => [],
    ];

    if (isset($this->destinationField) && $this->destinationField instanceof FieldDefinitionInterface) {
      $field_properties = $this->getFieldProperties($this->destinationField);
      foreach ($field_properties as $property => $info) {
        $config[$property] = $default_config;
        $config[$property]['destination']['key'] = implode('/', [$this->destinationKey, $property]);
      }
    }
    else {
      $config[$this->destinationKey] = $default_config;
    }

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration($property = NULL) {
    if (!isset($property)) {
      return $this->configuration;
    }

    return $this->configuration[$property];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel($property = NULL) {
    $label = ($this->destinationField) ? $this->destinationField->getLabel() : $this->destinationKey;

    if ($property) {
      $label .= ' (' . $property . ')';
    }

    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary($property = NULL) {
    $summary = '';
    if ($property) {
      $process = $this->configuration[$property]['process'] ?? [];
    }
    else {
      $process = $this->configuration['process'] ?? [];
    }

    foreach ($process as $info) {
      $plugin_id = $info['plugin'];
      $plugin = $this->loadMigrateFormPlugin($plugin_id, $info);
      $summary .= '<li>' . $plugin->getSummary() . '</li>';
    }

    return [
      '#markup' => $summary,
      '#prefix' => '<pre><ul>',
      '#suffix' => '</ul><pre>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationKey() {
    return $this->configuration['destination']['key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDestinationField() {
    return $this->configuration['destination']['field'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $mapping = $this->configuration;
    $field = $this->destinationField;

    // If the field has one or more properties, iterate over them and render
    // a mapping form.
    if (isset($this->destinationField) && $this->destinationField instanceof FieldDefinitionInterface) {
      /** @var \Drupal\Core\TypedData\TypedDataInterface[] $field_properties */
      $field_properties = $this->getFieldProperties($field);
      foreach ($field_properties as $property => $info) {
        $form['properties'][$property] = [
          '#title' => $this->t('Mapping for field property %property.', ['%property' => $info->getName()]),
          '#type' => 'details',
          '#group' => 'plugin_settings',
          '#open' => TRUE,
        ];

        $form['properties'][$property]['source'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Source'),
          '#default_value' => $mapping[$property]['source'] ?? '',
        ];

        $form['properties'][$property]['is_unique'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Unique Field'),
          '#default_value' => $mapping[$property]['is_unique'],
        ];

        $form['properties'][$property] += $this->buildProcessPluginsConfigurationForm([], $form_state, $property);
      }
    }
    else {
      $form = [
        '#title' => $this->t('Mapping for %field.', ['%field' => $this->getLabel($mapping)]),
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#open' => TRUE,
      ];

      $form['source'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Source'),
        '#default_value' => $mapping['source'],
      ];

      $form['is_unique'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Unique Field'),
        '#default_value' => $mapping['is_unique'],
      ];

      $form += $this->buildProcessPluginsConfigurationForm([], $form_state);
    }

    return $form;
  }

  /**
   * Every field (property) can add one or many migration process plugins to
   * prepare the data before it is stored.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $property
   *   The field property to render the process plugin table for.
   *
   * @return array
   *   The form structure.
   */
  protected function buildProcessPluginsConfigurationForm(array $form, FormStateInterface $form_state, $property = NULL) {
    // Generate a unique HTML id for AJAX callback.
    $ajax_id = implode('-', [
      'feeds-migration-mapping',
      $property,
      'ajax-wrapper',
    ]);
    // Declare AJAX settings for process plugin table.
    $ajax_settings = [
      'event' => 'click',
      'effect' => 'fade',
      'progress' => 'throbber',
      'callback' => [get_called_class(), 'ajaxCallback'],
      'wrapper' => $ajax_id,
    ];
    // Load process plugins from configuration or form state.
    $plugins = $this->loadProcessPlugins($form_state, $property);

    $form['process'] = [
      '#type' => 'container',
      '#prefix' => "<div id='$ajax_id'>",
      '#suffix' => "</div>",
    ];

    // The process plugin table, with config forms for each instance.
    $form['process']['plugins'] = [
      '#tree' => TRUE,
      '#type' => 'table',
      '#header' => [
        $this->t('Plugin'),
        $this->t('Configuration'),
        $this->t('Weight'),
        $this->t('Operations'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
      '#empty' => $this->t('No process plugins have been added yet.'),
      '#default_value' => [],
    ];

    // Selector for adding new process plugin instances.
    $form['process']['add']['plugin'] = [
      '#type' => 'select',
      '#options' => $this->getProcessPlugins(),
      '#empty_option' => $this->t('- Select a process plugin -'),
      '#default_value' => NULL,
    ];

    $form['process']['add']['button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add'),
      '#context' => [
        'action' => 'add',
      ],
      '#limit_validation_errors' => [],
      '#submit' => [__CLASS__ . '::addProcessPluginSubmit'],
      '#ajax' => $ajax_settings,
    ];

    // Build out table.
    foreach ($plugins as $delta => $configuration) {
      $plugin_id = $configuration['plugin'];
      $plugin = $this->loadMigrateFormPlugin($plugin_id, $configuration);

      if ($plugin) {
        $form['process']['plugins'][$delta] = $this->buildProcessRow($form, $form_state, $plugin, $delta, $property);
      }
    }

    return $form;
  }

  /**
   * Builds a single process plugin row.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface $plugin
   *   The migrate process plugin.
   * @param int $delta
   *   The index number of the process plugin.
   * @param string $property
   *   The field property for this mapping.
   *
   * @return array
   *   The built table row.
   */
  protected function buildProcessRow(array $form, FormStateInterface $form_state, MigrateFormPluginInterface $plugin, $delta, $property = NULL) {
    $plugin_id = $plugin->getPluginId();
    $configuration = $plugin->getConfiguration();

    // Generate a unique HTML id for AJAX callback.
    $ajax_id = implode('-', [
      'feeds-migration-mapping',
      $property,
      'ajax-wrapper',
    ]);
    // Declare AJAX settings for process plugin table.
    $ajax_settings = [
      'event' => 'click',
      'effect' => 'fade',
      'progress' => 'throbber',
      'callback' => [get_called_class(), 'ajaxCallback'],
      'wrapper' => $ajax_id,
    ];

    $row = [
      '#attributes' => [
        'class' => ['draggable'],
      ],
      'label' => [
        '#type' => 'label',
        '#title' => $plugin->getPluginDefinition()['title'],
      ],
      'configuration' => [
        '#type' => 'container',
      ],
      'weight' => [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @process_name', [
          '@process_name' => $plugin_id,
        ]),
        '#title_display' => 'invisible',
        '#default_value' => $delta,
        '#attributes' => [
          'class' => [
            'table-sort-weight',
          ],
        ],
      ],
      'operations' => [
        '#type' => 'submit',
        // We need a unique element name so we can reliably use
        // $form_state->getTriggeringElement() in the submit callbacks.
        '#name' => implode(',', ['feeds-migration-mapping-', $property, $delta]),
        '#value' => $this->t('Remove'),
        '#limit_validation_errors' => [],
        '#submit' => [__CLASS__ . '::removeProcessPluginSubmit'],
        '#context' => [
          'action' => 'remove',
          'delta' => $delta,
        ],
        '#ajax' => $ajax_settings,
      ],
    ];

    // Load process form plugin configuration.
    $plugin_form_state = SubformState::createForSubform($row['configuration'], $form, $form_state);
    $row['configuration']['plugin'] = [
      '#type' => 'hidden',
      '#value' => $configuration['plugin'],
    ];
    $row['configuration'] += $plugin->buildConfigurationForm([], $plugin_form_state);

    return $row;
  }

  /****************************************************************************/
  // Callbacks.
  /****************************************************************************/

  /**
   * The form submit callback for adding a new column.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function addProcessPluginSubmit(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $process_form =& NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));
    $plugins = $process_form['plugins'];
    $values = $plugins['#value'];

    // Add new plugin id.
    $values[] = [
      'configuration' => [
        'plugin' => $process_form['add']['plugin']['#value'],
      ],
    ];

    // Update plugin's #value.
    $form_state->setValueForElement($plugins, $values);
    NestedArray::setValue($form_state->getUserInput(), $plugins['#parents'], $values);

    $form_state->setRebuild(TRUE);
  }

  /**
   * The form submit callback for removing a column.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function removeProcessPluginSubmit(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $delta = $button['#context']['delta'];
    $plugins =& NestedArray::getValue($form, array_slice($button['#array_parents'], 0, -2));
    $values = $plugins['#value'];

    // Remove plugin from values.
    unset($values[$delta]);
    // Re-index values.
    $values = array_values($values);

    // Update plugin's #value.
    $form_state->setValueForElement($plugins, $values);
    NestedArray::setValue($form_state->getUserInput(), $plugins['#parents'], $values);

    $form_state->setRebuild(TRUE);
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
    $button = $form_state->getTriggeringElement();
    $action = $button['#context']['action'] ?? NULL;
    $parent_offset = $action === 'remove' ? -3 : -2;

    return NestedArray::getValue($form, array_slice($button['#array_parents'], 0, $parent_offset));
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo iterate over all process plugins and execute
    //       validateConfigurationForm on them.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (isset($values['properties'])) {
      foreach ($values['properties'] as $property => $mapping) {
        $destination_key = $mapping['destination']['key'];
        $this->configuration[$destination_key]['source'] = $mapping['source'];
        $this->configuration[$destination_key]['is_unique'] = $mapping['is_unique'];

        $process_plugins = $mapping['process']['plugins'];
        foreach ($process_plugins as $delta => $info) {
          // Load migrate process plugin.
          $plugin_id = $info['configuration']['plugin'];
          $plugin = $this->loadMigratePlugin($plugin_id);

          // Find the plugin's form.
          $subform = &$form['properties'][$property]['process']['plugins'][$delta]['configuration'];
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          // Have the plugin save its configuration.
          $plugin->submitConfigurationForm($subform, $subform_state);

          // Retrieve the plugin configuration.
          $process_configuration = $plugin->getConfiguration();
          $this->configuration[$destination_key]['process'][] = $process_configuration;
        }
      }
    }
    else {
      $destination_key = $values['destination']['key'];
      $this->configuration[$destination_key]['source'] = $values['source'];
      $this->configuration[$destination_key]['is_unique'] = $values['is_unique'];

      $process_plugins = $values['process']['plugins'];
      foreach ($process_plugins as $delta => $info) {
        // Load migrate process form plugin.
        $plugin_id = $info['configuration']['plugin'];
        $plugin = $this->loadMigratePlugin($plugin_id);

        // Find the plugin's form.
        $subform = &$form['process']['plugins'][$delta]['configuration'];
        $subform_state = SubformState::createForSubform($subform, $form, $form_state);
        // Have the plugin save its configuration.
        $plugin->submitConfigurationForm($subform, $subform_state);

        // Retrieve the plugin configuration.
        $process_configuration = $plugin->getConfiguration();
        $this->configuration[$destination_key]['process'][] = $process_configuration;
      }
    }
  }

  /**
   * Retrieve all field properties that are not calculated.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The field definition to load the properties for.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface[]
   *   An array of property objects implementing the TypedDataInterface, keyed
   *   by property name.
   */
  protected function getFieldProperties(FieldDefinitionInterface $field) {
    $field_properties = [];

    try {
      $item_instance = $this->fieldTypeManager->createInstance($field->getType(), [
        'name' => NULL,
        'parent' => NULL,
        'field_definition' => $field,
      ]);

      $field_properties = $item_instance->getProperties();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not load properties for %field_name.', [
        '%field_name' => $field->getName(),
      ]));
    }

    return $field_properties;
  }

  /****************************************************************************/
  // Helper functions.
  /****************************************************************************/

  /**
   * Returns a list of available process plugins with a configuration form.
   *
   * @return array
   *   List of process plugins, keyed by plugin id.
   */
  protected function getProcessPlugins() {
    $plugins = [];
    foreach ($this->processPluginManager->getDefinitions() as $id => $definition) {
      if (!isset($definition['feeds_migrate']['form']['configuration'])) {
        // Only include process plugins which have a configuration form.
        continue;
      }

      $category = $definition['category'] ?? (string) t('Other');
      $plugins[$category][$id] = $definition['label'] ?? $id;
    }

    // Don't display plugins in categories if there's only one.
    if (count($plugins) === 1) {
      $plugins = reset($plugins);
    }
    else {
      // Sort categories.
      ksort($plugins);
    }

    return $plugins;
  }

  /**
   * Load the configured plugins from form_state or save configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $property
   *   The name of the field property, if any.
   *
   * @return array
   *   List of process plugin form configuration.
   */
  protected function loadProcessPlugins(FormStateInterface $form_state, $property = NULL) {
    $plugins = [];
    $mapping = $property ? $this->configuration[$property] : $this->configuration;
    $form_state_key = array_filter([
      ($property ? 'properties' : ''),
      $property,
      'process',
      'plugins',
    ]);
    $values = $form_state->getValue($form_state_key, $mapping['process']);
    foreach ($values as $delta => $info) {
      $plugins[] = $info['configuration'] ?? $info;
    }

    return $plugins;
  }

  /**
   * Loads the process plugin.
   *
   * @param string $plugin_id
   *   The id of the process plugin.
   * @param array $configuration
   *   The configuration for the process plugin.
   *
   * @return \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface|null
   *   The process plugin instance or null in case the process plugin could not
   *   be instantiated.
   */
  protected function loadMigrateFormPlugin($plugin_id, array $configuration = []) {
    try {
      /** @var \Drupal\migrate\Plugin\MigrateProcessInterface $plugin */
      $plugin = $this->processPluginManager->createInstance($plugin_id, $configuration);

      // Mapping only happens during configuration.
      $operation = MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;
      if (!$this->formFactory->hasForm($plugin, $operation)) {
        $this->messenger()->addError($this->t('Could not find form plugin for %plugin_id', [
          '%plugin_id' => $plugin_id,
        ]));

        return NULL;
      }

      /** @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface $form_plugin */
      $form_plugin = $this->formFactory->createInstance($plugin, $operation, $this->migration, $configuration);

      return $form_plugin;
    }
    catch (PluginException $e) {
      $this->messenger()->addError($this->t('The specified plugin %plugin_id is invalid.', [
        '%plugin_id' => $plugin_id,
      ]));
    }

    return NULL;
  }

}
