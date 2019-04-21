<?php

namespace Drupal\feeds_migrate\Form;

use Drupal;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Url;
use Drupal\feeds_migrate\FeedsMigrateImporterInterface;
use Drupal\feeds_migrate\Plugin\MigrateFormPluginFactory;
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
      $container->get('date.formatter'),
      $container->get('queue'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Feeds migrate importer settings.
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

    $form['import_period'] = [
      '#type' => 'select',
      '#title' => $this->t('Import frequency'),
      '#options' => $options,
      '#description' => $this->t('Choose how often the importer should run.'),
      '#default_value' => $this->entity->importPeriod,
    ];

    // Settings on how to process existing entities.
    $form['processor_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Processor settings'),
      '#tree' => FALSE,
    ];
    $form['processor_settings']['existing'] = [
      '#type' => 'radios',
      '#title' => $this->t('Update Existing Content'),
      '#default_value' => $this->entity->existing ?: 0,
      '#options' => [
        0 => $this->t('Do not update existing content'),
        1 => $this->t('Replace existing content'),
        2 => $this->t('Update existing content'),
      ],
    ];
    $form['processor_settings']['orphans'] = [
      '#type' => 'select',
      '#title' => $this->t('Orphaned Items'),
      '#default_value' => $this->entity->orphans ?: '__keep',
      '#options' => [
        '_keep' => $this->t('Keep'),
        '_delete' => $this->t('Delete'),
      ],
    ];

    // Migration settings.
    $form['migration_settings'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Migration settings'),
      '#tree' => FALSE,
    ];

    // Source.
    $options = [];
    $migration_source = $form_state->getValue('source') ?? $this->entity->source;
    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    foreach (Migration::loadMultiple() as $migration) {
      $options[$migration->id()] = $migration->label();
    }

    $form['migration_settings']['source'] = [
      '#type' => 'select',
      '#title' => $this->t('Migration Source'),
      '#options' => $options,
      '#default_value' => $migration_source,
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'wrapper' => 'feeds-migration-ajax-wrapper',
      ],
      '#required' => TRUE,
      '#attributes' => [
        'disabled' => !empty($this->entity->source),
      ],
    ];

    // Migrate plugins.
    $form['migration_settings']['plugin_settings'] = [
      '#type' => 'vertical_tabs',
      '#prefix' => '<div id="feeds-migration-ajax-wrapper">',
      '#suffix' => '</div>',
      '#states' => [
        'visible' => [
          ':input[name="source"]' => ['filled' => TRUE],
        ],
      ],
    ];

    if ($migration_source) {
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
    $migration = $this->migrationPluginManager->createInstance($this->entity->source);
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
   * @return array
   *   The form element to return.
   */
  public function ajaxCallback(array $form, FormStateInterface $form_state) {
    return $form;
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

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromRoute('entity.feeds_migrate_importer.collection'));
    return parent::save($form, $form_state);
  }

}
