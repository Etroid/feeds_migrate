<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Traversable;

/**
 * Class MigrateFormPluginManager.
 *
 * @package Drupal\feeds_migrate
 */
class MigrateFormPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new Migrate Form Plugin Manager.
   *
   * @param string $type
   *   The plugin type, for example data_parser, data_fetcher, destination...
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct($type, Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $module = $this->getPluginModule($type);
    parent::__construct("Plugin/$module/$type/Form", $namespaces, $module_handler, 'Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface', 'Drupal\feeds_migrate\Annotation\MigrateForm');

    $this->alterInfo("migrate_form_{$type}_info");
    $this->setCacheBackend($cache_backend, "migrate_form:{$type}");
  }

  /**
   * Get a simple array of all the plugins.
   *
   * @return array
   *   Keyed array of the defined plugins.
   */
  public function getOptions() {
    $options = [];
    foreach ($this->getDefinitions() as $definition) {
      $options[$definition['id']] = $definition['title'];
    }
    return $options;
  }

  /**
   * Get the module this plugin was declared in.
   *
   * @param string $type
   *   The plugin type, for example data_parser, data_fetcher, destination...
   *
   * @return string
   *   The module name.
   */
  private function getPluginModule($type) {
    // Migrate plus.
    if (in_array($type, ['authentication', 'data_fetcher', 'data_parser'])) {
      return 'migrate_plus';
    }

    // Default to core migrate plugins.
    return 'migrate';
  }

}
