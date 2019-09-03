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
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, PluginManagerInterface $process_plugin_manager, FieldTypePluginManagerInterface $field_type_manager, MigrateFormPluginFactory $form_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migration = $migration;
    $this->fieldTypeManager = $field_type_manager;
    $this->processPluginManager = $process_plugin_manager;
    $this->formFactory = $form_factory;

    // Set some properties.
    $this->destinationField = $this->configuration['#destination']['#field'];
    $this->destinationKey = $this->configuration['#destination']['key'];
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
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
      $container->get('feeds_migrate.migrate_form_plugin_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [];
    $default_config = [
      'source' => '',
      'is_unique' => FALSE,
      'process' => [],
    ];

    if (isset($this->destinationField) && $this->destinationField instanceof FieldDefinitionInterface) {
      $field_properties = $this->getFieldProperties($this->destinationField);
      foreach ($field_properties as $property => $info) {
        $destination_key = implode('/', [$this->destinationKey, $property]);
        $config[$destination_key] = $default_config;
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
  public function getConfiguration() {
    return $this->configuration;
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
  public function getKey(array $mapping) {
    return $mapping['#destination']['key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(array $mapping) {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $mapping_field */
    $mapping_field = $mapping['#destination']['field'] ?? FALSE;

    return ($mapping_field) ? $mapping_field->getLabel() : $this->getKey($mapping);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(array $mapping, $property = NULL) {
    if ($property) {
      $process = $mapping['#properties'][$property]['#process'] ?? [];
    }
    else {
      $process = $mapping['#process'] ?? [];
    }

    return !empty($process) ? Yaml::encode($process) : '';
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
          '#default_value' => $mapping['#properties'][$property]['source'] ?? '',
        ];

        $checked = array_key_exists($this->configuration['source'], $this->migration->source["ids"]);
        $form['properties'][$property]['is_unique'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Unique Field'),
          '#default_value' => $checked,
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

      $checked = array_key_exists($mapping['source'], $this->migration->source["ids"]);
      $form['is_unique'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Unique Field'),
        '#default_value' => $checked,
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
        $this->t('Plugin ID'),
        $this->t('Label'),
        $this->t('Configuration'),
        $this->t('Weight'),
        $this->t('Remove'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
      '#empty' => $this->t('No process plugins have been added yet.'),
      '#sticky' => TRUE,

    ];

    // Selector for adding new process plugin instances.
    $form['process']['add'] = [
      '#type' => 'select',
      '#options' => $this->getProcessPlugins(),
      '#empty_option' => $this->t('- Select a process plugin -'),
      '#default_value' => [],
      '#ajax' => [
        'event' => 'change',
        'method' => 'replace',
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => $ajax_id,
      ],
    ];

    // Create the plugin configuration form for the plugin that has just been
    // selected.
    // @todo load config forms for existing plugins as well.
    if ($property) {
      $plugin_id = $form_state->getValue(['properties', $property, 'process', 'add']);
    }
    else {
      $plugin_id = $form_state->getValue(['process', 'add']);
    }
    $plugin = $this->preparePlugin($plugin_id);
    if ($plugin) {
      $delta = 1;
      $form['process']['plugins'][$delta] = $this->buildProcessRow($form, $form_state, $plugin, $delta);
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
   * @param \Drupal\migrate\Plugin\MigrateProcessInterface $plugin
   *   The migrate process plugin.
   * @param int $delta
   *   The index number of the process plugin.
   *
   * @return array
   *   The built table row.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function buildProcessRow(array $form, FormStateInterface $form_state, MigrateProcessInterface $plugin, $delta) {
    $ajax_delta = -1;
    $plugin_id = $plugin->getPluginId();
    $plugin_form_type = $this->getPluginDefinition()['form_type'] ?? MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;

    $row = ['#attributes' => ['class' => ['draggable', 'tabledrag-leaf']]];

    $row['plugin_id'] = [
      '#type' => 'hidden',
      '#default_value' => $plugin->getPluginId(),
    ];
    $row['label'] = [
      '#type' => 'textfield',
      '#default_value' => $plugin->getPluginDefinition()['label'],
    ];
    $row['configuration'] = [];

    if ($this->formFactory->hasForm($plugin, $plugin_form_type)) {
      $config_form_state = SubformState::createForSubform($row['configuration'], $form, $form_state);
      $config_form = $this->formFactory->createInstance($plugin, 'process', 'configuration', $this->migration);
      $row['configuration'] += $config_form->buildConfigurationForm([], $config_form_state);
    }

    $row['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight for @process_name', [
        '@process_name' => $plugin_id,
      ]),
      '#title_display' => 'invisible',
      '#default_value' => $plugin_id,
      '#attributes' => [
        'class' => [
          'table-sort-weight',
        ],
      ],
    ];

    // @todo allow to remove a row. This is copied from
    // \Drupal\feeds\Form\MappingForm::buildRow().
    $default_button = [
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'feeds-migrate-process-ajax-wrapper',
        'effect' => 'fade',
        'progress' => 'none',
      ],
      '#delta' => $delta,
    ];
    if ($delta != $ajax_delta) {
      $row['remove'] = $default_button + [
        '#title' => $this->t('Remove'),
        '#type' => 'checkbox',
        '#default_value' => FALSE,
        '#title_display' => 'invisible',
        '#parents' => ['remove_mappings', $delta],
        '#remove' => TRUE,
      ];
    }
    else {
      $row['remove']['#markup'] = '';
    }

    return $row;
  }

  /**
   * Prepares the process plugin.
   *
   * @param string $plugin_id
   *   The id of the process plugin.
   *
   * @return \Drupal\migrate\Plugin\MigrateProcessInterface|null
   *   The process plugin instance or null in case the process plugin could not
   *   be instantiated.
   */
  protected function preparePlugin($plugin_id = NULL) {
    if (empty($plugin_id)) {
      return NULL;
    }

    try {
      return $this->processPluginManager->createInstance($plugin_id);
    }
    catch (PluginException $e) {
      $this->messenger()->addError($this->t('The specified plugin is invalid.'));
    }
  }

  /**
   * Ajax callback for the process plugin table.
   *
   * @return array
   *   The process plugin table.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
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
      foreach ($values['properties'] as $property => $info) {
        $destination_key = implode('/', [$this->destinationKey, $property]);
        $this->configuration[$destination_key]['source'] = $info['source'];
        $this->configuration[$destination_key]['is_unique'] = $info['is_unique'];

        $process_plugins = $info['process']['plugins'];
        foreach ($process_plugins as $delta => $plugin_info) {
          // Load migrate process plugin.
          $plugin_id = $plugin_info['plugin_id'];
          $plugin = $this->preparePlugin($plugin_id);

          // Find the plugin's form.
          $form_plugin = $this->formFactory->createInstance($plugin, 'process', 'configuration', $this->migration);
          $subform = &$form['properties'][$property]['process']['plugins'][$delta]['configuration'];
          $subform_state = SubformState::createForSubform($subform, $form, $form_state);
          // Have the plugin save its configuration.
          $form_plugin->submitConfigurationForm($subform, $subform_state);

          // Retrieve the plugin configuration and save on the migration entity.
          $plugin_configuration = $form_plugin->getConfiguration();
          $this->configuration[$destination_key]['process'][] = $plugin_configuration;
        }
      }
    }
    else {
      $destination_key = $this->destinationKey;
      $this->configuration[$destination_key]['source'] = $values['source'];
      $this->configuration[$destination_key]['is_unique'] = $values['is_unique'];
      // @todo do the same for process plugins.
    }
  }

  /**
   * Retrieve all field properties that are not calculated.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
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
      // todo log error.
    }

    return $field_properties;
  }

  /**
   * Returns a list of migrate process plugins with a configuration form.
   */
  protected function getProcessPlugins() {
    $plugins = [];
    foreach ($this->processPluginManager->getDefinitions() as $id => $definition) {
      // Only include process plugins which have a configuration form.
      if (isset($definition['feeds_migrate']['form']['configuration'])) {
        $plugins[$id] = isset($definition['label']) ? $definition['label'] : $id;
      }
    }

    return $plugins;
  }

}
