<?php

namespace Drupal\feeds_migrate;

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
    $form['process'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Settings'),
        $this->t('Weight'),
      ],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
      '#empty' => $this->t('No process plugins have been added yet.'),
    ];

    // Load available migrate process plugins.
    $plugins = $this->getProcessPlugins();
    $form['add'] = [
      '#type' => 'select',
      '#options' => $plugins,
      '#empty_option' => $this->t('- Select a process plugin -'),
      '#default_value' => [],
      '#ajax' => [
        // TODO implement AJAX
        'wrapper' => '',
        'callback' => [$this, 'ajaxCallback'],
        'event' => 'change',
        'effect' => 'fade',
        'progress' => 'throbber',
      ],
    ];

    foreach ($plugins as $plugin_id => $label) {
      $plugin = $this->processPluginManager->createInstance($plugin_id);
      $plugin_form_type = $this->getPluginDefinition()['form_type'] ?? MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;

      $form['process'][$plugin_id][$plugin_form_type] = [
        '#type' => 'container',
        '#prefix' => '<div id="feeds-migration-plugin-process-' . $plugin_id . '-configuration">',
        '#suffix' => '</div>',
      ];

      if ($plugin && $this->formFactory->hasForm($plugin, $plugin_form_type)) {
        $config_form_state = SubformState::createForSubform($form['process'][$plugin_id][$plugin_form_type], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'process', 'configuration', $this->migration);
        $form['process'][$plugin_id][$plugin_form_type] += $config_form->buildConfigurationForm([], $config_form_state);

        $form['process'][$plugin_id][$plugin_form_type]['weight'] = [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @process_name', [
            '@process_name' => $label,
          ]),
          '#title_display' => 'invisible',
          '#default_value' => $plugin_id,
          '#attributes' => [
            'class' => [
              'table-sort-weight',
            ],
          ],
        ];
      }
    }
  }

  /**
   * Callback for ajax requests.
   *
   * @return array
   *   The form element to return.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();

    return NestedArray::getValue(
      $form, array_splice($trigger['#array_parents'], 0, -1)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $mapping = $this->getConfigurationFormMapping($form, $form_state);

    // Todo: iterate over all process plugins and execute
    //       validateConfigurationForm on them.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $mapping = $this->getConfigurationFormMapping($form, $form_state);

    $unique = $this->isUnique($form, $form_state);

    // Todo: iterate over all process plugins and execute
    //       submitConfigurationForm on them.
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationFormMapping(array &$form, FormStateInterface $form_state) {
    $mapping = [
      'plugin' => 'get',
      'source' => $form_state->getValue('source', NULL),
      '#process' => [], // todo get process lines from each plugin (i.e. tamper)
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
