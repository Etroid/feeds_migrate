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
   * The factory for generating forms for migrate plugins.
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
   * Constructs a new MigrationForm.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   Plugin manager for migration plugins.
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $source_plugin_manager
   *   Plugin manager for source plugins.
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $destination_plugin_manager
   *   Plugin manager for destination plugins.
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory $form_factory
   *   The factory for generating forms for migrate plugins.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
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
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

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
    foreach ($plugins as $plugin_type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($plugin_type, $plugin_id);
      $options = $this->getPluginOptionsList($plugin_type);
      natcasesort($options);

      $form[$plugin_type . '_wrapper'] = [
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#title' => ucfirst($plugin_type),
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
          '#parents' => ['migration', $plugin_type, 'plugin'],
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
          '#plugin_type' => $plugin_type,
          '#parents' => ['migration', $plugin_type, 'plugin'],
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
        $option_form = $this->formFactory->createInstance($plugin, 'option', $migration);
        $form[$plugin_type . '_wrapper']['options'] += $option_form->buildConfigurationForm([], $option_form_state);
      }

      // Configuration form for the plugin.
      $form[$plugin_type . '_wrapper']['configuration'] = [
        '#type' => 'container',
        '#prefix' => '<div id="feeds-migration-plugin-' . $plugin_type . '-configuration">',
        '#suffix' => '</div>',
      ];

      if ($plugin && $this->formFactory->hasForm($plugin, 'configuration')) {
        $config_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['configuration'], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'configuration', $migration);
        $form[$plugin_type . '_wrapper']['configuration'] += $config_form->buildConfigurationForm([], $config_form_state);
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
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->entity;

    // Allow plugins to validate their settings.
    foreach ($this->getPlugins() as $plugin_type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($plugin_type, $plugin_id);

      if ($plugin && isset($form[$plugin_type . '_wrapper']['options']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $migration);
        $option_form->validateConfigurationForm($form[$plugin_type . '_wrapper']['options'], $option_form_state);
      }

      if ($plugin && isset($form[$plugin_type . '_wrapper']['configuration']) && $this->formFactory->hasForm($plugin, 'configuration')) {
        $config_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['configuration'], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'configuration', $migration);
        $config_form->validateConfigurationForm($form[$plugin_type . '_wrapper']['configuration'], $config_form_state);
      }
    }
  }

  /**
   * Get dummy migration plugin instance.
   *
   * Convert migration entity to array in order to create a dummy migration
   * plugin instance. This dummy is needed in order to instantiate a
   * destination plugin. We cannot call toArray() on the migration entity,
   * because that may only be called on saved entities. And we really need an
   * array representation for unsaved entities too.
   *
   * @return \Drupal\migrate\Plugin\Migration
   *   The dummy migration plugin.
   */
  protected function getMigration() {
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
   * @return \Drupal\Component\Plugin\PluginInspectionInterface|null
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
        /** @var \Drupal\Component\Plugin\PluginInspectionInterface $plugin */
        $plugin = $this->sourcePluginManager->createInstance($plugin_id, $migration->get('source'), $migration);
        break;

      case 'destination':
        /** @var \Drupal\Component\Plugin\PluginInspectionInterface $plugin */
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
   *   A list of available plugins.
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
   *
   * @todo Don't have plugins save their values directly on the migration
   *   entity. Instead, have each plugin save its own configuration. The
   *   form submit handler on this form should retrieve all configuration and
   *   save them on the entity.
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Map values from form directly to migration entity where possible.
    // We use a root `migration` key to prevent collision with reserved keywords
    // in the $form_state. Example: `destination` cannot be used on the root
    // $form_state as it is stripped by RequestSanitizer on AJAX callback:
    // @see /core/lib/Drupal/Core/Security/RequestSanitizer.php:92
    $values = $form_state->getValue('migration');
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $entity;

    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }

    // Allow plugins to set values on the Migration entity.
    foreach ($this->getPlugins() as $plugin_type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($plugin_type, $plugin_id);
      if ($plugin && isset($form[$plugin_type . '_wrapper']['options']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['options'], $form, $form_state);
        $option_form = $this->formFactory->createInstance($plugin, 'option', $migration);
        $option_form->copyFormValuesToEntity($entity, $form[$plugin_type . '_wrapper']['options'], $option_form_state);
      }

      if ($plugin && isset($form[$plugin_type . '_wrapper']['configuration']) && $this->formFactory->hasForm($plugin, 'configuration')) {
        $config_form_state = SubformState::createForSubform($form[$plugin_type . '_wrapper']['configuration'], $form, $form_state);
        $config_form = $this->formFactory->createInstance($plugin, 'configuration', $migration);
        $config_form->copyFormValuesToEntity($entity, $form[$plugin_type . '_wrapper']['configuration'], $config_form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    // Redirect the user to the mapping after the initial save operation.
    if ($this->operation === 'add-form') {
      $form_state->setRedirect('entity.migration.mapping.list',
        ['migration_group' => $this->entity->get('migration_group')]);
    }
    else {
      // Redirect the user back to the listing route after the save operation.
      $form_state->setRedirect('entity.migration.list',
        ['migration_group' => $this->entity->get('migration_group')]);
    }
  }

}
