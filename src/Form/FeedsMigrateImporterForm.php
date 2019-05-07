<?php

namespace Drupal\feeds_migrate\Form;

use Drupal;
use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\feeds_migrate\FeedsMigrateImporterInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\Migration;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FeedsMigrateImporterForm.
 *
 * @package Drupal\feeds_migrate\Form
 */
class FeedsMigrateImporterForm extends EntityForm {

  /**
   * The feeds importer entity.
   *
   * @var \Drupal\feeds_migrate\FeedsMigrateImporterInterface
   */
  protected $entity;

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
   * Date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Migration entity.
   *
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   */
  protected $migration;

  /**
   * FeedsMigrateImporterForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $source_plugin_manager
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $destination_plugin_manager
   * @param \Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory $form_factory
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, MigratePluginManagerInterface $source_plugin_manager, MigratePluginManagerInterface $destination_plugin_manager, MigrateFormPluginFactory $form_factory, DateFormatterInterface $date_formatter) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->sourcePluginManager = $source_plugin_manager;
    $this->destinationPluginManager = $destination_plugin_manager;
    $this->formFactory = $form_factory;
    $this->dateFormatter = $date_formatter;
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
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    // Initialize migration entity when editing an existing importer.
    if ($this->entity->getMigrationId()) {
      $this->migration = $this->entity->getMigration();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // SubFormStates work best when form tree structure is preserved.
    $form['#tree'] = TRUE;

    // Feeds migrate importer settings.
    $form['importer_settings'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Importer settings'),
      '#tree' => FALSE,
    ];
    $form['importer_settings']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the @type.', [
        '@type' => $this->entity->getEntityType()->getLabel(),
      ]),
      '#required' => TRUE,
      '#parents' => ['label'],
    ];

    $entity_class = $this->entity->getEntityType()->getClass();
    $form['importer_settings']['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#disabled' => !$this->entity->isNew(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\\' . $entity_class . '::load',
        'replace_pattern' => '[^a-z0-9_]+',
        'replace' => '_',
        'source' => ['importer_settings', 'label'],
      ],
      '#required' => TRUE,
      '#parents' => ['id'],
    ];

    // Define import period intervals.
    $options = [
      FeedsMigrateImporterInterface::SCHEDULE_NEVER => $this->t('Off'),
      FeedsMigrateImporterInterface::SCHEDULE_CONTINUOUSLY => $this->t('As often as possible'),
    ];
    $intervals = [
      900,
      1800,
      3600,
      10800,
      21600,
      43200,
      86400,
      259200,
      604800,
      2419200,
    ];
    $intervals = array_combine($intervals, $intervals);
    foreach ($intervals as $interval) {
      $options[$interval] = $this->t('Every @time', [
        '@time' => $this->dateFormatter->formatInterval($interval),
      ]);
    }

    $form['importer_settings']['import_frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Import frequency'),
      '#options' => $options,
      '#description' => $this->t('Choose how often the importer should run.'),
      '#default_value' => $this->entity->getImportFrequency(),
      '#parents' => ['importFrequency'],
    ];

    // Settings on how to process existing entities.
    $form['processor_settings'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Processor settings'),
      '#tree' => FALSE,
    ];
    $form['processor_settings']['existing'] = [
      '#type' => 'radios',
      '#title' => $this->t('Update Existing Content'),
      '#default_value' => $this->entity->getExisting() ?: FeedsMigrateImporterInterface::EXISTING_LEAVE,
      '#options' => [
        FeedsMigrateImporterInterface::EXISTING_LEAVE => $this->t('Do not update existing content'),
        FeedsMigrateImporterInterface::EXISTING_REPLACE => $this->t('Replace existing content'),
        FeedsMigrateImporterInterface::EXISTING_UPDATE => $this->t('Update existing content'),
      ],
      '#parents' => ['existing'],
    ];
    $form['processor_settings']['keep_orphans'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep orphaned Items?'),
      '#default_value' => $this->entity->keepOrphans() ?: FALSE,
      '#parents' => ['keepOrphans'],
    ];

    // Migration settings.
    $form['migration_settings'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Migration settings'),
    ];

    // Source.
    $options = [];
    /** @var \Drupal\Core\Entity\EntityInterface $migration */
    foreach (Migration::loadMultiple() as $migration) {
      $options[$migration->id()] = $migration->label();
    }

    $form['migration_settings']['migration_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Migration Source'),
      '#options' => $options,
      '#default_value' => $this->entity->getMigrationId(),
      "#empty_option" => t('- Select Migration -'),
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'change',
        'wrapper' => 'feeds-migration-ajax-wrapper',
        'effect' => 'fade',
        'progress' => 'throbber',
      ],
      '#limit_validation_errors' => [],
      '#required' => TRUE,
      '#attributes' => [
        'disabled' => !empty($this->entity->getMigrationId()),
      ],
      '#parents' => ['migrationId'],
    ];

    // Configure migration plugins.
    $form['migration_settings']['wrapper'] = [
      '#type' => 'container',
      '#prefix' => '<div id="feeds-migration-ajax-wrapper">',
      '#suffix' => '</div>',
    ];

    if ($this->migration) {
      $form['migration_settings']['wrapper']['plugin_settings'] = [
        '#type' => 'vertical_tabs',
        '#parents' => ['plugin_settings'],
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
          '#title' => ucwords($type),
          '#attributes' => [
            'id' => 'plugin_settings--' . $type,
            'class' => ['feeds-plugin-inline'],
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
              'event' => 'change',
              'wrapper' => 'feeds-migration-ajax-wrapper',
              'effect' => 'fade',
              'progress' => 'throbber',
            ],
            '#plugin_type' => $type,
            '#parents' => ['migration', $type, 'plugin'],
          ];
        }

        // This is the small form that appears directly under the plugin
        // dropdown.
        $form[$type . '_wrapper']['options'] = [
          '#type' => 'container',
          '#prefix' => '<div id="feeds-migration-plugin-' . $type . '-options">',
          '#suffix' => '</div>',
        ];

        if ($plugin && $this->formFactory->hasForm($plugin, 'option')) {
          $option_form_state = SubformState::createForSubform($form[$type . '_wrapper']['options'], $form, $form_state);
          $option_form = $this->formFactory->createInstance($plugin, 'option', $this->migration, MigrateFormPluginInterface::CONTEXT_IMPORTER);
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
          $config_form = $this->formFactory->createInstance($plugin, 'configuration', $this->migration, MigrateFormPluginInterface::CONTEXT_IMPORTER);
          $form[$type . '_wrapper']['configuration'] += $config_form->buildConfigurationForm([], $config_form_state);
        }

        // Increment weight by 5 to allow other plugins to insert additional
        // settings as vertical tabs.
        // @see Drupal\feeds_migrate\Plugin\migrate\source\Form\UrlForm
        $weight += 5;
      }
    }

    return $form;
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
    $plugins = [];

    if ($this->migration) {
      // Source.
      $source = $this->migration->get('source');
      if (isset($source['plugin'])) {
        $plugins['source'] = $source['plugin'];
      }

      // Destination.
      $destination = $this->migration->get('destination');
      if (isset($destination['plugin'])) {
        $plugins['destination'] = $destination['plugin'];
      }
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
    $migration = $this->migrationPluginManager->createInstance($this->migration->id(), $this->migration->toArray());
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
        $manager = Drupal::service("plugin.manager.migrate.$plugin_type");
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
   * Callback for ajax requests.
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
    return $form['migration_settings']['wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Allow plugins to validate their settings.
    if ($this->migration && $this->migration->id() === $this->entity->getMigrationId()) {
      foreach ($this->getPlugins() as $type => $plugin_id) {
        $plugin = $this->loadMigratePlugin($type, $plugin_id);

        if ($plugin && isset($form[$type . '_wrapper']['options']) && $this->formFactory->hasForm($plugin, 'option')) {
          $option_form_state = SubformState::createForSubform($form[$type . '_wrapper']['options'], $form, $form_state);
          $option_form = $this->formFactory->createInstance($plugin, 'option', $this->migration, MigrateFormPluginInterface::CONTEXT_IMPORTER);
          $option_form->validateConfigurationForm($form[$type . '_wrapper']['options'], $option_form_state);
        }

        if ($plugin && isset($form[$type . '_wrapper']['configuration']) && $this->formFactory->hasForm($plugin, 'configuration')) {
          $config_form_state = SubformState::createForSubform($form[$type . '_wrapper']['configuration'], $form, $form_state);
          $config_form = $this->formFactory->createInstance($plugin, 'configuration', $this->migration, MigrateFormPluginInterface::CONTEXT_IMPORTER);
          $config_form->validateConfigurationForm($form[$type . '_wrapper']['configuration'], $config_form_state);
        }
      }
    }
    // Save our migration entity.
    elseif ($this->entity->getMigrationId()) {
      $this->migration = $this->entity->getMigration();
    }
    else {
      $this->migration = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    parent::copyFormValuesToEntity($entity, $form, $form_state);

    // Allow plugins to set values on the migration entity.
    if ($this->migration && $this->migration->id() === $this->entity->getMigrationId()) {
      // Map values from form directly to migration entity where possible.
      $values = $form_state->getValue('migration');
      foreach ($values as $key => $value) {
        $this->migration->set($key, $value);
      }

      foreach ($this->getPlugins() as $type => $plugin_id) {
        $plugin = $this->loadMigratePlugin($type, $plugin_id);

        if ($plugin && isset($form[$type . '_wrapper']['options']) && $this->formFactory->hasForm($plugin, 'option')) {
          $option_form_state = SubformState::createForSubform($form[$type . '_wrapper']['options'], $form, $form_state);
          $option_form = $this->formFactory->createInstance($plugin, 'option', $this->migration, MigrateFormPluginInterface::CONTEXT_IMPORTER);
          $option_form->copyFormValuesToEntity($this->migration, $form[$type . '_wrapper']['options'], $option_form_state);
        }

        if ($plugin && isset($form[$type . '_wrapper']['configuration']) && $this->formFactory->hasForm($plugin, 'configuration')) {
          $config_form_state = SubformState::createForSubform($form[$type . '_wrapper']['configuration'], $form, $form_state);
          $config_form = $this->formFactory->createInstance($plugin, 'configuration', $this->migration, MigrateFormPluginInterface::CONTEXT_IMPORTER);
          $config_form->copyFormValuesToEntity($this->migration, $form[$type . '_wrapper']['configuration'], $config_form_state);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Copy migration overwrites to the feeds migrate importer entity.
    $original_migration = $this->entity->getOriginalMigration();
    $migration_config = [
      'source' => DiffArray::diffAssocRecursive($this->migration->get('source'), $original_migration->get('source')),
      'destination' => DiffArray::diffAssocRecursive($this->migration->get('destination'), $original_migration->get('destination')),
    ];
    $this->entity->set('migrationConfig', $migration_config);
    $status = parent::save($form, $form_state);

    // Redirect the user back to the listing route after the save operation.
    $form_state->setRedirect('entity.feeds_migrate_importer.collection');
  }

}
