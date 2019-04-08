<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Renderer;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MigrationForm.
 *
 * @package Drupal\feeds_migrate_ui\Form
 */
class MigrationForm extends EntityForm {

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * Plugin manager for source plugins.
   *
   * @var \Drupal\migrate\Plugin\MigratePluginManagerInterface
   */
  protected $sourcePluginManager;

  /**
   * Plugin manager for destination plugins.
   *
   * @var \Drupal\migrate\Plugin\MigratePluginManagerInterface
   */
  protected $destinationPluginManager;

  /**
   * The form factory.
   *
   * @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory
   */
  protected $formFactory;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('plugin.manager.migrate.source'),
      $container->get('plugin.manager.migrate.destination'),
      $container->get('feeds_migrate.migrate_form_plugin_factory'),
      $container->get('renderer')
    );
  }

  /**
   * MigrationForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $source_plugin_manager
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $destination_plugin_manager
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory $form_factory
   * @param \Drupal\Core\Render\Renderer $renderer
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, MigratePluginManagerInterface $source_plugin_manager, MigratePluginManagerInterface $destination_plugin_manager, MigrateFormPluginFactory $form_factory, Renderer $renderer) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->sourcePluginManager = $source_plugin_manager;
    $this->destinationPluginManager = $destination_plugin_manager;
    $this->formFactory = $form_factory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    // Ensure some values are set on the entity in order to comply to the config
    // schema.
    $defaults = [
      'source' => [
        'plugin' => 'url',
      ],
      'process' => [],
      'destination' => [
        'plugin' => 'entity:node',
      ],
      'migration_tags' => [],
      'migration_dependencies' => [],
    ];

    foreach ($defaults as $key => $value) {
      if (is_null($this->entity->get($key))) {
        $this->entity->set($key, $value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save');

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['#tree'] = TRUE;

    // Core Migration Settings.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the @type.', [
        '@type' => $this->entity->getEntityType()->getLabel(),
      ]),
      '#required' => TRUE,
      '#parents' => ['migration', 'label'],
    ];

    $entity_class = $this->entity->getEntityType()->getClass();
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#disabled' => !$this->entity->isNew(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\\' . $entity_class . '::load',
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
        'source' => ['label'],
      ],
      '#required' => TRUE,
      '#parents' => ['migration', 'id'],
    ];

    // Migration Group.
    $groups = MigrationGroup::loadMultiple();
    $group_options = [];
    foreach ($groups as $group) {
      $group_options[$group->id()] = $group->label();
    }
    if (!$this->entity->get('migration_group') && isset($group_options['default'])) {
      $this->entity->set('migration_group', 'default');
    }

    $form['migration_group'] = [
      '#type' => 'select',
      '#title' => $this->t('Migration Group'),
      '#empty_value' => '',
      '#default_value' => $this->entity->get('migration_group'),
      '#options' => $group_options,
      '#description' => $this->t('Assign this migration to an existing group.'),
      '#parents' => ['migration', 'migration_group'],
    ];

    // Plugins.
    $form['plugin_settings'] = [
      '#type' => 'vertical_tabs',
      '#prefix' => '<div id="feeds-migration-ajax-wrapper">',
      '#suffix' => '</div>',
    ];

    $plugins = $this->getPlugins();
    $weight = 0;
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
          '#parents' => ['migration', $type, 'plugin'],
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
            'wrapper' => 'feeds-migration-ajax-wrapper',
          ],
          '#plugin_type' => $type,
          '#parents' => ['migration', $type, 'plugin'],
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

      // Increment weight by 5 to allow other plugins to insert additional
      // settings as vertical tabs.
      // @see Drupal\feeds_migrate\Plugin\migrate\source\Form\UrlForm
      $weight += 5;
    }

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Allow plugins to validate their settings.
    foreach ($this->getPlugins() as $type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($type, $plugin_id);

      if ($plugin && isset($form[$type . '_wrapper']['options']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->entity);
        $option_form->validateConfigurationForm($form[$type . '_wrapper']['options'], $option_form_state);
      }

      if ($plugin && isset($form[$type . '_wrapper']['configuration']) && $this->formFactory->hasForm($plugin, 'configuration')) {
        $config_form_state = SubformState::createForSubform($form[$type . '_wrapper']['configuration'], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'configuration', $this->entity);
        $config_form->validateConfigurationForm($form[$type . '_wrapper']['configuration'], $config_form_state);
      }
    }
  }

  protected function getMigration() {
    // Convert migration entity to array in order to create a dummy migration
    // plugin instance. This dummy is needed in order to instantiate a
    // destination plugin. We cannot call toArray() on the migration entity,
    // because that may only be called on saved entities. And we really need an
    // array representation for unsaved entities too.
    $keys = [
      'source',
      'process',
      'destination',
      'migration_tags',
      'migration_dependencies',
    ];
    $migration_data = [];
    foreach ($keys as $key) {
      $migration_data[$key] = $this->entity->get($key);
    }

    // And instantiate the migration plugin.
    $migration = $this->migrationPluginManager->createStubMigration($migration_data);

    return $migration;
  }

  /**
   * Returns a list of plugins on the migration, listed per type.
   *
   * Would be nice to instantiate data parser plugin here but this will cause
   * issues with us needing a real readable source.
   *
   * @return array
   *   A list of plugins, listed per type.
   *
   * @todo move to a service class.
   */
  protected function getPlugins() {
    $plugins = array_fill_keys([
      'source',
      'destination',
    ], NULL);

    // Source.
    $source = $this->entity->get('source');
    if (isset($source['plugin'])) {
      $plugins['source'] = $source['plugin'];
    }

    // Destination.
    $destination = $this->entity->get('destination');
    if (isset($destination['plugin'])) {
      $plugins['destination'] = $destination['plugin'];
    }

    return $plugins;
  }

  /**
   * Load a Migrate Plugin based on type and id.
   *
   * @param string $type
   *   The type of migrate plugin.
   * @param string $plugin_id
   *   The plugin identifier.
   *
   * @return object|null
   *   The plugin, or NULL if type is not supported.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function loadMigratePlugin($type, $plugin_id) {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->getMigration();
    $plugin = NULL;

    switch ($type) {
      case 'source':
        $plugin = $this->sourcePluginManager->createInstance($plugin_id, $migration->get('source'), $migration);
        break;

      case 'destination':
        $plugin = $this->destinationPluginManager->createInstance($plugin_id, $migration->get('destination'), $migration);
        break;
    }

    return $plugin;
  }

  /**
   * Returns list of possible options for a certain plugin type.
   *
   * @param string $plugin_type
   *   The plugin type to return possible values for.
   *
   * @return array
   *   A list of choosable plugins.
   *
   * @todo move to a service class.
   */
  protected function getPluginOptionsList($plugin_type) {
    switch ($plugin_type) {
      case 'source':
      case 'destination':
        $manager = \Drupal::service("plugin.manager.migrate.$plugin_type");
        break;

      default:
        return [];
    }

    $options = [];
    foreach ($manager->getDefinitions() as $id => $definition) {
      // Filter out empty and null plugins.
      if (in_array($id, ['null', 'empty'])) {
        continue;
      }
      $options[$id] = isset($definition['label']) ? $definition['label'] : $id;
    }

    return $options;
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    \Drupal::messenger()
      ->addMessage($this->t('Saved migration %label', ['%label' => $this->entity->label()]));
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Map values from form directly to migration entity where possible.
    // We use a root `migration` key to prevent collision with reserved keywords
    // in the $form_state. Example: `destination` cannot be used on the root
    // $form_state as it is stripped by RequestSanitizer on AJAX callback:
    // @see /core/lib/Drupal/Core/Security/RequestSanitizer.php:92
    $values = $form_state->getValue('migration');

    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }

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
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    // Redirect the user back to the listing route after the save operation.
    $form_state->setRedirect('entity.migration.list',
      ['migration_group' => $this->entity->get('migration_group')]);
  }

}
