<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Renderer;
use Drupal\feeds_migrate\Plugin\PluginFormFactory;
use Drupal\migrate\Plugin\MigratePluginManagerInterface;
use Drupal\migrate\Plugin\MigrateSourcePluginManager;
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
   * @var \Drupal\feeds_migrate\Plugin\PluginFormFactory
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
      $container->get('feeds_migrate.plugin_form_factory'),
      $container->get('renderer')
    );
  }

  /**
   * MigrationForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $source_plugin_manager
   * @param \Drupal\migrate\Plugin\MigratePluginManagerInterface $destination_plugin_manager
   * @param \Drupal\feeds_migrate\Plugin\PluginFormFactory $form_factory
   * @param \Drupal\Core\Render\Renderer $renderer
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, MigratePluginManagerInterface $source_plugin_manager, MigratePluginManagerInterface $destination_plugin_manager, PluginFormFactory $form_factory, Renderer $renderer) {
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
      'source' => [],
      'process' => [],
      'destination' => [],
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

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the @type.', [
        '@type' => $this->entity->getEntityType()->getLabel(),
      ]),
      '#required' => TRUE,
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
        'source' => ['basics', 'label'],
      ],
    ];

    $form['plugin_settings'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 99,
    ];

    $form['plugin_settings']['#prefix'] = '<div id="feeds-ajax-form-wrapper" class="feeds-feed-type-secondary-settings">';
    $form['plugin_settings']['#suffix'] = '</div>';

    // Settings.
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
    ];

    // Plugins.
    $values = $form_state->getValues();
    $default_plugins = [
      'source' => 'url',
      'destination' => 'entity:node',
    ];
    $plugins = $this->getPlugins();
    $plugins = array_merge($default_plugins, array_filter($plugins));
    foreach ($plugins as $type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($type, $plugin_id);
      $options = $this->getPluginOptionsList($type);
      natcasesort($options);

      $form[$type . '_wrapper'] = [
        '#type' => 'details',
        '#group' => 'plugin_settings',
        '#title' => ucwords($this->t($type)),
        '#attributes' => ['class' => ['feeds-plugin-inline']],
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
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->entity);
        $form[$type . '_wrapper']['advanced'] = $option_form->buildConfigurationForm([], $plugin_state);
      }

      $form[$type . '_wrapper']['advanced']['#prefix'] = '<div id="feeds-plugin-' . $type . '-advanced">';
      $form[$type . '_wrapper']['advanced']['#suffix'] = '</div>';

      if ($plugin && $this->formFactory->hasForm($plugin, 'configuration')) {
        $form_builder = $this->formFactory->createInstance($plugin, 'configuration', $this->entity);

        $plugin_form = $form_builder->buildConfigurationForm([], $plugin_state);
        $form[$type . '_wrapper']['configuration'] = [
          '#type' => 'container',
        ];
        $form[$type . '_wrapper']['configuration'] += $plugin_form;
      }
    }

    return parent::form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      return;
    }

    // Validate option form for each plugin.
    foreach ($this->getPlugins() as $type => $plugin) {
      $plugin_state = $this->createSubFormState($type . '_configuration', $form_state);
      if ($plugin && isset($form[$type . '_configuration']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form = $this->formFactory->createInstance($plugin, 'option', $this->entity);
        $option_form->validateConfigurationForm($form[$type . '_configuration'], $plugin_state);
        $form_state->setValue($type . '_configuration', $plugin_state->getValues());
      }
    }

    // Validate settings form for each plugin.
    foreach ($this->getPluginForms() as $type => $plugin_form) {
      if (!isset($form[$type . '_configuration'])) {
        // When switching from a non-configurable plugin to a configurable
        // plugin, no form is yet available. So skip validating it to avoid
        // fatal errors.
        continue;
      }

      $plugin_state = $this->createSubFormState($type . '_configuration', $form_state);
      $plugin_form->validateConfigurationForm($form[$type . '_configuration'], $plugin_state);
      $form_state->setValue($type . '_configuration', $plugin_state->getValues(), $this->entity);

      $this->moveFormStateErrors($plugin_state, $form_state);
    }

    // Build the feed type object from the submitted values.
    parent::validateForm($form, $form_state);
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
   * @param $type
   * @param $plugin_id
   *
   * @return object|null
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
   * Returns the plugin forms for this feed type.
   *
   * @return \Drupal\feeds_migrate\Plugin\Type\ExternalPluginFormInterface[]
   *   A list of form objects, keyed by plugin id.
   */
  protected function getPluginForms() {
    $plugins = [];
    foreach ($this->getPlugins() as $type => $plugin) {
      if ($plugin && $this->formFactory->hasForm($plugin, 'configuration')) {
        $plugins[$type] = $this->formFactory->createInstance($plugin, 'configuration');
      }
    }

    return $plugins;
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
    $renderer = $this->renderer;
    $type = $form_state->getTriggeringElement()['#plugin_type'];

    $response = new AjaxResponse();

    // Set URL hash so that the correct settings tab is open.
    // @todo activate once plugin configuration form is implemented.
    /*
    if (isset($form[$type . '_configuration']['#id'])) {
    $hash = ltrim($form[$type . '_configuration']['#id'], '#');
    $response->addCommand(new SetHashCommand($hash));
    }
     */

    // Update the forms.
    $plugin_settings = $renderer->renderRoot($form['plugin_settings']);
    $advanced_settings = $renderer->renderRoot($form[$type . '_wrapper']['advanced']);
    $response->addCommand(new ReplaceCommand('#feeds-ajax-form-wrapper', $plugin_settings));
    $response->addCommand(new ReplaceCommand('#feeds-plugin-' . $type . '-advanced', $advanced_settings));

    // Add attachments.
    $attachments = NestedArray::mergeDeep($form['plugin_settings']['#attached'], $form[$type . '_wrapper']['advanced']['#attached']);
    $response->setAttachments($attachments);

    // Display status messages.
    $status_messages = ['#type' => 'status_messages'];
    $output = $renderer->renderRoot($status_messages);
    if (!empty($output)) {
      $response->addCommand(new HtmlCommand('.region-messages', $output));
    }

    return $response;
  }

  /**
   * Ajax callback for entity type selection.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return mixed
   *   The entity bundle field.
   */
  public function entityTypeChosenAjax(array $form, FormStateInterface $form_state) {
    return $form['entity_bundle'];
  }

  /**
   * Find the entity type the migration is importing into.
   *
   * @return string
   *   Machine name of the entity type eg 'node'.
   */
  protected function getEntityTypeFromMigration() {
    if (isset($this->entity->destination['plugin'])) {
      $destination = $this->entity->destination['plugin'];
      if (strpos($destination, ':') !== FALSE) {
        list(, $entity_type) = explode(':', $destination);
        return $entity_type;
      }
    }
  }

  /**
   * The bundle the migration is importing into.
   *
   * @return string
   *   Entity type bundle eg 'article'.
   */
  protected function getEntityBundleFromMigration() {
    if (!empty($this->entity->source['constants']['bundle'])) {
      return $this->entity->source['constants']['bundle'];
    }
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
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $values =& $form_state->getValues();

    // Moved advanced settings to regular settings.
    foreach ($this->getPlugins() as $type => $plugin_id) {
      if (isset($values[$type . '_wrapper']['advanced'])) {
        if (!isset($values[$type . '_configuration'])) {
          $values[$type . '_configuration'] = [];
        }
        $values[$type . '_configuration'] += $values[$type . '_wrapper']['advanced'];
      }
      unset($values[$type . '_wrapper']);
    }

    // Set ID and label.
    $entity->set('id', $values['id']);
    $entity->set('label', $values['label']);

    // Get source.
    $source = $this->entity->get('source');

    // Set source plugin.
    // @todo Make it so that source plugin is not hard coded.
    $source['plugin'] = 'null';

    // Set fetcher and parser on source.
    $source['data_fetcher_plugin'] = $values['fetcher'];
    $source['data_parser_plugin'] = $values['parser'];

    // Set id_selector and fields.
    // @todo Make it so id_selector is not hard coded.
    $id_selector = '//';
    $source['item_selector'] = '//';
    $source['ids'] = ['guid' => ['type' => 'string']];
    $source['fields']['guid'] = [
      'name' => 'guid',
      'label' => 'guid',
      'selector' => $id_selector,
    ];

    // Write source back to entity.
    $entity->set('source', $source);

    // Set destination.
    $entity->set('destination', ['plugin' => $values['destination']]);

    // Set migration group.
    $entity->set('migration_group', $values['migration_group']);

    // Allow option forms to set values.
    foreach ($this->getPlugins() as $type => $plugin_id) {
      $plugin = $this->loadMigratePlugin($type, $plugin_id);
      $plugin_state = $this->createSubFormState($type . '_configuration', $form_state);
      if ($plugin && isset($form[$type . '_wrapper']['advanced']) && $this->formFactory->hasForm($plugin, 'option')) {
        $option_form = $this->formFactory->createInstance($plugin, 'option');
        $option_form->copyFormValuesToEntity($entity, $form[$type . '_wrapper']['advanced'], $plugin_state);
      }
    }

    // @todo allow configuration forms to set values.
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
  public function save(array $form, FormStateInterface $form_state) {
    $status = parent::save($form, $form_state);

    // Redirect the user back to the listing route after the save operation.
    $form_state->setRedirect('entity.migration.list',
    ['migration_group' => $this->entity->get('migration_group')]);
  }

}
