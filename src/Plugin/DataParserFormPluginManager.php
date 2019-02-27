<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Class DataParserFormPluginManager.
 *
 * @package Drupal\feeds_migrate
 */
class DataParserFormPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new Migrate Data Parser Form Plugin Form Manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/migrate_plus/data_parser/form', $namespaces, $module_handler, 'Drupal\feeds_migrate\MigratePluginFormInterface', 'Drupal\feeds_migrate\Annotation\DataParserForm');

    $this->alterInfo('data_parser_form_info');
    $this->setCacheBackend($cache_backend, 'migrate_plus_plugins_data_parser_form');
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

}
