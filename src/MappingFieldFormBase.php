<?php

namespace Drupal\feeds_migrate;

use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FeedsMigrateUiFieldProcessorBase.
 *
 * @package Drupal\feeds_migrate
 */
abstract class MappingFieldFormBase extends PluginBase implements MappingFieldFormInterface {

  /**
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   */
  protected $migration;

  /**
   * @var \Drupal\migrate\Plugin\MigratePluginManagerInterface
   */
  protected $migrateProcessManager;

  /**
   * Field Type Manager Service.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManager
   */
  protected $fieldTypeManager;

  /**
   * Constructs an entity destination plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldTypePluginManager $field_type_manager
   *   The plugin manager for handling field types.
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $migrate_process_manager
   *   The migration process manager.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FieldTypePluginManager $field_type_manager, MigratePluginManagerInterface $migrate_process_manager, MigrationInterface $migration = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fieldTypeManager = $field_type_manager;
    $this->migrateProcessManager = $migrate_process_manager;
    $this->migration = $migration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.field.field_type'),
      $container->get('plugin.manager.migrate.process'),
      $migration
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
      '#header' => [],
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
  }

  /**
   * Callback for ajax requests.
   *
   * @return array
   *   The form element to return.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    // TODO adds new row to table with selected process plugin configuration form.
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
    foreach ($this->migrateProcessManager->getDefinitions() as $id => $definition) {
      // Only include process plugins which have a configuration form.
      if (isset($definition['feeds_migrate']['form']['configuration'])) {
        $plugins[$id] = isset($definition['label']) ? $definition['label'] : $id;
      }
    }

    return $plugins;
  }

}
