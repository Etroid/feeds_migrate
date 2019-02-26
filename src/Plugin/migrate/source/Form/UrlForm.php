<?php

namespace Drupal\feeds_migrate\Plugin\migrate\source\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\ExternalPluginFormBase;
use Drupal\feeds_migrate\Plugin\PluginFormFactory;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The configuration form for the url migrate source plugin.
 */
class UrlForm extends SourcePluginFormBase {

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
   * @var \Drupal\feeds_migrate\Plugin\PluginFormFactory
   */
  protected $formFactory;

  /**
   * The migrate source plugin.
   *
   * @var \Drupal\migrate_plus\Plugin\migrate\source\Url
   */
  protected $plugin;

  /**
   * UrlForm constructor.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $authentication_plugin_manager
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_fetcher_plugin_manager
   * @param \Drupal\Component\Plugin\PluginManagerInterface $data_parser_plugin_manager
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, PluginManagerInterface $authentication_plugin_manager, PluginManagerInterface $data_fetcher_plugin_manager, PluginManagerInterface $data_parser_plugin_manager, PluginFormFactory $form_factory) {
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
      $container->get('feeds_migrate.plugin_form_factory')
    );
  }

  /**
   * Get the plugin.
   *
   * @return \Drupal\migrate_plus\Plugin\migrate\source\Url
   *   The migrate source plugin.
   */
  protected function getPlugin() {
    return $this->plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // The url source plugin support additional data fetcher and data parser plugins.
    $plugins = [
      'data_fetcher' => 'file',
      'data_parser' => 'json',
    ];

    foreach ($plugins as $type => $plugin_id) {
      $plugin = $this->loadPlugin($type, $plugin_id);
      $options = $this->getPluginOptionsList($type);
      natcasesort($options);

      $form[$type . '_wrapper'] = [
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#title' => ucwords($this->t($type)),
        '#weight' => 10,
      ];

      if (count($options) === 1) {
        $form[$type . '_wrapper']['id'] = [
          '#type' => 'value',
          '#value' => $plugin_id,
          '#plugin_type' => $type,
          '#parents' => [$type],
        ];
      }
      else {
        $form[$type . '_wrapper']['id'] = [
          '#type' => 'select',
          '#title' => $this->t('@type plugin', ['@type' => ucfirst($type)]),
          '#options' => $options,
          '#default_value' => $plugin_id,
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => 'feeds-ajax-form-wrapper',
          ],
          '#plugin_type' => $type,
          '#parents' => [$type],
        ];
      }

      $plugin_state = $this->createSubFormState($type . '_configuration', $form_state);

      // This is the small form that appears directly under the plugin dropdown.
      if ($plugin && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->getMigrationEntity());
        $form[$type . '_wrapper']['advanced'] = $option_form->buildConfigurationForm([], $plugin_state);
      }

      $form[$type . '_wrapper']['advanced']['#prefix'] = '<div id="feeds-plugin-' . $type . '-advanced">';
      $form[$type . '_wrapper']['advanced']['#suffix'] = '</div>';

      if ($plugin && $this->formFactory->hasForm($plugin, 'configuration')) {
        $form_builder = $this->formFactory->createInstance($plugin, 'configuration', $this->getMigrationEntity());

        $plugin_form = $form_builder->buildConfigurationForm([], $plugin_state);
        $form[$type . '_wrapper']['configuration'] = [
          '#type' => 'container',
        ];
        $form[$type . '_wrapper']['configuration'] += $plugin_form;
      }
    }

    // @todo

    return $form;
  }

  /**
   * Creates a FormStateInterface object for a plugin.
   *
   * @param string|array $key
   *   The form state key.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to copy values from.
   *
   * @return \Drupal\Core\Form\FormStateInterface
   *   A new form state object.
   *
   * @see FormStateInterface::getValue()
   */
  protected function createSubFormState($key, FormStateInterface $form_state) {
    // There might turn out to be other things that need to be copied and passed
    // into plugins. This works for now.
    return (new FormState())->setValues($form_state->getValue($key, []));
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // @todo
  }

  /**
   * @param $type
   * @param $plugin_id
   *
   * @return object|null
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadPlugin($type, $plugin_id) {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->getMigrationArray();
    $plugin = NULL;

    switch ($type) {
      case 'data_fetcher':
        $configuration = $migration->get('source')['data_fetcher_plugin'] ?? [];
        $plugin = $this->dataFetcherPluginManager->createInstance($plugin_id, $configuration);
        break;

      case 'data_parser':
        $configuration = $migration->get('source')['data_parser_plugin'] ?? [];
        $plugin = $this->dataParserPluginManager->createInstance($plugin_id, $configuration);
        break;
    }

    return $plugin;
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
      if (in_array($plugin_id, ['null', 'empty'])) {
        continue;
      }
      $options[$plugin_id] = isset($definition['label']) ? $definition['label'] : $plugin_id;
    }

    return $options;
  }

}
