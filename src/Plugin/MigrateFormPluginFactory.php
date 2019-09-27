<?php

namespace Drupal\feeds_migrate\Plugin;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\PluginAwareInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\migrate_plus\Entity\MigrationInterface;

/**
 * Provides form discovery capabilities for plugins.
 *
 * This is based on PluginFormFactoryInterface, but because migrate plugins in
 * core don't implement the following interfaces, we need to work around it.
 *   - PluginWithFormsInterface|PluginFormInterface.
 *   - ConfigurableInterface.
 */
class MigrateFormPluginFactory {

  /**
   * Returns whether or not the plugin implements a form for the given type.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The Migrate plugin.
   * @param string $operation
   *   The type of form to check for, which can be for example:
   *   - configuration
   *     Displayed when configuring the feed type.
   *   - feed
   *     Displayed on the feed add/edit form.
   *   - option
   *     A small form to appear on the plugin select box. The entity processor
   *     plugins use this to display a form for selecting an entity bundle.
   *
   * @return bool
   *   True if the plugin implements a form of the given type. False otherwise.
   */
  public function hasForm(PluginInspectionInterface $plugin, $operation) {
    $definition = $plugin->getPluginDefinition();

    return !empty($definition['feeds_migrate']['form'][$operation]);
  }

  /**
   * Creates a new migrate form plugin instance.
   *
   * @param \Drupal\Component\Plugin\PluginInspectionInterface $plugin
   *   The plugin the form plugin is for.
   * @param string $operation
   *   The name of the operation to use, e.g., 'configuration' or 'import'.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface|null $migration
   *   The migration entity.
   * @param array $configuration
   *   The form plugin configuration.
   *
   * @return \Drupal\Core\Plugin\PluginFormInterface
   *   A plugin form instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createInstance(PluginInspectionInterface $plugin, $operation, MigrationInterface $migration = NULL, array $configuration = []) {
    // @todo when migrate plugins implement PluginWithFormsInterface, we can
    //   use $plugin->hasFormClass method instead.
    if (!$this->hasForm($plugin, $operation)) {
      throw new InvalidPluginDefinitionException($plugin->getPluginId(), sprintf('The "%s" plugin did not specify a "%s" form class', $plugin->getPluginId(), $operation));
    }

    $plugin_definition = $plugin->getPluginDefinition();
    // If the form specified is the plugin itself, use it directly.
    $form_plugin_id = $plugin_definition['feeds_migrate']['form'][$operation];
    if ($plugin instanceof PluginFormInterface && $plugin->getPluginId() === $form_plugin_id) {
      $form_plugin = $plugin;
    }
    else {
      // Try and resolve the migrate plugin type.
      $form_plugin_type = $plugin_definition['type'] ?? NULL;

      if (empty($type)) {
        $namespace_parts = explode('\\', $plugin_definition['class']);
        $form_plugin_type = $namespace_parts[count($namespace_parts) - 2];
      }

      /* @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginManager $manager */
      $manager = \Drupal::service("plugin.manager.feeds_migrate.migrate.{$form_plugin_type}_form");
      /** @var \Drupal\feeds_migrate\Plugin\MigrateFormPluginInterface $form_plugin */
      $form_plugin = $manager->createInstance($form_plugin_id, $configuration, $plugin, $migration);
    }

    // Ensure the resulting object is a plugin form.
    if (!$form_plugin instanceof PluginFormInterface) {
      throw new InvalidPluginDefinitionException($plugin->getPluginId(), sprintf('The "%s" plugin did not specify a valid "%s" form class, must implement \Drupal\Core\Plugin\PluginFormInterface', $plugin->getPluginId(), $operation));
    }

    return $form_plugin;
  }

}
