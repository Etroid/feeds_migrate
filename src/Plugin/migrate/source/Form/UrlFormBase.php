<?php

namespace Drupal\feeds_migrate\Plugin\migrate\source\Form;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base form for the url migrate source plugin.
 */
abstract class UrlFormBase extends SourceFormPluginBase {

  /**
   * Plugin manager for authentication plugins.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $authenticationPluginManager;

  /**
   * Plugin manager for data fetcher plugins.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $dataFetcherPluginManager;

  /**
   * Plugin manager for data parser plugins.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $dataParserPluginManager;

  /**
   * The form factory.
   *
   * @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory
   */
  protected $formFactory;

  /**
   * UrlForm constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $migrate_plugin
   *   The migrate plugin instance this form plugin is for.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $authentication_plugin_manager
   *   The plugin manager for migrate plus authentication plugins.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_fetcher_plugin_manager
   *   The plugin manager for migrate plus data fetcher plugins.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_parser_plugin_manager
   *   The plugin manager for migrate plus data parser plugins.
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory $form_factory
   *   The factory for feeds migrate form plugins.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PluginInspectionInterface $migrate_plugin, MigrationInterface $migration, PluginManagerInterface $authentication_plugin_manager, PluginManagerInterface $data_fetcher_plugin_manager, PluginManagerInterface $data_parser_plugin_manager, MigrateFormPluginFactory $form_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migrate_plugin, $migration);
    $this->authenticationPluginManager = $authentication_plugin_manager;
    $this->dataFetcherPluginManager = $data_fetcher_plugin_manager;
    $this->dataParserPluginManager = $data_parser_plugin_manager;
    $this->formFactory = $form_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, PluginInspectionInterface $migrate_plugin = NULL, MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migrate_plugin,
      $migration,
      $container->get('plugin.manager.migrate_plus.authentication'),
      $container->get('plugin.manager.migrate_plus.data_fetcher'),
      $container->get('plugin.manager.migrate_plus.data_parser'),
      $container->get('feeds_migrate.migrate_form_plugin_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    $plugins = $this->getPlugins();
    $weight = 1;
    foreach ($plugins as $plugin_type => $plugin_id) {
      /** @var \Drupal\Component\Plugin\PluginInspectionInterface $plugin */
      $plugin = $this->loadMigratePlugin($plugin_type, $plugin_id);
      $plugin_form_type = $this->getPluginDefinition()['form_type'] ?? MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;
      $options = $this->getPluginOptionsList($plugin_type);
      natcasesort($options);

      $form[$plugin_type . '_wrapper'] = [
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#title' => ucwords($plugin_type),
        '#attributes' => [
          'id' => 'plugin_settings--' . $plugin_type,
          'class' => ['feeds-plugin-inline'],
        ],
        '#weight' => $weight,
      ];

      if (count($options) === 1) {
        $form[$plugin_type . '_wrapper']['id'] = [
          '#type' => 'value',
          '#value' => $plugin_id,
          '#plugin_type' => $plugin_type,
          '#parents' => ['migration', 'source', "{$plugin_type}_plugin"],
        ];
      }
      else {
        $form[$plugin_type . '_wrapper']['id'] = [
          '#type' => 'select',
          '#title' => $this->t('@type plugin', ['@type' => ucfirst($plugin_type)]),
          '#options' => $options,
          '#default_value' => $plugin_id,
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => 'feeds-migration-ajax-wrapper',
          ],
          '#limit_validation_errors' => [],
          '#plugin_type' => $plugin_type,
          '#parents' => ['migration', 'source', "{$plugin_type}_plugin"],
        ];
      }

      // This is the small form that appears directly under the plugin dropdown.
      $form[$plugin_type . '_wrapper']['options'] = [
        '#type' => 'container',
        '#prefix' => '<div id="feeds-migration-plugin-' . $plugin_type . '-options">',
        '#suffix' => '</div>',
      ];

      if ($plugin && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->migration);
        $form[$plugin_type . '_wrapper']['options'] += $option_form->buildConfigurationForm([], $option_form_state);
      }

      // Configuration form for the plugin.
      $form[$plugin_type . '_wrapper'][$plugin_form_type] = [
        '#type' => 'container',
        '#prefix' => '<div id="feeds-migration-plugin-' . $plugin_type . '-' . $plugin_form_type . '>',
        '#suffix' => '</div>',
      ];

      if ($plugin && $this->formFactory->hasForm($plugin, $plugin_form_type)) {
        $config_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper'][$plugin_form_type], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, $plugin_form_type, $this->migration);
        $form[$plugin_type . '_wrapper'][$plugin_form_type] += $config_form->buildConfigurationForm([], $config_form_state);
      }

      // Increment weight.
      $weight++;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Allow plugins to validate their settings.
    foreach ($this->getPlugins() as $plugin_type => $plugin_id) {
      /** @var \Drupal\Component\Plugin\PluginInspectionInterface $plugin */
      $plugin = $this->loadMigratePlugin($plugin_type, $plugin_id);
      $plugin_form_type = $this->getPluginDefinition()['form_type'] ?? MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;

      if ($plugin && isset($form[$plugin_type . '_wrapper']['option']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->migration);
        $option_form->validateConfigurationForm($form[$plugin_type . '_wrapper']['option'], $option_form_state);
      }

      if ($plugin && isset($form[$plugin_type . '_wrapper'][$plugin_form_type]) && $this->formFactory->hasForm($plugin, $plugin_form_type)) {
        $config_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper'][$plugin_form_type], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, $plugin_form_type, $this->migration);
        $config_form->validateConfigurationForm($form[$plugin_type . '_wrapper'][$plugin_form_type], $config_form_state);
      }
    }
  }

  /**
   * Sends an ajax response.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form element to return.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form['plugin_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);

    // Allow plugins to set values on the Migration entity.
    foreach ($this->getPlugins() as $plugin_type => $plugin_id) {
      /** @var \Drupal\Component\Plugin\PluginInspectionInterface $plugin */
      $plugin = $this->loadMigratePlugin($plugin_type, $plugin_id);
      $plugin_form_type = $this->getPluginDefinition()['form_type'] ?? MigrateFormPluginInterface::FORM_TYPE_CONFIGURATION;

      if ($plugin && isset($form[$plugin_type . '_wrapper']['options']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->migration);
        $option_form->copyFormValuesToEntity($entity, $form[$plugin_type . '_wrapper']['options'], $option_form_state);
      }

      if ($plugin && isset($form[$plugin_type . '_wrapper'][$plugin_form_type]) && $this->formFactory->hasForm($plugin, $plugin_form_type)) {
        $config_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper'][$plugin_form_type], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, $plugin_form_type, $this->migration);
        $config_form->copyFormValuesToEntity($entity, $form[$plugin_type . '_wrapper'][$plugin_form_type], $config_form_state);
      }
    }
  }

  /**
   * Load the Migrate plugin for a given type.
   *
   * @param string $type
   *   The type of Migration Plugin (e.g. data_fetcher, data_parser).
   * @param string $id
   *   The id of the plugin.
   *
   * @return object|null
   *   The loaded migrate plugin, or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadMigratePlugin($type, $id) {
    $plugin = NULL;

    switch ($type) {
      case 'data_fetcher':
        $plugin = $this->dataFetcherPluginManager->createInstance($id);
        break;

      case 'data_parser':
        $plugin = $this->dataParserPluginManager->createInstance($id);
        break;
    }

    return $plugin;
  }

  /**
   * Returns a list of plugins on the migration source plugin, listed per type.
   *
   * @return array
   *   A list of plugins, listed per type.
   */
  protected function getPlugins() {
    $source = $this->migration->get('source');

    // Declare some default plugins.
    $plugins = [
      'data_fetcher' => 'file',
      'data_parser' => 'json',
    ];

    // Data fetcher.
    if (isset($source['data_fetcher_plugin'])) {
      $plugins['data_fetcher'] = $source['data_fetcher_plugin'];
    }

    // Data parser.
    if (isset($source['data_parser_plugin'])) {
      $plugins['data_parser'] = $source['data_parser_plugin'];
    }

    return $plugins;
  }

  /**
   * Returns list of possible plugins for a certain plugin type.
   *
   * @param string $plugin_type
   *   The plugin type to return possible values for.
   *
   * @return array
   *   A list of available plugins.
   */
  protected function getPluginOptionsList($plugin_type) {
    $options = [];
    switch ($plugin_type) {
      case 'data_fetcher':
        $manager = $this->dataFetcherPluginManager;
        break;

      case 'data_parser':
        $manager = $this->dataParserPluginManager;
        break;

      default:
        return $options;
    }

    // Iterate over available plugins and filter out empty/null plugins.
    foreach ($manager->getDefinitions() as $plugin_id => $definition) {
      // @todo let's not hard code this.
      if (in_array($plugin_id, ['null', 'empty'])) {
        continue;
      }
      $options[$plugin_id] = isset($definition['label']) ? $definition['label'] : $plugin_id;
    }

    return $options;
  }

}
