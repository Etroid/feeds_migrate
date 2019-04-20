<?php

namespace Drupal\feeds_migrate\Plugin\migrate\source\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The configuration form for the url migrate source plugin.
 *
 * @MigrateForm(
 *   id = "url",
 *   title = @Translation("Url Source Plugin Form"),
 *   form = "configuration",
 *   parent_id = "url",
 *   parent_type = "source",
 * )
 */
class UrlForm extends SourceFormPluginBase {

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
   * @param \Drupal\Component\Plugin\PluginManagerInterface $authentication_plugin_manager
   *   The plugin manager for migrate plus authentication plugins.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_fetcher_plugin_manager
   *   The plugin manager for migrate plus data fetcher plugins.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_parser_plugin_manager
   *   The plugin manager for migrate plus data parser plugins.
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory $form_factory
   *   The factory for feeds migrate form plugins.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, PluginManagerInterface $authentication_plugin_manager, PluginManagerInterface $data_fetcher_plugin_manager, PluginManagerInterface $data_parser_plugin_manager, MigrateFormPluginFactory $form_factory) {
    parent::__construct($migration_plugin_manager);
    $this->authenticationPluginManager = $authentication_plugin_manager;
    $this->dataFetcherPluginManager = $data_fetcher_plugin_manager;
    $this->dataParserPluginManager = $data_parser_plugin_manager;
    $this->formFactory = $form_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
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
    foreach ($plugins as $type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($type, $plugin_id);
      $options = $this->getPluginOptionsList($type);
      natcasesort($options);

      $form[$type . '_wrapper'] = [
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#title' => ucwords($this->t($type)),
        '#attributes' => [
          'id' => 'plugin_settings--' . $type,
          'class' => ['feeds-plugin-inline']
        ],
        '#weight' => $weight,
      ];

      if (count($options) === 1) {
        $form[$type . '_wrapper']['id'] = [
          '#type' => 'value',
          '#value' => $plugin_id,
          '#plugin_type' => $type,
          '#parents' => ['migration', 'source', "{$type}_plugin"],
        ];
      }
      else {
        $form[$type . '_wrapper']['id'] = [
          '#type' => 'select',
          '#title' => $this->t('@type plugin', ['@type' => ucfirst($type)]),
          '#options' => $options,
          '#default_value' => $plugin_id,
          '#ajax' => [
            'callback' => [get_class($this), 'ajaxCallback'],
            'wrapper' => 'feeds-migration-ajax-wrapper',
          ],
          '#plugin_type' => $type,
          '#parents' => ['migration', 'source', "{$type}_plugin"],
        ];
      }

      // This is the small form that appears directly under the plugin dropdown.
      $form[$type . '_wrapper']['options'] = [
        '#type' => 'container',
        '#prefix' => '<div id="feeds-migration-plugin-' . $type . '-options">',
        '#suffix' => '</div>',
      ];

      if ($plugin && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->entity);
        $form[$type . '_wrapper']['options'] += $option_form->buildConfigurationForm([], $option_form_state);
      }

      // Configuration form for the plugin.
      $form[$type . '_wrapper']['configuration'] = [
        '#type' => 'container',
        '#prefix' => '<div id="feeds-migration-plugin-' . $type . '-configuration">',
        '#suffix' => '</div>',
      ];

      if ($plugin && $this->formFactory->hasForm($plugin, 'configuration')) {
        $config_form_state = SubformState::createForSubform($form[$type . '_wrapper']['configuration'], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'configuration', $this->entity);
        $form[$type . '_wrapper']['configuration'] += $config_form->buildConfigurationForm([], $config_form_state);
      }

      // Increment weight.
      $weight++;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Allow plugins to validate their settings.
    foreach ($this->getPlugins() as $type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($type, $plugin_id);

      if ($plugin && isset($form[$type . '_wrapper']['option']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->entity);
        $option_form->validateConfigurationForm($form[$type . '_wrapper']['option'], $option_form_state);
      }

      if ($plugin && isset($form[$type . '_wrapper']['configuration']) && $this->formFactory->hasForm($plugin, 'configuration')) {
        $config_form_state = SubformState::createForSubform($form[$type . '_wrapper']['configuration'], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'configuration', $this->entity);
        $config_form->validateConfigurationForm($form[$type . '_wrapper']['configuration'], $config_form_state);
      }
    }
  }

  /**
   * Sends an ajax response.
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
    foreach ($this->getPlugins() as $type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($type, $plugin_id);
      if ($plugin && isset($form[$type . '_wrapper']['options']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->entity);
        $option_form->copyFormValuesToEntity($entity, $form[$type . '_wrapper']['options'], $option_form_state);
      }

      if ($plugin && isset($form[$type . '_wrapper']['configuration']) && $this->formFactory->hasForm($plugin, 'configuration')) {
        $config_form_state = SubformState::createForSubform($form[$type . '_wrapper']['configuration'], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'configuration', $this->entity);
        $config_form->copyFormValuesToEntity($entity, $form[$type . '_wrapper']['configuration'], $config_form_state);
      }
    }
  }

  /**
   * Load the Migrate plugin for a given type.
   *
   * @param string $type
   *  The type of Migration Plugin (e.g. data_fetcher, data_parser).
   * @param string $id
   *  The id of the plugin.
   *
   * @return object|null
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
    $source = $this->entity->get('source');

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
