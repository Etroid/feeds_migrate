<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for migrate plugins that have external configuration forms.
 */
abstract class MigrateFormPluginBase implements MigrateFormPluginInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The migrate plugin.
   *
   * @var object
   */
  protected $plugin;

  /**
   * The migration entity.
   *
   * @var \Drupal\migrate_plus\Entity\MigrationInterface
   */
  protected $entity;

  /**
   * MigratePluginFormBase constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The plugin manager for config entity-based migrations.
   */
  public function __construct(MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setPlugin(PluginInspectionInterface $plugin) {
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(MigrationInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Validation is optional.
  }

  /**
   * Gets the migration entity from the plugin.
   *
   * @return \Drupal\migrate_plus\Entity\MigrationInterface
   *   The migration.
   */
  protected function getMigrationEntity() {
    if (!empty($this->entity)) {
      return $this->entity;
    }

    if (!empty($this->plugin)) {
      // Get migration from plugin. We need to use reflection here as there
      // is no public method to retrieve the plugin's migration.
      $class = new ReflectionClass(get_class($this->plugin));
      $property = $class->getProperty('migration');
      $property->setAccessible(TRUE);
      $entity = $property->getValue($this->plugin);

      return $entity;
    }
  }

  /**
   * Gets the migration array from the plugin.
   *
   * @return \Drupal\migrate\Plugin\Migration
   */
  protected function getMigrationArray() {
    $entity = $this->getMigrationEntity();

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
      $migration_data[$key] = $entity->get($key);
    }

    // And instantiate the migration plugin.
    $migration = $this->migrationPluginManager->createStubMigration($migration_data);

    return $migration;
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
    if (!empty($this->plugin)) {
      // Get configuration from plugin. We need to use reflection here as there
      // are no public methods to retrieve the plugin's configuration.
      $class = new ReflectionClass(get_class($this->plugin));
      $property = $class->getProperty('configuration');
      $property->setAccessible(TRUE);
      $configuration = $property->getValue($this->plugin);

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
