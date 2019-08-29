<?php

namespace Drupal\feeds_migrate;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginBase;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Drupal\migrate\Plugin\MigrateProcessInterface;
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

    $this->buildProcessPluginsConfigurationForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildProcessPluginsConfigurationForm(array &$form, FormStateInterface $form_state) {
    // The process plugin table, with config forms for each instance.
    $form['process'] = [
      '#tree' => TRUE,
      '#type' => 'table',
      '#header' => [
        $this->t('Label'),
        $this->t('Settings'),
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
      '#prefix' => '<div id="feeds-migrate-process-ajax-wrapper">',
      '#suffix' => '</div>',
    ];

    // Selector for adding new process plugin instances.
    $form['add'] = [
      '#type' => 'select',
      '#options' => $this->getProcessPlugins(),
      '#empty_option' => $this->t('- Select a process plugin -'),
      '#default_value' => [],
      '#ajax' => [
        'event' => 'change',
        'method' => 'replace',
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => 'feeds-migrate-process-ajax-wrapper',
      ],
    ];

    // Create the plugin configuration form for the plugin that has just been
    // selected.
    // @todo load config forms for existing plugins as well.
    $plugin_id = $form_state->getValue('add');
    $plugin = $this->preparePlugin($plugin_id);
    if ($plugin) {
      $delta = 1;
      $form['process'][$delta] = $this->buildProcessRow($form, $form_state, $plugin, $delta);
    }
  }

  /**
   * Builds a single process row.
   *
   * @param array $form
   *   The complete mapping form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the complete form.
   * @param \Drupal\migrate\Plugin\MigrateProcessInterface $plugin
   *   The process plugin.
   * @param int $delta
   *   The index number of the process plugin.
   */
  protected function buildProcessRow(array $form, FormStateInterface $form_state, MigrateProcessInterface $plugin, $delta) {
    $ajax_delta = -1;
    $plugin_id = $plugin->getPluginId();
    $plugin_form_type = $this->getPluginDefinition()['form_type'] ?? MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;

    $row = ['#attributes' => ['class' => ['draggable', 'tabledrag-leaf']]];

    $row['label'] = [
      '#type' => 'textfield',
      '#default_value' => $plugin->getPluginDefinition()['label'],
    ];
    $row['settings'] = [];

    if ($this->formFactory->hasForm($plugin, $plugin_form_type)) {
      $config_form_state = SubformState::createForSubform($row['settings'], $form, $form_state);
      $config_form = $this->formFactory->createInstance($plugin, 'process', 'configuration', $this->migration);
      $row['settings'] += $config_form->buildConfigurationForm([], $config_form_state);
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
    return $form['mapping'][$this->configuration['source']]['process'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $mapping = $this->getConfigurationFormMapping($form, $form_state);

    // @todo iterate over all process plugins and execute
    //       validateConfigurationForm on them.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $mapping = $this->getConfigurationFormMapping($form, $form_state);

    $unique = $this->isUnique($form, $form_state);

    // @todo iterate over all process plugins and execute
    //       submitConfigurationForm on them.
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFormMapping(array &$form, FormStateInterface $form_state) {
    $mapping = [
      'plugin' => 'get',
      'source' => $form_state->getValue('source', NULL),
      '#process' => [], // @todo get process lines from each plugin (i.e. tamper)
    ];

    return $mapping;
  }

  /**
   * {@inheritdoc}
   */
  public function isUnique(array &$form, FormStateInterface $form_state) {
    $unique = $form_state->getValue('is_unique');
    return $unique;
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
