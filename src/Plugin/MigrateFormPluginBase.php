<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate_plus\Entity\MigrationInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for migrate plugins that have external configuration forms.
 */
abstract class MigrateFormPluginBase extends PluginBase implements MigrateFormPluginInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The migrate plugin this form plugin is for.
   *
   * @var \Drupal\Component\Plugin\PluginInspectionInterface
   */
  protected $migratePlugin;

  /**
   * The migration.
   *
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   */
  protected $migration;

  /**
   * MigratePluginFormBase constructor.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PluginInspectionInterface $migrate_plugin, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migratePlugin = $migrate_plugin;
    $this->migration = $migration;
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
      $migration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['plugin' => $this->pluginId];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // This function will replace copyFormValuesToEntity.
    // Saves the form state values in the plugin configuration.
    // Apply submitted form state to configuration.
    $values = $form_state->getValues();
    foreach ($values as $key => $value) {
      if (array_key_exists($key, $this->configuration)) {
        $this->configuration[$key] = $value;
      }
      else {
        // Remove from form state.
        unset($values[$key]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    foreach ($values as $key => $value) {
      $entity->set($key, $value);
    }
  }

  /**
   * Get a particular plugin configuration value.
   *
   * @param string $key
   *   Key of the configuration.
   *
   * @return mixed|null
   *   Setting value if found.
   *
   * @throws \ReflectionException
   */
  protected function getSetting($key) {
    if (!empty($this->migratePlugin)) {
      // Get configuration from plugin. We need to use reflection here as there
      // are no public methods to retrieve the plugin's configuration.
      $class = new ReflectionClass(get_class($this->migratePlugin));
      $property = $class->getProperty('configuration');
      $property->setAccessible(TRUE);
      $configuration = $property->getValue($this->migratePlugin);

      if (isset($configuration[$key])) {
        return $configuration[$key];
      }
    }

    // Try default configuration.
    $default_configuration = $this->defaultConfiguration();
    if (isset($default_configuration[$key])) {
      return $default_configuration[$key];
    }
  }

}
