<?php

namespace Drupal\feeds_migrate_ui\Form;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feeds_migrate\Plugin\PluginFormFactory;
use Drupal\feeds_migrate_ui\FeedsMigrateUIEntityTrait;
use Drupal\feeds_migrate_ui\FeedsMigrateUiFieldManager;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
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

  // Temporarily use a trait for easier development.
  use FeedsMigrateUIEntityTrait;

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
   * @var \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldManager
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
   * The migration mapping destination.
   *
   * @var string
   */
  protected $destinationKey;

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
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $migration = NULL, string $destination_key = NULL) {
    $this->destinationKey = $destination_key;

    $options = $this->getDestinationOptions();
    asort($options);

    // General mapping settings
    $form['general'] = [
      '#title' => $this->t('General Mapping Settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => FALSE,
    ];

    $form['general']['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select a destination -'),
      '#default_value' => $this->destinationKey,
      '#disabled' => isset($this->destinationKey),
      '#required' => TRUE,
    ];

    // Plugin settings
    if (isset($this->destinationKey)) {
      $plugin = $this->getMappingPlugin();
      $plugin_state = $this->createSubFormState($this->destinationKey . '_configuration', $form_state);

      if ($plugin) {
        $plugin_form = $plugin->buildConfigurationForm([], $plugin_state);

        $form['plugin'] = [
          '#title' => $this->t('Field settings'),
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
   * Gets the initialized mapping plugin
   *
   * @return \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getMappingPlugin() {
    $field = $this->getDestinationField($this->destinationKey);

    /** @var \Drupal\feeds_migrate_ui\FeedsMigrateUiFieldInterface $plugin */
    $plugin = $this->fieldProcessorManager->getFieldPlugin($field, $this->entity);

    return $plugin;
  }

  /**
   * Returns a list of all mapping destination options, keyed by field name.
   */
  protected function getDestinationOptions() {
    $options = [];

    /** @var FieldDefinitionInterface[] $fields */
    $fields =  $this->fieldManager->getFieldDefinitions($this->getEntityTypeIdFromMigration(), $this->getEntityBundleFromMigration());
    foreach ($fields as $field_name => $field) {
      $options[$field->getName()] = $field->getLabel();
    }

    return $options;
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
