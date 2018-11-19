<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\AuthenticationFormPluginManager;
use Drupal\feeds_migrate\DataFetcherFormPluginManager;
use Drupal\feeds_migrate\DataParserPluginManager;
use Drupal\feeds_migrate\Plugin\PluginFormFactory;
use Drupal\feeds_migrate_ui\FeedsMigrateUiFieldManager;
use Drupal\feeds_migrate_ui\FeedsMigrateUiParserSuggestion;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base form for mapping settings.
 *
 * @package Drupal\feeds_migrate\Form
 *
 * @todo consider moving this UX into migrate_tools module to allow editors
 * to create simple migrations directly from the admin interface
 */
class MigrationMappingFormBase extends EntityForm {

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The form factory.
   *
   * @var \Drupal\feeds_migrate\Plugin\PluginFormFactory
   */
  protected $formFactory;

  /**
   * Fill This.
   *
   * @var \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldProcessorManager
   */
  protected $fieldProcessorManager;

  /**
   * Fill This.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Fill This.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Fill This.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleManager;

  /**
   * The migration mapping.
   *
   * @var array
   */
  protected $mapping = [];

  /**
   * The migration mapping destination.
   *
   * @var string
   */
  protected $mappingKey;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('feeds_migrate.plugin_form_factory'),
      $container->get('plugin.manager.feeds_migrate_ui.field'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * @todo: document.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager, PluginFormFactory $form_factory, FeedsMigrateUiFieldManager $field_processor, EntityFieldManager $field_manager) {
    $this->migrationPluginManager = $migration_plugin_manager;
    $this->formFactory = $form_factory;
    $this->fieldProcessorManager = $field_processor;
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $migration = NULL, string $key = NULL) {
    $this->migration = $migration;
    $this->mappingKey = $key;

    $options = $this->getMappingTargetOptions();
    asort($options);

    // General mapping settings
    $form['general'] = [
      '#title' => $this->t('Mapping settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => FALSE,
    ];

    $form['general']['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Mapping destination'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a destination -'),
      '#default_value' => $this->mappingKey,
      '#disabled' => isset($this->mappingKey),
      '#required' => TRUE,
    ];

    // Plugin settings
    if ($this->mappingKey) {
      $plugin = $this->getMappingPlugin();
      $plugin_state = $this->createSubFormState($this->mappingKey . '_configuration', $form_state);

      if ($plugin) {
        $plugin_form = $plugin->buildConfigurationForm([], $plugin_state);

        $form['plugin'] = [
          '#title' => $this->t('Field plugin settings'),
          '#type' => 'details',
          '#group' => 'plugin_settings',
          '#open' => TRUE,
        ];
        $form['plugin'] += [$plugin_form];
      }
    }

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityFormController::actions().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   An array of supported actions for the current entity form.
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Get the basic actions from the base class.
    $actions = parent::actions($form, $form_state);

    // Change the submit button text.
    $actions['submit']['#value'] = $this->t('Save');

    // Return the result.
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $migration = $this->getEntity();

    // TODO save off mapping information to $entity['process']['key'][]
    //$status = $migration->save();

    // Redirect the user back to the mapping route after the save operation.
    $form_state->setRedirect('entity.migration.mapping.list',
      ['migration' => $migration->id()]);
  }

  /**
   * Find the entity type the migration is importing into.
   *
   * @return string
   *   Machine name of the entity type eg 'node'.
   */
  protected function getEntityTypeFromMigration() {
    $destination = $this->entity->destination['plugin'];
    if (strpos($destination, ':') !== FALSE) {
      list(, $entity_type) = explode(':', $destination);
      return $entity_type;
    }
  }

  /**
   * The bundle the migration is importing into.
   *
   * @return string
   *   Entity type bundle eg 'article'.
   */
  protected function getEntityBundleFromMigration() {
    if (!empty($this->entity->destination['default_bundle'])) {
      return $this->entity->destination['default_bundle'];
    }
    elseif (!empty($this->entity->source['constants']['bundle'])) {
      return $this->entity->source['constants']['bundle'];
    }
  }

  /****************************************************************************/
  // Mapping handling. @todo move to migration entity
  /****************************************************************************/

  /**
   * Gets the initialized mapping plubin
   *
   * @return \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getMappingPlugin() {
    $field = $this->getMappingTarget($this->mappingKey);

    /** @var \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldInterface $plugin */
    $plugin = $this->fieldProcessorManager->getFieldPlugin($field, $this->migration);

    return $plugin;
  }

  /**
   * Get all mapping destinations.
   *
   * @return FieldDefinitionInterface[]
   *  A list of mapping destination objects.
   */
  protected function getMappingTargets() {
    /** @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage $entity_storage */
    $entity_storage = $this->entityTypeManager->getStorage($this->getEntityTypeFromMigration());
    /** @var \Drupal\Core\Entity\ContentEntityType $entity_type */
    $entity_type = $entity_storage->getEntityType();

    return $this->fieldManager->getFieldDefinitions($entity_type->id(), $this->getEntityBundleFromMigration());
  }

  /**
   * Get a single mapping target identified by its name.
   *
   * @param $name
   *  The machine name of the mapping target to return.
   * @return string|FieldDefinitionInterface
   */
  protected function getMappingTarget($name) {
    $mapping_targets = $this->getMappingTargets();

    return $mapping_targets[$name];
  }

  /**
   * Returns a list of all mapping target options, keyed by field name.
   */
  protected function getMappingTargetOptions() {
    $mapping_target_options = [];

    /** @var FieldDefinitionInterface[] $fields */
    $mapping_targets = $this->getMappingTargets();
    foreach ($mapping_targets as $field_name => $field) {
      $mapping_target_options[$field->getName()] = $field->getLabel();
    }

    return $mapping_target_options;
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

}
